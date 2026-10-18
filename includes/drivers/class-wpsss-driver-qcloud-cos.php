<?php

if (!defined('ABSPATH')) {
    exit;
}

class Wpsss_Driver_Qcloud_Cos implements Wpsss_Driver
{
    public function id(): string
    {
        return Wpsss_Options::DRIVER_COS;
    }

    public function label(): string
    {
        return '腾讯云 COS';
    }

    public function is_configured(array $settings): bool
    {
        return $settings['bucket'] !== ''
            && $settings['region'] !== ''
            && $settings['access_key_id'] !== ''
            && $settings['access_key_secret'] !== '';
    }

    public function put_object(array $settings, string $object, string $absolute_path, string $mime, string $acl = 'public-read')
    {
        if (!is_readable($absolute_path)) {
            return new WP_Error('wpsss_missing_file', '本地文件不存在，无法上传。');
        }
        $body = file_get_contents($absolute_path);
        if ($body === false) {
            return new WP_Error('wpsss_read_file', '读本地文件失败。');
        }
        $mime = $mime !== '' ? $mime : 'application/octet-stream';
        $extra = ($acl === 'private') ? ['x-cos-acl' => 'private'] : [];
        return $this->request($settings, 'PUT', $object, $body, $mime, $extra, Wpsss_Stream::put_timeout((int) filesize($absolute_path)));
    }

    public function copy_object(array $to_settings, string $to_object, array $from_settings, string $from_object)
    {
        $from_object = ltrim($from_object, '/');
        $source = '/' . rawurlencode($from_settings['bucket']) . '/' . implode('/', array_map('rawurlencode', explode('/', $from_object)));
        return $this->request($to_settings, 'PUT', $to_object, '', '', ['x-cos-copy-source' => $source]);
    }

    public function delete_objects(array $settings, array $objects)
    {
        $last = true;
        foreach (array_unique(array_filter($objects)) as $object) {
            $result = $this->request($settings, 'DELETE', (string) $object, '', '');
            if (is_wp_error($result)) {
                $last = $result;
            }
        }
        return $last;
    }

    public function head_object(array $settings, string $object)
    {
        return $this->request($settings, 'HEAD', $object, '', '');
    }

    public function get_object(array $settings, string $object)
    {
        return $this->request($settings, 'GET', $object, '', '');
    }

    public function get_object_range(array $settings, string $object, int $start, int $end)
    {
        return $this->request(
            $settings,
            'GET',
            $object,
            '',
            '',
            ['Range' => 'bytes=' . $start . '-' . $end],
            max(60, (int) ceil(($end - $start + 1) / 2097152) + 30),
            true
        );
    }

    public function probe(array $settings)
    {
        $object = 'wpsss-probe/' . gmdate('YmdHis') . '.txt';
        $put = $this->request($settings, 'PUT', $object, 'wpsss-ok', 'text/plain');
        if (is_wp_error($put)) {
            return $put;
        }
        $delete = $this->request($settings, 'DELETE', $object, '', '');
        return is_wp_error($delete) ? $delete : true;
    }

    public function inspect_bucket(array $settings)
    {
        $info = Wpsss_Bucket_Info::blank();
        $info['app_id'] = Wpsss_Bucket_Info::app_id_from_cos_bucket((string) ($settings['bucket'] ?? ''));
        $loc = $this->bucket_query($settings, 'location');
        if (is_wp_error($loc)) {
            $guess = Wpsss_Bucket_Info::region_from_error_data(is_array($loc->get_error_data()) ? $loc->get_error_data() : []);
            if ($guess !== '' && $guess !== ($settings['region'] ?? '')) {
                $settings['region'] = $guess;
                $loc = $this->bucket_query($settings, 'location');
            }
        }
        if (is_wp_error($loc)) {
            return $loc;
        }
        $info['region'] = Wpsss_Bucket_Info::pick_known_region(
            Wpsss_Bucket_Info::parse_location_xml($loc['body']) ?: Wpsss_Bucket_Info::region_from_headers($loc['headers']),
            Wpsss_Options::regions_for($this->id())
        );
        $acl = $this->bucket_query($settings, 'acl');
        if (is_wp_error($acl)) {
            $info['notes'][] = '已读取区域信息，请自行选择对象访问方式。';
            return $info;
        }
        $info['url_access'] = Wpsss_Bucket_Info::parse_acl_xml($acl['body']);
        if ($info['url_access'] === '') {
            $info['notes'][] = '已连接存储桶，但无法判断公开或私有，请自行选择对象访问方式。';
        }
        return $info;
    }

    /**
     * @return array{code: int, body: string, headers: array<string, string>}|WP_Error
     */
    private function bucket_query(array $settings, string $subresource)
    {
        $host = preg_replace('#^https?://#', '', Wpsss_Options::api_host($settings)) ?: '';
        $headers = ['Host' => $host];
        $params = [$subresource => ''];
        $now = time();
        $auth = Wpsss_Cos_Signer::authorization(
            $settings['access_key_id'],
            $settings['access_key_secret'],
            'GET',
            '',
            $headers,
            $params,
            $now,
            $now + 3600
        );
        $headers['Authorization'] = $auth;
        $url = rtrim(Wpsss_Options::api_host($settings), '/') . '/?' . $subresource;
        $response = wp_remote_request(
            $url,
            [
                'method' => 'GET',
                'headers' => $headers,
                'timeout' => 30,
                'redirection' => 0,
            ]
        );
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $flat = Wpsss_Bucket_Info::flatten_headers(wp_remote_retrieve_headers($response));
        if ($code >= 200 && $code < 300) {
            return ['code' => $code, 'body' => $body, 'headers' => $flat];
        }
        $message = 'COS 返回 HTTP ' . $code;
        if (preg_match('/<Code>([^<]+)<\/Code>/', $body, $c) && preg_match('/<Message>([^<]+)<\/Message>/', $body, $m)) {
            $message = $c[1] . '：' . $m[1];
        }
        return new WP_Error('wpsss_cos_http', $message, [
            'status' => $code,
            'headers' => $flat,
            'body' => $body,
        ]);
    }

    /**
     * @return true|WP_Error
     */
    /**
     * @param array<string, string> $extra_headers 不进签名的附加头（Range 等）
     * @return true|string|array{code: int, body: string, headers: array<string, string>}|WP_Error
     */
    private function request(array $settings, string $method, string $object, string $body, string $mime, array $extra_headers = [], int $timeout = 60, bool $with_meta = false)
    {
        $object = ltrim($object, '/');
        $host = preg_replace('#^https?://#', '', Wpsss_Options::api_host($settings)) ?: '';
        $headers = ['Host' => $host];
        if ($mime !== '') {
            $headers['Content-Type'] = $mime;
        }
        if ($method === 'PUT') {
            $headers['Content-MD5'] = Wpsss_Oss_Signer::content_md5($body);
        }
        $now = time();
        $auth = Wpsss_Cos_Signer::authorization(
            $settings['access_key_id'],
            $settings['access_key_secret'],
            $method,
            $object,
            $headers,
            [],
            $now,
            $now + 3600
        );
        $headers['Authorization'] = $auth;
        foreach ($extra_headers as $name => $value) {
            $headers[$name] = $value;
        }
        $url = Wpsss_Oss_Signer::object_url(Wpsss_Options::api_host($settings), $object);
        $response = wp_remote_request(
            $url,
            [
                'method' => $method,
                'headers' => $headers,
                'body' => $method === 'PUT' ? $body : '',
                'timeout' => $timeout,
                'redirection' => 0,
            ]
        );
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            if ($with_meta) {
                return [
                    'code' => $code,
                    'body' => (string) wp_remote_retrieve_body($response),
                    'headers' => Wpsss_Bucket_Info::flatten_headers(wp_remote_retrieve_headers($response)),
                ];
            }
            return $method === 'GET' ? (string) wp_remote_retrieve_body($response) : true;
        }
        $raw = (string) wp_remote_retrieve_body($response);
        if (preg_match('/<Code>([^<]+)<\/Code>/', $raw, $c) && preg_match('/<Message>([^<]+)<\/Message>/', $raw, $m)) {
            return new WP_Error('wpsss_cos_http', $c[1] . '：' . $m[1], ['status' => $code]);
        }
        return new WP_Error('wpsss_cos_http', 'COS 返回 HTTP ' . $code, ['status' => $code]);
    }
}
