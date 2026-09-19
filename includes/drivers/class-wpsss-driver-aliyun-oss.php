<?php

if (!defined('ABSPATH')) {
    exit;
}

class Wpsss_Driver_Aliyun_Oss implements Wpsss_Driver
{
    public function id(): string
    {
        return 'aliyun-oss';
    }

    public function label(): string
    {
        return '阿里云 OSS';
    }

    public function is_configured(array $settings): bool
    {
        if ($settings['bucket'] === '' || $settings['region'] === '') {
            return false;
        }
        if (($settings['oss_auth'] ?? 'ak') === 'ram') {
            return true;
        }
        return $settings['access_key_id'] !== '' && $settings['access_key_secret'] !== '';
    }

    public function put_object(array $settings, string $object, string $absolute_path, string $mime)
    {
        if (!is_readable($absolute_path)) {
            return new WP_Error('wpsss_missing_file', '本地文件不存在，无法上传。');
        }

        $body = file_get_contents($absolute_path);
        if ($body === false) {
            return new WP_Error('wpsss_read_file', '读本地文件失败。');
        }

        $mime = $mime !== '' ? $mime : 'application/octet-stream';
        $acl = ['x-oss-object-acl' => 'public-read'];
        $result = $this->request($settings, 'PUT', $object, $body, $mime, $acl);
        if (!is_wp_error($result)) {
            return $result;
        }

        $msg = $result->get_error_message();
        if (stripos($msg, 'AccessDenied') === false && stripos($msg, 'Acl') === false) {
            return $result;
        }

        return $this->request($settings, 'PUT', $object, $body, $mime);
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

    public function probe(array $settings)
    {
        $object = 'wpsss-probe/' . gmdate('YmdHis') . '.txt';
        $put = $this->request($settings, 'PUT', $object, 'wpsss-ok', 'text/plain');
        if (is_wp_error($put)) {
            return $put;
        }
        $delete = $this->request($settings, 'DELETE', $object, '', '');
        if (is_wp_error($delete)) {
            return $delete;
        }
        return true;
    }

    public function inspect_bucket(array $settings)
    {
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
        $info = Wpsss_Bucket_Info::blank();
        $info['region'] = Wpsss_Bucket_Info::pick_known_region(
            Wpsss_Bucket_Info::parse_location_xml($loc['body']) ?: Wpsss_Bucket_Info::region_from_headers($loc['headers']),
            Wpsss_Options::regions_for($this->id())
        );
        if ($info['region'] === '') {
            $info['region'] = (string) ($settings['region'] ?? '');
        }
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
        $settings = $this->ready_settings($settings);
        $url = Wpsss_Oss_Signer::object_url(Wpsss_Options::api_host($settings), '') . '?' . $subresource;
        if (Wpsss_Options::oss_sign_version($settings) === 'v4') {
            $headers = $this->v4_headers($settings, 'GET', '', '', [], [$subresource => '']);
            $response = wp_remote_request($url, [
                'method' => 'GET',
                'headers' => $headers,
                'timeout' => 30,
                'redirection' => 0,
            ]);
            return $this->inspect_response($response, 'oss');
        }
        $date = Wpsss_Oss_Signer::gmt_date();
        $oss_headers = [];
        if (($settings['security_token'] ?? '') !== '') {
            $oss_headers['x-oss-security-token'] = (string) $settings['security_token'];
        }
        $auth = Wpsss_Oss_Signer::authorization(
            $settings['access_key_id'],
            $settings['access_key_secret'],
            'GET',
            '',
            '',
            $date,
            $settings['bucket'],
            '',
            $oss_headers,
            $subresource
        );
        $headers = [
            'Date' => $date,
            'Authorization' => $auth,
        ];
        foreach ($oss_headers as $name => $value) {
            $headers[$name] = $value;
        }
        $response = wp_remote_request(
            $url,
            [
                'method' => 'GET',
                'headers' => $headers,
                'timeout' => 30,
                'redirection' => 0,
            ]
        );
        return $this->inspect_response($response, 'oss');
    }

    /**
     * @param array<string, mixed>|WP_Error $response
     * @return array{code: int, body: string, headers: array<string, string>}|WP_Error
     */
    private function inspect_response($response, string $who)
    {
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $headers = Wpsss_Bucket_Info::flatten_headers(wp_remote_retrieve_headers($response));
        if ($code >= 200 && $code < 300) {
            return ['code' => $code, 'body' => $body, 'headers' => $headers];
        }
        $message = $this->error_message($body, $code);
        return new WP_Error('wpsss_' . $who . '_http', $message, [
            'status' => $code,
            'headers' => $headers,
            'body' => $body,
        ]);
    }

    /**
     * @param array<string, string> $oss_headers
     * @return true|WP_Error
     */
    private function request(
        array $settings,
        string $method,
        string $object,
        string $body,
        string $mime,
        array $oss_headers = []
    ) {
        $object = ltrim($object, '/');
        $settings = $this->ready_settings($settings);
        $md5 = ($method === 'PUT') ? Wpsss_Oss_Signer::content_md5($body) : '';
        if (($settings['security_token'] ?? '') !== '') {
            $oss_headers['x-oss-security-token'] = (string) $settings['security_token'];
        }
        if ($md5 !== '') {
            $oss_headers['Content-MD5'] = $md5;
        }
        if (Wpsss_Options::oss_sign_version($settings) === 'v4') {
            $headers = $this->v4_headers($settings, $method, $object, $mime, $oss_headers, []);
        } else {
            $date = Wpsss_Oss_Signer::gmt_date();
            $auth = Wpsss_Oss_Signer::authorization(
                $settings['access_key_id'],
                $settings['access_key_secret'],
                $method,
                $md5,
                $mime,
                $date,
                $settings['bucket'],
                $object,
                $oss_headers
            );
            $headers = [
                'Date' => $date,
                'Authorization' => $auth,
            ];
            if ($md5 !== '') {
                $headers['Content-MD5'] = $md5;
            }
            if ($mime !== '') {
                $headers['Content-Type'] = $mime;
            }
            foreach ($oss_headers as $name => $value) {
                $headers[$name] = $value;
            }
        }

        $url = Wpsss_Oss_Signer::object_url(Wpsss_Options::api_host($settings), $object);
        $response = wp_remote_request(
            $url,
            [
                'method' => $method,
                'headers' => $headers,
                'body' => $method === 'PUT' ? $body : '',
                'timeout' => 60,
                'redirection' => 0,
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            return $method === 'GET' ? (string) wp_remote_retrieve_body($response) : true;
        }

        $raw = (string) wp_remote_retrieve_body($response);
        $message = $this->error_message($raw, $code);
        return new WP_Error('wpsss_oss_http', $message, ['status' => $code]);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function ready_settings(array $settings): array
    {
        return class_exists('Wpsss_Oss_Ram') ? Wpsss_Oss_Ram::hydrate($settings) : $settings;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, string> $oss_headers
     * @param array<string, string> $query
     * @return array<string, string>
     */
    private function v4_headers(
        array $settings,
        string $method,
        string $object,
        string $mime,
        array $oss_headers,
        array $query
    ): array {
        $host = (string) (parse_url(Wpsss_Options::api_host($settings), PHP_URL_HOST) ?: '');
        $iso = Wpsss_Oss_Signer::iso_date();
        $headers = [
            'Host' => $host,
            'x-oss-date' => $iso,
            'x-oss-content-sha256' => Wpsss_Oss_Signer::UNSIGNED_PAYLOAD,
        ];
        if ($mime !== '') {
            $headers['Content-Type'] = $mime;
        }
        foreach ($oss_headers as $name => $value) {
            $headers[$name] = $value;
        }
        return Wpsss_Oss_Signer::v4_sign_headers(
            (string) $settings['access_key_id'],
            (string) $settings['access_key_secret'],
            $method,
            (string) $settings['bucket'],
            $object,
            Wpsss_Options::oss_sign_region($settings),
            $iso,
            $headers,
            $query,
            ['host']
        );
    }

    private function error_message(string $body, int $code): string
    {
        if (preg_match('/<Code>([^<]+)<\/Code>/', $body, $match_code)
            && preg_match('/<Message>([^<]+)<\/Message>/', $body, $match_msg)
        ) {
            return $match_code[1] . '：' . $match_msg[1];
        }
        return 'OSS 返回 HTTP ' . $code;
    }
}
