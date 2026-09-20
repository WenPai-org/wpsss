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

    public function put_object(array $settings, string $object, string $absolute_path, string $mime, string $acl = 'public-read')
    {
        if (!is_readable($absolute_path)) {
            return new WP_Error('wpsss_missing_file', '本地文件不存在，无法上传。');
        }

        $size = (int) filesize($absolute_path);
        // 大文件走分片（仅 v4 签名；v1 老站退回单请求 + 超时缩放）。
        if ($size >= Wpsss_Stream::MULTIPART_THRESHOLD && Wpsss_Options::oss_sign_version($settings) === 'v4') {
            $multipart = $this->put_object_multipart($settings, $object, $absolute_path, $mime, $size, $acl);
            if (!is_wp_error($multipart)) {
                return $multipart;
            }
            if ($multipart->get_error_code() !== 'wpsss_multipart_init' && $multipart->get_error_code() !== 'wpsss_oss_multipart_unsupported') {
                return $multipart;
            }
            // 初始化失败（桶未开启分片等）退回单请求试一次。
        }

        $body = file_get_contents($absolute_path);
        if ($body === false) {
            return new WP_Error('wpsss_read_file', '读本地文件失败。');
        }

        $mime = $mime !== '' ? $mime : 'application/octet-stream';
        $acl_headers = ['x-oss-object-acl' => $acl];
        $cc = class_exists('Wpsss_Options') ? Wpsss_Options::object_cache_control($settings) : '';
        if ($cc !== '') {
            $acl_headers['Cache-Control'] = $cc;
        }
        $result = $this->request($settings, 'PUT', $object, $body, $mime, $acl_headers, [], Wpsss_Stream::put_timeout($size));
        if (!is_wp_error($result)) {
            return $result;
        }

        $msg = $result->get_error_message();
        if (stripos($msg, 'AccessDenied') === false && stripos($msg, 'Acl') === false) {
            return $result;
        }

        $plain = [];
        if ($cc !== '') {
            $plain['Cache-Control'] = $cc;
        }
        return $this->request($settings, 'PUT', $object, $body, $mime, $plain, [], Wpsss_Stream::put_timeout($size));
    }

    public function copy_object(array $to_settings, string $to_object, array $from_settings, string $from_object)
    {
        $from_object = ltrim($from_object, '/');
        $source = '/' . rawurlencode($from_settings['bucket']) . '/' . implode('/', array_map('rawurlencode', explode('/', $from_object)));
        return $this->request($to_settings, 'PUT', $to_object, '', '', ['x-oss-copy-source' => $source]);
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

    /**
     * 分段拉回：Range 头对 OSS 不要求签名，直接并入请求头。
     */
    public function get_object_range(array $settings, string $object, int $start, int $end)
    {
        return $this->request(
            $settings,
            'GET',
            $object,
            '',
            '',
            ['Range' => 'bytes=' . $start . '-' . $end],
            [],
            max(60, (int) ceil(($end - $start + 1) / 2097152) + 30),
            true
        );
    }

    /**
     * 大文件分片上传（OSS multipart，v4 签名）。
     *
     * @return true|WP_Error
     */
    private function put_object_multipart(array $settings, string $object, string $absolute_path, string $mime, int $size, string $acl = 'public-read')
    {
        $cc = class_exists('Wpsss_Options') ? Wpsss_Options::object_cache_control($settings) : '';
        $initiate = function () use ($settings, $object, $mime, $cc, $acl) {
            $headers = ['x-oss-object-acl' => $acl];
            if ($cc !== '') {
                $headers['Cache-Control'] = $cc;
            }
            $r = $this->request($settings, 'POST', $object, '', $mime !== '' ? $mime : 'application/octet-stream', $headers, ['uploads' => ''], 60, true);
            if (is_wp_error($r)) {
                return $r;
            }
            $id = Wpsss_Stream::parse_upload_id((string) $r['body']);
            return $id !== '' ? $id : new WP_Error('wpsss_multipart_init', '初始化分片上传失败。');
        };
        $part = function (string $upload_id, int $number, string $body) use ($settings, $object) {
            $r = $this->request($settings, 'PUT', $object, $body, '', [], [
                'partNumber' => (string) $number,
                'uploadId' => $upload_id,
            ], Wpsss_Stream::put_timeout(strlen($body)), true);
            if (is_wp_error($r)) {
                return $r;
            }
            $etag = '';
            foreach ($r['headers'] as $name => $value) {
                if (strcasecmp((string) $name, 'etag') === 0) {
                    $etag = trim((string) $value, "\"'");
                }
            }
            return $etag !== '' ? $etag : new WP_Error('wpsss_multipart_etag', '分片上传没拿到 ETag。');
        };
        $complete = function (string $upload_id, string $xml) use ($settings, $object) {
            $r = $this->request($settings, 'POST', $object, $xml, 'application/xml', [], [
                'uploadId' => $upload_id,
            ], 120, true);
            if (is_wp_error($r)) {
                return $r;
            }
            if (preg_match('/<Code>([^<]+)<\/Code>/', (string) $r['body'], $m)) {
                return new WP_Error('wpsss_oss_multipart_complete', '分片合并失败：' . $m[1]);
            }
            return true;
        };
        $abort = function (string $upload_id) use ($settings, $object) {
            $this->request($settings, 'DELETE', $object, '', '', [], ['uploadId' => $upload_id]);
        };
        return Wpsss_Stream::upload($initiate, $part, $complete, $abort, $absolute_path);
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
     * @param array<string, string> $query
     * @return true|string|array{code: int, body: string, headers: array<string, string>}|WP_Error
     */
    private function request(
        array $settings,
        string $method,
        string $object,
        string $body,
        string $mime,
        array $oss_headers = [],
        array $query = [],
        int $timeout = 60,
        bool $with_meta = false
    ) {
        $object = ltrim($object, '/');
        $settings = $this->ready_settings($settings);
        $md5 = ($method === 'PUT' || $method === 'POST') && $body !== '' ? Wpsss_Oss_Signer::content_md5($body) : '';
        // WP HTTP 对带 body 的 PUT/POST 会默认补 application/x-www-form-urlencoded，
        // 服务端把它当已签名头；mime 为空时先补默认值，两侧才对得上。
        if (($method === 'PUT' || $method === 'POST') && $body !== '' && $mime === '') {
            $mime = 'application/octet-stream';
        }

        if (($settings['security_token'] ?? '') !== '') {
            $oss_headers['x-oss-security-token'] = (string) $settings['security_token'];
        }
        if ($md5 !== '') {
            $oss_headers['Content-MD5'] = $md5;
        }
        if (Wpsss_Options::oss_sign_version($settings) === 'v4') {
            $headers = $this->v4_headers($settings, $method, $object, $mime, $oss_headers, $query);
        } else {
            ksort($query);
            $sub = '';
            foreach ($query as $k => $v) {
                $sub .= ($sub === '' ? '' : '&') . $k . ($v !== '' ? '=' . $v : '');
            }
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
                $oss_headers,
                $sub
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
        if ($query !== []) {
            // 空值参数拼裸键（?uploads），与 v4_canonical_query 的空值约定一致，
            // 否则服务器算出的规范请求和我们对不上（SignatureDoesNotMatch）。
            $parts = [];
            foreach ($query as $k => $v) {
                $parts[] = ($v === '') ? rawurlencode((string) $k) : rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
            }
            sort($parts);
            $url .= '?' . implode('&', $parts);
        }
        $response = wp_remote_request(
            $url,
            [
                'method' => $method,
                'headers' => $headers,
                'body' => ($method === 'PUT' || $method === 'POST') ? $body : '',
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
