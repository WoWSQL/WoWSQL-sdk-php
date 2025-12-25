<?php

namespace WOWSQL;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\MultipartStream;

/**
 * WOWSQL Storage Client - Manage S3 storage with automatic quota validation.
 */
class WOWSQLStorage
{
    private $projectSlug;
    private $apiKey;
    private $baseUrl;
    private $httpClient;
    private $timeout;
    private $autoCheckQuota;
    private $quotaCache;

    /**
     * Initialize WOWSQL Storage client.
     * 
     * @param string $projectSlug Project slug (e.g., 'myproject')
     * @param string $apiKey API key for authentication
     * @param string $baseUrl API base URL (default: https://api.wowsql.com)
     * @param int $timeout Request timeout in seconds (default: 60 for file uploads)
     * @param bool $autoCheckQuota Automatically check quota before uploads (default: true)
     */
    public function __construct($projectSlug, $apiKey, $baseUrl = "https://api.wowsql.com", $timeout = 60, $autoCheckQuota = true)
    {
        $this->projectSlug = $projectSlug;
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->autoCheckQuota = $autoCheckQuota;

        $this->httpClient = new HttpClient([
            'base_uri' => $this->baseUrl,
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
            ],
        ]);
    }

    /**
     * Get storage quota information.
     * 
     * @param bool $forceRefresh Force refresh quota from server
     * @return array Storage quota details
     * @throws StorageException If quota fetch fails
     */
    public function getQuota($forceRefresh = false)
    {
        if ($this->quotaCache !== null && !$forceRefresh) {
            return $this->quotaCache;
        }

        $response = $this->request('GET', "/api/v1/storage/s3/projects/{$this->projectSlug}/quota", null, null);
        $this->quotaCache = $response;
        return $response;
    }

    /**
     * Upload file from local filesystem path.
     * 
     * @param string $filePath Path to local file
     * @param string $fileKey File name or path in bucket
     * @param string|null $folder Optional folder path
     * @param string|null $contentType Optional content type
     * @param bool|null $checkQuota Override auto quota checking
     * @return array Upload result
     * @throws StorageException If upload fails
     * @throws StorageLimitExceededException If storage quota would be exceeded
     */
    public function uploadFromPath($filePath, $fileKey = null, $folder = null, $contentType = null, $checkQuota = null)
    {
        if (!file_exists($filePath)) {
            throw new StorageException("File not found: {$filePath}");
        }

        if ($fileKey === null) {
            $fileKey = basename($filePath);
        }

        $fileData = fopen($filePath, 'rb');
        return $this->uploadFile($fileData, $fileKey, $folder, $contentType, $checkQuota);
    }

    /**
     * Upload file from file resource or stream.
     * 
     * @param resource|string $fileData File resource or file path
     * @param string $fileKey File name or path in bucket
     * @param string|null $folder Optional folder path
     * @param string|null $contentType Optional content type
     * @param bool|null $checkQuota Override auto quota checking
     * @return array Upload result
     * @throws StorageException If upload fails
     */
    public function uploadFile($fileData, $fileKey, $folder = null, $contentType = null, $checkQuota = null)
    {
        $shouldCheck = $checkQuota !== null ? $checkQuota : $this->autoCheckQuota;

        // Read file data
        if (is_resource($fileData)) {
            $fileBytes = stream_get_contents($fileData);
            rewind($fileData);
        } else {
            $fileBytes = file_get_contents($fileData);
        }
        $fileSize = strlen($fileBytes);

        // Check quota if enabled
        if ($shouldCheck) {
            $quota = $this->getQuota(true);
            $fileSizeGb = $fileSize / (1024.0 * 1024.0 * 1024.0);
            if ($fileSizeGb > $quota['storage_available_gb']) {
                throw new StorageLimitExceededException(
                    "Storage limit exceeded! File size: " . number_format($fileSizeGb, 4) . " GB, " .
                    "Available: " . number_format($quota['storage_available_gb'], 4) . " GB."
                );
            }
        }

        // Build URL
        $url = "/api/v1/storage/s3/projects/{$this->projectSlug}/upload";
        if ($folder !== null && $folder !== '') {
            $url .= "?folder=" . urlencode($folder);
        }

        try {
            // Create multipart form
            $multipart = [
                [
                    'name' => 'file',
                    'contents' => $fileBytes,
                    'filename' => $fileKey,
                ],
                [
                    'name' => 'key',
                    'contents' => $fileKey,
                ],
            ];

            if ($contentType !== null) {
                $multipart[] = [
                    'name' => 'content_type',
                    'contents' => $contentType,
                ];
            }

            $response = $this->httpClient->request('POST', $url, [
                'multipart' => $multipart,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            $this->quotaCache = null; // Refresh quota cache
            return $result;
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            
            $errorData = [];
            try {
                $errorData = json_decode($errorBody, true) ?: [];
            } catch (\Exception $ex) {
                // Ignore parse errors
            }
            
            $errorMsg = $errorData['detail'] ?? $errorData['message'] ?? $e->getMessage();
            
            if ($statusCode === 413) {
                throw new StorageLimitExceededException($errorMsg, $statusCode, $errorData);
            }
            
            throw new StorageException($errorMsg, $statusCode, $errorData);
        }
    }

    /**
     * List files in S3 bucket.
     * 
     * @param string|null $prefix Filter by prefix/folder
     * @param int $maxKeys Maximum files to return
     * @return array List of file objects
     * @throws StorageException If listing fails
     */
    public function listFiles($prefix = null, $maxKeys = 1000)
    {
        $params = ['max_keys' => $maxKeys];
        if ($prefix !== null && $prefix !== '') {
            $params['prefix'] = $prefix;
        }

        $response = $this->request('GET', "/api/v1/storage/s3/projects/{$this->projectSlug}/files", $params, null);
        return $response['files'] ?? [];
    }

    /**
     * Delete a file from S3 bucket.
     * 
     * @param string $fileKey Path to file in bucket
     * @return array Deletion result
     * @throws StorageException If deletion fails
     */
    public function deleteFile($fileKey)
    {
        $response = $this->request('DELETE', "/api/v1/storage/s3/projects/{$this->projectSlug}/files/{$fileKey}", null, null);
        $this->quotaCache = null; // Refresh quota cache
        return $response;
    }

    /**
     * Get presigned URL for file access with full metadata.
     * 
     * @param string $fileKey Path to file in bucket
     * @param int $expiresIn URL validity in seconds (default: 3600 = 1 hour)
     * @return array Dict with file URL and metadata
     * @throws StorageException If URL generation fails
     */
    public function getFileUrl($fileKey, $expiresIn = 3600)
    {
        $params = ['expires_in' => $expiresIn];
        return $this->request('GET', "/api/v1/storage/s3/projects/{$this->projectSlug}/files/{$fileKey}/url", $params, null);
    }

    /**
     * Generate presigned URL for file operations.
     * 
     * @param string $fileKey Path to file in bucket
     * @param int $expiresIn URL validity in seconds (default: 3600)
     * @param string $operation 'get_object' (download) or 'put_object' (upload)
     * @return string Presigned URL string
     * @throws StorageException If URL generation fails
     */
    public function getPresignedUrl($fileKey, $expiresIn = 3600, $operation = 'get_object')
    {
        $payload = [
            'file_key' => $fileKey,
            'expires_in' => $expiresIn,
            'operation' => $operation,
        ];

        $response = $this->request('POST', "/api/v1/storage/s3/projects/{$this->projectSlug}/presigned-url", null, $payload);
        return $response['url'] ?? '';
    }

    /**
     * Get S3 storage information for the project.
     * 
     * @return array Dict with storage info
     * @throws StorageException If request fails
     */
    public function getStorageInfo()
    {
        return $this->request('GET', "/api/v1/storage/s3/projects/{$this->projectSlug}/info", null, null);
    }

    /**
     * Provision S3 storage for the project.
     * ⚠️ IMPORTANT: Save the credentials returned! They're only shown once.
     * 
     * @param string $region AWS region (default: 'us-east-1')
     * @return array Dict with provisioning result including credentials
     * @throws StorageException If provisioning fails
     */
    public function provisionStorage($region = 'us-east-1')
    {
        $payload = ['region' => $region];
        return $this->request('POST', "/api/v1/storage/s3/projects/{$this->projectSlug}/provision", null, $payload);
    }

    /**
     * Get list of available S3 regions with pricing.
     * 
     * @return array List of region dictionaries with pricing info
     * @throws StorageException If request fails
     */
    public function getAvailableRegions()
    {
        $response = $this->request('GET', '/api/v1/storage/s3/regions', null, null);
        
        // Handle both list and map responses
        if (is_array($response) && isset($response[0])) {
            return $response;
        }
        
        if (is_array($response) && isset($response['regions'])) {
            return $response['regions'];
        }
        
        return [$response];
    }

    /**
     * Make HTTP request to Storage API.
     */
    private function request($method, $path, $params = null, $json = null)
    {
        try {
            $options = [];
            
            if ($params) {
                $options['query'] = $params;
            }
            
            if ($json && ($method === 'POST' || $method === 'PATCH' || $method === 'PUT')) {
                $options['json'] = $json;
            }

            $response = $this->httpClient->request($method, $path, $options);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            
            $errorData = [];
            try {
                $errorData = json_decode($errorBody, true) ?: [];
            } catch (\Exception $ex) {
                // Ignore parse errors
            }
            
            $errorMsg = $errorData['detail'] ?? $errorData['message'] ?? $e->getMessage();
            
            if ($statusCode === 413) {
                throw new StorageLimitExceededException($errorMsg, $statusCode, $errorData);
            }
            
            throw new StorageException($errorMsg, $statusCode, $errorData);
        } catch (\Exception $e) {
            throw new StorageException("Request failed: " . $e->getMessage());
        }
    }
}

