<?php

namespace CasSystem\LaravelClient\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CasAuthService
{
    protected $config;
    protected $httpClient;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->httpClient = new Client([
            'timeout' => $config['timeout'] ?? 30,
            'verify' => $config['verify_ssl'] ?? true,
        ]);
    }

    /**
     * Generate SSO login URL
     */
    public function getLoginUrl(?string $returnUrl = null): string
    {
        $params = [
            'client_id' => $this->config['client_id'],
            'response_type' => 'token',
            'redirect_uri' => $returnUrl ?: $this->config['callback_url'],
        ];

        // The /sso/login redirect is followed by the END USER'S BROWSER, so it
        // must use the PUBLIC, browser-reachable CAS base when one is configured
        // (split-horizon deploys: the browser cannot reach the internal back-
        // channel host used for token validation). Falls back to server_url so
        // single-url setups keep working unchanged. Only the login URL uses this;
        // all server-to-server calls below keep using server_url.
        $loginBaseUrl = ($this->config['public_url'] ?? null) ?: $this->config['server_url'];

        return $loginBaseUrl . '/sso/login?' . http_build_query($params);
    }

    /**
     * Generate SSO Token using Client Credentials for a specific user
     * Enhanced security: Uses client_id + client_secret + username instead of password
     */
    public function generateSSOToken(string $username): ?array
    {
        try {
            $timestamp = time();
            $clientId = $this->config['client_id'];
            
            $requestData = [
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'username' => $username
            ];

            $headers = [
                'Content-Type' => 'application/json',
                'X-Client-ID' => $clientId,
                'X-Timestamp' => $timestamp,
            ];

            // Add signature if enabled
            if ($this->config['enable_signature_validation'] ?? false) {
                $signature = $this->generateSignature(
                    'POST',
                    '/api/sso/token',
                    $requestData,
                    $timestamp,
                    $clientId
                );
                $headers['X-Signature'] = $signature;
            }

            $response = $this->httpClient->post(
                $this->config['server_url'] . '/api/sso/token',
                [
                    'headers' => $headers,
                    'json' => $requestData,
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);

            if ($response->getStatusCode() === 200 && isset($data['token'])) {
                return $data;
            }

            return null;
        } catch (RequestException $e) {
            Log::error('CAS SSO token generation failed', [
                'error' => $e->getMessage(),
                'username' => $username,
                'client_id' => $this->config['client_id']
            ]);
            return null;
        }
    }

    /**
     * Validate SSO token with CAS server using Client Credentials
     * Enhanced security: Uses client_id + client_secret instead of username/password
     */
    public function validateToken(string $token): ?array
    {
        Log::info('CasAuthService: Validating token', ['token_sub' => substr($token, 0, 10)]);

        try {
            $timestamp = time();
            $clientId = $this->config['client_id'];
            
            $requestData = [
                'token' => $token,
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret']
            ];

            Log::info('CasAuthService: Validation Request Config', [
                'url' => $this->config['server_url'] . '/api/sso/validate',
                'client_id' => $clientId,
                // 'secret' => '***', // Don't log secret
            ]);

            $headers = [
                'Content-Type' => 'application/json',
                'X-Client-ID' => $clientId,
                'X-Timestamp' => $timestamp,
            ];

            // Add signature if enabled
            if ($this->config['enable_signature_validation'] ?? false) {
                $signature = $this->generateSignature(
                    'POST',
                    '/api/sso/validate',
                    $requestData,
                    $timestamp,
                    $clientId
                );
                $headers['X-Signature'] = $signature;
            }

            $response = $this->httpClient->post(
                $this->config['server_url'] . '/api/sso/validate',
                [
                    'headers' => $headers,
                    'json' => $requestData,
                ]
            );

            Log::info('CasAuthService: Response Code', ['code' => $response->getStatusCode()]);

            $content = $response->getBody()->getContents();
            Log::info('CasAuthService: Response Body', ['body' => $content]);

            $data = json_decode($content, true);

            if ($response->getStatusCode() === 200 && isset($data['user'])) {
                // Cache user data
                $cacheKey = "cas_user_" . md5($token);
                Cache::put($cacheKey, $data['user'], now()->addMinutes(60));
                return $data['user'];
            }

            Log::error('CasAuthService: Validation failed', ['data' => $data]);
            return null;
        } catch (\Exception $e) {
            Log::error('CAS token validation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'token' => substr($token, 0, 10) . '...',
            ]);
            return null;
        }
    }

    /**
     * Get user from token (cached)
     */
    public function getUserFromToken(string $token): ?array
    {
        return Cache::get("cas_user_" . md5($token));
    }

    /**
     * Logout from CAS server
     */
    public function logout(?string $token = null): bool
    {
        try {
            if ($token) {
                Cache::forget("cas_user_" . md5($token));
            }

            $response = $this->httpClient->post(
                $this->config['server_url'] . '/api/logout',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ]
            );

            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            Log::error('CAS logout failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Generate HMAC signature for request
     */
    protected function generateSignature(string $method, string $uri, array $data, int $timestamp, string $clientId): string
    {
        $body = json_encode($data);
        $payload = implode('|', [
            $method,
            $uri,
            $body,
            $timestamp,
            $clientId
        ]);

        $secretKey = $this->config['signature_secret'] ?? 'default-signature-secret';
        return 'sha256=' . hash_hmac('sha256', $payload, $secretKey);
    }

    /**
     * Check if user has specific role
     */
    public function userHasRole(array $user, string $role): bool
    {
        return in_array($role, $user['roles'] ?? []);
    }

    /**
     * Check if user has any of the specified roles
     */
    public function userHasAnyRole(array $user, array $roles): bool
    {
        $userRoles = $user['roles'] ?? [];
        return !empty(array_intersect($userRoles, $roles));
    }

    /**
     * Check if user has all specified roles
     */
    public function userHasAllRoles(array $user, array $roles): bool
    {
        $userRoles = $user['roles'] ?? [];
        return empty(array_diff($roles, $userRoles));
    }
}