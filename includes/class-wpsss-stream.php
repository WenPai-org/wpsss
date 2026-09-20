<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 大文件的分段下载与分片上传引擎。
 *
 * 各厂商 API 都支持 Range GET 与 multipart upload，差异只在签名与响应形状；
 * 这里只保留编排（分段循环、分片循环、失败清理），HTTP 与签名由调用方经
 * 闭包适配。内存上界 = 单段/单片大小，与文件总大小无关。
 */
final class Wpsss_Stream
{
    /** @var int 拉回单段 8MB */
    public const RANGE_CHUNK = 8388608;

    /** @var int 上传单片 8MB */
    public const PART_SIZE = 8388608;

    /** @var int 超过 32MB 走分片上传 */
    public const MULTIPART_THRESHOLD = 33554432;

    /**
     * 分段下载到本地文件。
     *
     * @param callable(int,int): array{code:int, body:string, headers:array<string,string>}|WP_Error $range_fetch
     *   请求 [start, endInclusive] 的字节区间，返回响应元数据。
     * @return true|WP_Error
     */
    public static function download(callable $range_fetch, string $dest)
    {
        $fh = @fopen($dest, 'wb');
        if ($fh === false) {
            return new WP_Error('wpsss_open_dest', '无法创建本地文件。');
        }
        $pos = 0;
        $total = -1;
        $chunk = self::RANGE_CHUNK;
        while (true) {
            $r = $range_fetch($pos, $pos + $chunk - 1);
            if (is_wp_error($r)) {
                fclose($fh);
                @unlink($dest);
                return $r;
            }
            $code = (int) ($r['code'] ?? 0);
            $body = (string) ($r['body'] ?? '');
            $headers = isset($r['headers']) && is_array($r['headers']) ? $r['headers'] : [];
            if ($code === 200) {
                // 服务器忽略 Range 整包返回：直接落盘收工。
                if (fwrite($fh, $body) !== strlen($body)) {
                    fclose($fh);
                    @unlink($dest);
                    return new WP_Error('wpsss_write_dest', '写本地文件失败。');
                }
                fclose($fh);
                return true;
            }
            if ($code !== 206) {
                fclose($fh);
                @unlink($dest);
                return new WP_Error('wpsss_range_http', '拉回分段返回 HTTP ' . $code . '。');
            }
            $content_range = '';
            foreach ($headers as $name => $value) {
                if (strcasecmp((string) $name, 'content-range') === 0) {
                    $content_range = (string) $value;
                }
            }
            if ($content_range !== '' && preg_match('#bytes\s+\d+-\d+/(\d+)#i', $content_range, $m)) {
                $total = (int) $m[1];
            }
            if (fwrite($fh, $body) !== strlen($body)) {
                fclose($fh);
                @unlink($dest);
                return new WP_Error('wpsss_write_dest', '写本地文件失败。');
            }
            $pos += strlen($body);
            if ($total >= 0 && $pos >= $total) {
                break;
            }
            if ($body === '') {
                fclose($fh);
                @unlink($dest);
                return new WP_Error('wpsss_range_empty', '拉回分段返回了空内容。');
            }
            if ($total < 0 && strlen($body) < $chunk) {
                break;
            }
        }
        fclose($fh);
        if ($total >= 0 && (int) @filesize($dest) !== $total) {
            @unlink($dest);
            return new WP_Error('wpsss_size_mismatch', '拉回下来的文件大小与桶内不一致。');
        }
        return true;
    }

    /**
     * 分片上传一个本地文件。
     *
     * @param callable(): string|WP_Error $initiate 返回 uploadId
     * @param callable(string,int,string): string|WP_Error $part 上传一片，返回 ETag
     * @param callable(string,string): true|WP_Error $complete 提交（第二参为 CompleteMultipartUpload XML）
     * @param callable(string): void $abort 放弃
     * @return true|WP_Error
     */
    public static function upload(callable $initiate, callable $part, callable $complete, callable $abort, string $path, int $part_size = self::PART_SIZE)
    {
        if (!is_readable($path) || ($size = (int) filesize($path)) <= 0) {
            return new WP_Error('wpsss_read_file', '读本地文件失败。');
        }
        $upload_id = $initiate();
        if (is_wp_error($upload_id) || !is_string($upload_id) || $upload_id === '') {
            return is_wp_error($upload_id) ? $upload_id : new WP_Error('wpsss_multipart_init', '初始化分片上传失败。');
        }
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            $abort($upload_id);
            return new WP_Error('wpsss_read_file', '读本地文件失败。');
        }
        $number = 1;
        $etags = [];
        while (!feof($fh)) {
            $body = fread($fh, $part_size);
            if ($body === false || $body === '') {
                break;
            }
            $etag = $part($upload_id, $number, $body);
            if (is_wp_error($etag)) {
                fclose($fh);
                $abort($upload_id);
                return $etag;
            }
            $etags[$number] = (string) $etag;
            $number++;
        }
        fclose($fh);
        if ($etags === []) {
            $abort($upload_id);
            return new WP_Error('wpsss_multipart_empty', '没有读到任何分片内容。');
        }
        $done = $complete($upload_id, self::complete_xml($etags));
        if (is_wp_error($done)) {
            $abort($upload_id);
            return $done;
        }
        return true;
    }

    /**
     * @param array<int, string> $etags 分片号 => ETag
     */
    public static function complete_xml(array $etags): string
    {
        $xml = '<CompleteMultipartUpload>';
        ksort($etags);
        foreach ($etags as $number => $etag) {
            $xml .= '<Part><PartNumber>' . (int) $number . '</PartNumber><ETag>' . esc_xml((string) $etag) . '</ETag></Part>';
        }
        return $xml . '</CompleteMultipartUpload>';
    }

    public static function parse_upload_id(string $body): string
    {
        if (preg_match('/<UploadId>([^<]+)<\/UploadId>/', $body, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /**
     * 上传请求超时按体积缩放：按 2MB/s 预算再加 30 秒余量，短请求保持原基线。
     */
    public static function put_timeout(int $bytes, int $base = 60): int
    {
        if ($bytes <= 0) {
            return $base;
        }
        return max($base, (int) ceil($bytes / 2097152) + 30);
    }
}
