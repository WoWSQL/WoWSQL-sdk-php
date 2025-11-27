<?php

namespace WOWSQL;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;

/**
 * WOWSQL client for interacting with your database via REST API.
 * 
 * This client is used for DATABASE OPERATIONS (CRUD on tables).
 * Use Service Role Key or Anonymous Key for authentication.
 */
class WOWSQLClient
{
    private $apiUrl;
    private $apiKey;
    private $httpClient;
    private $timeout;

    /**
     * Initialize WOWSQL client for DATABASE OPERATIONS.
     * 
     * @param string $projectUrl Project subdomain or full URL
     * @param string $apiKey API key for database operations authentication
     * @param int $timeout Request timeout in seconds (default: 30)
     */
    public function __construct($projectUrl, $apiKey, $timeout = 30)
    {
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;

        // Build API URL
        if (strpos($projectUrl, 'http://') === 0 || strpos($projectUrl, 'https://') === 0) {
            $baseUrl = rtrim($projectUrl, '/');
            if (strpos($baseUrl, '/api') !== false) {
                $this->apiUrl = str_replace('/api', '', $baseUrl) . '/api/v2';
            } else {
                $this->apiUrl = $baseUrl . '/api/v2';
            }
        } else {
            $this->apiUrl = "https://{$projectUrl}.wowsql.com/api/v2";
        }

        $this->httpClient = new HttpClient([
            'base_uri' => $this->apiUrl,
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Get a table interface for fluent API.
     * 
     * @param string $tableName Name of the table
     * @return Table Table instance for the specified table
     */
    public function table($tableName)
    {
        return new Table($this, $tableName);
    }

    /**
     * List all tables in the database.
     * 
     * @return array List of table names
     * @throws WOWSQLException If the request fails
     */
    public function listTables()
    {
        $response = $this->request('GET', '/tables', null, null);
        return $response['tables'] ?? [];
    }

    /**
     * Get table schema information.
     * 
     * @param string $tableName Name of the table
     * @return array Table schema with columns and primary key
     * @throws WOWSQLException If the request fails
     */
    public function getTableSchema($tableName)
    {
        return $this->request('GET', "/tables/{$tableName}/schema", null, null);
    }

    /**
     * Make HTTP request to API.
     * 
     * @param string $method HTTP method
     * @param string $path API path
     * @param array|null $params Query parameters
     * @param array|null $json Request body
     * @return array Response data
     * @throws WOWSQLException If the request fails
     */
    public function request($method, $path, $params = null, $json = null)
    {
        try {
            $options = [];
            
            if ($params) {
                $options['query'] = $params;
            }
            
            if ($json && ($method === 'POST' || $method === 'PATCH')) {
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
            
            throw new WOWSQLException($errorMsg, $statusCode, $errorData);
        } catch (\Exception $e) {
            throw new WOWSQLException("Request failed: " . $e->getMessage());
        }
    }
}

