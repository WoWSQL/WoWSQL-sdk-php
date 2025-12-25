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
    private $filters;

    public function __construct(WOWSQLClient $client, $tableName)
    {
        $this->client = $client;
        $this->tableName = $tableName;
        $this->params = [];
        $this->filters = [];
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

    /**
     * Add a filter condition (generic method).
     * 
     * @param string $column Column name
     * @param string $operator Operator (eq, neq, gt, gte, lt, lte, like, in, not_in, between, not_between, is, is_not)
     * @param mixed $value Filter value
     * @param string $logicalOp Logical operator for combining with previous filters ("AND" or "OR")
     * @return $this
     */
    public function filter($column, $operator, $value, $logicalOp = 'AND')
    {
        $this->addFilter($column, $operator, $value, $logicalOp);
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

    public function isNotNull($column)
    {
        $this->addFilter($column, 'is_not', null);
        return $this;
    }

    public function in($column, $values)
    {
        $this->addFilter($column, 'in', $values);
        return $this;
    }

    public function notIn($column, $values)
    {
        $this->addFilter($column, 'not_in', $values);
        return $this;
    }

    public function between($column, $min, $max)
    {
        $this->addFilter($column, 'between', [$min, $max]);
        return $this;
    }

    public function notBetween($column, $min, $max)
    {
        $this->addFilter($column, 'not_between', [$min, $max]);
        return $this;
    }

    public function or($column, $op, $value)
    {
        $this->addFilter($column, $op, $value, 'OR');
        return $this;
    }

    public function orderBy($column, $desc = false)
    {
        $this->params['order'] = $column;
        $this->params['order_direction'] = $desc ? 'desc' : 'asc';
        return $this;
    }

    /**
     * Order results by column (alias for orderBy, backward compatibility).
     * 
     * @param string $column Column to order by
     * @param string $direction Sort direction ('asc' or 'desc')
     * @return $this
     */
    public function order($column, $direction = 'asc')
    {
        return $this->orderBy($column, strtolower($direction) === 'desc');
    }

    /**
     * Group results by column(s).
     * 
     * @param string ...$columns Column name(s) to group by
     * @return $this
     */
    public function groupBy(...$columns)
    {
        $this->params['group_by'] = $columns;
        return $this;
    }

    /**
     * Add HAVING clause filter (for filtering aggregated results).
     * 
     * @param string $column Column name or aggregate function (e.g., "COUNT(*)")
     * @param string $operator Operator (eq, neq, gt, gte, lt, lte)
     * @param mixed $value Filter value
     * @return $this
     */
    public function having($column, $operator, $value)
    {
        if (!isset($this->params['having'])) {
            $this->params['having'] = [];
        }
        $this->params['having'][] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];
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
        // Check if we need POST endpoint (advanced features)
        $hasAdvancedFeatures = 
            (isset($this->params['group_by']) && !empty($this->params['group_by'])) ||
            (isset($this->params['having']) && !empty($this->params['having'])) ||
            $this->hasAdvancedFilters();

        if ($hasAdvancedFeatures) {
            // Use POST endpoint for advanced queries
            $body = $this->buildQueryBody();
            return $this->client->request('POST', "/{$this->tableName}/query", null, $body);
        } else {
            // Use GET endpoint for simple queries (backward compatibility)
            return $this->client->request('GET', "/{$this->tableName}", $this->params, null);
        }
    }
    
    private function hasAdvancedFilters()
    {
        foreach ($this->filters as $filter) {
            $op = $filter['operator'];
            if (in_array($op, ['in', 'not_in', 'between', 'not_between'])) {
                return true;
            }
        }
        return false;
    }
    
    private function buildQueryBody()
    {
        $body = [];
        
        if (isset($this->params['select'])) {
            $body['select'] = is_array($this->params['select']) 
                ? $this->params['select'] 
                : explode(',', $this->params['select']);
        }
        
        if (!empty($this->filters)) {
            $body['filters'] = $this->filters;
        }
        
        if (isset($this->params['group_by'])) {
            $body['group_by'] = is_array($this->params['group_by']) 
                ? $this->params['group_by'] 
                : [$this->params['group_by']];
        }
        
        if (isset($this->params['having'])) {
            $body['having'] = $this->params['having'];
        }
        
        if (isset($this->params['order'])) {
            $body['order_by'] = $this->params['order'];
            $body['order_direction'] = $this->params['order_direction'] ?? 'asc';
        }
        
        if (isset($this->params['limit'])) {
            $body['limit'] = (int)$this->params['limit'];
        }
        
        if (isset($this->params['offset'])) {
            $body['offset'] = (int)$this->params['offset'];
        }
        
        return $body;
    }

    /**
     * Execute the query (alias for get).
     */
    public function execute()
    {
        return $this->get();
    }

    public function first()
    {
        $result = $this->limit(1)->get();
        return !empty($result['data']) ? $result['data'][0] : null;
    }

    private function addFilter($column, $op, $value, $logicalOp = 'AND')
    {
        // Store structured filter for POST endpoint
        $this->filters[] = [
            'column' => $column,
            'operator' => $op,
            'value' => $value,
            'logical_op' => $logicalOp
        ];
        
        // Also build string filter for GET endpoint (backward compatibility)
        $filterValue = "{$column}.{$op}." . ($value !== null ? (is_array($value) ? json_encode($value) : (string)$value) : 'null');
        
        if (isset($this->params['filter'])) {
            $this->params['filter'] .= ',' . $filterValue;
        } else {
            $this->params['filter'] = $filterValue;
        }
    }
}

