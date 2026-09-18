<?php

if (!defined('ABSPATH')) {
    exit;
}

class Wpsss_Driver_Qiniu_Kodo implements Wpsss_Driver
{
    public function id(): string
    {
        return Wpsss_Options::DRIVER_KODO;
    }

    public function label(): string
    {
        return '七牛云 KODO';
    }

    public function is_configured(array $settings): bool
    {
        return $settings['bucket'] !== ''
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
        $object = ltrim($object, '/');
        $mime = $mime !== '' ? $mime : 'application/octet-stream';
        $token = Wpsss_Kodo_Signer::upload_token(
            $settings['access_key_id'],
            $settings['access_key_secret'],
            [
                'scope' => $settings['bucket'] . ':' . $object,
                'deadline' => time() + 3600,
            ]
        );
        $boundary = '----Wpsss' . wp_generate_password(16, false);
        $payload = $this->multipart($boundary, [
            'token' => $token,
            'key' => $object,
        ], $object, $mime, $body);
        $response = wp_remote_post(
            Wpsss_Options::kodo_upload_host($settings['region'] ?? 'z0'),
            [
                'headers' => [
                    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                ],
                'body' => $payload,
                'timeout' => 60,
                'redirection' => 0,
            ]
        );
        return $this->ok($response, 'kodo');
    }

    public function delete_objects(array $settings, array $objects)
    {
        $last = true;
        foreach (array_unique(array_filter($objects)) as $object) {
            $result = $this->manage($settings, 'delete', (string) $object);
            if (is_wp_error($result)) {
                $last = $result;
            }
        }
        return $last;
    }

    public function head_object(array $settings, string $object)
    {
        return $this->manage($settings, 'stat', $object);
    }

    public function get_object(array $settings, string $object)
    {
        $object = ltrim($object, '/');
        $base = trim((string) ($settings['custom_domain'] ?? ''));
        if ($base === '') {
            return new WP_Error('wpsss_kodo_domain', '七牛拉回需要自定义域名。');
        }
        $url = Wpsss_Kodo_Signer::private_download_url(
            $settings['access_key_id'],
            $settings['access_key_secret'],
            untrailingslashit($base) . '/' . $object,
            3600
        );
        $response = wp_remote_get($url, ['timeout' => 60, 'redirection' => 0]);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            return (string) wp_remote_retrieve_body($response);
        }
        return new WP_Error('wpsss_kodo_http', 'KODO 拉回 HTTP ' . $code, ['status' => $code]);
    }

    public function probe(array $settings)
    {
        $tmp = wp_tempnam('wpsss-kodo');
        if ($tmp === false) {
            return new WP_Error('wpsss_tmp', '写不了临时文件。');
        }
        file_put_contents($tmp, 'wpsss-ok');
        $object = 'wpsss-probe/' . gmdate('YmdHis') . '.txt';
        $put = $this->put_object($settings, $object, $tmp, 'text/plain');
        @unlink($tmp);
        if (is_wp_error($put)) {
            return $put;
        }
        return $this->delete_objects($settings, [$object]);
    }

    /**
     * @param array<string, string> $fields
     */
    private function multipart(string $boundary, array $fields, string $filename, string $mime, string $body): string
    {
        $out = '';
        foreach ($fields as $name => $value) {
            $out .= '--' . $boundary . "\r\n";
            $out .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
            $out .= $value . "\r\n";
        }
        $out .= '--' . $boundary . "\r\n";
        $out .= 'Content-Disposition: form-data; name="file"; filename="' . basename($filename) . '"' . "\r\n";
        $out .= 'Content-Type: ' . $mime . "\r\n\r\n";
        $out .= $body . "\r\n";
        $out .= '--' . $boundary . "--\r\n";
        return $out;
    }

    /**
     * @return true|WP_Error
     */
    private function manage(array $settings, string $op, string $object)
    {
        $object = ltrim($object, '/');
        $entry = Wpsss_Kodo_Signer::encoded_entry($settings['bucket'], $object);
        $path = '/' . $op . '/' . $entry;
        $host = Wpsss_Options::kodo_rs_host($settings['region'] ?? 'z0');
        $auth = Wpsss_Kodo_Signer::qbox($settings['access_key_id'], $settings['access_key_secret'], $path);
        $response = wp_remote_post(
            $host . $path,
            [
                'headers' => [
                    'Authorization' => $auth,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => '',
                'timeout' => 30,
                'redirection' => 0,
            ]
        );
        return $this->ok($response, 'kodo');
    }

    /**
     * @param array<string, mixed>|WP_Error $response
     * @return true|WP_Error
     */
    private function ok($response, string $who)
    {
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            return true;
        }
        $raw = (string) wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);
        if (is_array($json) && isset($json['error'])) {
            return new WP_Error('wpsss_' . $who . '_http', (string) $json['error'], ['status' => $code]);
        }
        return new WP_Error('wpsss_' . $who . '_http', strtoupper($who) . ' 返回 HTTP ' . $code, ['status' => $code]);
    }
}
