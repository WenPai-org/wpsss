<?php

if (!defined('ABSPATH') && !defined('WPSSS_TEST')) {
    exit;
}

/**
 * 读这一只桶的 Location / ACL。不列全部桶。
 */
class Wpsss_Bucket_Info
{
    /**
     * @param string[] $notes
     * @return array{region: string, url_access: string, app_id: string, custom_domain: string, notes: string[]}
     */
    public static function blank(array $notes = []): array
    {
        return [
            'region' => '',
            'url_access' => '',
            'app_id' => '',
            'custom_domain' => '',
            'notes' => $notes,
        ];
    }

    public static function parse_location_xml(string $xml): string
    {
        $xml = trim($xml);
        if ($xml === '') {
            return '';
        }
        if (preg_match('/<LocationConstraint[^>]*>([^<]*)<\/LocationConstraint>/', $xml, $m)) {
            return self::clean_region($m[1]);
        }
        if (preg_match('/<Location[^>]*>([^<]*)<\/Location>/', $xml, $m)) {
            return self::clean_region($m[1]);
        }
        return '';
    }

    public static function parse_acl_xml(string $xml): string
    {
        $xml = strtolower($xml);
        if ($xml === '') {
            return '';
        }
        if (strpos($xml, 'public-read-write') !== false || strpos($xml, 'public-read') !== false) {
            return 'public';
        }
        if (strpos($xml, 'allusers') !== false && (strpos($xml, '>read<') !== false || strpos($xml, '>fullcontrol<') !== false)) {
            return 'public';
        }
        if (strpos($xml, 'http://acs.amazonaws.com/groups/global/allusers') !== false) {
            return 'public';
        }
        if (strpos($xml, '<permission>read</permission>') !== false && strpos($xml, 'allusers') !== false) {
            return 'public';
        }
        if (strpos($xml, 'private') !== false || strpos($xml, 'accesscontrollist') !== false) {
            return 'signed';
        }
        return '';
    }

    public static function app_id_from_cos_bucket(string $bucket): string
    {
        if (preg_match('/-(\d{8,})$/', trim($bucket), $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * @param array<string, string> $headers
     */
    public static function region_from_headers(array $headers): string
    {
        $keys = ['x-oss-bucket-region', 'x-cos-bucket-region', 'x-amz-bucket-region', 'x-obs-bucket-region'];
        foreach ($keys as $key) {
            if (!empty($headers[$key])) {
                return self::clean_region((string) $headers[$key]);
            }
        }
        return '';
    }

    public static function region_from_endpoint(string $text): string
    {
        if (preg_match('/\.oss-([a-z0-9-]+)\.aliyuncs\.com/', $text, $m)) {
            return 'oss-' . $m[1];
        }
        if (preg_match('/\.cos\.([a-z0-9-]+)\.myqcloud\.com/', $text, $m)) {
            return $m[1];
        }
        if (preg_match('/\.obs\.([a-z0-9-]+)\.myhuaweicloud\.com/', $text, $m)) {
            return $m[1];
        }
        if (preg_match('/s3[.-]([a-z0-9-]+)\.amazonaws\.com/', $text, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * @param array<string, mixed> $error_data
     */
    public static function region_from_error_data(array $error_data): string
    {
        $headers = [];
        if (isset($error_data['headers']) && is_array($error_data['headers'])) {
            foreach ($error_data['headers'] as $name => $value) {
                $headers[strtolower((string) $name)] = is_array($value) ? (string) reset($value) : (string) $value;
            }
        }
        $from_header = self::region_from_headers($headers);
        if ($from_header !== '') {
            return $from_header;
        }
        $body = isset($error_data['body']) ? (string) $error_data['body'] : '';
        if (preg_match('/<Endpoint>([^<]+)<\/Endpoint>/', $body, $m)) {
            $from_ep = self::region_from_endpoint($m[1]);
            if ($from_ep !== '') {
                return $from_ep;
            }
        }
        return self::region_from_endpoint($body);
    }

    public static function clean_region(string $raw): string
    {
        $raw = strtolower(trim($raw));
        if ($raw === '' || $raw === 'none' || $raw === 'null') {
            return '';
        }
        return $raw;
    }

    public static function s3_empty_location_region(): string
    {
        return 'us-east-1';
    }

    /**
     * @param mixed $headers
     * @return array<string, string>
     */
    public static function flatten_headers($headers): array
    {
        if (is_object($headers) && method_exists($headers, 'getAll')) {
            $headers = $headers->getAll();
        }
        if (!is_array($headers)) {
            return [];
        }
        $out = [];
        foreach ($headers as $name => $value) {
            $out[strtolower((string) $name)] = is_array($value) ? (string) reset($value) : (string) $value;
        }
        return $out;
    }

    /**
     * @param array<string, string> $known
     */
    public static function pick_known_region(string $raw, array $known): string
    {
        $raw = self::clean_region($raw);
        if ($raw === '') {
            return '';
        }
        if (isset($known[$raw])) {
            return $raw;
        }
        foreach ($known as $id => $_label) {
            if ($id === $raw || strpos($id, $raw) !== false || strpos($raw, (string) $id) !== false) {
                return (string) $id;
            }
        }
        return $raw;
    }
}
