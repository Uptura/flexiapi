<?php

namespace FlexiAPI\Controllers;

use FlexiAPI\Services\JWTAuth;
use FlexiAPI\Utils\Response;
use FlexiAPI\Utils\Validator;
use FlexiAPI\Utils\Encryptor;
use PDO;

class AuthController
{
    private PDO $db;
    private array $config;
    private ?JWTAuth $jwtAuth = null;

    public function __construct(PDO $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
        
        if (isset($config['jwt'])) {
            $this->jwtAuth = new JWTAuth(
                $config['jwt']['secret'],
                $config['jwt']['algorithm'] ?? 'HS256',
                $config['jwt']['expiration'] ?? 3600
            );
        }
    }

    /**
     * Generate API keys for authentication
     * POST /api/v1/auth/generate_keys
     */
    public function generateKeys(): void
    {
        try {
            $input = $this->getJsonInput();
            
            // Validate secret key
            $secretKey = $input['secret_key'] ?? '';
            if ($secretKey !== $this->config['api']['secret_key']) {
                Response::json(false, 'Invalid secret key', null, 403);
                return;
            }

            // Validate required fields
            $validator = new Validator();
            $errors = $validator->validate($input, [
                'user_id' => 'required|integer',
                'name' => 'required|min:3|max:50'
            ]);

            if (!empty($errors)) {
                Response::json(false, 'Validation failed', null, 400, ['errors' => $errors]);
                return;
            }

            // Generate API key
            $apiKey = $this->generateApiKey();
            $hashedKey = hash('sha256', $apiKey);

            // Create api_keys table if it doesn't exist
            $this->createApiKeysTable();

            // Insert API key into database
            $stmt = $this->db->prepare("
                INSERT INTO api_keys (user_id, name, api_key, created_at) 
                VALUES (:user_id, :name, :api_key, NOW())
            ");

            $stmt->execute([
                'user_id' => $input['user_id'],
                'name' => $input['name'],
                'api_key' => $hashedKey
            ]);

            Response::json(true, 'API key generated successfully', [
                'api_key' => $apiKey,
                'user_id' => $input['user_id'],
                'name' => $input['name'],
                'expires_at' => null // Never expires unless manually revoked
            ], 201);

        } catch (\Exception $e) {
            Response::json(false, 'Failed to generate API key', null, 500, [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * User login with JWT token generation
     * POST /api/v1/auth/login
     */
    public function login(): void
    {
        try {
            if (!$this->jwtAuth) {
                Response::json(false, 'JWT authentication not configured', null, 500);
                return;
            }

            $input = $this->getJsonInput();

            // Validate input
            $validator = new Validator();
            $errors = $validator->validate($input, [
                'email' => 'required|email',
                'password' => 'required|min:6'
            ]);

            if (!empty($errors)) {
                Response::json(false, 'Validation failed', null, 400, ['errors' => $errors]);
                return;
            }

            // Find user by email
            $stmt = $this->db->prepare("SELECT id, email, password FROM users WHERE email = ?");
            $stmt->execute([$input['email']]);
            $user = $stmt->fetch();

            if (!$user || !Encryptor::verifyPassword($input['password'], $user['password'])) {
                Response::json(false, 'Invalid credentials', null, 401);
                return;
            }

            // Generate JWT token
            $payload = [
                'user_id' => $user['id'],
                'email' => $user['email']
            ];

            $token = $this->jwtAuth->generateToken($payload);

            Response::json(true, 'Login successful', [
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email']
                ],
                'expires_in' => $this->config['jwt']['expiration'] ?? 3600
            ]);

        } catch (\Exception $e) {
            Response::json(false, 'Login failed', null, 500, [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Refresh JWT token
     * POST /api/v1/auth/refresh
     */
    public function refresh(): void
    {
        try {
            if (!$this->jwtAuth) {
                Response::json(false, 'JWT authentication not configured', null, 500);
                return;
            }

            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? '';

            if (!str_starts_with($authHeader, 'Bearer ')) {
                Response::json(false, 'Invalid authorization header', null, 401);
                return;
            }

            $oldToken = substr($authHeader, 7);

            try {
                $payload = $this->jwtAuth->validateToken($oldToken);
                $newToken = $this->jwtAuth->generateToken($payload);

                Response::json(true, 'Token refreshed successfully', [
                    'token' => $newToken,
                    'expires_in' => $this->config['jwt']['expiration'] ?? 3600
                ]);

            } catch (\Exception $e) {
                Response::json(false, 'Invalid token', null, 401);
            }

        } catch (\Exception $e) {
            Response::json(false, 'Token refresh failed', null, 500, [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Logout (for token blacklisting if implemented)
     * POST /api/v1/auth/logout
     */
    public function logout(): void
    {
        // For stateless JWT, logout is handled client-side
        // In a production app, you might want to implement token blacklisting
        Response::json(true, 'Logout successful', [
            'message' => 'Token should be removed from client storage'
        ]);
    }

    /**
     * Generate a secure API key
     */
    private function generateApiKey(): string
    {
        return 'flexiapi_' . bin2hex(random_bytes(32));
    }

    /**
     * Create api_keys table if it doesn't exist
     */
    private function createApiKeysTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS api_keys (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                api_key VARCHAR(255) NOT NULL UNIQUE,
                is_active BOOLEAN DEFAULT TRUE,
                last_used_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_api_key (api_key),
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $this->db->exec($sql);
    }

    /**
     * Get JSON input from request body
     */
    private function getJsonInput(): array
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        return $data ?: [];
    }
}