<?php

namespace WOWSQL;

/**
 * Fluent query builder for constructing database queries.
 */
class QueryBuilder
{
    private $client;
    private $tableName;
    private $params;

    public function __construct(WOWSQLClient $client, $tableName)
    {
        $this->client = $client;
        $this->tableName = $tableName;
        $this->params = [];
    }

    public function select(...$columns)
    {
        if (count($columns) === 1 && $columns[0] === '*') {
            $this->params['select'] = '*';
        } else {
            $this->params['select'] = implode(',', $columns);
        }
        return $this;
    }

    public function eq($column, $value)
    {
        $this->addFilter($column, 'eq', $value);
        return $this;
    }

    public function neq($column, $value)
    {
        $this->addFilter($column, 'neq', $value);
        return $this;
    }

    public function gt($column, $value)
    {
        $this->addFilter($column, 'gt', $value);
        return $this;
    }

    public function gte($column, $value)
    {
        $this->addFilter($column, 'gte', $value);
        return $this;
    }

    public function lt($column, $value)
    {
        $this->addFilter($column, 'lt', $value);
        return $this;
    }

    public function lte($column, $value)
    {
        $this->addFilter($column, 'lte', $value);
        return $this;
    }

    public function like($column, $pattern)
    {
        $this->addFilter($column, 'like', $pattern);
        return $this;
    }

    public function isNull($column)
    {
        $this->addFilter($column, 'is', null);
        return $this;
    }

    public function orderBy($column, $desc = false)
    {
        $this->params['order'] = $column;
        $this->params['order_direction'] = $desc ? 'desc' : 'asc';
        return $this;
    }

    public function limit($limit)
    {
        $this->params['limit'] = (string)$limit;
        return $this;
    }

    public function offset($offset)
    {
        $this->params['offset'] = (string)$offset;
        return $this;
    }

    public function get()
    {
        return $this->client->request('GET', "/{$this->tableName}", $this->params, null);
    }

    public function first()
    {
        $result = $this->limit(1)->get();
        return !empty($result['data']) ? $result['data'][0] : null;
    }

    private function addFilter($column, $op, $value)
    {
        $filterValue = "{$column}.{$op}." . ($value !== null ? $value : 'null');
        
        if (isset($this->params['filter'])) {
            $this->params['filter'] .= ',' . $filterValue;
        } else {
            $this->params['filter'] = $filterValue;
        }
    }
}

