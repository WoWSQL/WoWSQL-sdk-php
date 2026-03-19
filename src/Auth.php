<?php

namespace WOWSQL;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;

// ── Models ───────────────────────────────────────────────────────

/**
 * Represents an authenticated user.
 */
class AuthUser
{
    public $id;
    public $email;
    public $fullName;
    public $avatarUrl;
    public $emailVerified;
    public $userMetadata;
    public $appMetadata;
    public $createdAt;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->fullName = $data['full_name'] ?? null;
        $this->avatarUrl = $data['avatar_url'] ?? null;
        $this->emailVerified = (bool)($data['email_verified'] ?? false);
        $this->userMetadata = $data['user_metadata'] ?? [];
        $this->appMetadata = $data['app_metadata'] ?? [];
        $this->createdAt = $data['created_at'] ?? null;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'full_name' => $this->fullName,
            'avatar_url' => $this->avatarUrl,
            'email_verified' => $this->emailVerified,
            'user_metadata' => $this->userMetadata,
            'app_metadata' => $this->appMetadata,
            'created_at' => $this->createdAt,
        ];
    }
}

/**
 * Session tokens returned by the auth service.
 */
class AuthSession
{
    public $accessToken;
    public $refreshToken;
    public $tokenType;
    public $expiresIn;

    public function __construct(array $data = [])
    {
        $this->accessToken = $data['access_token'] ?? '';
        $this->refreshToken = $data['refresh_token'] ?? '';
        $this->tokenType = $data['token_type'] ?? 'bearer';
        $this->expiresIn = $data['expires_in'] ?? 0;
    }

    public function toArray()
    {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'token_type' => $this->tokenType,
            'expires_in' => $this->expiresIn,
        ];
    }
}

/**
 * Response for signup/login requests.
 */
class AuthResponse
{
    /** @var AuthSession */
    public $session;
    /** @var AuthUser|null */
    public $user;

    public function __construct(AuthSession $session, AuthUser $user = null)
    {
        $this->session = $session;
        $this->user = $user;
    }

    public function toArray()
    {
        return [
            'session' => $this->session->toArray(),
            'user' => $this->user ? $this->user->toArray() : null,
        ];
    }
}

// ── Token Storage ────────────────────────────────────────────────

/**
 * Interface for persisting tokens.
 */
interface TokenStorage
{
    public function getAccessToken();
    public function setAccessToken($token);
    public function getRefreshToken();
    public function setRefreshToken($token);
}

/**
 * Default in-memory token storage.
 */
class MemoryTokenStorage implements TokenStorage
{
    private $access;
    private $refresh;

    public function getAccessToken()
    {
        return $this->access;
    }

    public function setAccessToken($token)
    {
        $this->access = $token;
    }

    public function getRefreshToken()
    {
        return $this->refresh;
    }

    public function setRefreshToken($token)
    {
        $this->refresh = $token;
    }
}

// ── Auth Client ──────────────────────────────────────────────────

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
    /** @var TokenStorage */
    private $storage;

    /**
     * Initialize ProjectAuthClient for AUTHENTICATION OPERATIONS.
     *
     * @param string            $projectUrl   Project subdomain or full URL
     * @param string            $apiKey       Unified API key
     * @param string            $baseDomain   Base domain (default: wowsql.com)
     * @param bool              $secure       Use HTTPS (default: true)
     * @param int               $timeout      Request timeout in seconds (default: 30)
     * @param bool              $verifySsl    Verify SSL certificates (default: true)
     * @param TokenStorage|null $tokenStorage Optional token storage implementation
     */
    public function __construct(
        $projectUrl,
        $apiKey,
        $baseDomain = 'wowsql.com',
        $secure = true,
        $timeout = 30,
        $verifySsl = true,
        TokenStorage $tokenStorage = null
    ) {
        $this->apiKey = $apiKey;
        $this->timeout = $timeout;
        $this->storage = $tokenStorage ?: new MemoryTokenStorage();
        $this->baseUrl = self::buildAuthBaseUrl($projectUrl, $baseDomain, $secure);

        $this->accessToken = $this->storage->getAccessToken();
        $this->refreshToken = $this->storage->getRefreshToken();

        $this->httpClient = new HttpClient([
            'base_uri' => $this->baseUrl,
            'timeout' => $timeout,
            'verify' => $verifySsl,
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    // ── Public API ───────────────────────────────────────────────

    /**
     * Sign up a new user.
     *
     * @param  string     $email
     * @param  string     $password
     * @param  string|null $fullName
     * @param  array|null $userMetadata
     * @return AuthResponse
     * @throws WOWSQLException
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
        $user = isset($data['user']) ? new AuthUser(self::normalizeUser($data['user'])) : null;
        return new AuthResponse($session, $user);
    }

    /**
     * Sign in an existing user.
     *
     * @param  string $email
     * @param  string $password
     * @return AuthResponse
     * @throws WOWSQLException
     */
    public function signIn($email, $password)
    {
        $payload = [
            'email' => $email,
            'password' => $password,
        ];
        $data = $this->request('POST', '/login', null, $payload);
        $session = $this->persistSession($data);
        return new AuthResponse($session);
    }

    /**
     * Get the current authenticated user.
     *
     * @param  string|null $accessToken Override access token
     * @return AuthUser
     * @throws WOWSQLException
     */
    public function getUser($accessToken = null)
    {
        $token = $accessToken ?: $this->accessToken ?: $this->storage->getAccessToken();
        if (!$token) {
            throw new WOWSQLException('Access token is required. Call signIn first.');
        }

        $data = $this->request('GET', '/me', null, null, ['Authorization' => "Bearer {$token}"]);
        return new AuthUser(self::normalizeUser($data));
    }

    /**
     * Get OAuth authorization URL.
     *
     * @param  string      $provider    OAuth provider name
     * @param  string|null $redirectUri Optional frontend redirect URI
     * @return array
     * @throws WOWSQLException
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
     * @param  string      $provider
     * @param  string      $code
     * @param  string|null $redirectUri
     * @return AuthResponse
     * @throws WOWSQLException
     */
    public function exchangeOAuthCallback($provider, $code, $redirectUri = null)
    {
        $payload = ['code' => $code];
        if ($redirectUri !== null) {
            $payload['redirect_uri'] = $redirectUri;
        }

        $data = $this->request('POST', "/oauth/{$provider}/callback", null, $payload);
        $session = $this->persistSession($data);
        $user = isset($data['user']) ? new AuthUser(self::normalizeUser($data['user'])) : null;
        return new AuthResponse($session, $user);
    }

    /**
     * Request password reset.
     *
     * @param  string $email
     * @return array
     * @throws WOWSQLException
     */
    public function forgotPassword($email)
    {
        $data = $this->request('POST', '/forgot-password', null, ['email' => $email]);
        return [
            'success' => $data['success'] ?? true,
            'message' => $data['message'] ?? 'If that email exists, a password reset link has been sent',
        ];
    }

    /**
     * Reset password with token.
     *
     * @param  string $token
     * @param  string $newPassword
     * @return array
     * @throws WOWSQLException
     */
    public function resetPassword($token, $newPassword)
    {
        $data = $this->request('POST', '/reset-password', null, [
            'token' => $token,
            'new_password' => $newPassword,
        ]);
        return [
            'success' => $data['success'] ?? true,
            'message' => $data['message'] ?? 'Password reset successfully! You can now login with your new password',
        ];
    }

    /**
     * Send OTP code to user's email.
     *
     * @param  string $email
     * @param  string $purpose 'login', 'signup', or 'password_reset'
     * @return array
     * @throws WOWSQLException
     */
    public function sendOtp($email, $purpose = 'login')
    {
        if (!in_array($purpose, ['login', 'signup', 'password_reset'])) {
            throw new WOWSQLException("Purpose must be 'login', 'signup', or 'password_reset'");
        }

        $data = $this->request('POST', '/otp/send', null, [
            'email' => $email,
            'purpose' => $purpose,
        ]);
        return [
            'success' => $data['success'] ?? true,
            'message' => $data['message'] ?? 'If that email exists, an OTP code has been sent',
        ];
    }

    /**
     * Verify OTP and complete authentication.
     *
     * @param  string      $email
     * @param  string      $otp
     * @param  string      $purpose
     * @param  string|null $newPassword Required for password_reset
     * @return AuthResponse|array
     * @throws WOWSQLException
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
        $user = isset($data['user']) ? new AuthUser(self::normalizeUser($data['user'])) : null;
        return new AuthResponse($session, $user);
    }

    /**
     * Send magic link to user's email.
     *
     * @param  string $email
     * @param  string $purpose 'login', 'signup', or 'email_verification'
     * @return array
     * @throws WOWSQLException
     */
    public function sendMagicLink($email, $purpose = 'login')
    {
        if (!in_array($purpose, ['login', 'signup', 'email_verification'])) {
            throw new WOWSQLException("Purpose must be 'login', 'signup', or 'email_verification'");
        }

        $data = $this->request('POST', '/magic-link/send', null, [
            'email' => $email,
            'purpose' => $purpose,
        ]);
        return [
            'success' => $data['success'] ?? true,
            'message' => $data['message'] ?? 'If that email exists, a magic link has been sent',
        ];
    }

    /**
     * Verify email using token.
     *
     * @param  string $token Verification token from email
     * @return array
     * @throws WOWSQLException
     */
    public function verifyEmail($token)
    {
        $data = $this->request('POST', '/verify-email', null, ['token' => $token]);
        return [
            'success' => $data['success'] ?? true,
            'message' => $data['message'] ?? 'Email verified successfully!',
            'user' => isset($data['user']) ? new AuthUser(self::normalizeUser($data['user'])) : null,
        ];
    }

    /**
     * Resend verification email.
     *
     * @param  string $email
     * @return array
     * @throws WOWSQLException
     */
    public function resendVerification($email)
    {
        $data = $this->request('POST', '/resend-verification', null, ['email' => $email]);
        return [
            'success' => $data['success'] ?? true,
            'message' => $data['message'] ?? 'If that email exists, a verification email has been sent',
        ];
    }

    /**
     * Logout the current user by invalidating their session.
     *
     * @param  string|null $accessToken Override access token
     * @return array
     * @throws WOWSQLException
     */
    public function logout($accessToken = null)
    {
        $token = $accessToken ?: $this->accessToken ?: $this->storage->getAccessToken();
        if (!$token) {
            throw new WOWSQLException('Access token is required. Call signIn first.');
        }

        $data = $this->request('POST', '/logout', null, null, ['Authorization' => "Bearer {$token}"]);
        $this->clearSession();
        return $data;
    }

    /**
     * Exchange a refresh token for new access + refresh tokens.
     *
     * @param  string|null $refreshToken Override refresh token
     * @return AuthResponse
     * @throws WOWSQLException
     */
    public function refreshToken($refreshToken = null)
    {
        $token = $refreshToken ?: $this->refreshToken ?: $this->storage->getRefreshToken();
        if (!$token) {
            throw new WOWSQLException('Refresh token is required. Call signIn first.');
        }

        $data = $this->request('POST', '/refresh-token', null, ['refresh_token' => $token]);
        $session = $this->persistSession($data);
        return new AuthResponse($session);
    }

    /**
     * Change the authenticated user's password.
     *
     * @param  string      $currentPassword
     * @param  string      $newPassword
     * @param  string|null $accessToken Override access token
     * @return array
     * @throws WOWSQLException
     */
    public function changePassword($currentPassword, $newPassword, $accessToken = null)
    {
        $token = $accessToken ?: $this->accessToken ?: $this->storage->getAccessToken();
        if (!$token) {
            throw new WOWSQLException('Access token is required. Call signIn first.');
        }

        return $this->request('POST', '/change-password', null, [
            'current_password' => $currentPassword,
            'new_password' => $newPassword,
        ], ['Authorization' => "Bearer {$token}"]);
    }

    /**
     * Update the authenticated user's profile.
     *
     * @param  string|null $fullName
     * @param  string|null $avatarUrl
     * @param  string|null $username
     * @param  array|null  $userMetadata
     * @param  string|null $accessToken Override access token
     * @return AuthUser
     * @throws WOWSQLException
     */
    public function updateUser(
        $fullName = null,
        $avatarUrl = null,
        $username = null,
        $userMetadata = null,
        $accessToken = null
    ) {
        $token = $accessToken ?: $this->accessToken ?: $this->storage->getAccessToken();
        if (!$token) {
            throw new WOWSQLException('Access token is required. Call signIn first.');
        }

        $payload = [];
        if ($fullName !== null) {
            $payload['full_name'] = $fullName;
        }
        if ($avatarUrl !== null) {
            $payload['avatar_url'] = $avatarUrl;
        }
        if ($username !== null) {
            $payload['username'] = $username;
        }
        if ($userMetadata !== null) {
            $payload['user_metadata'] = $userMetadata;
        }

        if (empty($payload)) {
            throw new WOWSQLException('At least one field to update is required');
        }

        $data = $this->request('PATCH', '/me', null, $payload, ['Authorization' => "Bearer {$token}"]);
        return new AuthUser(self::normalizeUser($data));
    }

    // ── Session management ───────────────────────────────────────

    /**
     * Get current session tokens.
     *
     * @return array
     */
    public function getSession()
    {
        return [
            'access_token' => $this->accessToken ?: $this->storage->getAccessToken(),
            'refresh_token' => $this->refreshToken ?: $this->storage->getRefreshToken(),
        ];
    }

    /**
     * Set session tokens.
     *
     * @param string      $accessToken
     * @param string|null $refreshToken
     */
    public function setSession($accessToken, $refreshToken = null)
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->storage->setAccessToken($accessToken);
        $this->storage->setRefreshToken($refreshToken);
    }

    /**
     * Clear session tokens.
     */
    public function clearSession()
    {
        $this->accessToken = null;
        $this->refreshToken = null;
        $this->storage->setAccessToken(null);
        $this->storage->setRefreshToken(null);
    }

    /**
     * Close the HTTP client.
     */
    public function close()
    {
        // Guzzle does not need explicit close.
    }

    // ── Internal helpers ─────────────────────────────────────────

    private static function buildAuthBaseUrl($projectUrl, $baseDomain, $secure)
    {
        $normalized = trim($projectUrl);

        if (strpos($normalized, 'http://') === 0 || strpos($normalized, 'https://') === 0) {
            $normalized = rtrim($normalized, '/');
            if (substr($normalized, -4) === '/api') {
                $normalized = substr($normalized, 0, -4);
            }
            return $normalized . '/api/auth';
        }

        $protocol = $secure ? 'https' : 'http';
        if (strpos($normalized, ".{$baseDomain}") !== false || substr($normalized, -strlen($baseDomain)) === $baseDomain) {
            $normalized = "{$protocol}://{$normalized}";
        } else {
            $normalized = "{$protocol}://{$normalized}.{$baseDomain}";
        }

        $normalized = rtrim($normalized, '/');
        if (substr($normalized, -4) === '/api') {
            $normalized = substr($normalized, 0, -4);
        }

        return $normalized . '/api/auth';
    }

    private static function normalizeUser($user)
    {
        if (!$user) {
            return [
                'id' => '',
                'email' => '',
                'full_name' => null,
                'avatar_url' => null,
                'email_verified' => false,
                'user_metadata' => [],
                'app_metadata' => [],
                'created_at' => null,
            ];
        }
        return [
            'id' => $user['id'] ?? '',
            'email' => $user['email'] ?? '',
            'full_name' => $user['full_name'] ?? $user['fullName'] ?? null,
            'avatar_url' => $user['avatar_url'] ?? $user['avatarUrl'] ?? null,
            'email_verified' => (bool)($user['email_verified'] ?? $user['emailVerified'] ?? false),
            'user_metadata' => $user['user_metadata'] ?? $user['userMetadata'] ?? [],
            'app_metadata' => $user['app_metadata'] ?? $user['appMetadata'] ?? [],
            'created_at' => $user['created_at'] ?? $user['createdAt'] ?? null,
        ];
    }

    private function persistSession($data)
    {
        $session = new AuthSession([
            'access_token' => $data['access_token'] ?? '',
            'refresh_token' => $data['refresh_token'] ?? '',
            'token_type' => $data['token_type'] ?? 'bearer',
            'expires_in' => $data['expires_in'] ?? 0,
        ]);

        $this->accessToken = $session->accessToken;
        $this->refreshToken = $session->refreshToken;
        $this->storage->setAccessToken($session->accessToken);
        $this->storage->setRefreshToken($session->refreshToken);

        return $session;
    }

    /**
     * @param  string     $method
     * @param  string     $path
     * @param  array|null $params
     * @param  array|null $json
     * @param  array|null $extraHeaders
     * @return array
     * @throws WOWSQLException
     */
    private function request($method, $path, $params = null, $json = null, $extraHeaders = null)
    {
        try {
            $options = [];
            if ($params !== null) {
                $options['query'] = $params;
            }
            if ($json !== null) {
                $options['json'] = $json;
            }
            if ($extraHeaders !== null) {
                $options['headers'] = $extraHeaders;
            }

            $response = $this->httpClient->request($method, $path, $options);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $payload = [];
                try {
                    $payload = json_decode($response->getBody()->getContents(), true);
                } catch (\Exception $e) {
                    // ignore
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
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            $errorData = [];
            try {
                $errorData = json_decode($errorBody, true) ?: [];
            } catch (\Exception $ex) {
                // ignore
            }
            $message = $errorData['detail'] ?? $errorData['message'] ?? $errorData['error'] ?? $e->getMessage();
            throw new WOWSQLException($message, $statusCode, $errorData);
        } catch (\Exception $e) {
            throw new WOWSQLException("Request failed: " . $e->getMessage());
        }
    }
}
