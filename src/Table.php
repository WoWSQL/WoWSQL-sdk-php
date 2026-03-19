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
     * @param  string ...$columns Column(s) to select
     * @return QueryBuilder
     */
    public function select(...$columns)
    {
        return (new QueryBuilder($this->client, $this->tableName))->select(...$columns);
    }

    /**
     * Start a query with a filter.
     *
     * @param  string $column    Column name
     * @param  string $operator  Operator
     * @param  mixed  $value     Filter value
     * @param  string $logicalOp Logical operator ("AND" or "OR")
     * @return QueryBuilder
     */
    public function filter($column, $operator, $value, $logicalOp = 'AND')
    {
        return (new QueryBuilder($this->client, $this->tableName))->filter($column, $operator, $value, $logicalOp);
    }

    /**
     * Get all records with optional filters.
     *
     * @return array Query response
     * @throws WOWSQLException
     */
    public function get()
    {
        return (new QueryBuilder($this->client, $this->tableName))->get();
    }

    /**
     * Get a single record by ID.
     *
     * @param  mixed $recordId Record ID
     * @return array Record data
     * @throws WOWSQLException
     */
    public function getById($recordId)
    {
        return $this->client->request('GET', "/{$this->tableName}/{$recordId}", null, null);
    }

    /**
     * Create a new record.
     *
     * @param  array $data Record data
     * @return array Create response with new record ID
     * @throws WOWSQLException
     */
    public function create($data)
    {
        return $this->client->request('POST', "/{$this->tableName}", null, $data);
    }

    /**
     * Insert a new record (alias for create).
     *
     * @param  array $data Record data
     * @return array
     * @throws WOWSQLException
     */
    public function insert($data)
    {
        return $this->create($data);
    }

    /**
     * Insert multiple records.
     *
     * Attempts a single batch POST first. Falls back to individual
     * inserts if the server does not support batch creation.
     *
     * @param  array $records List of record arrays
     * @return array List of create responses
     * @throws WOWSQLException
     */
    public function bulkInsert(array $records)
    {
        if (empty($records)) {
            return [];
        }
        try {
            $result = $this->client->request('POST', "/{$this->tableName}", null, $records);
            return is_array($result) && isset($result[0]) ? $result : [$result];
        } catch (\Exception $e) {
            $results = [];
            foreach ($records as $record) {
                $results[] = $this->create($record);
            }
            return $results;
        }
    }

    /**
     * Insert or update based on conflict column (upsert).
     *
     * Uses get-then-insert/update pattern to emulate PostgreSQL ON CONFLICT.
     *
     * @param  array  $data       Record data (must include the conflict column)
     * @param  string $onConflict Column to check for conflicts (default: "id")
     * @return array  Create or update response
     * @throws WOWSQLException
     */
    public function upsert(array $data, $onConflict = 'id')
    {
        $conflictValue = $data[$onConflict] ?? null;
        if ($conflictValue === null) {
            return $this->create($data);
        }

        $existing = (new QueryBuilder($this->client, $this->tableName))
            ->eq($onConflict, $conflictValue)
            ->first();

        if ($existing) {
            $updateData = array_diff_key($data, [$onConflict => true]);
            if (!empty($updateData)) {
                return $this->update($conflictValue, $updateData);
            }
            return ['message' => 'No changes', 'affected_rows' => 0];
        }

        return $this->create($data);
    }

    /**
     * Update a record by ID.
     *
     * @param  mixed $recordId Record ID
     * @param  array $data     Data to update
     * @return array Update response
     * @throws WOWSQLException
     */
    public function update($recordId, $data)
    {
        return $this->client->request('PATCH', "/{$this->tableName}/{$recordId}", null, $data);
    }

    /**
     * Delete a record by ID.
     *
     * @param  mixed $recordId Record ID
     * @return array Delete response
     * @throws WOWSQLException
     */
    public function delete($recordId)
    {
        return $this->client->request('DELETE', "/{$this->tableName}/{$recordId}", null, null);
    }

    // ── Convenience shortcuts ────────────────────────────────────

    /** Start a query filtering where column equals value. */
    public function eq($column, $value)
    {
        return (new QueryBuilder($this->client, $this->tableName))->eq($column, $value);
    }

    /** Start a query filtering where column does not equal value. */
    public function neq($column, $value)
    {
        return (new QueryBuilder($this->client, $this->tableName))->neq($column, $value);
    }

    /** Start a query filtering where column is greater than value. */
    public function gt($column, $value)
    {
        return (new QueryBuilder($this->client, $this->tableName))->gt($column, $value);
    }

    /** Start a query filtering where column >= value. */
    public function gte($column, $value)
    {
        return (new QueryBuilder($this->client, $this->tableName))->gte($column, $value);
    }

    /** Start a query filtering where column < value. */
    public function lt($column, $value)
    {
        return (new QueryBuilder($this->client, $this->tableName))->lt($column, $value);
    }

    /** Start a query filtering where column <= value. */
    public function lte($column, $value)
    {
        return (new QueryBuilder($this->client, $this->tableName))->lte($column, $value);
    }

    /** Start a query with ordering. */
    public function orderBy($column, $direction = 'asc')
    {
        return (new QueryBuilder($this->client, $this->tableName))->order($column, $direction);
    }

    /** Get total record count for this table. */
    public function count()
    {
        return (new QueryBuilder($this->client, $this->tableName))->count();
    }

    /** Paginate all records in this table. */
    public function paginate($page = 1, $perPage = 20)
    {
        return (new QueryBuilder($this->client, $this->tableName))->paginate($page, $perPage);
    }
}
