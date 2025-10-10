<?php

namespace FlexiAPI\Services;

use FlexiAPI\Services\CustomJWT;
use FlexiAPI\DB\MySQLAdapter;
use FlexiAPI\Utils\Response;

class JWTAuth
{
    private string $secretKey;
    private string $algorithm;
    private int $expirationTime;
    private MySQLAdapter $db;
    private CustomJWT $jwt;

    public function __construct(array $config, MySQLAdapter $db)
    {
        $this->secretKey = $config['jwt']['secret'] ?? throw new \InvalidArgumentException('JWT secret key is required');
        $this->algorithm = $config['jwt']['algorithm'] ?? 'HS256';
        $this->expirationTime = $config['jwt']['expiration'] ?? 3600; // 1 hour default
        $this->db = $db;
        $this->jwt = new CustomJWT($this->secretKey, $this->algorithm);
    }

    /**
     * Generate JWT token for authenticated user
     */
    public function generateToken(array $userData): string
    {
        $payload = [
            'iss' => $_SERVER['HTTP_HOST'] ?? 'flexiapi', // Issuer
            'aud' => $_SERVER['HTTP_HOST'] ?? 'flexiapi', // Audience
            'iat' => time(), // Issued at
            'exp' => time() + $this->expirationTime, // Expiration time
            'user_id' => $userData['id'] ?? $userData['user_id'],
            'username' => $userData['username'] ?? $userData['email'],
            'roles' => $userData['roles'] ?? ['user']
        ];

        return $this->jwt->encode($payload);
    }

    /**
     * Validate JWT token and return decoded payload
     */
    public function validateToken(string $token): ?array
    {
        try {
            return $this->jwt->decode($token);
        } catch (\Exception $e) {
            // Determine the type of error and send appropriate response
            $errorMessage = $e->getMessage();
            
            if (strpos($errorMessage, 'expired') !== false) {
                Response::json(false, 'Token has expired', null, 401, ['error' => 'token_expired']);
            } elseif (strpos($errorMessage, 'signature') !== false) {
                Response::json(false, 'Invalid token signature', null, 401, ['error' => 'invalid_signature']);
            } else {
                Response::json(false, 'Invalid token', null, 401, ['error' => 'invalid_token']);
            }
        }
        
        return null;
    }

    /**
     * Authenticate user with username/email and password
     */
    public function authenticate(string $identifier, string $password, string $table = 'users'): ?array
    {
        try {
            // Build query to find user by username or email
            $sql = "SELECT * FROM `{$table}` WHERE (username = :identifier OR email = :identifier) LIMIT 1";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->bindParam(':identifier', $identifier);
            $stmt->execute();
            
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$user) {
                return null; // User not found
            }

            // Verify password
            if ($this->verifyPassword($password, $user['password'])) {
                // Remove password from returned data
                unset($user['password']);
                return $user;
            }
            
            return null; // Invalid password
        } catch (\Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify password against hash
     */
    private function verifyPassword(string $password, string $hash): bool
    {
        // Check if it's a bcrypt hash
        if (password_verify($password, $hash)) {
            return true;
        }
        
        // Fallback for MD5 hashes (legacy support)
        if (md5($password) === $hash) {
            return true;
        }
        
        // Direct comparison for plain text (not recommended for production)
        return $password === $hash;
    }

    /**
     * Extract token from Auth-x header
     */
    public function extractTokenFromHeader(): ?string
    {
        $headers = getallheaders();
        $authHeader = $headers['Auth-x'] ?? $headers['auth-x'] ?? null;
        
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Check if current request has valid JWT token
     */
    public function isAuthenticated(): bool
    {
        $token = $this->extractTokenFromHeader();
        
        if (!$token) {
            return false;
        }
        
        return $this->jwt->validate($token);
    }

    /**
     * Get current user data from JWT token
     */
    public function getCurrentUser(): ?array
    {
        $token = $this->extractTokenFromHeader();
        
        if (!$token) {
            return null;
        }
        
        try {
            return $this->jwt->decode($token);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create a refresh token (stored in database)
     */
    public function generateRefreshToken(int $userId): string
    {
        $refreshToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 3600)); // 30 days
        
        try {
            $sql = "INSERT INTO refresh_tokens (user_id, token, expires_at, created_at) 
                    VALUES (:user_id, :token, :expires_at, NOW())
                    ON DUPLICATE KEY UPDATE 
                    token = :token, expires_at = :expires_at, created_at = NOW()";
            
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':token', $refreshToken);
            $stmt->bindParam(':expires_at', $expiresAt);
            $stmt->execute();
            
            return $refreshToken;
        } catch (\Exception $e) {
            error_log("Error creating refresh token: " . $e->getMessage());
            throw new \Exception("Could not create refresh token");
        }
    }

    /**
     * Validate refresh token
     */
    public function validateRefreshToken(string $refreshToken): ?array
    {
        try {
            $sql = "SELECT u.* FROM users u 
                    JOIN refresh_tokens rt ON u.id = rt.user_id 
                    WHERE rt.token = :token AND rt.expires_at > NOW()";
            
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->bindParam(':token', $refreshToken);
            $stmt->execute();
            
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($user) {
                unset($user['password']);
                return $user;
            }
            
            return null;
        } catch (\Exception $e) {
            error_log("Error validating refresh token: " . $e->getMessage());
            return null;
        }
    }
}