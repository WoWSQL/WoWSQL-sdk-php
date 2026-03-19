<?php

namespace WOWSQL;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;

/**
 * Schema management client for PostgreSQL.
 *
 * Requires a SERVICE ROLE key (wowsql_service_...), not an anonymous key.
 */
class WOWSQLSchema
{
    private $baseUrl;
    private $httpClient;
    private $timeout;

    /**
     * Initialize Schema management client.
     *
     * @param string $projectUrl Project subdomain or full URL
     * @param string $serviceKey Service role key (wowsql_service_...)
     * @param string $baseDomain Base domain (default: wowsql.com)
     * @param bool   $secure     Use HTTPS (default: true)
     * @param int    $timeout    Request timeout in seconds (default: 30)
     * @param bool   $verifySsl  Verify SSL certificates (default: true)
     */
    public function __construct(
        $projectUrl,
        $serviceKey,
        $baseDomain = 'wowsql.com',
        $secure = true,
        $timeout = 30,
        $verifySsl = true
    ) {
        $this->timeout = $timeout;

        if (strpos($projectUrl, 'http://') === 0 || strpos($projectUrl, 'https://') === 0) {
            $base = rtrim($projectUrl, '/');
            if (strpos($base, '/api') !== false) {
                $base = explode('/api', $base, 2)[0];
            }
            $this->baseUrl = $base;
        } else {
            $protocol = $secure ? 'https' : 'http';
            if (strpos($projectUrl, ".{$baseDomain}") !== false || substr($projectUrl, -strlen($baseDomain)) === $baseDomain) {
                $this->baseUrl = "{$protocol}://{$projectUrl}";
            } else {
                $this->baseUrl = "{$protocol}://{$projectUrl}.{$baseDomain}";
            }
        }

        $this->httpClient = new HttpClient([
            'base_uri' => $this->baseUrl,
            'timeout' => $timeout,
            'verify' => $verifySsl,
            'headers' => [
                'Authorization' => "Bearer {$serviceKey}",
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    // ── Table operations ─────────────────────────────────────────

    /**
     * Create a new table.
     *
     * Supported PostgreSQL types: SERIAL, BIGSERIAL, VARCHAR(n), TEXT, INT,
     * BIGINT, BOOLEAN, NUMERIC(p,s), REAL, DOUBLE PRECISION, TIMESTAMPTZ,
     * DATE, TIME, UUID, JSONB, TEXT[], INT[], BYTEA, etc.
     *
     * @param  string     $tableName  Name of the table
     * @param  array      $columns    Column definitions (name, type, auto_increment, unique, nullable, default)
     * @param  string|null $primaryKey Primary key column name
     * @param  array|null $indexes    Columns to create indexes on
     * @return array
     * @throws WOWSQLException|SchemaPermissionException
     */
    public function createTable($tableName, array $columns, $primaryKey = null, $indexes = null)
    {
        return $this->request('POST', '/api/v2/schema/tables', null, [
            'table_name' => $tableName,
            'columns' => $columns,
            'primary_key' => $primaryKey,
            'indexes' => $indexes,
        ]);
    }

    /**
     * Alter an existing table.
     *
     * Operations: add_column, drop_column, modify_column, rename_column
     *
     * @param  string      $tableName
     * @param  string      $operation
     * @param  string|null $columnName
     * @param  string|null $columnType
     * @param  string|null $newColumnName
     * @param  bool        $nullable
     * @param  string|null $default
     * @return array
     * @throws WOWSQLException|SchemaPermissionException
     */
    public function alterTable(
        $tableName,
        $operation,
        $columnName = null,
        $columnType = null,
        $newColumnName = null,
        $nullable = true,
        $default = null
    ) {
        return $this->request('PATCH', "/api/v2/schema/tables/{$tableName}", null, [
            'table_name' => $tableName,
            'operation' => $operation,
            'column_name' => $columnName,
            'column_type' => $columnType,
            'new_column_name' => $newColumnName,
            'nullable' => $nullable,
            'default' => $default,
        ]);
    }

    /**
     * Drop a table. WARNING: This cannot be undone!
     *
     * @param  string $tableName
     * @param  bool   $cascade Also drop dependent objects
     * @return array
     * @throws WOWSQLException|SchemaPermissionException
     */
    public function dropTable($tableName, $cascade = false)
    {
        return $this->request('DELETE', "/api/v2/schema/tables/{$tableName}", ['cascade' => $cascade]);
    }

    /**
     * Execute raw DDL SQL.
     *
     * Only schema statements are allowed (CREATE TABLE, ALTER TABLE,
     * DROP TABLE, CREATE INDEX, etc.)
     *
     * @param  string $sql
     * @return array
     * @throws WOWSQLException|SchemaPermissionException
     */
    public function executeSql($sql)
    {
        return $this->request('POST', '/api/v2/schema/execute', null, ['sql' => $sql]);
    }

    // ── Convenience methods ──────────────────────────────────────

    /**
     * Add a column to an existing table.
     *
     * @param  string      $tableName
     * @param  string      $columnName
     * @param  string      $columnType
     * @param  bool        $nullable
     * @param  string|null $default
     * @return array
     */
    public function addColumn($tableName, $columnName, $columnType, $nullable = true, $default = null)
    {
        return $this->alterTable($tableName, 'add_column', $columnName, $columnType, null, $nullable, $default);
    }

    /**
     * Drop a column from a table.
     *
     * @param  string $tableName
     * @param  string $columnName
     * @return array
     */
    public function dropColumn($tableName, $columnName)
    {
        return $this->alterTable($tableName, 'drop_column', $columnName);
    }

    /**
     * Rename a column.
     *
     * @param  string $tableName
     * @param  string $oldName
     * @param  string $newName
     * @return array
     */
    public function renameColumn($tableName, $oldName, $newName)
    {
        return $this->alterTable($tableName, 'rename_column', $oldName, null, $newName);
    }

    /**
     * Change column type, nullability, or default value.
     *
     * @param  string      $tableName
     * @param  string      $columnName
     * @param  string|null $columnType
     * @param  bool|null   $nullable
     * @param  string|null $default
     * @return array
     */
    public function modifyColumn($tableName, $columnName, $columnType = null, $nullable = null, $default = null)
    {
        $args = ['column_name' => $columnName];
        if ($columnType !== null) {
            $args['column_type'] = $columnType;
        }
        if ($nullable !== null) {
            $args['nullable'] = $nullable;
        }
        if ($default !== null) {
            $args['default'] = $default;
        }
        return $this->alterTable(
            $tableName,
            'modify_column',
            $args['column_name'],
            $args['column_type'] ?? null,
            null,
            $args['nullable'] ?? true,
            $args['default'] ?? null
        );
    }

    /**
     * Create an index.
     *
     * @param  string          $tableName
     * @param  string|string[] $columnNames Column(s) to index
     * @param  bool            $unique      Create a UNIQUE index
     * @param  string|null     $name        Custom index name
     * @param  string|null     $using       Index method (btree, hash, gin, gist)
     * @return array
     */
    public function createIndex($tableName, $columnNames, $unique = false, $name = null, $using = null)
    {
        $cols = is_array($columnNames) ? $columnNames : [$columnNames];
        $idxName = $name ?: 'idx_' . $tableName . '_' . implode('_', $cols);
        $uniqueKw = $unique ? 'UNIQUE ' : '';
        $usingKw = $using ? " USING {$using}" : '';
        $colList = implode(', ', array_map(function ($c) { return "\"{$c}\""; }, $cols));
        $sql = "CREATE {$uniqueKw}INDEX IF NOT EXISTS \"{$idxName}\" ON \"{$tableName}\"{$usingKw} ({$colList})";
        return $this->executeSql($sql);
    }

    /**
     * List all tables via the v2 REST API.
     *
     * @return array List of table names
     * @throws WOWSQLException
     */
    public function listTables()
    {
        $resp = $this->request('GET', '/api/v2/tables');
        return $resp['tables'] ?? [];
    }

    /**
     * Get column-level schema information for a table.
     *
     * @param  string $tableName
     * @return array
     * @throws WOWSQLException
     */
    public function getTableSchema($tableName)
    {
        return $this->request('GET', "/api/v2/tables/{$tableName}/schema");
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

            if ($response->getStatusCode() === 403) {
                throw new SchemaPermissionException(
                    'Schema operations require a SERVICE ROLE key. '
                    . 'You are using an anonymous key which cannot modify database schema.'
                );
            }

            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;

            if ($statusCode === 403) {
                throw new SchemaPermissionException(
                    'Schema operations require a SERVICE ROLE key. '
                    . 'You are using an anonymous key which cannot modify database schema.'
                );
            }

            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            $errorData = [];
            try {
                $errorData = json_decode($errorBody, true) ?: [];
            } catch (\Exception $ex) {
                // ignore
            }
            $errorMsg = $errorData['detail'] ?? $errorData['message'] ?? $e->getMessage();
            throw new WOWSQLException($errorMsg, $statusCode, $errorData);
        } catch (SchemaPermissionException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new WOWSQLException("Request failed: " . $e->getMessage());
        }
    }
}
