<?php

namespace FlexiAPI\Core;

use FlexiAPI\Utils\Response;
use FlexiAPI\Utils\Validator;
use FlexiAPI\Utils\Encryptor;
use FlexiAPI\DB\MySQLAdapter;
use PDO;

abstract class BaseEndpointController
{
    protected MySQLAdapter $db;
    protected array $config;
    protected string $tableName;
    protected array $fillable = [];
    protected array $encrypted = []; // Fields that should be encrypted
    protected array $hidden = ['password']; // Fields to hide in responses
    
    public function __construct(MySQLAdapter $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * GET /{endpoint} - List all records with pagination
     */
    public function index(): void
    {
        try {
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            $search = $_GET['search'] ?? '';
            $sort = $_GET['sort'] ?? 'id';
            $order = $_GET['order'] ?? 'DESC';
            
            $offset = ($page - 1) * $limit;
            
            // Build query
            $whereClause = '';
            $params = [];
            
            if (!empty($search)) {
                $searchFields = $this->getSearchableFields();
                $searchConditions = [];
                foreach ($searchFields as $field) {
                    $searchConditions[] = "`{$field}` LIKE :search";
                }
                if (!empty($searchConditions)) {
                    $whereClause = 'WHERE ' . implode(' OR ', $searchConditions);
                    $params['search'] = "%{$search}%";
                }
            }
            
            // Count total records
            $countSql = "SELECT COUNT(*) FROM `{$this->tableName}` {$whereClause}";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // Get records
            $sql = "SELECT * FROM `{$this->tableName}` {$whereClause} ORDER BY `{$sort}` {$order} LIMIT :limit OFFSET :offset";
            $stmt = $this->db->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process records (decrypt, hide fields)
            $records = array_map([$this, 'processRecord'], $records);
            
            Response::json(true, 'Records retrieved successfully', [
                'data' => $records,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            
        } catch (\Exception $e) {
            Response::json(false, 'Failed to retrieve records', null, 500, [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * GET /{endpoint}/search/{column}?q={query} - Search by specific column
     */
    public function searchByColumn(): void
    {
        try {
            // Get column from URL parameter
            $column = $_GET['column'] ?? '';
            $query = $_GET['q'] ?? '';
            
            // Pagination parameters
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            $sort = $_GET['sort'] ?? 'id';
            $order = $_GET['order'] ?? 'DESC';
            
            $offset = ($page - 1) * $limit;
            
            // Validate column exists and is searchable
            if (empty($column)) {
                Response::json(false, 'Column parameter is required', null, 400);
                return;
            }
            
            if (empty($query)) {
                Response::json(false, 'Query parameter (q) is required', null, 400);
                return;
            }
            
            if (!in_array($column, $this->fillable)) {
                Response::json(false, "Column '{$column}' is not searchable", null, 400, [
                    'available_columns' => $this->fillable
                ]);
                return;
            }
            
            // Build search query
            $whereClause = "WHERE `{$column}` LIKE :query";
            $params = ['query' => "%{$query}%"];
            
            // Count total records
            $countSql = "SELECT COUNT(*) FROM `{$this->tableName}` {$whereClause}";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // Get records
            $sql = "SELECT * FROM `{$this->tableName}` {$whereClause} ORDER BY `{$sort}` {$order} LIMIT :limit OFFSET :offset";
            $stmt = $this->db->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process records (decrypt, hide fields)
            $records = array_map([$this, 'processRecord'], $records);
            
            Response::json(true, "Search results for '{$column}' containing '{$query}'", [
                'data' => $records,
                'search' => [
                    'column' => $column,
                    'query' => $query
                ],
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            
        } catch (\Exception $e) {
            Response::json(false, 'Failed to search records', null, 500, [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * POST /{endpoint} - Create new record
     */
    public function store(): void
    {
        try {
            $input = $this->getJsonInput();
            
            // Validate input
            $validator = new Validator();
            $errors = $validator->validate($input, $this->getValidationRules());
            
            if (!empty($errors)) {
                Response::json(false, 'Validation failed', null, 400, ['errors' => $errors]);
                return;
            }
            
            // Filter only fillable fields
            $data = array_intersect_key($input, array_flip($this->fillable));
            
            // Encrypt sensitive fields
            $data = $this->encryptFields($data);
            
            // Build insert query
            $fields = array_keys($data);
            $placeholders = array_map(fn($field) => ":{$field}", $fields);
            
            $sql = "INSERT INTO `{$this->tableName}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($data);
            
            $id = $this->db->lastInsertId();
            
            // Get created record
            $record = $this->findById($id);
            
            Response::json(true, 'Record created successfully', [
                'id' => $id,
                'data' => $this->processRecord($record)
            ], 201);
            
        } catch (\Exception $e) {
            Response::json(false, 'Failed to create record', null, 500, [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * GET /{endpoint}/{id} - Get specific record
     */
    public function show(int $id): void
    {
        try {
            $record = $this->findById($id);
            
            if (!$record) {
                Response::json(false, 'Record not found', null, 404);
                return;
            }
            
            Response::json(true, 'Record retrieved successfully', [
                'data' => $this->processRecord($record)
            ]);
            
        } catch (\Exception $e) {
            Response::json(false, 'Failed to retrieve record', null, 500, [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * PUT /{endpoint}/{id} - Update record
     */
    public function update(int $id): void
    {
        try {
            $record = $this->findById($id);
            
            if (!$record) {
                Response::json(false, 'Record not found', null, 404);
                return;
            }
            
            $input = $this->getJsonInput();
            
            // Validate input
            $validator = new Validator();
            $errors = $validator->validate($input, $this->getValidationRules());
            
            if (!empty($errors)) {
                Response::json(false, 'Validation failed', null, 400, ['errors' => $errors]);
                return;
            }
            
            // Filter only fillable fields
            $data = array_intersect_key($input, array_flip($this->fillable));
            
            // Encrypt sensitive fields
            $data = $this->encryptFields($data);
            
            // Build update query
            $setPairs = array_map(fn($field) => "`{$field}` = :{$field}", array_keys($data));
            
            $sql = "UPDATE `{$this->tableName}` SET " . implode(', ', $setPairs) . " WHERE `id` = :id";
            $stmt = $this->db->prepare($sql);
            $data['id'] = $id;
            $stmt->execute($data);
            
            // Get updated record
            $updatedRecord = $this->findById($id);
            
            Response::json(true, 'Record updated successfully', [
                'data' => $this->processRecord($updatedRecord)
            ]);
            
        } catch (\Exception $e) {
            Response::json(false, 'Failed to update record', null, 500, [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * DELETE /{endpoint}/{id} - Delete record
     */
    public function destroy(int $id): void
    {
        try {
            $record = $this->findById($id);
            
            if (!$record) {
                Response::json(false, 'Record not found', null, 404);
                return;
            }
            
            $sql = "DELETE FROM `{$this->tableName}` WHERE `id` = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            Response::json(true, 'Record deleted successfully');
            
        } catch (\Exception $e) {
            Response::json(false, 'Failed to delete record', null, 500, [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    protected function findById(int $id): ?array
    {
        $sql = "SELECT * FROM `{$this->tableName}` WHERE `id` = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        return $record ?: null;
    }
    
    protected function getJsonInput(): array
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        return $data ?: [];
    }
    
    protected function processRecord(array $record): array
    {
        // Decrypt encrypted fields
        $record = $this->decryptFields($record);
        
        // Hide sensitive fields
        foreach ($this->hidden as $field) {
            unset($record[$field]);
        }
        
        return $record;
    }
    
    protected function encryptFields(array $data): array
    {
        $encryptor = new Encryptor($this->config['encryption']['key']);
        
        foreach ($this->encrypted as $field) {
            if (isset($data[$field])) {
                $data[$field] = $encryptor->encrypt($data[$field]);
            }
        }
        
        return $data;
    }
    
    protected function decryptFields(array $data): array
    {
        $encryptor = new Encryptor($this->config['encryption']['key']);
        
        foreach ($this->encrypted as $field) {
            if (isset($data[$field])) {
                try {
                    $data[$field] = $encryptor->decrypt($data[$field]);
                } catch (\Exception $e) {
                    // Keep original value if decryption fails
                }
            }
        }
        
        return $data;
    }
    
    protected function getSearchableFields(): array
    {
        // By default, search in all fillable text fields
        return array_filter($this->fillable, function($field) {
            return !in_array($field, ['id', 'created_at', 'updated_at']);
        });
    }
    
    abstract protected function getValidationRules(): array;
}