<?php

if (!defined('ABSPATH')) {
    exit;
}

class Wpsss_S3_Signer
{
    public const EMPTY_SHA256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    public static function amz_date(?int $now = null): string
    {
        return gmdate('Ymd\THis\Z', $now ?? time());
    }

    public static function payload_hash(string $body): string
    {
        return hash('sha256', $body);
    }

    /**
     * @param array<string, string> $headers
     * @return array{canonical: string, signed: string, string_to_sign: string, signature: string, authorization: string}
     */
    public static function sign(
        string $access_key,
        string $secret,
        string $method,
        string $canonical_uri,
        string $query,
        array $headers,
        string $payload_hash,
        string $amz_date,
        string $region,
        string $service = 's3'
    ): array {
        $method = strtoupper($method);
        $region = $region !== '' ? $region : 'us-east-1';
        $short_date = substr($amz_date, 0, 8);

        $normalized = [];
        foreach ($headers as $name => $value) {
            $key = strtolower(trim((string) $name));
            $normalized[$key] = preg_replace('/\s+/', ' ', trim((string) $value)) ?? '';
        }
        ksort($normalized);

        $canonical_headers = '';
        $signed_names = [];
        foreach ($normalized as $name => $value) {
            $canonical_headers .= $name . ':' . $value . "\n";
            $signed_names[] = $name;
        }
        $signed = implode(';', $signed_names);

        $canonical = implode("\n", [
            $method,
            self::canonical_uri($canonical_uri),
            $query,
            $canonical_headers,
            $signed,
            $payload_hash,
        ]);

        $scope = $short_date . '/' . $region . '/' . $service . '/aws4_request';
        $string_to_sign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amz_date,
            $scope,
            hash('sha256', $canonical),
        ]);

        $signature = self::hex_hmac(self::signing_key($secret, $short_date, $region, $service), $string_to_sign);
        $authorization = 'AWS4-HMAC-SHA256 Credential=' . $access_key . '/' . $scope
            . ',SignedHeaders=' . $signed
            . ',Signature=' . $signature;

        return [
            'canonical' => $canonical,
            'signed' => $signed,
            'string_to_sign' => $string_to_sign,
            'signature' => $signature,
            'authorization' => $authorization,
        ];
    }

    public static function encode_key(string $object): string
    {
        $object = ltrim($object, '/');
        $parts = explode('/', $object);
        $encoded = [];
        foreach ($parts as $part) {
            $encoded[] = self::uri_encode($part, false);
        }
        return implode('/', $encoded);
    }

    public static function canonical_uri(string $uri): string
    {
        if ($uri === '') {
            return '/';
        }
        if ($uri[0] !== '/') {
            $uri = '/' . $uri;
        }
        $parts = explode('/', $uri);
        $encoded = [];
        foreach ($parts as $i => $part) {
            if ($i === 0) {
                $encoded[] = '';
                continue;
            }
            $encoded[] = self::uri_encode($part, false);
        }
        return implode('/', $encoded);
    }

    public static function uri_encode(string $value, bool $encode_slash = true): string
    {
        $out = '';
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $ch = $value[$i];
            $ord = ord($ch);
            $unreserved = ($ord >= 65 && $ord <= 90)
                || ($ord >= 97 && $ord <= 122)
                || ($ord >= 48 && $ord <= 57)
                || $ch === '-' || $ch === '_' || $ch === '.' || $ch === '~';
            if ($unreserved || ($ch === '/' && !$encode_slash)) {
                $out .= $ch;
                continue;
            }
            $out .= '%' . strtoupper(bin2hex($ch));
        }
        return $out;
    }

    public static function signing_key(string $secret, string $short_date, string $region, string $service = 's3'): string
    {
        $k_date = self::raw_hmac('AWS4' . $secret, $short_date);
        $k_region = self::raw_hmac($k_date, $region);
        $k_service = self::raw_hmac($k_region, $service);
        return self::raw_hmac($k_service, 'aws4_request');
    }

    private static function raw_hmac(string $key, string $data): string
    {
        return hash_hmac('sha256', $data, $key, true);
    }

    private static function hex_hmac(string $key, string $data): string
    {
        return hash_hmac('sha256', $data, $key, false);
    }
}
