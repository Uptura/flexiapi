<?php

namespace FlexiAPI\Utils;

class Encryptor
{
    private string $key;
    private string $cipher = 'aes-256-cbc';
    
    public function __construct(string $key)
    {
        $this->key = hash('sha256', $key);
    }
    
    /**
     * Encrypt a string value
     */
    public function encrypt(string $data): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher));
        $encrypted = openssl_encrypt($data, $this->cipher, $this->key, 0, $iv);
        
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt a string value
     */
    public function decrypt(string $data): string
    {
        $data = base64_decode($data);
        
        if ($data === false) {
            throw new \RuntimeException('Invalid encrypted data');
        }
        
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        
        $decrypted = openssl_decrypt($encrypted, $this->cipher, $this->key, 0, $iv);
        
        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed');
        }
        
        return $decrypted;
    }
    
    /**
     * Generate a random encryption key
     */
    public static function generateKey(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Hash a password using bcrypt
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }
    
    /**
     * Verify a password against a hash
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate MD5 hash (for compatibility)
     */
    public static function md5Hash(string $data): string
    {
        return md5($data);
    }
}