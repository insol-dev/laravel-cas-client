<?php

namespace CasSystem\LaravelClient\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SignatureClient
{
    protected $casServerUrl;
    protected $clientId;
    protected $clientSecret;

    public function __construct()
    {
        $this->casServerUrl = config('cas-client.cas_server_url');
        $this->clientId = config('cas-client.client_id');
        $this->clientSecret = config('cas-client.client_secret');
    }

    /**
     * Generate signature for outgoing requests to CAS server
     */
    public function generateRequestSignature(
        string $method,
        string $path,
        string $body,
        ?int $timestamp = null
    ): array {
        $timestamp = $timestamp ?: time();
        
        // Create signature payload
        $payload = implode('|', [
            strtoupper($method),
            $path,
            $timestamp,
            hash('sha256', $body)
        ]);

        // Generate HMAC signature
        $signature = hash_hmac('sha256', $payload, $this->clientSecret);

        return [
            'signature' => $signature,
            'timestamp' => $timestamp
        ];
    }

    /**
     * Validate incoming request signature from CAS server
     */
    public function validateRequestSignature(
        string $method,
        string $path,
        string $body,
        string $signature,
        int $timestamp
    ): bool {
        // Check timestamp (allow 5 minute window)
        $currentTime = time();
        if (abs($currentTime - $timestamp) > 300) {
            return false;
        }

        // Generate expected signature
        $expectedSignature = $this->generateRequestSignature(
            $method,
            $path,
            $body,
            $timestamp
        )['signature'];

        // Compare signatures using constant-time comparison
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Make signed HTTP request to CAS server
     */
    public function makeSignedRequest(
        string $method,
        string $endpoint,
        array $data = []
    ): array {
        $body = json_encode($data);
        $url = rtrim($this->casServerUrl, '/') . '/' . ltrim($endpoint, '/');
        
        // Parse URL to get path for signature
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '/';
        
        // Generate signature
        $signatureData = $this->generateRequestSignature(
            $method,
            $path,
            $body
        );

        try {
            // Make HTTP request with signature headers
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-CAS-Signature' => $signatureData['signature'],
                'X-CAS-Timestamp' => $signatureData['timestamp'],
                'X-CAS-Client-ID' => $this->clientId,
                'X-CAS-Client' => config('app.name', 'Laravel-Client')
            ])->{strtolower($method)}($url, $data);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json(),
                'headers' => $response->headers()
            ];

        } catch (\Exception $e) {
            Log::error('Signed CAS request failed', [
                'client_id' => $this->clientId,
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'status' => 500,
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Validate webhook signature from CAS server
     */
    public function verifyWebhookSignature(
        string $payload,
        string $signature
    ): bool {
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $this->clientSecret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Create signature validation headers for response to CAS server
     */
    public function createResponseHeaders(string $body): array
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $body . $timestamp, $this->clientSecret);

        return [
            'X-CAS-Response-Signature' => $signature,
            'X-CAS-Response-Timestamp' => $timestamp,
            'X-CAS-Client-ID' => $this->clientId
        ];
    }

    /**
     * Validate incoming SSO token with signature verification
     */
    public function validateSSOToken(string $token, ?string $signature = null): array
    {
        try {
            // Decode JWT token to get payload
            $tokenParts = explode('.', $token);
            if (count($tokenParts) !== 3) {
                return [
                    'valid' => false,
                    'error' => 'Invalid token format'
                ];
            }

            $payload = json_decode(base64_decode($tokenParts[1]), true);
            
            // If signature provided, validate it
            if ($signature) {
                $expectedSignature = $this->generateTokenSignature($payload);
                if (!hash_equals($expectedSignature, $signature)) {
                    return [
                        'valid' => false,
                        'error' => 'Token signature validation failed'
                    ];
                }
            }

            // Make signed request to CAS server to validate token
            $response = $this->makeSignedRequest(
                'POST',
                '/api/sso/validate-token',
                ['token' => $token]
            );

            if ($response['success'] && isset($response['data']['valid'])) {
                return [
                    'valid' => $response['data']['valid'],
                    'user_data' => $response['data']['user_data'] ?? null,
                    'token_data' => $payload
                ];
            }

            return [
                'valid' => false,
                'error' => 'Token validation failed on server'
            ];

        } catch (\Exception $e) {
            Log::error('SSO token validation failed', [
                'token' => substr($token, 0, 20) . '...',
                'error' => $e->getMessage()
            ]);

            return [
                'valid' => false,
                'error' => 'Token validation error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate SSO token with signature
     */
    public function validateSSOTokenWithSignature(array $tokenData, string $signature): bool
    {
        $expectedSignature = $this->generateTokenSignature($tokenData);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Generate signature for SSO tokens
     */
    public function generateTokenSignature(array $tokenData): string
    {
        $payload = json_encode($tokenData, JSON_SORT_KEYS);
        return hash_hmac('sha256', $payload, $this->clientSecret);
    }
}