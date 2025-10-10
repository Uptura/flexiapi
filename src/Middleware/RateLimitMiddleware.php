<?php

namespace FlexiAPI\Middleware;

use FlexiAPI\Utils\Response;

class RateLimitMiddleware
{
    private array $config;
    private string $storageType;
    private string $storageDir;
    
    public function __construct(array $config)
    {
        $this->config = $config['rate_limit'] ?? [];
        $this->storageType = $this->config['storage'] ?? 'file';
        $this->storageDir = 'storage/cache/rate_limits';
        
        // Create storage directory if needed
        if ($this->storageType === 'file' && !is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }
    
    /**
     * Check if the request should be rate limited
     */
    public function handle(): bool
    {
        // Check if rate limiting is enabled
        if (!($this->config['enabled'] ?? false)) {
            return true; // Allow request
        }
        
        $identifier = $this->getClientIdentifier();
        $limit = $this->config['requests_per_minute'] ?? 60;
        $windowSeconds = 60; // 1 minute window
        
        // Get current request count
        $requestCount = $this->getRequestCount($identifier, $windowSeconds);
        
        // Check if limit exceeded
        if ($requestCount >= $limit) {
            $this->sendRateLimitResponse($limit, $windowSeconds);
            return false; // Block request
        }
        
        // Record this request
        $this->recordRequest($identifier);
        
        // Add rate limit headers
        $this->addRateLimitHeaders($requestCount + 1, $limit, $windowSeconds);
        
        return true; // Allow request
    }
    
    /**
     * Get unique identifier for the client
     */
    private function getClientIdentifier(): string
    {
        // Priority order: API key, JWT user, IP address
        
        // Check for API key
        $headers = getallheaders();
        $apiKey = $headers['X-API-Key'] ?? '';
        if (!empty($apiKey)) {
            return 'api_key:' . hash('sha256', $apiKey);
        }
        
        // Check for JWT token
        $authHeader = $headers['Authorization'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            return 'jwt:' . hash('sha256', $token);
        }
        
        // Fall back to IP address
        $ip = $this->getClientIP();
        return 'ip:' . $ip;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Direct connection
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated IPs (take first one)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Get request count for identifier within time window
     */
    private function getRequestCount(string $identifier, int $windowSeconds): int
    {
        switch ($this->storageType) {
            case 'memory':
                return $this->getRequestCountMemory($identifier, $windowSeconds);
            default:
                return $this->getRequestCountFile($identifier, $windowSeconds);
        }
    }
    
    /**
     * Get request count from file storage
     */
    private function getRequestCountFile(string $identifier, int $windowSeconds): int
    {
        $filename = $this->storageDir . '/' . hash('sha256', $identifier) . '.json';
        
        if (!file_exists($filename)) {
            return 0;
        }
        
        $data = json_decode(file_get_contents($filename), true);
        if (!$data || !isset($data['requests'])) {
            return 0;
        }
        
        $currentTime = time();
        $cutoffTime = $currentTime - $windowSeconds;
        
        // Filter requests within the window
        $validRequests = array_filter($data['requests'], function($timestamp) use ($cutoffTime) {
            return $timestamp > $cutoffTime;
        });
        
        return count($validRequests);
    }
    
    /**
     * Get request count from memory storage (session-based)
     */
    private function getRequestCountMemory(string $identifier, int $windowSeconds): int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . hash('sha256', $identifier);
        
        if (!isset($_SESSION[$key])) {
            return 0;
        }
        
        $data = $_SESSION[$key];
        $currentTime = time();
        $cutoffTime = $currentTime - $windowSeconds;
        
        // Filter requests within the window
        $validRequests = array_filter($data, function($timestamp) use ($cutoffTime) {
            return $timestamp > $cutoffTime;
        });
        
        return count($validRequests);
    }
    
    /**
     * Record a request
     */
    private function recordRequest(string $identifier): void
    {
        switch ($this->storageType) {
            case 'memory':
                $this->recordRequestMemory($identifier);
                break;
            default:
                $this->recordRequestFile($identifier);
        }
    }
    
    /**
     * Record request in file storage
     */
    private function recordRequestFile(string $identifier): void
    {
        $filename = $this->storageDir . '/' . hash('sha256', $identifier) . '.json';
        
        $data = [];
        if (file_exists($filename)) {
            $data = json_decode(file_get_contents($filename), true) ?: [];
        }
        
        if (!isset($data['requests'])) {
            $data['requests'] = [];
        }
        
        // Add current request
        $data['requests'][] = time();
        
        // Keep only last 100 requests to prevent file bloat
        $data['requests'] = array_slice($data['requests'], -100);
        
        // Save updated data
        file_put_contents($filename, json_encode($data), LOCK_EX);
    }
    
    /**
     * Record request in memory storage
     */
    private function recordRequestMemory(string $identifier): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . hash('sha256', $identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        
        // Add current request
        $_SESSION[$key][] = time();
        
        // Keep only last 100 requests
        $_SESSION[$key] = array_slice($_SESSION[$key], -100);
    }
    
    /**
     * Send rate limit exceeded response
     */
    private function sendRateLimitResponse(int $limit, int $windowSeconds): void
    {
        $retryAfter = $windowSeconds;
        
        // Add rate limit headers
        header("X-RateLimit-Limit: {$limit}");
        header("X-RateLimit-Remaining: 0");
        header("X-RateLimit-Reset: " . (time() + $retryAfter));
        header("Retry-After: {$retryAfter}");
        
        Response::json(false, 'Rate limit exceeded', null, 429, [
            'error' => 'too_many_requests',
            'limit' => $limit,
            'window_seconds' => $windowSeconds,
            'retry_after' => $retryAfter,
            'message' => "You have exceeded the rate limit of {$limit} requests per minute. Please try again in {$retryAfter} seconds."
        ]);
    }
    
    /**
     * Add rate limit headers to response
     */
    private function addRateLimitHeaders(int $currentCount, int $limit, int $windowSeconds): void
    {
        $remaining = max(0, $limit - $currentCount);
        $resetTime = time() + $windowSeconds;
        
        header("X-RateLimit-Limit: {$limit}");
        header("X-RateLimit-Remaining: {$remaining}");
        header("X-RateLimit-Reset: {$resetTime}");
    }
    
    /**
     * Clean up old rate limit data (call periodically)
     */
    public function cleanup(): void
    {
        if ($this->storageType !== 'file') {
            return;
        }
        
        $files = glob($this->storageDir . '/*.json');
        $cutoffTime = time() - 3600; // Clean files older than 1 hour
        
        foreach ($files as $file) {
            $modified = filemtime($file);
            if ($modified < $cutoffTime) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get rate limit status for a client
     */
    public function getStatus(string $identifier = null): array
    {
        if ($identifier === null) {
            $identifier = $this->getClientIdentifier();
        }
        
        $limit = $this->config['requests_per_minute'] ?? 60;
        $windowSeconds = 60;
        $currentCount = $this->getRequestCount($identifier, $windowSeconds);
        $remaining = max(0, $limit - $currentCount);
        
        return [
            'enabled' => $this->config['enabled'] ?? false,
            'identifier' => $identifier,
            'limit' => $limit,
            'used' => $currentCount,
            'remaining' => $remaining,
            'window_seconds' => $windowSeconds,
            'reset_time' => time() + $windowSeconds
        ];
    }
}