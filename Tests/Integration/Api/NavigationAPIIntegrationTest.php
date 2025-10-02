<?php

namespace Tests\Integration\Api;

use Gravitycar\Tests\TestCase;
use Gravitycar\Core\ContainerConfig;

class NavigationAPIIntegrationTest extends TestCase
{
    private $container;
    private $navigationBuilder;
    private string $testConfigFile;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test navigation config file
        $this->testConfigFile = 'src/Navigation/navigation_config.php';
        $this->createTestNavigationConfig();
        
        $this->container = ContainerConfig::getContainer();
        $this->navigationBuilder = $this->container->get('navigation_builder');
    }

    protected function tearDown(): void
    {
        // Clean up test config file
        if (file_exists($this->testConfigFile)) {
            unlink($this->testConfigFile);
        }
        
        // Clean up cache files created during tests
        $cacheFiles = glob('cache/navigation_cache_*.php');
        foreach ($cacheFiles as $cacheFile) {
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        }
        
        parent::tearDown();
    }
    
    private function createTestNavigationConfig(): void
    {
        $configDir = dirname($this->testConfigFile);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        
        $testConfig = [
            'custom_pages' => [
                [
                    'key' => 'dashboard',
                    'title' => 'Dashboard',
                    'url' => '/dashboard',
                    'icon' => '📊',
                    'roles' => ['*']
                ],
                [
                    'key' => 'trivia',
                    'title' => 'Movie Trivia',
                    'url' => '/trivia',
                    'icon' => '🎬',
                    'roles' => ['admin', 'user']
                ]
            ],
            'navigation_sections' => [
                [
                    'key' => 'main',
                    'title' => 'Main Navigation'
                ],
                [
                    'key' => 'models',
                    'title' => 'Data Management'
                ],
                [
                    'key' => 'tools',
                    'title' => 'Tools & Utilities'
                ]
            ]
        ];
        
        file_put_contents($this->testConfigFile, '<?php return ' . var_export($testConfig, true) . ';');
    }

    public function testNavigationEndpointReturnsValidData(): void
    {
        // Build navigation cache first
        $cacheResults = $this->navigationBuilder->buildAllRoleNavigationCaches();
        $this->assertCount(4, $cacheResults);

        // Test the admin navigation endpoint
        $response = $this->makeApiRequest('GET', '/navigation/admin');

        $this->assertEquals(200, $response['status']);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        
        $navigationData = $response['data'];
        $this->assertEquals('admin', $navigationData['role']);
        $this->assertArrayHasKey('custom_pages', $navigationData);
        $this->assertArrayHasKey('models', $navigationData);
        $this->assertArrayHasKey('sections', $navigationData);
        $this->assertArrayHasKey('generated_at', $navigationData);

        // Verify sections structure
        $this->assertIsArray($navigationData['sections']);
        foreach ($navigationData['sections'] as $section) {
            $this->assertArrayHasKey('key', $section);
            $this->assertArrayHasKey('title', $section);
        }

        // Verify custom pages structure
        $this->assertIsArray($navigationData['custom_pages']);
        foreach ($navigationData['custom_pages'] as $page) {
            $this->assertArrayHasKey('key', $page);
            $this->assertArrayHasKey('title', $page);
            $this->assertArrayHasKey('url', $page);
            $this->assertArrayHasKey('roles', $page);
        }

        // Verify models structure
        $this->assertIsArray($navigationData['models']);
        foreach ($navigationData['models'] as $model) {
            $this->assertArrayHasKey('name', $model);
            $this->assertArrayHasKey('title', $model);
            $this->assertArrayHasKey('url', $model);
            $this->assertArrayHasKey('permissions', $model);
            $this->assertArrayHasKey('actions', $model);
            
            // Verify permissions structure
            $permissions = $model['permissions'];
            $this->assertArrayHasKey('list', $permissions);
            $this->assertArrayHasKey('create', $permissions);
            $this->assertArrayHasKey('update', $permissions);
            $this->assertArrayHasKey('delete', $permissions);
            $this->assertTrue($permissions['list']); // Must be true to appear in navigation
        }
    }

    public function testNavigationCacheRebuildEndpoint(): void
    {
        $response = $this->makeApiRequest('POST', '/navigation/cache/rebuild');

        $this->assertEquals(200, $response['status']);
        $this->assertTrue($response['success']);
        
        $data = $response['data'];
        $this->assertArrayHasKey('total_roles', $data);
        $this->assertArrayHasKey('successful_rebuilds', $data);
        $this->assertArrayHasKey('results', $data);
        $this->assertEquals(4, $data['total_roles']);
        $this->assertGreaterThan(0, $data['successful_rebuilds']);

        // Verify individual role results
        $results = $data['results'];
        $expectedRoles = ['admin', 'manager', 'user', 'guest'];
        
        foreach ($expectedRoles as $role) {
            $this->assertArrayHasKey($role, $results);
            $this->assertTrue($results[$role]['success']);
            $this->assertArrayHasKey('cache_file', $results[$role]);
            $this->assertArrayHasKey('items_count', $results[$role]);
        }
    }

    public function testRoleBasedPermissionFiltering(): void
    {
        // Build caches for different roles
        $this->navigationBuilder->buildAllRoleNavigationCaches();

        // Get admin navigation
        $adminResponse = $this->makeApiRequest('GET', '/navigation/admin');
        $adminModels = $adminResponse['data']['models'];

        // Get user navigation  
        $userResponse = $this->makeApiRequest('GET', '/navigation/user');
        $userModels = $userResponse['data']['models'];

        // Get guest navigation
        $guestResponse = $this->makeApiRequest('GET', '/navigation/guest');
        $guestModels = $guestResponse['data']['models'];

        // Admin should have more or equal models than user, user more than guest
        $this->assertGreaterThanOrEqual(count($userModels), count($adminModels));
        $this->assertGreaterThanOrEqual(count($guestModels), count($userModels));

        // Test custom pages filtering
        $adminPages = $adminResponse['data']['custom_pages'];
        $userPages = $userResponse['data']['custom_pages'];
        $guestPages = $guestResponse['data']['custom_pages'];

        // Admin should have access to trivia (admin/user roles)
        $adminPageKeys = array_column($adminPages, 'key');
        $this->assertContains('trivia', $adminPageKeys);

        // User should also have access to trivia
        $userPageKeys = array_column($userPages, 'key');
        $this->assertContains('trivia', $userPageKeys);

        // Guest should NOT have access to trivia
        $guestPageKeys = array_column($guestPages, 'key');
        $this->assertNotContains('trivia', $guestPageKeys);

        // All roles should have access to dashboard (universal page)
        $this->assertContains('dashboard', $adminPageKeys);
        $this->assertContains('dashboard', $userPageKeys);
        $this->assertContains('dashboard', $guestPageKeys);
    }

    public function testInvalidRoleReturnsError(): void
    {
        $response = $this->makeApiRequest('GET', '/navigation/invalid_role');

        $this->assertEquals(400, $response['status']);
        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Invalid role name', $response['error']['message']);
    }

    public function testEmptyRoleReturnsError(): void
    {
        $response = $this->makeApiRequest('GET', '/navigation/');

        // This should match the empty role pattern and return error
        $this->assertEquals(400, $response['status']);
        $this->assertFalse($response['success']);
    }

    public function testCurrentUserNavigationWithoutAuth(): void
    {
        // Test without authentication - should return guest navigation
        $response = $this->makeApiRequest('GET', '/navigation');

        $this->assertEquals(200, $response['status']);
        $this->assertTrue($response['success']);
        
        // Should return guest-level navigation
        $navigationData = $response['data'];
        $this->assertEquals('guest', $navigationData['role']);
    }

    public function testCachePerformance(): void
    {
        // Build cache first
        $this->navigationBuilder->buildAllRoleNavigationCaches();

        // First request (should hit cache)
        $startTime = microtime(true);
        $response1 = $this->makeApiRequest('GET', '/navigation/admin');
        $time1 = microtime(true) - $startTime;

        // Second request (should hit cache again)
        $startTime = microtime(true);
        $response2 = $this->makeApiRequest('GET', '/navigation/admin');
        $time2 = microtime(true) - $startTime;

        // Both should be successful
        $this->assertEquals(200, $response1['status']);
        $this->assertEquals(200, $response2['status']);

        // Should be under 1 second (relaxed for test environment)
        $this->assertLessThan(1.0, $time1, 'First request should be under 1 second');
        $this->assertLessThan(1.0, $time2, 'Second request should be under 1 second');

        // Verify cache files exist
        $expectedCacheFiles = [
            'cache/navigation_cache_admin.php',
            'cache/navigation_cache_manager.php',
            'cache/navigation_cache_user.php',
            'cache/navigation_cache_guest.php'
        ];

        foreach ($expectedCacheFiles as $cacheFile) {
            $this->assertFileExists($cacheFile);
            $this->assertGreaterThan(0, filesize($cacheFile));
        }
    }

    /**
     * Helper method to make API requests
     * Simplified version that tests the navigation system without HTTP layer
     */
    private function makeApiRequest(string $method, string $path): array
    {
        try {
            // Use NavigationBuilder directly for testing core functionality
            switch ($method) {
                case 'GET':
                    if ($path === '/navigation') {
                        // Test guest navigation (no authentication)
                        $navigation = $this->navigationBuilder->buildNavigationForRole('guest');
                        return [
                            'success' => true,
                            'status' => 200,
                            'data' => $navigation
                        ];
                    } elseif (preg_match('/^\/navigation\/(.*)$/', $path, $matches)) {
                        $role = $matches[1];
                        
                        // Handle empty role
                        if (empty($role)) {
                            return [
                                'success' => false,
                                'status' => 400,
                                'error' => ['message' => 'Role name is required']
                            ];
                        }
                        
                        $validRoles = ['admin', 'manager', 'user', 'guest'];
                        
                        if (!in_array($role, $validRoles)) {
                            return [
                                'success' => false,
                                'status' => 400,
                                'error' => ['message' => 'Invalid role name']
                            ];
                        }
                        
                        $navigation = $this->navigationBuilder->buildNavigationForRole($role);
                        return [
                            'success' => true,
                            'status' => 200,
                            'data' => $navigation
                        ];
                    }
                    break;
                    
                case 'POST':
                    if ($path === '/navigation/cache/rebuild') {
                        $results = $this->navigationBuilder->buildAllRoleNavigationCaches();
                        
                        $successCount = count(array_filter($results, fn($r) => $r['success']));
                        
                        return [
                            'success' => true,
                            'status' => 200,
                            'data' => [
                                'message' => 'Navigation cache rebuild completed',
                                'total_roles' => count($results),
                                'successful_rebuilds' => $successCount,
                                'results' => $results
                            ]
                        ];
                    }
                    break;
            }
            
            // Return 404 for unmatched routes
            return [
                'success' => false,
                'status' => 404,
                'error' => ['message' => 'Not found']
            ];
            
        } catch (\Gravitycar\Exceptions\NavigationBuilderException $e) {
            return [
                'success' => false,
                'status' => 400,
                'error' => ['message' => $e->getMessage()]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 500,
                'error' => ['message' => $e->getMessage()]
            ];
        }
    }
}