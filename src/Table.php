<?php

namespace WOWSQL;

/**
 * Table interface for PostgREST-native database operations.
 */
class Table
{
    private $client;
    private $tableName;

    public function __construct(WOWSQLClient $client, $tableName)
    {
        $this->client    = $client;
        $this->tableName = $tableName;
    }

    // ── Query chain entry points ─────────────────────────────────

    public function select(...$columns)
    {
        return (new QueryBuilder($this->client, $this->tableName))->select(...$columns);
    }

    public function filter($column, $operator, $value, $logicalOp = 'AND')
    {
        return (new QueryBuilder($this->client, $this->tableName))->filter($column, $operator, $value, $logicalOp);
    }

    public function get()
    {
        return (new QueryBuilder($this->client, $this->tableName))->get();
    }

    public function eq($column, $value)
    {
        return (new QueryBuilder($this->client, $this->tableName))->eq($column, $value);
    }

    public function neq($column, $value)
    {
        return (new QueryBuilder($this->client, $this->tableName))->neq($column, $value);
    }

    public function gt($column, $value)
    {
        return (new QueryBuilder($this->client, $this->tableName))->gt($column, $value);
    }

    public function gte($column, $value)
    {
        return (new QueryBuilder($this->client, $this->tableName))->gte($column, $value);
    }

    public function lt($column, $value)
    {
        return (new QueryBuilder($this->client, $this->tableName))->lt($column, $value);
    }

    public function lte($column, $value)
    {
        return (new QueryBuilder($this->client, $this->tableName))->lte($column, $value);
    }

    public function orderBy($column, $direction = 'asc')
    {
        return (new QueryBuilder($this->client, $this->tableName))->order($column, $direction);
    }

    public function count()
    {
        return (new QueryBuilder($this->client, $this->tableName))->count();
    }

    public function paginate($page = 1, $perPage = 20)
    {
        return (new QueryBuilder($this->client, $this->tableName))->paginate($page, $perPage);
    }

    // ── Single-record CRUD ───────────────────────────────────────

    /**
     * Fetch a single record by primary-key value.
     */
    public function getById($recordId)
    {
        return $this->client->request('GET', "/{$this->tableName}", ['id' => "eq.{$recordId}"],
            null, ['Accept' => 'application/vnd.pgrst.object+json']);
    }

    /**
     * Insert a new record and return the created row.
     */
    public function create($data)
    {
        $result = $this->client->request('POST', "/{$this->tableName}", null, $data,
            ['Prefer' => 'return=representation']);
        $row = is_array($result) && isset($result[0]) ? $result[0] : $result;
        return ['id' => $row['id'] ?? null, 'message' => 'Record created successfully', 'data' => $row];
    }

    /** Alias for create. */
    public function insert($data)
    {
        return $this->create($data);
    }

    /**
     * Insert multiple records in a single request.
     */
    public function bulkInsert(array $records)
    {
        if (empty($records)) return [];
        $result = $this->client->request('POST', "/{$this->tableName}", null, $records,
            ['Prefer' => 'return=representation']);
        $rows = is_array($result) && isset($result[0]) ? $result : [$result];
        return array_map(function ($row) {
            return ['id' => $row['id'] ?? null, 'message' => 'Record created successfully'];
        }, $rows);
    }

    /**
     * Insert or update using PostgREST merge-duplicates.
     */
    public function upsert(array $data, $onConflict = 'id')
    {
        $result = $this->client->request('POST', "/{$this->tableName}", null, $data, [
            'Prefer'      => 'return=representation,resolution=merge-duplicates',
            'on-conflict' => $onConflict,
        ]);
        $row = is_array($result) && isset($result[0]) ? $result[0] : $result;
        return ['id' => $row['id'] ?? null, 'message' => 'Record upserted successfully', 'data' => $row];
    }

    /**
     * Update a record by primary-key value.
     */
    public function update($recordId, $data)
    {
        $result = $this->client->request('PATCH', "/{$this->tableName}", ['id' => "eq.{$recordId}"],
            $data, ['Prefer' => 'return=representation']);
        $rows = is_array($result) && isset($result[0]) ? $result : (is_array($result) ? $result : []);
        return ['message' => 'Record updated successfully', 'affected_rows' => count($rows)];
    }

    /**
     * Delete a record by primary-key value.
     */
    public function delete($recordId)
    {
        $result = $this->client->request('DELETE', "/{$this->tableName}", ['id' => "eq.{$recordId}"],
            null, ['Prefer' => 'return=representation']);
        $rows = is_array($result) && isset($result[0]) ? $result : (is_array($result) ? $result : []);
        return ['message' => 'Record deleted successfully', 'affected_rows' => count($rows)];
    }
}
