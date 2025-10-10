<?php

namespace FlexiAPI\Services;

class QueryBuilder
{
    /**
     * Build WHERE clause and return SQL + params.
     */
    public static function buildWhere(array $conditions): array
    {
        $clauses = [];
        $params = [];
        $idx = 0;

        foreach ($conditions as $cond) {
            $field = $cond['field'];
            $op = strtoupper($cond['operator'] ?? '=');
            $val = $cond['value'] ?? null;

            if (in_array($op, ['IN', 'NOT IN']) && is_array($val)) {
                $placeholders = [];
                foreach ($val as $v) {
                    $key = ":{$field}_in_{$idx}";
                    $placeholders[] = $key;
                    $params[$key] = $v;
                    $idx++;
                }
                $clauses[] = sprintf("`%s` %s (%s)", $field, $op, implode(',', $placeholders));
            } elseif ($op === 'LIKE') {
                $key = ":{$field}_{$idx}";
                $clauses[] = sprintf("`%s` LIKE %s", $field, $key);
                $params[$key] = $val;
                $idx++;
            } else {
                $key = ":{$field}_{$idx}";
                $clauses[] = sprintf("`%s` %s %s", $field, $op, $key);
                $params[$key] = $val;
                $idx++;
            }
        }

        return [
            'sql' => $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '',
            'params' => $params
        ];
    }

    /**
     * Build SELECT query with optional conditions, pagination and ordering.
     */
    public static function buildSelect(
        string $table,
        array $columns = [],
        array $conditions = [],
        ?int $limit = null,
        ?int $offset = null,
        array $orderBy = []
    ): array {
        // Columns
        if (empty($columns) || (count($columns) === 1 && $columns[0] === '*')) {
            $cols = '*';
        } else {
            $safeCols = [];
            foreach ($columns as $c) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $c)) {
                    throw new \InvalidArgumentException("Invalid column name: $c");
                }
                $safeCols[] = "`$c`";
            }
            $cols = implode(',', $safeCols);
        }

        // WHERE
        $whereData = self::buildWhere($conditions);
        $where  = $whereData['sql'];
        $params = $whereData['params'];

        // ORDER BY
        $order = '';
        if (!empty($orderBy)) {
            $parts = [];
            foreach ($orderBy as $o) {
                $col = $o['column'] ?? '';
                $dir = strtoupper($o['direction'] ?? 'ASC');
                if (preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
                    $parts[] = "`{$col}` {$dir}";
                }
            }
            if ($parts) {
                $order = ' ORDER BY ' . implode(',', $parts);
            }
        }

        // LIMIT + OFFSET (safe integers, injected directly)
        $limitSql = '';
        if ($limit !== null) {
            $limit  = (int)$limit;
            $offset = (int)($offset ?? 0);
            $limitSql = " LIMIT {$limit} OFFSET {$offset}";
        }

        $sql = "SELECT {$cols} FROM `{$table}` {$where}{$order}{$limitSql}";
        return ['sql' => $sql, 'params' => $params];
    }
}
