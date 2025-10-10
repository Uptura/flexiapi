<?php

namespace FlexiAPI\Services;

class CustomJWT
{
    private string $secretKey;
    private string $algorithm;

    public function __construct(string $secretKey, string $algorithm = 'HS256')
    {
        $this->secretKey = $secretKey;
        $this->algorithm = $algorithm;
    }

    /**
     * Encode payload to JWT token
     */
    public function encode(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algorithm
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        $signature = $this->sign($headerEncoded . '.' . $payloadEncoded);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }

    /**
     * Decode JWT token and return payload
     */
    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Invalid token format');
        }

        [$headerEncoded, $payloadEncoded, $signature] = $parts;

        // Verify signature
        $expectedSignature = $this->sign($headerEncoded . '.' . $payloadEncoded);
        if (!hash_equals($signature, $expectedSignature)) {
            throw new \InvalidArgumentException('Invalid token signature');
        }

        // Decode header and payload
        $header = json_decode($this->base64UrlDecode($headerEncoded), true);
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (!$header || !$payload) {
            throw new \InvalidArgumentException('Invalid token data');
        }

        // Check algorithm
        if (($header['alg'] ?? '') !== $this->algorithm) {
            throw new \InvalidArgumentException('Invalid algorithm');
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \Exception('Token has expired');
        }

        return $payload;
    }

    /**
     * Validate token and return true if valid
     */
    public function validate(string $token): bool
    {
        try {
            $this->decode($token);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create signature for the given data
     */
    private function sign(string $data): string
    {
        switch ($this->algorithm) {
            case 'HS256':
                return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secretKey, true));
            case 'HS384':
                return $this->base64UrlEncode(hash_hmac('sha384', $data, $this->secretKey, true));
            case 'HS512':
                return $this->base64UrlEncode(hash_hmac('sha512', $data, $this->secretKey, true));
            default:
                throw new \InvalidArgumentException('Unsupported algorithm: ' . $this->algorithm);
        }
    }

    /**
     * Base64 URL-safe encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decode
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Get header from token without validation
     */
    public function getHeader(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        return $header ?: null;
    }

    /**
     * Get payload from token without signature validation (use with caution)
     */
    public function getPayload(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        return $payload ?: null;
    }
}