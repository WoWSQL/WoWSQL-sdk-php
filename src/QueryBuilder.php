<?php

namespace WOWSQL;

/**
 * Fluent query builder — translates all operations to PostgREST query parameters.
 */
class QueryBuilder
{
    private $client;
    private $tableName;
    private $queryParams = [];   // PostgREST query parameters (col=op.val)
    private $filters     = [];   // Raw filter list for translation
    private $selectCols  = null;
    private $groupByCols = [];
    private $havingList  = [];
    private $orderItems  = [];
    private $limitVal    = null;
    private $offsetVal   = null;

    public function __construct(WOWSQLClient $client, $tableName)
    {
        $this->client    = $client;
        $this->tableName = $tableName;
    }

    public function select(...$columns)
    {
        if (count($columns) === 1 && is_array($columns[0])) {
            $this->selectCols = $columns[0];
        } else {
            $this->selectCols = $columns;
        }
        return $this;
    }

    public function filter($column, $operator, $value, $logicalOp = 'AND')
    {
        $this->filters[] = [
            'column'     => $column,
            'operator'   => $operator,
            'value'      => $value,
            'logical_op' => $logicalOp,
        ];
        return $this;
    }

    public function eq($column, $value)      { return $this->filter($column, 'eq',       $value); }
    public function neq($column, $value)     { return $this->filter($column, 'neq',      $value); }
    public function gt($column, $value)      { return $this->filter($column, 'gt',       $value); }
    public function gte($column, $value)     { return $this->filter($column, 'gte',      $value); }
    public function lt($column, $value)      { return $this->filter($column, 'lt',       $value); }
    public function lte($column, $value)     { return $this->filter($column, 'lte',      $value); }
    public function like($column, $pattern)  { return $this->filter($column, 'like',     $pattern); }
    public function ilike($column, $pattern) { return $this->filter($column, 'ilike',    $pattern); }
    public function isNull($column)          { return $this->filter($column, 'is',       null); }
    public function isNotNull($column)       { return $this->filter($column, 'is_not',   null); }
    public function in($column, $values)     { return $this->filter($column, 'in',       $values); }
    public function notIn($column, $values)  { return $this->filter($column, 'not_in',   $values); }

    public function between($column, $min, $max)
    {
        return $this->filter($column, 'between', [$min, $max]);
    }

    public function notBetween($column, $min, $max)
    {
        return $this->filter($column, 'not_between', [$min, $max]);
    }

    public function orWhere($column, $op, $value)
    {
        return $this->filter($column, $op, $value, 'OR');
    }

    public function orderBy($column, $desc = false)
    {
        $this->orderItems[] = ['column' => $column, 'direction' => $desc ? 'desc' : 'asc'];
        return $this;
    }

    public function order($column, $direction = 'asc')
    {
        $this->orderItems[] = ['column' => $column, 'direction' => strtolower($direction)];
        return $this;
    }

    public function groupBy(...$columns)
    {
        $this->groupByCols = is_array($columns[0] ?? null) ? $columns[0] : $columns;
        return $this;
    }

    public function having($column, $operator, $value)
    {
        $this->havingList[] = ['column' => $column, 'operator' => $operator, 'value' => $value];
        return $this;
    }

    public function limit($limit)
    {
        $this->limitVal = (int)$limit;
        return $this;
    }

    public function offset($offset)
    {
        $this->offsetVal = (int)$offset;
        return $this;
    }

    /**
     * Execute the query using PostgREST native query parameters.
     *
     * @return array{data: array, count: int, total: int, limit: int, offset: int}
     * @throws WOWSQLException
     */
    public function get()
    {
        $params = [];

        // SELECT
        $sel = $this->selectCols;
        if (!empty($this->groupByCols)) {
            $sel = $sel ? array_unique(array_merge($sel, $this->groupByCols)) : $this->groupByCols;
        }
        if ($sel) {
            $params['select'] = implode(',', $sel);
        }

        // FILTERS → PostgREST native
        foreach ($this->filters as $f) {
            $col = $f['column'];
            $op  = $f['operator'];
            $val = $f['value'];

            switch ($op) {
                case 'eq':          $params[$col] = "eq.{$val}"; break;
                case 'neq':         $params[$col] = "neq.{$val}"; break;
                case 'gt':          $params[$col] = "gt.{$val}"; break;
                case 'gte':         $params[$col] = "gte.{$val}"; break;
                case 'lt':          $params[$col] = "lt.{$val}"; break;
                case 'lte':         $params[$col] = "lte.{$val}"; break;
                case 'like':        $params[$col] = 'like.' . str_replace('%', '*', (string)$val); break;
                case 'ilike':       $params[$col] = 'ilike.' . str_replace('%', '*', (string)$val); break;
                case 'is':          $params[$col] = $val === null ? 'is.null' : "is.{$val}"; break;
                case 'is_not':      $params[$col] = $val === null ? 'not.is.null' : "not.is.{$val}"; break;
                case 'in':
                    $list = is_array($val) ? implode(',', $val) : $val;
                    $params[$col] = "in.({$list})";
                    break;
                case 'not_in':
                    $list = is_array($val) ? implode(',', $val) : $val;
                    $params[$col] = "not.in.({$list})";
                    break;
                case 'between':
                    if (is_array($val) && count($val) === 2) {
                        $params[$col]          = "gte.{$val[0]}";
                        $params[$col . '_lte'] = "lte.{$val[1]}";
                    }
                    break;
                case 'not_between':
                    if (is_array($val) && count($val) === 2) {
                        $params[$col . '_lt'] = "lt.{$val[0]}";
                        $params[$col . '_gt'] = "gt.{$val[1]}";
                    }
                    break;
            }
        }

        // ORDER — PostgREST: ?order=col.asc,col2.desc
        if (!empty($this->orderItems)) {
            $parts = array_map(function ($o) {
                return $o['column'] . '.' . ($o['direction'] ?? 'asc');
            }, $this->orderItems);
            $params['order'] = implode(',', $parts);
        }

        // LIMIT / OFFSET
        if ($this->limitVal !== null)  $params['limit']  = (string)$this->limitVal;
        if ($this->offsetVal !== null) $params['offset'] = (string)$this->offsetVal;

        $headers = ['Prefer' => 'count=exact'];
        $result = $this->client->request('GET', "/{$this->tableName}", $params, null, $headers);

        $data    = is_array($result) ? (isset($result[0]) || empty($result) ? $result : [$result]) : [];
        $crTotal = WOWSQLClient::parseTotalFromContentRange($this->client->lastContentRange, count($data));

        return [
            'data'   => $data,
            'count'  => count($data),
            'total'  => $crTotal,
            'limit'  => $this->limitVal ?? 100,
            'offset' => $this->offsetVal ?? 0,
        ];
    }

    public function execute()
    {
        return $this->get();
    }

    public function first()
    {
        $result = $this->limit(1)->get();
        return !empty($result['data']) ? $result['data'][0] : null;
    }

    public function single()
    {
        $result = $this->limit(2)->get();
        if (empty($result['data'])) throw new WOWSQLException('No records found');
        if (count($result['data']) > 1) throw new WOWSQLException('Multiple records found, expected exactly one');
        return $result['data'][0];
    }

    public function count()
    {
        $saved = [$this->selectCols, $this->groupByCols, $this->havingList, $this->orderItems,
                  $this->limitVal, $this->offsetVal];
        $this->selectCols  = null;
        $this->groupByCols = [];
        $this->havingList  = [];
        $this->orderItems  = [];
        $this->limitVal    = 0;
        $this->offsetVal   = null;

        try {
            $headers = ['Prefer' => 'count=exact'];
            $params  = $this->buildFilterParams();
            $params['limit'] = '0';
            $this->client->request('GET', "/{$this->tableName}", $params, null, $headers);
            return WOWSQLClient::parseTotalFromContentRange($this->client->lastContentRange, 0);
        } finally {
            [$this->selectCols, $this->groupByCols, $this->havingList, $this->orderItems,
             $this->limitVal, $this->offsetVal] = $saved;
        }
    }

    public function sum($column)
    {
        $saved = $this->selectCols;
        $this->selectCols = ["sum({$column})"];
        $this->limitVal   = null;
        $this->offsetVal  = null;
        try {
            $result = $this->get();
            return (float)(($result['data'][0]['sum'] ?? 0) ?: 0);
        } finally {
            $this->selectCols = $saved;
        }
    }

    public function avg($column)
    {
        $saved = $this->selectCols;
        $this->selectCols = ["avg({$column})"];
        $this->limitVal   = null;
        $this->offsetVal  = null;
        try {
            $result = $this->get();
            return (float)(($result['data'][0]['avg'] ?? 0) ?: 0);
        } finally {
            $this->selectCols = $saved;
        }
    }

    public function paginate($page = 1, $perPage = 20)
    {
        $offsetVal = (max($page, 1) - 1) * $perPage;
        $result    = $this->limit($perPage)->offset($offsetVal)->get();
        $total     = $result['total'] ?? 0;
        return [
            'data'        => $result['data'],
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => $total > 0 ? (int)ceil($total / $perPage) : 0,
        ];
    }

    // ── Internal ─────────────────────────────────────────────────

    private function buildFilterParams()
    {
        $params = [];
        foreach ($this->filters as $f) {
            $col = $f['column'];
            $op  = $f['operator'];
            $val = $f['value'];
            if ($op === 'eq')    $params[$col] = "eq.{$val}";
            elseif ($op === 'neq') $params[$col] = "neq.{$val}";
            elseif ($op === 'gt')  $params[$col] = "gt.{$val}";
            elseif ($op === 'gte') $params[$col] = "gte.{$val}";
            elseif ($op === 'lt')  $params[$col] = "lt.{$val}";
            elseif ($op === 'lte') $params[$col] = "lte.{$val}";
        }
        return $params;
    }
}
