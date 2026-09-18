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
        return $settings['bucket'] !== ''
            && $settings['region'] !== ''
            && $settings['access_key_id'] !== ''
            && $settings['access_key_secret'] !== '';
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
        $date = Wpsss_Oss_Signer::gmt_date();
        $md5 = ($method === 'PUT') ? Wpsss_Oss_Signer::content_md5($body) : '';
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
            return true;
        }

        $raw = (string) wp_remote_retrieve_body($response);
        $message = $this->error_message($raw, $code);
        return new WP_Error('wpsss_oss_http', $message, ['status' => $code]);
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
