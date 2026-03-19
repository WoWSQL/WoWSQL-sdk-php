<?php

namespace WOWSQL;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;

// ── Models ───────────────────────────────────────────────────────

/**
 * Bucket information.
 */
class StorageBucket
{
    public $id;
    public $name;
    public $public;
    public $fileSizeLimit;
    public $allowedMimeTypes;
    public $createdAt;
    public $objectCount;
    public $totalSize;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->public = (bool)($data['public'] ?? false);
        $this->fileSizeLimit = $data['file_size_limit'] ?? null;
        $this->allowedMimeTypes = $data['allowed_mime_types'] ?? null;
        $this->createdAt = $data['created_at'] ?? null;
        $this->objectCount = $data['object_count'] ?? 0;
        $this->totalSize = $data['total_size'] ?? 0;
    }
}

/**
 * Storage file / object information.
 */
class StorageFile
{
    public $id;
    public $bucketId;
    public $name;
    public $path;
    public $mimeType;
    public $size;
    public $metadata;
    public $createdAt;
    public $publicUrl;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? '';
        $this->bucketId = $data['bucket_id'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->path = $data['path'] ?? '';
        $this->mimeType = $data['mime_type'] ?? null;
        $this->size = $data['size'] ?? 0;
        $this->metadata = $data['metadata'] ?? [];
        $this->createdAt = $data['created_at'] ?? null;
        $this->publicUrl = $data['public_url'] ?? null;
    }

    /** @return float Size in megabytes. */
    public function sizeMb()
    {
        return $this->size / (1024 * 1024);
    }

    /** @return float Size in gigabytes. */
    public function sizeGb()
    {
        return $this->size / (1024 ** 3);
    }
}

/**
 * Storage quota / statistics information.
 */
class StorageQuota
{
    public $totalFiles;
    public $totalSizeBytes;
    public $totalSizeGb;
    public $fileTypes;

    public function __construct(array $data = [])
    {
        $this->totalFiles = $data['total_files'] ?? 0;
        $this->totalSizeBytes = $data['total_size_bytes'] ?? 0;
        $this->totalSizeGb = $data['total_size_gb'] ?? 0;
        $this->fileTypes = $data['file_types'] ?? [];
    }
}

// ── Storage Client ───────────────────────────────────────────────

/**
 * WOWSQL Storage Client — PostgreSQL-native file storage.
 *
 * Files are stored as BYTEA inside each project's ``storage`` schema.
 * No external S3 dependency — everything lives in PostgreSQL.
 */
class WOWSQLStorage
{
    private $baseUrl;
    private $projectSlug;
    private $httpClient;
    private $timeout;
    private $verifySsl;

    /**
     * Initialize WOWSQL Storage client.
     *
     * @param string $projectUrl   Project subdomain or full URL
     * @param string $apiKey       API key for authentication
     * @param string $projectSlug  Explicit slug (used with $baseUrl)
     * @param string $baseUrl      Explicit base URL (used with $projectSlug)
     * @param string $baseDomain   Base domain (default: wowsql.com)
     * @param bool   $secure       Use HTTPS (default: true)
     * @param int    $timeout      Request timeout in seconds (default: 60)
     * @param bool   $verifySsl    Verify SSL certificates (default: true)
     */
    public function __construct(
        $projectUrl = '',
        $apiKey = '',
        $projectSlug = '',
        $baseUrl = '',
        $baseDomain = 'wowsql.com',
        $secure = true,
        $timeout = 60,
        $verifySsl = true
    ) {
        if ($projectSlug && $baseUrl) {
            $this->baseUrl = rtrim($baseUrl, '/');
            $this->projectSlug = $projectSlug;
        } elseif ($projectUrl) {
            $url = trim($projectUrl);
            if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
                $this->baseUrl = rtrim($url, '/');
                if (strpos($this->baseUrl, '/api') !== false) {
                    $this->baseUrl = explode('/api', $this->baseUrl, 2)[0];
                }
            } else {
                $protocol = $secure ? 'https' : 'http';
                if (strpos($url, ".{$baseDomain}") !== false || substr($url, -strlen($baseDomain)) === $baseDomain) {
                    $this->baseUrl = "{$protocol}://{$url}";
                } else {
                    $this->baseUrl = "{$protocol}://{$url}.{$baseDomain}";
                }
            }
            $slug = str_replace(['https://', 'http://'], '', $url);
            $parts = explode('.', $slug, 2);
            $this->projectSlug = $parts[0];
        } else {
            throw new \InvalidArgumentException('Either projectUrl or (projectSlug + baseUrl) must be provided');
        }

        $this->timeout = $timeout;
        $this->verifySsl = $verifySsl;

        $this->httpClient = new HttpClient([
            'base_uri' => $this->baseUrl,
            'timeout' => $timeout,
            'verify' => $verifySsl,
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
            ],
        ]);
    }

    // ── Buckets ──────────────────────────────────────────────────

    /**
     * Create a new storage bucket.
     *
     * @param  string     $name
     * @param  bool       $public
     * @param  int|null   $fileSizeLimit
     * @param  array|null $allowedMimeTypes
     * @return StorageBucket
     * @throws StorageException
     */
    public function createBucket($name, $public = false, $fileSizeLimit = null, $allowedMimeTypes = null)
    {
        $data = $this->request('POST', "/api/v1/storage/projects/{$this->projectSlug}/buckets", null, [
            'name' => $name,
            'public' => $public,
            'file_size_limit' => $fileSizeLimit,
            'allowed_mime_types' => $allowedMimeTypes,
        ]);
        return new StorageBucket($data);
    }

    /**
     * List all buckets in the project.
     *
     * @return StorageBucket[]
     * @throws StorageException
     */
    public function listBuckets()
    {
        $data = $this->request('GET', "/api/v1/storage/projects/{$this->projectSlug}/buckets");
        return array_map(function ($b) { return new StorageBucket($b); }, $data);
    }

    /**
     * Get a specific bucket by name.
     *
     * @param  string $name
     * @return StorageBucket
     * @throws StorageException
     */
    public function getBucket($name)
    {
        $data = $this->request('GET', "/api/v1/storage/projects/{$this->projectSlug}/buckets/{$name}");
        return new StorageBucket($data);
    }

    /**
     * Update bucket settings.
     *
     * @param  string $name   Bucket name
     * @param  array  $fields Fields to update (name, public, file_size_limit, allowed_mime_types)
     * @return StorageBucket
     * @throws StorageException
     */
    public function updateBucket($name, array $fields)
    {
        $data = $this->request('PATCH', "/api/v1/storage/projects/{$this->projectSlug}/buckets/{$name}", null, $fields);
        return new StorageBucket($data);
    }

    /**
     * Delete a bucket and all its files.
     *
     * @param  string $name
     * @return array
     * @throws StorageException
     */
    public function deleteBucket($name)
    {
        return $this->request('DELETE', "/api/v1/storage/projects/{$this->projectSlug}/buckets/{$name}");
    }

    // ── Files ────────────────────────────────────────────────────

    /**
     * Upload a file to a bucket.
     *
     * @param  string          $bucketName Target bucket
     * @param  resource|string $fileData   File resource or file contents as string
     * @param  string|null     $path       File path within bucket
     * @param  string|null     $fileName   Override filename
     * @return StorageFile
     * @throws StorageException
     */
    public function upload($bucketName, $fileData, $path = null, $fileName = null)
    {
        if (is_resource($fileData)) {
            $content = stream_get_contents($fileData);
        } else {
            $content = $fileData;
        }

        $name = $fileName ?: ($path ? basename($path) : 'file');

        $folder = '';
        if ($path && strpos($path, '/') !== false) {
            $folder = substr($path, 0, strrpos($path, '/'));
        }

        $params = [];
        if ($folder) {
            $params['folder'] = $folder;
        }

        $url = "/api/v1/storage/projects/{$this->projectSlug}/buckets/{$bucketName}/files";

        try {
            $options = [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $content,
                        'filename' => $name,
                    ],
                ],
            ];
            if (!empty($params)) {
                $options['query'] = $params;
            }

            $response = $this->httpClient->request('POST', $url, $options);
            $result = json_decode($response->getBody()->getContents(), true);
            return new StorageFile($result);
        } catch (RequestException $e) {
            $this->handleRequestException($e);
        }
    }

    /**
     * Upload a file from a local filesystem path.
     *
     * @param  string      $filePath   Path to local file
     * @param  string      $bucketName Target bucket (default: 'default')
     * @param  string|null $path       File path within bucket
     * @return StorageFile
     * @throws StorageException
     */
    public function uploadFromPath($filePath, $bucketName = 'default', $path = null)
    {
        if (!file_exists($filePath)) {
            throw new StorageException("File not found: {$filePath}");
        }
        $fileName = basename($filePath);
        $handle = fopen($filePath, 'rb');
        try {
            return $this->upload($bucketName, $handle, $path ?: $fileName, $fileName);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /**
     * List files in a bucket.
     *
     * @param  string      $bucketName
     * @param  string|null $prefix Filter by prefix/folder
     * @param  int         $limit
     * @param  int         $offset
     * @return StorageFile[]
     * @throws StorageException
     */
    public function listFiles($bucketName, $prefix = null, $limit = 100, $offset = 0)
    {
        $params = ['limit' => $limit, 'offset' => $offset];
        if ($prefix !== null && $prefix !== '') {
            $params['prefix'] = $prefix;
        }
        $data = $this->request('GET', "/api/v1/storage/projects/{$this->projectSlug}/buckets/{$bucketName}/files", $params);
        $items = is_array($data) && isset($data[0]) ? $data : ($data['files'] ?? $data['data'] ?? []);
        return array_map(function ($f) { return new StorageFile($f); }, $items);
    }

    /**
     * Download a file and return its binary contents.
     *
     * @param  string $bucketName
     * @param  string $filePath
     * @return string Raw file bytes
     * @throws StorageException
     */
    public function download($bucketName, $filePath)
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                "/api/v1/storage/projects/{$this->projectSlug}/files/{$bucketName}/{$filePath}",
                ['stream' => true]
            );
            return $response->getBody()->getContents();
        } catch (RequestException $e) {
            $this->handleRequestException($e);
        }
    }

    /**
     * Download a file and save it to a local path.
     *
     * @param  string $bucketName
     * @param  string $filePath
     * @param  string $localPath
     * @return string The local path written to
     * @throws StorageException
     */
    public function downloadToFile($bucketName, $filePath, $localPath)
    {
        $content = $this->download($bucketName, $filePath);
        $dir = dirname($localPath);
        if ($dir && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($localPath, $content);
        return $localPath;
    }

    /**
     * Delete a file from a bucket.
     *
     * @param  string $bucketName
     * @param  string $filePath
     * @return array
     * @throws StorageException
     */
    public function deleteFile($bucketName, $filePath)
    {
        return $this->request('DELETE', "/api/v1/storage/projects/{$this->projectSlug}/files/{$bucketName}/{$filePath}");
    }

    // ── Utilities ────────────────────────────────────────────────

    /**
     * Get the public URL for a file in a public bucket.
     *
     * @param  string $bucketName
     * @param  string $filePath
     * @return string
     */
    public function getPublicUrl($bucketName, $filePath)
    {
        return "{$this->baseUrl}/api/v1/storage/projects/{$this->projectSlug}/files/{$bucketName}/{$filePath}";
    }

    /**
     * Get storage statistics for the project.
     *
     * @return StorageQuota
     * @throws StorageException
     */
    public function getStats()
    {
        $data = $this->request('GET', "/api/v1/storage/projects/{$this->projectSlug}/stats");
        return new StorageQuota($data);
    }

    /**
     * Get storage quota (alias for getStats).
     *
     * @param  bool $forceRefresh Unused, kept for API parity
     * @return StorageQuota
     * @throws StorageException
     */
    public function getQuota($forceRefresh = false)
    {
        return $this->getStats();
    }

    /**
     * Close the HTTP client.
     */
    public function close()
    {
        // Guzzle does not hold persistent connections that need explicit closing.
    }

    // ── Internal ─────────────────────────────────────────────────

    private function request($method, $path, $params = null, $json = null)
    {
        try {
            $options = [];
            if ($params) {
                $options['query'] = $params;
            }
            if ($json !== null && in_array($method, ['POST', 'PATCH', 'PUT'])) {
                $options['json'] = $json;
            }

            $response = $this->httpClient->request($method, $path, $options);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $this->handleRequestException($e);
        } catch (\Exception $e) {
            throw new StorageException("Request failed: " . $e->getMessage());
        }
    }

    private function handleRequestException(RequestException $e)
    {
        $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
        $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';

        $errorData = [];
        try {
            $errorData = json_decode($errorBody, true) ?: [];
        } catch (\Exception $ex) {
            // ignore
        }

        $errorMsg = $errorData['detail'] ?? $errorData['message'] ?? $e->getMessage();

        if ($statusCode === 413) {
            throw new StorageLimitExceededException($errorMsg, $statusCode, $errorData);
        }

        throw new StorageException($errorMsg, $statusCode, $errorData);
    }
}
