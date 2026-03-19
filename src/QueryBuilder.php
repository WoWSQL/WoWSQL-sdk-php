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

    /**
     * Select specific columns or expressions.
     *
     * @param  string ...$columns Column name(s) or expressions
     * @return $this
     */
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
     * Add a filter condition.
     *
     * @param  string $column    Column name
     * @param  string $operator  Operator (eq, neq, gt, gte, lt, lte, like, in, not_in, between, not_between, is, is_not)
     * @param  mixed  $value     Filter value
     * @param  string $logicalOp Logical operator ("AND" or "OR")
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

    /**
     * Add an OR filter condition.
     *
     * @param  string $column
     * @param  string $op
     * @param  mixed  $value
     * @return $this
     */
    public function orWhere($column, $op, $value)
    {
        $this->addFilter($column, $op, $value, 'OR');
        return $this;
    }

    /**
     * Order results by column.
     *
     * @param  string $column Column to order by
     * @param  bool   $desc   Descending order (default: false → ascending)
     * @return $this
     */
    public function orderBy($column, $desc = false)
    {
        $this->params['order'] = $column;
        $this->params['order_direction'] = $desc ? 'desc' : 'asc';
        return $this;
    }

    /**
     * Order results by column (string direction variant).
     *
     * @param  string $column    Column to order by
     * @param  string $direction Sort direction ('asc' or 'desc')
     * @return $this
     */
    public function order($column, $direction = 'asc')
    {
        return $this->orderBy($column, strtolower($direction) === 'desc');
    }

    /**
     * Group results by column(s).
     *
     * @param  string ...$columns Column name(s) to group by
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
     * @param  string $column   Column name or aggregate function
     * @param  string $operator Operator (eq, neq, gt, gte, lt, lte)
     * @param  mixed  $value    Filter value
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
            'value' => $value,
        ];
        return $this;
    }

    /**
     * Limit number of results.
     *
     * @param  int $limit
     * @return $this
     */
    public function limit($limit)
    {
        $this->params['limit'] = (string)$limit;
        return $this;
    }

    /**
     * Skip records (pagination offset).
     *
     * @param  int $offset
     * @return $this
     */
    public function offset($offset)
    {
        $this->params['offset'] = (string)$offset;
        return $this;
    }

    /**
     * Execute the query.
     *
     * @return array Query response with data and metadata
     */
    public function get()
    {
        $hasAdvancedFeatures =
            (isset($this->params['group_by']) && !empty($this->params['group_by'])) ||
            (isset($this->params['having']) && !empty($this->params['having'])) ||
            $this->hasAdvancedFilters();

        if ($hasAdvancedFeatures) {
            $body = $this->buildQueryBody();
            return $this->client->request('POST', "/{$this->tableName}/query", null, $body);
        } else {
            return $this->client->request('GET', "/{$this->tableName}", $this->params, null);
        }
    }

    /**
     * Execute the query (alias for get).
     *
     * @return array
     */
    public function execute()
    {
        return $this->get();
    }

    /**
     * Get first record matching query.
     *
     * @return array|null First record or null
     */
    public function first()
    {
        $result = $this->limit(1)->get();
        return !empty($result['data']) ? $result['data'][0] : null;
    }

    /**
     * Get exactly one record. Throws if zero or more than one found.
     *
     * @return array The single matching record
     * @throws WOWSQLException If zero or more than one record found
     */
    public function single()
    {
        $result = $this->limit(2)->get();
        if (empty($result['data'])) {
            throw new WOWSQLException('No records found');
        }
        if (count($result['data']) > 1) {
            throw new WOWSQLException('Multiple records found, expected exactly one');
        }
        return $result['data'][0];
    }

    /**
     * Get the total count of records matching the current filters.
     *
     * @return int
     */
    public function count()
    {
        $savedSelect = $this->params['select'] ?? null;
        $savedGroupBy = null;
        $savedHaving = null;
        $savedOrder = null;
        $savedOrderDir = null;

        if (isset($this->params['group_by'])) {
            $savedGroupBy = $this->params['group_by'];
            unset($this->params['group_by']);
        }
        if (isset($this->params['having'])) {
            $savedHaving = $this->params['having'];
            unset($this->params['having']);
        }
        if (isset($this->params['order'])) {
            $savedOrder = $this->params['order'];
            unset($this->params['order']);
        }
        if (isset($this->params['order_direction'])) {
            $savedOrderDir = $this->params['order_direction'];
            unset($this->params['order_direction']);
        }

        $this->params['select'] = 'COUNT(*) as count';

        try {
            $result = $this->get();
        } finally {
            if ($savedSelect !== null) {
                $this->params['select'] = $savedSelect;
            } else {
                unset($this->params['select']);
            }
            if ($savedGroupBy !== null) {
                $this->params['group_by'] = $savedGroupBy;
            }
            if ($savedHaving !== null) {
                $this->params['having'] = $savedHaving;
            }
            if ($savedOrder !== null) {
                $this->params['order'] = $savedOrder;
            }
            if ($savedOrderDir !== null) {
                $this->params['order_direction'] = $savedOrderDir;
            }
        }

        if (!empty($result['data'])) {
            return (int)($result['data'][0]['count'] ?? 0);
        }
        return 0;
    }

    /**
     * Paginate results with page-based interface.
     *
     * @param  int $page    Page number (1-indexed)
     * @param  int $perPage Records per page
     * @return array{data: array, page: int, per_page: int, total: int, total_pages: int}
     */
    public function paginate($page = 1, $perPage = 20)
    {
        $offsetVal = (max($page, 1) - 1) * $perPage;
        $result = $this->limit($perPage)->offset($offsetVal)->get();
        $total = $result['total'] ?? $result['count'] ?? 0;
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;

        return [
            'data' => $result['data'],
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ];
    }

    // ── Internal helpers ─────────────────────────────────────────

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

    private function addFilter($column, $op, $value, $logicalOp = 'AND')
    {
        $this->filters[] = [
            'column' => $column,
            'operator' => $op,
            'value' => $value,
            'logical_op' => $logicalOp,
        ];

        $filterValue = "{$column}.{$op}."
            . ($value !== null
                ? (is_array($value) ? json_encode($value) : (string)$value)
                : 'null');

        if (isset($this->params['filter'])) {
            $this->params['filter'] .= ',' . $filterValue;
        } else {
            $this->params['filter'] = $filterValue;
        }
    }
}
