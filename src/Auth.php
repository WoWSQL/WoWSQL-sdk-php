<?php

namespace WOWSQL;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;

/**
 * Project-level authentication client.
 * 
 * UNIFIED AUTHENTICATION: Uses the same API keys (anon/service) as database operations.
 * One project = one set of keys for ALL operations (auth + database).
 * 
 * Key Types:
 *     - Anonymous Key (wowsql_anon_...): For client-side auth operations (signup, login, OAuth)
 *     - Service Role Key (wowsql_service_...): For server-side auth operations (admin, full access)
 */
class ProjectAuthClient
{
    private $baseUrl;
    private $apiKey;
    private $httpClient;
    private $timeout;
    private $accessToken;
    private $refreshToken;

    /**
     * Initialize ProjectAuthClient for AUTHENTICATION OPERATIONS.
     * 
     * @param string $projectUrl Project subdomain or full URL
     * @param string $apiKey Unified API key - Anonymous Key (wowsql_anon_...) for client-side,
     *                      or Service Role Key (wowsql_service_...) for server-side.
     *                      This same key works for both auth and database operations.
     * @param int $timeout Request timeout in seconds (default: 30)
     */
    public function __construct($projectUrl, $apiKey, $timeout = 30)
    {
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
        $this->baseUrl = $this->buildAuthBaseUrl($projectUrl);
        
        $this->httpClient = new HttpClient([
            'base_uri' => $this->baseUrl,
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Sign up a new user.
     * 
     * @param string $email User email
     * @param string $password User password (minimum 8 characters)
     * @param string|null $fullName Optional full name
     * @param array|null $userMetadata Optional user metadata
     * @return array Response with session and user data
     * @throws WOWSQLException If the request fails
     */
    public function signUp($email, $password, $fullName = null, $userMetadata = null)
    {
        $payload = [
            'email' => $email,
            'password' => $password,
        ];
        
        if ($fullName !== null) {
            $payload['full_name'] = $fullName;
        }
        
        if ($userMetadata !== null) {
            $payload['user_metadata'] = $userMetadata;
        }
        
        $data = $this->request('POST', '/signup', null, $payload);
        $session = $this->persistSession($data);
        
        return [
            'session' => $session,
            'user' => isset($data['user']) ? $this->normalizeUser($data['user']) : null,
        ];
    }

    /**
     * Sign in an existing user.
     * 
     * @param string $email User email
     * @param string $password User password
     * @return array Response with session and user data
     * @throws WOWSQLException If the request fails
     */
    public function signIn($email, $password)
    {
        $payload = [
            'email' => $email,
            'password' => $password,
        ];
        
        $data = $this->request('POST', '/login', null, $payload);
        $session = $this->persistSession($data);
        
        return [
            'session' => $session,
            'user' => isset($data['user']) ? $this->normalizeUser($data['user']) : null,
        ];
    }

    /**
     * Get OAuth authorization URL.
     * 
     * @param string $provider OAuth provider name (e.g., 'github', 'google')
     * @param string|null $redirectUri Optional frontend redirect URI
     * @return array Response with authorization_url and other OAuth details
     * @throws WOWSQLException If the request fails
     */
    public function getOAuthAuthorizationUrl($provider, $redirectUri = null)
    {
        if (empty($provider)) {
            throw new WOWSQLException('provider is required and cannot be empty');
        }
        
        $params = [];
        if ($redirectUri !== null) {
            $params['frontend_redirect_uri'] = trim($redirectUri);
        }
        
        try {
            $data = $this->request('GET', "/oauth/{$provider}", $params, null);
            
            return [
                'authorization_url' => $data['authorization_url'] ?? '',
                'provider' => $data['provider'] ?? $provider,
                'backend_callback_url' => $data['backend_callback_url'] ?? '',
                'frontend_redirect_uri' => $data['frontend_redirect_uri'] ?? ($redirectUri ?? ''),
            ];
        } catch (WOWSQLException $e) {
            if ($e->getStatusCode() == 502) {
                throw new WOWSQLException(
                    "Bad Gateway (502): The backend server may be down or unreachable. Check if the backend is running and accessible at {$this->baseUrl}",
                    502,
                    $e->getResponse()
                );
            } elseif ($e->getStatusCode() == 400) {
                throw new WOWSQLException(
                    "Bad Request (400): {$e->getMessage()}. Ensure OAuth provider '{$provider}' is configured and enabled for this project.",
                    400,
                    $e->getResponse()
                );
            }
            throw $e;
        }
    }

    /**
     * Exchange OAuth callback code for access tokens.
     * 
     * @param string $provider OAuth provider name
     * @param string $code Authorization code from OAuth provider callback
     * @param string|null $redirectUri Optional redirect URI
     * @return array Response with session and user data
     * @throws WOWSQLException If the request fails
     */
    public function exchangeOAuthCallback($provider, $code, $redirectUri = null)
    {
        $payload = [
            'code' => $code,
        ];
        
        if ($redirectUri !== null) {
            $payload['redirect_uri'] = $redirectUri;
        }
        
        $data = $this->request('POST', "/oauth/{$provider}/callback", null, $payload);
        $session = $this->persistSession($data);
        
        return [
            'session' => $session,
            'user' => isset($data['user']) ? $this->normalizeUser($data['user']) : null,
        ];
    }

    /**
     * Request password reset.
     * 
     * @param string $email User's email address
     * @return array Response with success status and message
     * @throws WOWSQLException If the request fails
     */
    public function forgotPassword($email)
    {
        $payload = ['email' => $email];
        $data = $this->request('POST', '/forgot-password', null, $payload);
        
        return [
            'success' => $data['success'] ?? true,
            'message' => $data['message'] ?? 'If that email exists, a password reset link has been sent',
        ];
    }

    /**
     * Reset password with token.
     * 
     * @param string $token Password reset token from email
     * @param string $newPassword New password (minimum 8 characters)
     * @return array Response with success status and message
     * @throws WOWSQLException If the request fails
     */
    public function resetPassword($token, $newPassword)
    {
        $payload = [
            'token' => $token,
            'new_password' => $newPassword,
        ];
        
        $data = $this->request('POST', '/reset-password', null, $payload);
        
        return [
            'success' => $data['success'] ?? true,
            'message' => $data['message'] ?? 'Password reset successfully! You can now login with your new password',
        ];
    }

    /**
     * Send OTP code to user's email.
     * 
     * @param string $email User's email address
     * @param string $purpose Purpose of OTP - 'login', 'signup', or 'password_reset' (default: 'login')
     * @return array Response with success status and message
     * @throws WOWSQLException If the request fails
     */
    public function sendOtp($email, $purpose = 'login')
    {
        if (!in_array($purpose, ['login', 'signup', 'password_reset'])) {
            throw new WOWSQLException("Purpose must be 'login', 'signup', or 'password_reset'");
        }
        
        $payload = [
            'email' => $email,
            'purpose' => $purpose,
        ];
        
        $data = $this->request('POST', '/otp/send', null, $payload);
        
        return [
            'success' => $data['success'] ?? true,
            'message' => $data['message'] ?? 'If that email exists, an OTP code has been sent',
        ];
    }

    /**
     * Verify OTP and complete authentication.
     * 
     * @param string $email User's email address
     * @param string $otp 6-digit OTP code
     * @param string $purpose Purpose of OTP - 'login', 'signup', or 'password_reset' (default: 'login')
     * @param string|null $newPassword Required for password_reset purpose, new password (minimum 8 characters)
     * @return array Response with session and user data (for login/signup) or success message (for password_reset)
     * @throws WOWSQLException If the request fails
     */
    public function verifyOtp($email, $otp, $purpose = 'login', $newPassword = null)
    {
        if (!in_array($purpose, ['login', 'signup', 'password_reset'])) {
            throw new WOWSQLException("Purpose must be 'login', 'signup', or 'password_reset'");
        }
        
        if ($purpose === 'password_reset' && $newPassword === null) {
            throw new WOWSQLException('new_password is required for password_reset purpose');
        }
        
        $payload = [
            'email' => $email,
            'otp' => $otp,
            'purpose' => $purpose,
        ];
        
        if ($newPassword !== null) {
            $payload['new_password'] = $newPassword;
        }
        
        $data = $this->request('POST', '/otp/verify', null, $payload);
        
        if ($purpose === 'password_reset') {
            return [
                'success' => $data['success'] ?? true,
                'message' => $data['message'] ?? 'Password reset successfully! You can now login with your new password',
            ];
        }
        
        $session = $this->persistSession($data);
        
        return [
            'session' => $session,
            'user' => isset($data['user']) ? $this->normalizeUser($data['user']) : null,
        ];
    }

    /**
     * Send magic link to user's email.
     * 
     * @param string $email User's email address
     * @param string $purpose Purpose of magic link - 'login', 'signup', or 'email_verification' (default: 'login')
     * @return array Response with success status and message
     * @throws WOWSQLException If the request fails
     */
    public function sendMagicLink($email, $purpose = 'login')
    {
        if (!in_array($purpose, ['login', 'signup', 'email_verification'])) {
            throw new WOWSQLException("Purpose must be 'login', 'signup', or 'email_verification'");
        }
        
        $payload = [
            'email' => $email,
            'purpose' => $purpose,
        ];
        
        $data = $this->request('POST', '/magic-link/send', null, $payload);
        
        return [
            'success' => $data['success'] ?? true,
            'message' => $data['message'] ?? 'If that email exists, a magic link has been sent',
        ];
    }

    /**
     * Verify email using token.
     * 
     * @param string $token Verification token from email
     * @return array Response with success status, message, and user info
     * @throws WOWSQLException If the request fails
     */
    public function verifyEmail($token)
    {
        $payload = ['token' => $token];
        $data = $this->request('POST', '/verify-email', null, $payload);
        
        return [
            'success' => $data['success'] ?? true,
            'message' => $data['message'] ?? 'Email verified successfully!',
            'user' => isset($data['user']) ? $this->normalizeUser($data['user']) : null,
        ];
    }

    /**
     * Resend verification email.
     * 
     * @param string $email User's email address
     * @return array Response with success status and message
     * @throws WOWSQLException If the request fails
     */
    public function resendVerification($email)
    {
        $payload = ['email' => $email];
        $data = $this->request('POST', '/resend-verification', null, $payload);
        
        return [
            'success' => $data['success'] ?? true,
            'message' => $data['message'] ?? 'If that email exists, a verification email has been sent',
        ];
    }

    /**
     * Get current session tokens.
     * 
     * @return array Current access_token and refresh_token
     */
    public function getSession()
    {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
        ];
    }

    /**
     * Set session tokens.
     * 
     * @param string $accessToken Access token
     * @param string|null $refreshToken Optional refresh token
     */
    public function setSession($accessToken, $refreshToken = null)
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
    }

    /**
     * Clear session tokens.
     */
    public function clearSession()
    {
        $this->accessToken = null;
        $this->refreshToken = null;
    }

    /**
     * Build authentication base URL from project URL.
     * 
     * @param string $projectUrl Project subdomain or full URL
     * @return string Authentication base URL
     */
    private function buildAuthBaseUrl($projectUrl)
    {
        $normalized = trim($projectUrl);
        
        // If it's already a full URL, use it as-is
        if (strpos($normalized, 'http://') === 0 || strpos($normalized, 'https://') === 0) {
            $normalized = rtrim($normalized, '/');
            if (substr($normalized, -4) === '/api') {
                $normalized = substr($normalized, 0, -4);
            }
            return $normalized . '/api/auth';
        }
        
        // If it already contains the base domain, don't append it again
        if (strpos($normalized, '.wowsql.com') !== false || substr($normalized, -10) === 'wowsql.com') {
            $normalized = 'https://' . $normalized;
        } else {
            // Just a project slug, append domain
            $normalized = 'https://' . $normalized . '.wowsql.com';
        }
        
        $normalized = rtrim($normalized, '/');
        if (substr($normalized, -4) === '/api') {
            $normalized = substr($normalized, 0, -4);
        }
        
        return $normalized . '/api/auth';
    }

    /**
     * Normalize user data from API response.
     * 
     * @param array $user User data from API
     * @return array Normalized user data
     */
    private function normalizeUser($user)
    {
        return [
            'id' => $user['id'] ?? null,
            'email' => $user['email'] ?? null,
            'full_name' => $user['full_name'] ?? $user['fullName'] ?? null,
            'avatar_url' => $user['avatar_url'] ?? $user['avatarUrl'] ?? null,
            'email_verified' => (bool)($user['email_verified'] ?? $user['emailVerified'] ?? false),
            'user_metadata' => $user['user_metadata'] ?? $user['userMetadata'] ?? [],
            'app_metadata' => $user['app_metadata'] ?? $user['appMetadata'] ?? [],
            'created_at' => $user['created_at'] ?? $user['createdAt'] ?? null,
        ];
    }

    /**
     * Persist session tokens from API response.
     * 
     * @param array $data Response data from API
     * @return array Session data
     */
    private function persistSession($data)
    {
        $session = [
            'access_token' => $data['access_token'] ?? '',
            'refresh_token' => $data['refresh_token'] ?? '',
            'token_type' => $data['token_type'] ?? 'bearer',
            'expires_in' => $data['expires_in'] ?? 0,
        ];
        
        $this->accessToken = $session['access_token'];
        $this->refreshToken = $session['refresh_token'];
        
        return $session;
    }

    /**
     * Make HTTP request to API.
     * 
     * @param string $method HTTP method
     * @param string $path API path
     * @param array|null $params Query parameters
     * @param array|null $json Request body
     * @return array Response data
     * @throws WOWSQLException If the request fails
     */
    private function request($method, $path, $params = null, $json = null)
    {
        try {
            $options = [];
            if ($params !== null) {
                $options['query'] = $params;
            }
            if ($json !== null) {
                $options['json'] = $json;
            }
            
            $response = $this->httpClient->request($method, $path, $options);
            
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $payload = [];
                try {
                    $payload = json_decode($response->getBody()->getContents(), true);
                } catch (\Exception $e) {
                    // Ignore JSON decode errors
                }
                
                $message = $payload['detail'] ?? $payload['message'] ?? $payload['error'] ?? "Request failed with status {$statusCode}";
                throw new WOWSQLException($message, $statusCode, $payload);
            }
            
            $body = $response->getBody()->getContents();
            if (empty($body)) {
                return [];
            }
            
            return json_decode($body, true) ?: [];
        } catch (WOWSQLException $e) {
            throw $e;
        } catch (RequestException $e) {
            $message = "Request failed: " . $e->getMessage();
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            throw new WOWSQLException($message, $statusCode);
        } catch (\Exception $e) {
            throw new WOWSQLException("Request failed: " . $e->getMessage());
        }
    }
}

