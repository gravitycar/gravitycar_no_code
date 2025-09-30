<?php

namespace Gravitycar\Api;

use Gravitycar\Core\Config;
use Gravitycar\Services\AuthenticationService;
use Gravitycar\Services\GoogleOAuthService;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Exceptions\BadRequestException;
use Gravitycar\Exceptions\UnauthorizedException;
use Gravitycar\Exceptions\InternalServerErrorException;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;
use Exception;

/**
 * Authentication Controller
 * Handles Google OAuth and traditional authentication endpoints
 * Pure dependency injection - all dependencies explicitly injected via constructor.
 */
class AuthController extends ApiControllerBase
{
    private ?AuthenticationService $authService;
    private ?GoogleOAuthService $googleOAuthService;

    protected array $rolesAndActions = [
        // Full permissions by default - controllers can override this property
        'admin' => ['list', 'delete', 'create', 'update', 'read', 'logout', 'me'],
        'manager' => ['list', 'delete', 'create', 'update', 'read', 'logout', 'me'],
        'user' => ['list', 'delete', 'create', 'update', 'read', 'logout', 'me'],
        'guest' => ['read', 'create', 'update', 'list', 'delete']
        ];
    
    /**
     * Pure dependency injection constructor - all dependencies explicitly provided
     * For backwards compatibility during route discovery, all parameters are optional with null defaults
     * 
     * @param Logger $logger
     * @param ModelFactory $modelFactory
     * @param DatabaseConnectorInterface $databaseConnector
     * @param MetadataEngineInterface $metadataEngine
     * @param Config $config
     * @param CurrentUserProviderInterface $currentUserProvider
     * @param AuthenticationService $authService
     * @param GoogleOAuthService $googleOAuthService
     */
    public function __construct(
        Logger $logger = null,
        ModelFactory $modelFactory = null,
        DatabaseConnectorInterface $databaseConnector = null,
        MetadataEngineInterface $metadataEngine = null,
        Config $config = null,
        CurrentUserProviderInterface $currentUserProvider = null,
        AuthenticationService $authService = null,
        GoogleOAuthService $googleOAuthService = null
    ) {
        // All dependencies explicitly injected - no ServiceLocator fallbacks
        parent::__construct($logger, $modelFactory, $databaseConnector, $metadataEngine, $config, $currentUserProvider);
        $this->authService = $authService;
        $this->googleOAuthService = $googleOAuthService;
    }
    
    /**
     * Get authentication service
     */
    protected function getAuthService(): ?AuthenticationService {
        return $this->authService;
    }
    
    /**
     * Get Google OAuth service
     */
    protected function getGoogleOAuthService(): ?GoogleOAuthService {
        return $this->googleOAuthService;
    }

    /**
     * Register routes for APIRouteRegistry compatibility
     */
    public function registerRoutes(): array
    {
        return [
            [
                // Public endpoint
                'method' => 'GET',
                'path' => '/auth/google/url',
                'apiClass' => self::class,
                'apiMethod' => 'getGoogleAuthUrl',
                'parameterNames' => [],
            ],
            [
                 // Public endpoint
                'method' => 'POST',
                'path' => '/auth/google',
                'apiClass' => self::class,
                'apiMethod' => 'authenticateWithGoogle',
                'parameterNames' => [],
            ],
            [
                // Public endpoint
                'method' => 'POST',
                'path' => '/auth/login',
                'apiClass' => self::class,
                'apiMethod' => 'authenticateTraditional',
                'parameterNames' => [],
            ],
            [
                 // Public endpoint (but requires valid refresh token)
                'method' => 'POST',
                'path' => '/auth/refresh',
                'apiClass' => self::class,
                'apiMethod' => 'refreshToken',
                'parameterNames' => [],
            ],
            [
                // Requires authentication
                'method' => 'POST',
                'path' => '/auth/logout',
                'apiClass' => self::class,
                'apiMethod' => 'logout',
                'parameterNames' => [],
                'rbacAction' => 'logout',
            ],
            [
                // JWT authentication only, no specific roles required
                'method' => 'GET',
                'path' => '/auth/me',
                'apiClass' => self::class,
                'apiMethod' => 'getMe',
                'parameterNames' => [],
                'rbacAction' => 'me',
            ],
            [
                 // Public endpoint
                'method' => 'POST',
                'path' => '/auth/register',
                'apiClass' => self::class,
                'apiMethod' => 'register',
                'parameterNames' => [],
            ]
        ];
    }


    /**
     * GET /auth/google/url - Get Google OAuth authorization URL
     */
    public function getGoogleAuthUrl(Request $request): array
    {
        try {
            $state = bin2hex(random_bytes(16)); // Generate random state
            $authUrl = $this->getGoogleOAuthService()->getAuthorizationUrl(['state' => $state]);

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
            $requestData = $request->getRequestData();

            if (empty($requestData['google_token'])) {
                throw new BadRequestException('Google token is required');
            }

            $googleToken = $requestData['google_token'];
            $state = $requestData['state'] ?? '';

            // Authenticate with Google OAuth
            $authResult = $this->getAuthService()->authenticateWithGoogle($googleToken);

            if (!$authResult || !isset($authResult['user']) || !$authResult['user']) {
                throw new UnauthorizedException('Google authentication failed');
            }

            $user = $authResult['user'];

            $this->logger->info('Google OAuth authentication successful', [
                'user_id' => $user->get('id'),
                'email' => $user->get('email')
            ]);

            // Determine if this is a new user
            $isNewUser = $user->get('created_at') === $user->get('updated_at');
            $statusCode = $isNewUser ? 201 : 200;

            $response = [
                'success' => true,
                'status' => $statusCode,
                'data' => [
                    'user' => $authResult['user_data'],
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
            $requestData = $request->getRequestData();

            // Accept either 'username' or 'email' as the username field
            $username = $requestData['username'] ?? $requestData['email'] ?? '';
            $password = $requestData['password'] ?? '';

            if (empty($username) || empty($password)) {
                throw new GCException('Username/email and password are required');
            }

            // Authenticate with username/password
            $authResult = $this->getAuthService()->authenticateTraditional($username, $password);

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
                'user_id' => $authResult['user']['id'],
                'username' => $username
            ]);

            return [
                'success' => true,
                'status' => 200,
                'data' => [
                    'user' => $authResult['user'],
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
            $requestData = $request->getRequestData();

            if (empty($requestData['refresh_token'])) {
                throw new GCException('Refresh token is required');
            }

            $refreshToken = $requestData['refresh_token'];

            // Refresh the JWT token
            $authResult = $this->getAuthService()->refreshJwtToken($refreshToken);

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
            $currentUser = $this->getCurrentUser();

            if (!$currentUser) {
                throw new GCException('User not authenticated');
            }

            // Logout the user (invalidate refresh tokens)
            $this->getAuthService()->logout($currentUser);

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
            $requestData = $request->getRequestData();

            $requiredFields = ['username', 'email', 'password', 'first_name', 'last_name'];
            foreach ($requiredFields as $field) {
                if (empty($requestData[$field])) {
                    throw new GCException("Field '{$field}' is required");
                }
            }

            // Register new user
            $user = $this->getAuthService()->registerUser($requestData);

            // Generate JWT tokens for the new user
            $authResult = $this->getAuthService()->generateTokensForUser($user);

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
    /**
     * Get current authenticated user information
     * 
     * @param Request $request
     * @return array
     * @throws GCException
     */
    public function getMe(Request $request): array
    {
        try {
            // Get the JWT token from the Authorization header
            // Check multiple possible sources for the Authorization header
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? 
                         $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 
                         $this->config->getEnv('HTTP_AUTHORIZATION', '') ?? 
                         (function_exists('getallheaders') ? (getallheaders()['Authorization'] ?? '') : '') ?? 
                         '';
            
            if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
                throw new UnauthorizedException('No authorization token provided', [
                    'code' => 'NO_TOKEN',
                    'auth_header_present' => !empty($authHeader),
                    'has_bearer_prefix' => !empty($authHeader) && str_starts_with($authHeader, 'Bearer ')
                ]);
            }
            
            $token = substr($authHeader, 7); // Remove "Bearer " prefix
            
            // Validate and decode the JWT token
            $user = $this->getAuthService()->validateJwtToken($token);
            
            if (!$user) {
                throw new UnauthorizedException('Invalid or expired token', [
                    'code' => 'INVALID_TOKEN',
                    'token_length' => strlen($token)
                ]);
            }
            
            // Format user data (same format as login response)
            $userData = [
                'id' => $user->get('id'),
                'email' => $user->get('email'),
                'username' => $user->get('username'),
                'first_name' => $user->get('first_name'),
                'last_name' => $user->get('last_name'),
                'auth_provider' => $user->get('auth_provider'),
                'last_login_method' => $user->get('last_login_method'),
                'profile_picture_url' => $user->get('profile_picture_url'),
                'is_active' => $user->get('is_active')
            ];
            
            return $userData;
            
        } catch (UnauthorizedException $e) {
            // Re-throw UnauthorizedException as-is
            throw $e;
        } catch (GCException $e) {
            $this->logger->warning('Get current user failed', [
                'error' => $e->getMessage()
            ]);

            throw new InternalServerErrorException('Authentication service error', [
                'code' => 'AUTH_SERVICE_ERROR',
                'original_error' => $e->getMessage()
            ], $e);
        } catch (Exception $e) {
            $this->logger->error('Unexpected error in getMe', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new InternalServerErrorException('Unexpected error during authentication', [
                'code' => 'INTERNAL_ERROR',
                'original_error' => $e->getMessage()
            ], $e);
        }
    }

    /**
     * Get request data from JSON or form input
     * 
     * @return array
     * @throws GCException
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
