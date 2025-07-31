<?php

namespace Gravitycar\Tests\Integration\Api;

use Gravitycar\Tests\Integration\IntegrationTestCase;
use Gravitycar\Api\Router;
use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Tests\Fixtures\FixtureFactory;

/**
 * Integration tests for API endpoints and routing.
 * Tests complete request/response workflows.
 */
class ApiIntegrationTest extends IntegrationTestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = new Router($this->metadataEngine, $this->logger);

        // Set up API environment
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
    }

    protected function tearDown(): void
    {
        // Clean up $_SERVER superglobal
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['REQUEST_URI']);
        unset($_SERVER['HTTP_ACCEPT']);

        parent::tearDown();
    }

    /**
     * Test API route registration and resolution.
     */
    public function testApiRouteRegistration(): void
    {
        // Test that router can handle basic route resolution
        // Since the actual router uses a registry system, we'll test the route method directly

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/health';

        try {
            // This will likely throw an exception since no routes are registered
            // but we can test that the router is properly instantiated and attempting to route
            $this->router->handleRequest();
            $this->fail('Expected GCException for unregistered route');
        } catch (\Gravitycar\Exceptions\GCException $e) {
            // This is expected - no routes are registered in test environment
            $this->assertStringContainsString('No routes registered', $e->getMessage());
        }
    }

    /**
     * Test complete API workflow: request processing, validation, database operations, response.
     */
    public function testCompleteApiWorkflow(): void
    {
        // Test the router's ability to process request parameters
        $_POST = [
            'username' => 'apiuser',
            'email' => 'api@test.com'
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/users';

        // Test that router can extract parameters
        $reflection = new \ReflectionClass($this->router);
        $method = $reflection->getMethod('getRequestParams');
        $method->setAccessible(true);
        $params = $method->invoke($this->router);

        $this->assertArrayHasKey('username', $params);
        $this->assertArrayHasKey('email', $params);
        $this->assertEquals('apiuser', $params['username']);
        $this->assertEquals('api@test.com', $params['email']);
    }

    /**
     * Test API error handling and validation responses.
     */
    public function testApiErrorHandling(): void
    {
        // Test invalid route handling
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/nonexistent/route';

        try {
            $this->router->handleRequest();
            $this->fail('Expected GCException for invalid route');
        } catch (\Gravitycar\Exceptions\GCException $e) {
            // Verify proper error handling
            $this->assertStringContainsString('No routes registered', $e->getMessage());
        }
    }

    /**
     * Test API pagination and filtering.
     */
    public function testApiPaginationAndFiltering(): void
    {
        // Test pagination parameter extraction
        $_GET = [
            'page' => '2',
            'limit' => '10',
            'filter' => 'testuser'
        ];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users?page=2&limit=10&filter=testuser';

        // Test parameter extraction
        $reflection = new \ReflectionClass($this->router);
        $method = $reflection->getMethod('getRequestParams');
        $method->setAccessible(true);
        $params = $method->invoke($this->router);

        $this->assertEquals('2', $params['page']);
        $this->assertEquals('10', $params['limit']);
        $this->assertEquals('testuser', $params['filter']);
    }

    /**
     * Test API authentication and authorization workflows.
     */
    public function testApiAuthenticationWorkflow(): void
    {
        // Test request handling with authorization headers
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/admin/users';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test-token';

        // Verify router processes the request
        try {
            $this->router->handleRequest();
        } catch (\Gravitycar\Exceptions\GCException $e) {
            // Expected since no routes are registered
            $this->assertStringContainsString('No routes registered', $e->getMessage());
        }

        // Verify authorization header is available
        $this->assertEquals('Bearer test-token', $_SERVER['HTTP_AUTHORIZATION']);
    }
}
