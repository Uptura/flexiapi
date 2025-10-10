<?php

namespace FlexiAPI\Controllers;

use FlexiAPI\DB\DBAdapterInterface;
use FlexiAPI\Services\QueryBuilder;
use FlexiAPI\Utils\Response;
use FlexiAPI\Validators\InputValidator;

class TableController extends BaseController
{
    private InputValidator $validator;

    public function __construct(DBAdapterInterface $db, array $config)
    {
        parent::__construct($db, $config);
        $this->validator = new InputValidator($config);
    }

    /**
     * ✅ GET (Select rows)
     */
    public function get(array $input)
    {
        try {
            $validated = $this->validator->validateGet($input);

            $qb = QueryBuilder::buildSelect(
                $validated['table'],
                $validated['columns'],
                $validated['condition'],
                $validated['limit'],
                ($validated['page'] - 1) * $validated['limit'],
                $validated['order']
            );

            $rows = $this->db->query($qb['sql'], $qb['params']);

            // Count query for pagination
            $whereData = QueryBuilder::buildWhere($validated['condition']);
            $countSql = "SELECT COUNT(*) as cnt FROM `{$validated['table']}` {$whereData['sql']}";
            $countRes = $this->db->query($countSql, $whereData['params']);
            $totalRows = (int)($countRes[0]['cnt'] ?? 0);

            return Response::json(true, 'Data retrieved successfully', $rows, 200, [
                'current_page' => $validated['page'],
                'limit' => $validated['limit'],
                'total_rows' => $totalRows,
                'total_pages' => (int)ceil($totalRows / $validated['limit'])
            ]);
        } catch (\InvalidArgumentException $e) {
            return Response::json(false, $e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return Response::json(false, 'Unexpected error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * ✅ POST (Insert rows)
     */
    public function create(array $input)
    {
        try {
            $validated = $this->validator->validateCreate($input);
            $table = $validated['table'];
            $rows = $validated['data'];
            $upsert = $validated['upsert'] ?? false; // Optional flag

            $columns = array_keys($rows[0]);
            $colList = implode(',', array_map(fn($c) => "`$c`", $columns));

            // Build placeholders for multiple rows
            $placeRows = [];
            $params = [];
            $i = 0;
            foreach ($rows as $row) {
                $placeholders = [];
                foreach ($columns as $col) {
                    $key = ":{$col}_{$i}";
                    $placeholders[] = $key;
                    $params[$key] = $row[$col] ?? null;
                }
                $placeRows[] = '(' . implode(',', $placeholders) . ')';
                $i++;
            }

            $sql = "INSERT INTO `{$table}` ({$colList}) VALUES " . implode(',', $placeRows);

            // ✅ Add ON DUPLICATE KEY UPDATE if upsert requested
            if ($upsert) {
                $updateParts = [];
                foreach ($columns as $col) {
                    $updateParts[] = "`$col`=VALUES(`$col`)";
                }
                $sql .= " ON DUPLICATE KEY UPDATE " . implode(',', $updateParts);
            }

            $this->db->beginTransaction();
            $this->db->execute($sql, $params);

            $firstId = (int) $this->db->lastInsertId();
            $count = count($rows);

            $insertedIds = $firstId > 0 ? range($firstId, $firstId + $count - 1) : [];

            $this->db->commit();

            return Response::json(true, $upsert ? 'Rows upserted successfully' : 'Rows inserted successfully', [
                'ids' => $insertedIds
            ]);
        } catch (\InvalidArgumentException $e) {
            return Response::json(false, $e->getMessage(), null, 400);
        } catch (\Exception $e) {
            $this->db->rollBack();
            return Response::json(false, 'Insert/Upsert failed: ' . $e->getMessage(), null, 500);
        }
    }



    /**
     * ✅ PUT (Update rows)
     */
    public function update(array $input)
    {
        try {
            $validated = $this->validator->validateUpdate($input);
            $table = $validated['table'];
            $where = $validated['where'];
            $data = $validated['data'];

            $setParts = [];
            $params = [];
            foreach ($data as $k => $v) {
                $p = ":set_{$k}";
                $setParts[] = "`$k` = $p";
                $params[$p] = $v;
            }

            $whereData = QueryBuilder::buildWhere([$where]);
            $params = array_merge($params, $whereData['params']);

            $sql = "UPDATE `{$table}` SET " . implode(',', $setParts) . " {$whereData['sql']}";

            $rows = $this->db->execute($sql, $params);
            return Response::json(true, 'Rows updated successfully', ['affected' => $rows]);
        } catch (\InvalidArgumentException $e) {
            return Response::json(false, $e->getMessage(), null, 400);
        } catch (\Exception $e) {
            return Response::json(false, 'Update failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * ✅ DELETE (Delete rows)
     */
    public function delete(array $input)
    {
        try {
            $validated = $this->validator->validateDelete($input);
            $table = $validated['table'];
            $column = $validated['column'];
            $values = $validated['values'];
            $limit = $validated['limit'];

            $params = [];
            $placeholders = [];
            foreach ($values as $i => $v) {
                $k = ":val{$i}";
                $placeholders[] = $k;
                $params[$k] = $v;
            }

            // ✅ Directly inject limit after validating as int
            $limitSql = $limit ? " LIMIT " . (int)$limit : '';

            $sql = "DELETE FROM `{$table}` WHERE `{$column}` IN (" . implode(',', $placeholders) . ") {$limitSql}";

            $this->db->beginTransaction();
            $affected = $this->db->execute($sql, $params);
            $this->db->commit();

            return Response::json(true, 'Rows deleted successfully', ['affected' => $affected]);
        } catch (\InvalidArgumentException $e) {
            return Response::json(false, $e->getMessage(), null, 400);
        } catch (\Exception $e) {
            $this->db->rollBack();
            return Response::json(false, 'Delete failed: ' . $e->getMessage(), null, 500);
        }
    }
}
