<?php
namespace Tests\Integration;

use Gravitycar\Tests\TestCase;
use Gravitycar\Api\RestApiHandler;

/**
 * Integration test for REST API entry point functionality
 */
class RestApiEntryPointTest extends TestCase {
    
    private $handler;
    
    protected function setUp(): void {
        parent::setUp();
        
        // Set up test environment
        $GLOBALS['GRAVITYCAR_TEST_MODE'] = true;
        
        // Include REST API handler
        require_once __DIR__ . '/../../rest_api.php';
        
        $this->handler = new RestApiHandler();
    }
    
    /**
     * Test GET request handling
     */
    public function testGetRequestHandling(): void {
        // Set up GET request environment
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/Users';
        $_SERVER['ORIGINAL_PATH'] = '/Users';
        $_GET = [];
        
        // Capture output
        ob_start();
        $this->handler->handleRequest();
        $output = ob_get_clean();
        
        // Parse JSON response
        $response = json_decode($output, true);
        
        // Verify response structure
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('timestamp', $response);
        
        // Verify successful response
        if ($response['success']) {
            $this->assertArrayHasKey('data', $response);
            $this->assertArrayHasKey('count', $response);
        } else {
            $this->assertArrayHasKey('error', $response);
        }
    }
    
    /**
     * Test POST request handling
     */
    public function testPostRequestHandling(): void {
        // Set up POST request environment
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/Users';
        $_SERVER['ORIGINAL_PATH'] = '/Users';
        $_GET = [];
        $_POST = [
            'username' => 'testapi@example.com',
            'email' => 'testapi@example.com',
            'first_name' => 'Test',
            'last_name' => 'API',
            'password' => 'password123'
        ];
        
        // Capture output
        ob_start();
        $this->handler->handleRequest();
        $output = ob_get_clean();
        
        // Parse JSON response
        $response = json_decode($output, true);
        
        // Verify response structure
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('timestamp', $response);
        
        // POST requests may succeed or fail depending on model validation
        // The important thing is that we get a proper JSON response
        $this->assertTrue(is_bool($response['success']));
    }
    
    /**
     * Test error handling for invalid paths
     */
    public function testErrorHandlingInvalidPath(): void {
        // Set up invalid request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['ORIGINAL_PATH'] = '/';
        $_GET = [];
        
        // Capture output
        ob_start();
        $this->handler->handleRequest();
        $output = ob_get_clean();
        
        // Parse JSON response
        $response = json_decode($output, true);
        
        // Verify error response structure
        $this->assertIsArray($response);
        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('message', $response['error']);
        $this->assertEquals('Invalid API path', $response['error']['message']);
    }
    
    /**
     * Test JSON response format for ReactJS compatibility
     */
    public function testReactJsResponseFormat(): void {
        // Set up request
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/Users';
        $_SERVER['ORIGINAL_PATH'] = '/Users';
        $_GET = [];
        
        // Capture output
        ob_start();
        $this->handler->handleRequest();
        $output = ob_get_clean();
        
        // Parse JSON response
        $response = json_decode($output, true);
        
        // Verify ReactJS-friendly format
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('timestamp', $response);
        
        // Check timestamp format (ISO 8601)
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $response['timestamp']);
        
        // If successful, check data structure
        if ($response['success']) {
            $this->assertArrayHasKey('data', $response);
            if (is_array($response['data'])) {
                $this->assertArrayHasKey('count', $response);
                $this->assertEquals(count($response['data']), $response['count']);
            }
        }
    }
    
    /**
     * Test different HTTP methods support
     */
    public function testHttpMethodsSupport(): void {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        
        foreach ($methods as $method) {
            // Set up request
            $_SERVER['REQUEST_METHOD'] = $method;
            $_SERVER['REQUEST_URI'] = '/Users';
            $_SERVER['ORIGINAL_PATH'] = '/Users';
            $_GET = [];
            $_POST = [];
            
            // Capture output
            ob_start();
            $this->handler->handleRequest();
            $output = ob_get_clean();
            
            // Parse JSON response
            $response = json_decode($output, true);
            
            // Verify we get a proper JSON response (not a 405 Method Not Allowed)
            $this->assertIsArray($response);
            $this->assertArrayHasKey('success', $response);
            $this->assertNotEquals(405, $response['status'], "Method $method should be supported");
        }
    }
}
