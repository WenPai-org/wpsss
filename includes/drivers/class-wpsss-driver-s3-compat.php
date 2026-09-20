<?php

if (!defined('ABSPATH')) {
    exit;
}

class Wpsss_Driver_S3_Compat implements Wpsss_Driver
{
    /** @var string */
    private $id;

    /** @var string */
    private $label;

    public function __construct(string $id = 's3-compat', string $label = 'Amazon S3 / 兼容')
    {
        $this->id = $id;
        $this->label = $label;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function is_configured(array $settings): bool
    {
        $host = Wpsss_Options::s3_request_host($settings);
        return $settings['bucket'] !== ''
            && ($settings['region'] !== '' || Wpsss_Options::effective_s3_preset($settings) === 'r2')
            && $settings['access_key_id'] !== ''
            && $settings['access_key_secret'] !== ''
            && $host !== '';
    }

    public function put_object(array $settings, string $object, string $absolute_path, string $mime, string $acl = 'public-read')
    {
        if (!is_readable($absolute_path)) {
            return new WP_Error('wpsss_missing_file', '本地文件不存在，无法上传。');
        }

        $size = (int) filesize($absolute_path);
        if ($size >= Wpsss_Stream::MULTIPART_THRESHOLD) {
            $multipart = $this->put_object_multipart($settings, $object, $absolute_path, $mime, $size, $acl);
            if (!is_wp_error($multipart)) {
                return $multipart;
            }
            if ($multipart->get_error_code() !== 'wpsss_multipart_init' && $multipart->get_error_code() !== 'wpsss_s3_multipart_unsupported') {
                return $multipart;
            }
        }

        $body = file_get_contents($absolute_path);
        if ($body === false) {
            return new WP_Error('wpsss_read_file', '读本地文件失败。');
        }

        $mime = $mime !== '' ? $mime : 'application/octet-stream';
        $extra = $this->put_headers($settings);
        if ($acl === 'private') {
            return $this->request($settings, 'PUT', $object, $body, $mime, $extra + ['x-amz-acl' => 'private'], [], Wpsss_Stream::put_timeout($size));
        }
        if (($settings['url_access'] ?? 'public') !== 'signed') {
            $result = $this->request($settings, 'PUT', $object, $body, $mime, $extra + ['x-amz-acl' => 'public-read'], [], Wpsss_Stream::put_timeout($size));
            if (!is_wp_error($result)) {
                return $result;
            }
            $msg = $result->get_error_message();
            if (stripos($msg, 'AccessDenied') === false && stripos($msg, 'Acl') === false && stripos($msg, 'ACL') === false) {
                return $result;
            }
        }
        return $this->request($settings, 'PUT', $object, $body, $mime, $extra, [], Wpsss_Stream::put_timeout($size));
    }

    /**
     * @param array<string, string> $settings
     * @return array<string, string>
     */
    private function put_headers(array $settings): array
    {
        $cc = class_exists('Wpsss_Options')
            ? Wpsss_Options::object_cache_control($settings)
            : ((($settings['url_access'] ?? 'public') === 'signed') ? 'private, max-age=3600' : 'public, max-age=2592000');
        return $cc !== '' ? ['Cache-Control' => $cc] : [];
    }

    public function copy_object(array $to_settings, string $to_object, array $from_settings, string $from_object)
    {
        $from_object = ltrim($from_object, '/');
        $source = '/' . rawurlencode($from_settings['bucket']) . '/' . implode('/', array_map('rawurlencode', explode('/', $from_object)));
        return $this->request($to_settings, 'PUT', $to_object, '', '', ['x-amz-copy-source' => $source]);
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
     * 分段拉回：Range 头不进 S3 V4 签名白名单也可发送。
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
     * 大文件分片上传（S3 multipart）。
     *
     * @return true|WP_Error
     */
    private function put_object_multipart(array $settings, string $object, string $absolute_path, string $mime, int $size, string $acl = 'public-read')
    {
        $extra = $this->put_headers($settings);
        if ($acl === 'private') {
            $extra['x-amz-acl'] = 'private';
        }
        $initiate = function () use ($settings, $object, $mime, $extra, $acl) {
            $r = $this->request($settings, 'POST', $object, '', $mime !== '' ? $mime : 'application/octet-stream', $extra, ['uploads' => ''], 60, true);
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
            $r = $this->request($settings, 'POST', $object, $xml, 'application/xml', [], ['uploadId' => $upload_id], 120, true);
            if (is_wp_error($r)) {
                return $r;
            }
            if (preg_match('/<Code>([^<]+)<\/Code>/', (string) $r['body'], $m)) {
                return new WP_Error('wpsss_s3_multipart_complete', '分片合并失败：' . $m[1]);
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
        $loc = $this->bucket_query($settings, 'location=');
        if (is_wp_error($loc)) {
            $guess = Wpsss_Bucket_Info::region_from_error_data(is_array($loc->get_error_data()) ? $loc->get_error_data() : []);
            if ($guess !== '' && $guess !== ($settings['region'] ?? '')) {
                $settings['region'] = $guess;
                $loc = $this->bucket_query($settings, 'location=');
            }
        }
        if (is_wp_error($loc)) {
            return $loc;
        }
        $info = Wpsss_Bucket_Info::blank();
        $parsed = Wpsss_Bucket_Info::parse_location_xml($loc['body']);
        if ($parsed === '') {
            $parsed = Wpsss_Bucket_Info::region_from_headers($loc['headers']);
        }
        if ($parsed === '') {
            $parsed = Wpsss_Bucket_Info::s3_empty_location_region();
        }
        $info['region'] = Wpsss_Bucket_Info::pick_known_region($parsed, Wpsss_Options::regions_for($this->id()));
        $acl = $this->bucket_query($settings, 'acl=');
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
    private function bucket_query(array $settings, string $query)
    {
        $built = Wpsss_Options::s3_object_request($settings, '');
        if ($built['url'] === '' || $built['host'] === '') {
            return new WP_Error('wpsss_s3_host', 'S3 兼容缺少 endpoint / 区域，无法签名。');
        }
        $amz_date = Wpsss_S3_Signer::amz_date();
        $payload_hash = Wpsss_S3_Signer::EMPTY_SHA256;
        $region = Wpsss_Options::s3_signing_region($settings);
        $headers = [
            'host' => $built['host'],
            'x-amz-content-sha256' => $payload_hash,
            'x-amz-date' => $amz_date,
        ];
        $signed = Wpsss_S3_Signer::sign(
            $settings['access_key_id'],
            $settings['access_key_secret'],
            'GET',
            $built['canonical_uri'],
            $query,
            $headers,
            $payload_hash,
            $amz_date,
            $region
        );
        $url = $built['url'] . (strpos($built['url'], '?') === false ? '?' : '&') . $query;
        $response = wp_remote_request(
            $url,
            [
                'method' => 'GET',
                'headers' => [
                    'Authorization' => $signed['authorization'],
                    'x-amz-content-sha256' => $payload_hash,
                    'x-amz-date' => $amz_date,
                ],
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
        return new WP_Error('wpsss_s3_http', $this->error_message($body, $code), [
            'status' => $code,
            'headers' => $flat,
            'body' => $body,
        ]);
    }

    /**
     * @param array<string, string> $extra
     * @param array<string, string> $query
     * @return true|string|array{code: int, body: string, headers: array<string, string>}|WP_Error
     */
    private function request(
        array $settings,
        string $method,
        string $object,
        string $body,
        string $mime,
        array $extra = [],
        array $query = [],
        int $timeout = 60,
        bool $with_meta = false
    ) {
        $object = ltrim($object, '/');
        $built = Wpsss_Options::s3_object_request($settings, $object);
        // WP HTTP 对带 body 的 PUT/POST 会默认补 application/x-www-form-urlencoded，
        // 服务端把它当已签名头；mime 为空时先补默认值，两侧才对得上。
        if (($method === 'PUT' || $method === 'POST') && $body !== '' && $mime === '') {
            $mime = 'application/octet-stream';
        }

        if ($built['url'] === '' || $built['host'] === '') {
            return new WP_Error('wpsss_s3_host', 'S3 兼容缺少 endpoint / 区域，无法签名。');
        }

        $amz_date = Wpsss_S3_Signer::amz_date();
        $payload_hash = ($method === 'PUT' || $method === 'POST')
            ? Wpsss_S3_Signer::payload_hash($body)
            : Wpsss_S3_Signer::EMPTY_SHA256;
        $region = Wpsss_Options::s3_signing_region($settings);

        $headers = [
            'host' => $built['host'],
            'x-amz-content-sha256' => $payload_hash,
            'x-amz-date' => $amz_date,
        ];
        if ($mime !== '') {
            $headers['content-type'] = $mime;
        }
        foreach ($extra as $name => $value) {
            $headers[strtolower((string) $name)] = (string) $value;
        }

        $canonical_query = '';
        if ($query !== []) {
            ksort($query);
            foreach ($query as $k => $v) {
                // 与 S3 规范一致：键值都做 RFC3986 编码。
                $canonical_query .= ($canonical_query === '' ? '' : '&') . rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
            }
        }

        $signed = Wpsss_S3_Signer::sign(
            $settings['access_key_id'],
            $settings['access_key_secret'],
            $method,
            $built['canonical_uri'],
            $canonical_query,
            $headers,
            $payload_hash,
            $amz_date,
            $region
        );

        $request_headers = [
            'Authorization' => $signed['authorization'],
            'x-amz-content-sha256' => $payload_hash,
            'x-amz-date' => $amz_date,
        ];
        if ($mime !== '') {
            $request_headers['Content-Type'] = $mime;
        }
        foreach ($extra as $name => $value) {
            $request_headers[$name] = $value;
        }

        $url = $built['url'];
        if ($query !== []) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }
        $response = wp_remote_request(
            $url,
            [
                'method' => $method,
                'headers' => $request_headers,
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
        return new WP_Error('wpsss_s3_http', $this->error_message($raw, $code), ['status' => $code]);
    }

    private function error_message(string $body, int $code): string
    {
        if (preg_match('/<Code>([^<]+)<\/Code>/', $body, $match_code)
            && preg_match('/<Message>([^<]+)<\/Message>/', $body, $match_msg)
        ) {
            return $match_code[1] . '：' . $match_msg[1];
        }
        return 'S3 兼容返回 HTTP ' . $code;
    }
}
