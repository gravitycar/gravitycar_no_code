<?php

namespace Gravitycar\Api;

use Gravitycar\Core\ServiceLocator;
use Gravitycar\Services\AuthenticationService;
use Gravitycar\Services\GoogleOAuthService;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * Authentication Controller
 * Handles Google OAuth and traditional authentication endpoints
 */
class AuthController
{
    private Logger $logger;
    private AuthenticationService $authService;
    private GoogleOAuthService $googleOAuthService;

    public function __construct()
    {
        $this->logger = ServiceLocator::getLogger();
        $this->authService = ServiceLocator::get(AuthenticationService::class);
        $this->googleOAuthService = ServiceLocator::get(GoogleOAuthService::class);
    }

    /**
     * Get routes for this controller
     */
    public function getRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/auth/google/url',
                'apiClass' => self::class,
                'apiMethod' => 'getGoogleAuthUrl',
                'parameterNames' => [],
                'allowedRoles' => ['all'] // Public endpoint
            ],
            [
                'method' => 'POST',
                'path' => '/auth/google',
                'apiClass' => self::class,
                'apiMethod' => 'authenticateWithGoogle',
                'parameterNames' => [],
                'allowedRoles' => ['all'] // Public endpoint
            ],
            [
                'method' => 'POST',
                'path' => '/auth/login',
                'apiClass' => self::class,
                'apiMethod' => 'authenticateTraditional',
                'parameterNames' => [],
                'allowedRoles' => ['all'] // Public endpoint
            ],
            [
                'method' => 'POST',
                'path' => '/auth/refresh',
                'apiClass' => self::class,
                'apiMethod' => 'refreshToken',
                'parameterNames' => [],
                'allowedRoles' => ['all'] // Public endpoint (but requires valid refresh token)
            ],
            [
                'method' => 'POST',
                'path' => '/auth/logout',
                'apiClass' => self::class,
                'apiMethod' => 'logout',
                'parameterNames' => [],
                'allowedRoles' => ['user'] // Requires authentication
            ],
            [
                'method' => 'POST',
                'path' => '/auth/register',
                'apiClass' => self::class,
                'apiMethod' => 'register',
                'parameterNames' => [],
                'allowedRoles' => ['all'] // Public endpoint
            ]
        ];
    }

    /**
     * Register routes for APIRouteRegistry compatibility
     */
    public function registerRoutes(): array
    {
        return $this->getRoutes();
    }

    /**
     * GET /auth/google/url - Get Google OAuth authorization URL
     */
    public function getGoogleAuthUrl(Request $request): array
    {
        try {
            $state = bin2hex(random_bytes(16)); // Generate random state
            $authUrl = $this->googleOAuthService->getAuthorizationUrl(['state' => $state]);

            $this->logger->info('Google OAuth authorization URL generated', [
                'state' => $state
            ]);

            return [
                'success' => true,
                'status' => 200,
                'data' => [
                    'authorization_url' => $authUrl,
                    'state' => $state
                ],
                'timestamp' => date('c')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate Google OAuth URL', [
                'error' => $e->getMessage()
            ]);

            throw new GCException('Failed to generate Google authorization URL', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /auth/google - Authenticate with Google OAuth token
     */
    public function authenticateWithGoogle(Request $request): array
    {
        try {
            $requestData = $this->getRequestData();

            if (empty($requestData['google_token'])) {
                throw new GCException('Google token is required');
            }

            $googleToken = $requestData['google_token'];
            $state = $requestData['state'] ?? '';

            // Authenticate with Google OAuth
            $authResult = $this->authService->authenticateWithGoogle($googleToken);

            $this->logger->info('Google OAuth authentication successful', [
                'user_id' => $authResult['user']->get('id'),
                'email' => $authResult['user']->get('email')
            ]);

            // Determine if this is a new user
            $isNewUser = $authResult['user']->get('created_at') === $authResult['user']->get('updated_at');
            $statusCode = $isNewUser ? 201 : 200;

            $response = [
                'success' => true,
                'status' => $statusCode,
                'data' => [
                    'user' => [
                        'id' => $authResult['user']->get('id'),
                        'email' => $authResult['user']->get('email'),
                        'first_name' => $authResult['user']->get('first_name'),
                        'last_name' => $authResult['user']->get('last_name'),
                        'auth_provider' => $authResult['user']->get('auth_provider'),
                        'google_id' => $authResult['user']->get('google_id'),
                        'profile_picture_url' => $authResult['user']->get('profile_picture_url'),
                        'is_active' => $authResult['user']->get('is_active')
                    ],
                    'access_token' => $authResult['access_token'],
                    'refresh_token' => $authResult['refresh_token'],
                    'expires_in' => $authResult['expires_in'],
                    'token_type' => 'Bearer'
                ],
                'timestamp' => date('c')
            ];

            if ($isNewUser) {
                $response['data']['user']['created_from_oauth'] = true;
            }

            return $response;

        } catch (GCException $e) {
            $this->logger->warning('Google OAuth authentication failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'status' => 401,
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => 'GOOGLE_AUTH_FAILED'
                ],
                'timestamp' => date('c')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Google OAuth authentication error', [
                'error' => $e->getMessage()
            ]);

            throw new GCException('Authentication service error', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /auth/login - Traditional username/password authentication
     */
    public function authenticateTraditional(Request $request): array
    {
        try {
            $requestData = $this->getRequestData();

            if (empty($requestData['username']) || empty($requestData['password'])) {
                throw new GCException('Username and password are required');
            }

            $username = $requestData['username'];
            $password = $requestData['password'];

            // Authenticate with username/password
            $authResult = $this->authService->authenticateTraditional($username, $password);

            if (!$authResult) {
                return [
                    'success' => false,
                    'status' => 401,
                    'error' => [
                        'message' => 'Invalid username or password',
                        'code' => 'INVALID_CREDENTIALS'
                    ],
                    'timestamp' => date('c')
                ];
            }

            $this->logger->info('Traditional authentication successful', [
                'user_id' => $authResult['user']->get('id'),
                'username' => $username
            ]);

            return [
                'success' => true,
                'status' => 200,
                'data' => [
                    'user' => [
                        'id' => $authResult['user']->get('id'),
                        'username' => $authResult['user']->get('username'),
                        'email' => $authResult['user']->get('email'),
                        'first_name' => $authResult['user']->get('first_name'),
                        'last_name' => $authResult['user']->get('last_name'),
                        'auth_provider' => $authResult['user']->get('auth_provider'),
                        'is_active' => $authResult['user']->get('is_active')
                    ],
                    'access_token' => $authResult['access_token'],
                    'refresh_token' => $authResult['refresh_token'],
                    'expires_in' => $authResult['expires_in'],
                    'token_type' => 'Bearer'
                ],
                'timestamp' => date('c')
            ];

        } catch (GCException $e) {
            $this->logger->warning('Traditional authentication failed', [
                'username' => $requestData['username'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'status' => 401,
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => 'AUTH_FAILED'
                ],
                'timestamp' => date('c')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Traditional authentication error', [
                'error' => $e->getMessage()
            ]);

            throw new GCException('Authentication service error', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /auth/refresh - Refresh JWT access token
     */
    public function refreshToken(Request $request): array
    {
        try {
            $requestData = $this->getRequestData();

            if (empty($requestData['refresh_token'])) {
                throw new GCException('Refresh token is required');
            }

            $refreshToken = $requestData['refresh_token'];

            // Refresh the JWT token
            $authResult = $this->authService->refreshJwtToken($refreshToken);

            $this->logger->info('JWT token refreshed successfully', [
                'user_id' => $authResult['user']->get('id')
            ]);

            return [
                'success' => true,
                'status' => 200,
                'data' => [
                    'access_token' => $authResult['access_token'],
                    'refresh_token' => $authResult['refresh_token'],
                    'expires_in' => $authResult['expires_in'],
                    'token_type' => 'Bearer'
                ],
                'timestamp' => date('c')
            ];

        } catch (GCException $e) {
            $this->logger->warning('Token refresh failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'status' => 401,
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => 'TOKEN_REFRESH_FAILED'
                ],
                'timestamp' => date('c')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Token refresh error', [
                'error' => $e->getMessage()
            ]);

            throw new GCException('Token refresh service error', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /auth/logout - Logout and invalidate tokens
     */
    public function logout(Request $request): array
    {
        try {
            // Get current user from context (set by authorization middleware)
            $currentUser = ServiceLocator::getCurrentUser();

            if (!$currentUser) {
                throw new GCException('User not authenticated');
            }

            // Logout the user (invalidate refresh tokens)
            $this->authService->logout($currentUser);

            $this->logger->info('User logged out successfully', [
                'user_id' => $currentUser->get('id')
            ]);

            return [
                'success' => true,
                'status' => 200,
                'data' => [
                    'message' => 'Logged out successfully'
                ],
                'timestamp' => date('c')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Logout error', [
                'error' => $e->getMessage()
            ]);

            throw new GCException('Logout service error', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /auth/register - Register new user account
     */
    public function register(Request $request): array
    {
        try {
            $requestData = $this->getRequestData();

            $requiredFields = ['username', 'email', 'password', 'first_name', 'last_name'];
            foreach ($requiredFields as $field) {
                if (empty($requestData[$field])) {
                    throw new GCException("Field '{$field}' is required");
                }
            }

            // Register new user
            $user = $this->authService->registerUser($requestData);

            // Generate JWT tokens for the new user
            $authResult = $this->authService->generateTokensForUser($user);

            $this->logger->info('User registered successfully', [
                'user_id' => $user->get('id'),
                'username' => $user->get('username'),
                'email' => $user->get('email')
            ]);

            return [
                'success' => true,
                'status' => 201,
                'data' => [
                    'user' => [
                        'id' => $user->get('id'),
                        'username' => $user->get('username'),
                        'email' => $user->get('email'),
                        'first_name' => $user->get('first_name'),
                        'last_name' => $user->get('last_name'),
                        'auth_provider' => $user->get('auth_provider'),
                        'is_active' => $user->get('is_active')
                    ],
                    'access_token' => $authResult['access_token'],
                    'refresh_token' => $authResult['refresh_token'],
                    'expires_in' => $authResult['expires_in'],
                    'token_type' => 'Bearer'
                ],
                'timestamp' => date('c')
            ];

        } catch (GCException $e) {
            $this->logger->warning('User registration failed', [
                'username' => $requestData['username'] ?? 'unknown',
                'email' => $requestData['email'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'status' => 400,
                'error' => [
                    'message' => $e->getMessage(),
                    'code' => 'REGISTRATION_FAILED'
                ],
                'timestamp' => date('c')
            ];

        } catch (\Exception $e) {
            $this->logger->error('User registration error', [
                'error' => $e->getMessage()
            ]);

            throw new GCException('Registration service error', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get request data from POST body or input stream
     */
    private function getRequestData(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            // Handle JSON input
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new GCException('Invalid JSON in request body');
            }
            
            return $data ?? [];
        } else {
            // Handle form data
            return $_POST ?? [];
        }
    }
}
