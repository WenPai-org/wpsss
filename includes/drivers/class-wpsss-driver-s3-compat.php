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
        $extra = $this->put_headers($settings);
        if (($settings['url_access'] ?? 'public') !== 'signed') {
            $result = $this->request($settings, 'PUT', $object, $body, $mime, $extra + ['x-amz-acl' => 'public-read']);
            if (!is_wp_error($result)) {
                return $result;
            }
            $msg = $result->get_error_message();
            if (stripos($msg, 'AccessDenied') === false && stripos($msg, 'Acl') === false && stripos($msg, 'ACL') === false) {
                return $result;
            }
        }
        return $this->request($settings, 'PUT', $object, $body, $mime, $extra);
    }

    /**
     * @param array<string, string> $settings
     * @return array<string, string>
     */
    private function put_headers(array $settings): array
    {
        if (($settings['url_access'] ?? 'public') === 'signed') {
            return ['Cache-Control' => 'private, max-age=3600'];
        }
        return ['Cache-Control' => 'public, max-age=2592000'];
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
     * @return true|WP_Error
     */
    private function request(
        array $settings,
        string $method,
        string $object,
        string $body,
        string $mime,
        array $extra = []
    ) {
        $object = ltrim($object, '/');
        $built = Wpsss_Options::s3_object_request($settings, $object);
        if ($built['url'] === '' || $built['host'] === '') {
            return new WP_Error('wpsss_s3_host', 'S3 兼容缺少 endpoint / 区域，无法签名。');
        }

        $amz_date = Wpsss_S3_Signer::amz_date();
        $payload_hash = ($method === 'PUT')
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

        $signed = Wpsss_S3_Signer::sign(
            $settings['access_key_id'],
            $settings['access_key_secret'],
            $method,
            $built['canonical_uri'],
            '',
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

        $response = wp_remote_request(
            $built['url'],
            [
                'method' => $method,
                'headers' => $request_headers,
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
