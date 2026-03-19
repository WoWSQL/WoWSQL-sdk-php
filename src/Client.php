<?php

namespace WOWSQL;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;

/**
 * WOWSQL client for interacting with your database via REST API.
 *
 * This client is used for DATABASE OPERATIONS (CRUD on tables).
 * Use Service Role Key or Anonymous Key for authentication.
 *
 * Key Types:
 *     - Service Role Key: Full access to all database operations (recommended for server-side)
 *     - Anonymous Key: Public access with limited permissions (for client-side/public access)
 */
class WOWSQLClient
{
    private $baseUrl;
    private $apiUrl;
    private $apiKey;
    private $httpClient;
    private $timeout;
    private $verifySsl;

    /**
     * Initialize WOWSQL client for DATABASE OPERATIONS.
     *
     * @param string $projectUrl  Project subdomain or full URL
     * @param string $apiKey      API key for database operations authentication
     * @param string $baseDomain  Base domain (default: wowsql.com)
     * @param bool   $secure      Use HTTPS (default: true)
     * @param int    $timeout     Request timeout in seconds (default: 30)
     * @param bool   $verifySsl   Verify SSL certificates (default: true)
     */
    public function __construct(
        $projectUrl,
        $apiKey,
        $baseDomain = 'wowsql.com',
        $secure = true,
        $timeout = 30,
        $verifySsl = true
    ) {
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
        $this->verifySsl = $verifySsl;

        if (strpos($projectUrl, 'http://') === 0 || strpos($projectUrl, 'https://') === 0) {
            $this->baseUrl = rtrim($projectUrl, '/');
            if (strpos($this->baseUrl, '/api') !== false) {
                $this->baseUrl = explode('/api', $this->baseUrl, 2)[0];
                $this->apiUrl = $this->baseUrl . '/api/v2';
            } else {
                $this->apiUrl = $this->baseUrl . '/api/v2';
            }
        } else {
            $protocol = $secure ? 'https' : 'http';
            if (strpos($projectUrl, ".{$baseDomain}") !== false || substr($projectUrl, -strlen($baseDomain)) === $baseDomain) {
                $this->baseUrl = "{$protocol}://{$projectUrl}";
            } else {
                $this->baseUrl = "{$protocol}://{$projectUrl}.{$baseDomain}";
            }
            $this->apiUrl = $this->baseUrl . '/api/v2';
        }

        $clientOptions = [
            'base_uri' => $this->apiUrl,
            'timeout' => $timeout,
            'verify' => $verifySsl,
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ],
        ];

        $this->httpClient = new HttpClient($clientOptions);
    }

    /**
     * Get a table interface for fluent API.
     *
     * @param  string $tableName Name of the table
     * @return Table
     */
    public function table($tableName)
    {
        return new Table($this, $tableName);
    }

    /**
     * List all tables in the database.
     *
     * @return array List of table names
     * @throws WOWSQLException
     */
    public function listTables()
    {
        $response = $this->request('GET', '/tables', null, null);
        return $response['tables'] ?? [];
    }

    /**
     * Get table schema information.
     *
     * @param  string $tableName Name of the table
     * @return array  Table schema with columns and primary key
     * @throws WOWSQLException
     */
    public function getTableSchema($tableName)
    {
        return $this->request('GET', "/tables/{$tableName}/schema", null, null);
    }

    /**
     * Close the HTTP client (no-op for Guzzle, provided for API parity).
     */
    public function close()
    {
        // Guzzle does not hold persistent connections that need explicit closing.
    }

    /**
     * Make HTTP request to API.
     *
     * @param  string     $method HTTP method
     * @param  string     $path   API path
     * @param  array|null $params Query parameters
     * @param  array|null $json   Request body
     * @return array
     * @throws WOWSQLException
     */
    public function request($method, $path, $params = null, $json = null)
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
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';

            $errorData = [];
            try {
                $errorData = json_decode($errorBody, true) ?: [];
            } catch (\Exception $ex) {
                // ignore
            }

            $errorMsg = $errorData['detail'] ?? $errorData['message'] ?? $e->getMessage();

            throw new WOWSQLException($errorMsg, $statusCode, $errorData);
        } catch (\Exception $e) {
            throw new WOWSQLException("Request failed: " . $e->getMessage());
        }
    }
}
