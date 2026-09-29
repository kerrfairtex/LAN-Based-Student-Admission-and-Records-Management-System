<?php

declare(strict_types=1);

/**
 * Storage abstraction for file upload/backup operations.
 *
 * On Render, files are written to the local filesystem (uploads/, backups/).
 * On Vercel, the filesystem is read-only — files must go to Supabase Storage.
 *
 * This abstraction lets the application code call storage_upload(),
 * storage_download(), storage_delete(), storage_exists(), storage_list()
 * without knowing the underlying provider.
 *
 * Environment:
 *   APP_ENV=production  → Supabase Storage (HTTP API)
 *   APP_ENV!=production → Local filesystem (for dev/testing)
 */

interface StorageAdapter
{
    /** Upload content to storage. Returns the object key/path. */
    public function upload(string $path, string $content, string $mimeType = 'application/octet-stream'): string;

    /** Download content from storage. Returns null if not found. */
    public function download(string $path): ?string;

    /** Delete an object from storage. Returns true on success. */
    public function delete(string $path): bool;

    /** Check if an object exists in storage. */
    public function exists(string $path): bool;

    /** List objects with the given prefix. Returns array of paths. */
    public function list(string $prefix = ''): array;

    /** Stream an object to the browser for download. */
    public function streamDownload(string $path, string $contentType, string $filename): void;
}

/**
 * Local filesystem storage adapter (development/testing).
 */
class LocalStorageAdapter implements StorageAdapter
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function upload(string $path, string $content, string $mimeType = 'application/octet-stream'): string
    {
        $fullPath = $this->basePath . '/' . $path;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($fullPath, $content);
        return $path;
    }

    public function download(string $path): ?string
    {
        $fullPath = $this->basePath . '/' . $path;
        if (!is_file($fullPath)) {
            return null;
        }
        return file_get_contents($fullPath);
    }

    public function delete(string $path): bool
    {
        $fullPath = $this->basePath . '/' . $path;
        return @unlink($fullPath);
    }

    public function exists(string $path): bool
    {
        return is_file($this->basePath . '/' . $path);
    }

    public function list(string $prefix = ''): array
    {
        $dir = $this->basePath . '/' . $prefix;
        if (!is_dir($dir)) {
            return [];
        }
        $results = [];
        $entries = scandir($dir);
        if ($entries === false) {
            return [];
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $fullPath = $dir . '/' . $entry;
            if (is_file($fullPath)) {
                $results[] = $prefix !== '' ? $prefix . '/' . $entry : $entry;
            } elseif (is_dir($fullPath)) {
                $results = array_merge($results, $this->list($prefix !== '' ? $prefix . '/' . $entry : $entry));
            }
        }
        return $results;
    }

    public function streamDownload(string $path, string $contentType, string $filename): void
    {
        $fullPath = $this->basePath . '/' . $path;
        if (!is_file($fullPath)) {
            http_response_code(404);
            exit('File not found.');
        }
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }
}

/**
 * Supabase Storage adapter (production/Vercel).
 *
 * Uses the Supabase Storage REST API. The bucket name is configurable
 * via SUPABASE_STORAGE_BUCKET env var (default: 'sarms-backups').
 *
 * Requires:
 *   SUPABASE_URL — e.g. https://xxxx.supabase.co
 *   SUPABASE_API_KEY — service_role or anon key with storage permissions
 *
 * The upload path within the bucket mirrors the local filesystem path
 * (e.g. 'backups/trac_jhs_backup_2026-09-29_120000.sql').
 */
class SupabaseStorageAdapter implements StorageAdapter
{
    private string $supabaseUrl;
    private string $supabaseKey;
    private string $bucket;

    public function __construct()
    {
        $this->supabaseUrl = rtrim(getenv('SUPABASE_URL') ?: '', '/');
        $this->supabaseKey = (string) (getenv('SUPABASE_API_KEY') ?: '');
        $this->bucket = (string) (getenv('SUPABASE_STORAGE_BUCKET') ?: 'sarms-backups');
    }

    private function request(string $method, string $endpoint, ?string $body = null): array
    {
        $url = $this->supabaseUrl . '/storage/v1' . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $this->supabaseKey,
            'apikey: ' . $this->supabaseKey,
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/octet-stream';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headerPart = substr((string) $response, 0, $headerSize);
        $bodyPart = substr((string) $response, $headerSize);
        curl_close($ch);

        return [
            'http_code' => $httpCode,
            'headers' => $headerPart,
            'body' => $bodyPart,
        ];
    }

    /**
     * Upload a file to the bucket. The path is the object key within
     * the bucket. Returns the full object path on success.
     */
    public function upload(string $path, string $content, string $mimeType = 'application/octet-stream'): string
    {
        $endpoint = '/object/' . $this->bucket . '/' . $path;
        $resp = $this->request('POST', $endpoint, $content);

        if ($resp['http_code'] >= 400) {
            error_log('SupabaseStorage: upload failed for ' . $path . ': HTTP ' . $resp['http_code']);
            throw new RuntimeException('Storage upload failed: ' . $resp['body']);
        }

        return $path;
    }

    /**
     * Download a file from the bucket. Returns null if not found.
     */
    public function download(string $path): ?string
    {
        $endpoint = '/object/' . $this->bucket . '/' . $path;
        $resp = $this->request('GET', $endpoint);

        if ($resp['http_code'] === 406 || $resp['http_code'] === 404) {
            return null;
        }

        if ($resp['http_code'] >= 400) {
            error_log('SupabaseStorage: download failed for ' . $path . ': HTTP ' . $resp['http_code']);
            return null;
        }

        return $resp['body'];
    }

    public function delete(string $path): bool
    {
        $endpoint = '/object/' . $this->bucket . '/' . $path;
        $resp = $this->request('DELETE', $endpoint);
        return $resp['http_code'] < 400;
    }

    public function exists(string $path): bool
    {
        $endpoint = '/object/' . $this->bucket . '/' . $path;
        $resp = $this->request('GET', $endpoint);
        return $resp['http_code'] === 200;
    }

    public function list(string $prefix = ''): array
    {
        $endpoint = '/object/list/' . $this->bucket . '?prefix=' . urlencode($prefix);
        $resp = $this->request('GET', $endpoint);

        if ($resp['http_code'] !== 200) {
            return [];
        }

        $data = json_decode($resp['body'], true);
        if (!is_array($data)) {
            return [];
        }

        $results = [];
        foreach ($data as $item) {
            if (isset($item['name'])) {
                $results[] = $item['name'];
            }
        }
        return $results;
    }

    public function streamDownload(string $path, string $contentType, string $filename): void
    {
        $content = $this->download($path);
        if ($content === null) {
            http_response_code(404);
            exit('File not found.');
        }

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
}

/**
 * Factory function: returns the appropriate storage adapter.
 * Uses Supabase Storage in production, local filesystem otherwise.
 */
function storage(): StorageAdapter
{
    static $adapter = null;

    if ($adapter instanceof StorageAdapter) {
        return $adapter;
    }

    $appEnv = getenv('APP_ENV') ?: '';

    if ($appEnv === 'production') {
        $adapter = new SupabaseStorageAdapter();
    } else {
        // Determine the local storage base path
        $storageDir = getenv('STORAGE_DIR') ?: (dirname(__DIR__) . '/uploads');
        $adapter = new LocalStorageAdapter($storageDir);
    }

    return $adapter;
}

/**
 * Convenience helpers matching the conceptual API from the migration plan.
 */
function storage_upload(string $path, string $content, string $mimeType = 'application/octet-stream'): string
{
    return storage()->upload($path, $content, $mimeType);
}

function storage_download(string $path): ?string
{
    return storage()->download($path);
}

function storage_delete(string $path): bool
{
    return storage()->delete($path);
}

function storage_exists(string $path): bool
{
    return storage()->exists($path);
}

function storage_list(string $prefix = ''): array
{
    return storage()->list($prefix);
}

function storage_stream_download(string $path, string $contentType, string $filename): void
{
    storage()->streamDownload($path, $contentType, $filename);
}
