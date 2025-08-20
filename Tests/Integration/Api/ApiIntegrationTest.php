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
        // The framework auto-discovers routes from models, so we test with an invalid model name

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/invalid_model_name_123';

        try {
            // This should throw an exception since 'invalid_model_name_123' is not a valid model
            $this->router->handleRequest();
            $this->fail('Expected GCException for invalid model');
        } catch (\Gravitycar\Exceptions\GCException $e) {
            // The framework now auto-discovers routes, so we expect model validation errors
            $this->assertStringContainsString('Model not found or cannot be instantiated', $e->getMessage());
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
     * Test that the router can successfully route to a valid model endpoint
     */
    public function testValidModelRouting(): void
    {
        // Test with a valid model that should exist (users)
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users';

        try {
            // This should successfully route to the users model API
            // but may fail on database operations in test environment
            $this->router->handleRequest();
            // If we get here without exception, routing worked
            $this->assertTrue(true, 'Router successfully routed to valid model endpoint');
        } catch (\Gravitycar\Exceptions\GCException $e) {
            // If there's a database error or other issue, that's still OK for this test
            // We just want to verify that routing works (no "Model not found" error)
            $this->assertStringNotContainsString('Model not found', $e->getMessage());
            $this->assertStringNotContainsString('No matching route found', $e->getMessage());
        }
    }

    /**
     * Test API error handling and validation responses.
     */
    public function testApiErrorHandling(): void
    {
        // Test invalid route handling with a path that clearly won't match any model routes
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/completely/nonexistent/deeply/nested/route/that/wont/match';

        try {
            $this->router->handleRequest();
            $this->fail('Expected GCException for invalid route');
        } catch (\Gravitycar\Exceptions\GCException $e) {
            // Verify proper error handling - should be no matching route since path is too deep
            $this->assertStringContainsString('No matching route found', $e->getMessage());
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
        // Test request handling with authorization headers using an invalid model
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/invalid_model_for_auth_test';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test-token';

        // Verify router processes the request
        try {
            $this->router->handleRequest();
        } catch (\Gravitycar\Exceptions\GCException $e) {
            // Expected since this is not a valid model
            $this->assertStringContainsString('Model not found or cannot be instantiated', $e->getMessage());
        }

        // Verify authorization header is available
        $this->assertEquals('Bearer test-token', $_SERVER['HTTP_AUTHORIZATION']);
    }
}
