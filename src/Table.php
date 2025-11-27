<?php

namespace WOWSQL;

/**
 * Table interface for database operations.
 */
class Table
{
    private $client;
    private $tableName;

    public function __construct(WOWSQLClient $client, $tableName)
    {
        $this->client = $client;
        $this->tableName = $tableName;
    }

    /**
     * Start a query with column selection.
     * 
     * @param string ...$columns Column(s) to select
     * @return QueryBuilder QueryBuilder for chaining
     */
    public function select(...$columns)
    {
        return new QueryBuilder($this->client, $this->tableName)->select(...$columns);
    }

    /**
     * Get all records with optional filters.
     * 
     * @return array Query response
     * @throws WOWSQLException If the request fails
     */
    public function get()
    {
        return (new QueryBuilder($this->client, $this->tableName))->get();
    }

    /**
     * Get a single record by ID.
     * 
     * @param mixed $recordId Record ID
     * @return array Record data
     * @throws WOWSQLException If the request fails
     */
    public function getById($recordId)
    {
        return $this->client->request('GET', "/{$this->tableName}/{$recordId}", null, null);
    }

    /**
     * Create a new record.
     * 
     * @param array $data Record data
     * @return array Create response with new record ID
     * @throws WOWSQLException If the request fails
     */
    public function create($data)
    {
        return $this->client->request('POST', "/{$this->tableName}", null, $data);
    }

    /**
     * Update a record by ID.
     * 
     * @param mixed $recordId Record ID
     * @param array $data Data to update
     * @return array Update response
     * @throws WOWSQLException If the request fails
     */
    public function update($recordId, $data)
    {
        return $this->client->request('PATCH', "/{$this->tableName}/{$recordId}", null, $data);
    }

    /**
     * Delete a record by ID.
     * 
     * @param mixed $recordId Record ID
     * @return array Delete response
     * @throws WOWSQLException If the request fails
     */
    public function delete($recordId)
    {
        return $this->client->request('DELETE', "/{$this->tableName}/{$recordId}", null, null);
    }
}

