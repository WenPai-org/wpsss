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
        return is_wp_error($delete) ? $delete : true;
    }

    /**
     * @return true|WP_Error
     */
    private function request(array $settings, string $method, string $object, string $body, string $mime)
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
        if (preg_match('/<Code>([^<]+)<\/Code>/', $raw, $c) && preg_match('/<Message>([^<]+)<\/Message>/', $raw, $m)) {
            return new WP_Error('wpsss_cos_http', $c[1] . '：' . $m[1], ['status' => $code]);
        }
        return new WP_Error('wpsss_cos_http', 'COS 返回 HTTP ' . $code, ['status' => $code]);
    }
}
