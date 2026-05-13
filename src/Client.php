<?php

namespace WOWSQL;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * WowSQL client for PostgREST-native database operations.
 *
 * All requests are sent directly to the PostgREST endpoint (/rest/v1).
 * Use the anonymous key for client-side operations and the service role key
 * for server-side operations.
 */
class WOWSQLClient
{
    private $baseUrl;
    private $apiUrl;
    private $apiKey;
    private $httpClient;
    private $timeout;
    private $verifySsl;

    /** @var string|null Last Content-Range header from the most recent list request. */
    public $lastContentRange = null;

    /**
     * @param string $projectUrl  Project slug, domain, or full URL
     * @param string $apiKey      Anonymous (wowsql_anon_...) or service role key (wowsql_service_...)
     * @param string $baseDomain  Base domain appended when projectUrl is a slug (default: wowsqlconnect.com)
     * @param bool   $secure      Use HTTPS (default: true)
     * @param int    $timeout     Request timeout in seconds (default: 30)
     * @param bool   $verifySsl   Verify SSL certificates (default: true)
     */
    public function __construct(
        $projectUrl,
        $apiKey,
        $baseDomain = 'wowsqlconnect.com',
        $secure = true,
        $timeout = 30,
        $verifySsl = true
    ) {
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
        $this->verifySsl = $verifySsl;

        if (strpos($projectUrl, 'http://') === 0 || strpos($projectUrl, 'https://') === 0) {
            $base = rtrim($projectUrl, '/');
            if (strpos($base, '/api') !== false) {
                $base = explode('/api', $base, 2)[0];
            }
            $this->baseUrl = $base;
        } else {
            $protocol = $secure ? 'https' : 'http';
            if (strpos($projectUrl, ".{$baseDomain}") !== false
                || substr($projectUrl, -strlen($baseDomain)) === $baseDomain) {
                $this->baseUrl = "{$protocol}://{$projectUrl}";
            } else {
                $this->baseUrl = "{$protocol}://{$projectUrl}.{$baseDomain}";
            }
        }

        $this->apiUrl = $this->baseUrl . '/rest/v1';

        $this->httpClient = new HttpClient([
            'base_uri' => $this->apiUrl,
            'timeout'  => $timeout,
            'verify'   => $verifySsl,
            'headers'  => [
                'apikey'       => $apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Return a Table interface for the given table name.
     *
     * @param  string $tableName
     * @return Table
     */
    public function table($tableName)
    {
        return new Table($this, $tableName);
    }

    /** No-op — provided for API parity. */
    public function close() {}

    /**
     * Make an HTTP request and return the decoded response body.
     *
     * @param  string     $method
     * @param  string     $path
     * @param  array|null $params       Query parameters
     * @param  mixed      $json         Request body
     * @param  array|null $extraHeaders Additional headers (e.g. Prefer, on-conflict)
     * @return array|null
     * @throws WOWSQLException
     */
    public function request($method, $path, $params = null, $json = null, $extraHeaders = null)
    {
        try {
            $options = [];
            if ($params) {
                $options['query'] = $params;
            }
            if ($json !== null && in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'])) {
                $options['json'] = $json;
            }
            if ($extraHeaders) {
                $options['headers'] = $extraHeaders;
            }

            $response = $this->httpClient->request($method, $path, $options);
            $this->lastContentRange = $response->getHeaderLine('Content-Range') ?: null;

            $body = $response->getBody()->getContents();
            return $body ? json_decode($body, true) : null;

        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            $errorData = [];
            try { $errorData = json_decode($errorBody, true) ?: []; } catch (\Exception $ex) {}
            $errorMsg = $errorData['message'] ?? $errorData['detail'] ?? $e->getMessage();
            throw new WOWSQLException($errorMsg, $statusCode, $errorData);
        } catch (\Exception $e) {
            throw new WOWSQLException("Request failed: " . $e->getMessage());
        }
    }

    /**
     * Parse total count from Content-Range header.
     *
     * @param  string|null $header   e.g. "0-19/100"
     * @param  int         $fallback
     * @return int
     */
    public static function parseTotalFromContentRange($header, $fallback)
    {
        if (!$header || strpos($header, '/') === false) return $fallback;
        $parts = explode('/', $header);
        $val = intval(end($parts));
        return $val > 0 ? $val : $fallback;
    }
}
