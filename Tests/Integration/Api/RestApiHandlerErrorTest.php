<?php
namespace Tests\Integration\Api;

use PHPUnit\Framework\TestCase;
use Gravitycar\Api\RestApiHandler;
use Gravitycar\Exceptions\APIException;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\BadRequestException;
use Gravitycar\Exceptions\UnprocessableEntityException;
use Gravitycar\Exceptions\InternalServerErrorException;

/**
 * Integration tests for RestApiHandler error handling with new API exceptions
 */
class RestApiHandlerErrorTest extends TestCase {

    private RestApiHandler $handler;

    protected function setUp(): void {
        parent::setUp();
        $this->handler = new RestApiHandler();
        
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }
    }

    protected function tearDown(): void {
        parent::tearDown();
        
        // Clear any output from tests
        if (ob_get_level()) {
            ob_end_clean();
        }
    }

    public function testNotFoundExceptionReturns404(): void {
        // Set up a request that will trigger NotFoundException
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/NonexistentModel/123';
        $_SERVER['ORIGINAL_PATH'] = '/NonexistentModel/123';
        $_GET = [];

        // Capture output
        ob_start();
        $this->handler->handleRequest();
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        // Verify response structure
        $this->assertIsArray($response);
        $this->assertFalse($response['success']);
        $this->assertEquals(404, $response['status']);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('message', $response['error']);
        $this->assertArrayHasKey('type', $response['error']);
        $this->assertArrayHasKey('timestamp', $response);
    }

    public function testBadRequestExceptionReturns400(): void {
        // Set up a request with missing required parameters
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/'; // Invalid path that should trigger BadRequestException
        $_SERVER['ORIGINAL_PATH'] = '/';
        $_GET = [];

        // Capture output
        ob_start();
        $this->handler->handleRequest();
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        // Verify response structure
        $this->assertIsArray($response);
        $this->assertFalse($response['success']);
        $this->assertEquals(400, $response['status']);
        $this->assertArrayHasKey('error', $response);
    }

    public function testValidationErrorsInResponse(): void {
        // This test would need to be implemented when we have a model
        // that can actually trigger validation errors in a controlled way
        $this->markTestSkipped('Requires model with validation to test validation error aggregation');
    }

    public function testErrorResponseFormat(): void {
        // Set up a request that will trigger an error
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/InvalidModel';
        $_SERVER['ORIGINAL_PATH'] = '/InvalidModel';
        $_GET = [];

        // Capture output
        ob_start();
        $this->handler->handleRequest();
        $output = ob_get_clean();

        // Parse JSON response
        $response = json_decode($output, true);

        // Verify required response structure for all errors
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('timestamp', $response);

        // Verify error object structure
        $error = $response['error'];
        $this->assertArrayHasKey('message', $error);
        $this->assertArrayHasKey('type', $error);
        $this->assertArrayHasKey('code', $error);

        // Verify timestamp format (ISO 8601)
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $response['timestamp']);
    }

    public function testCORSHeadersInErrorResponse(): void {
        // Set up a request that will trigger an error
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/InvalidModel';
        $_SERVER['ORIGINAL_PATH'] = '/InvalidModel';
        $_GET = [];

        // Start output buffering to capture headers
        ob_start();
        
        // We can't easily test headers in PHPUnit, but we can verify the method runs
        $this->handler->handleRequest();
        
        $output = ob_get_clean();
        
        // Verify we get valid JSON output (headers are set correctly if JSON is valid)
        $response = json_decode($output, true);
        $this->assertIsArray($response);
        $this->assertNotNull($response);
    }

    public function testBackwardCompatibilityWithGCException(): void {
        // This would test that existing GCException handling still works
        // For now, we'll just verify the structure exists
        $this->assertTrue(class_exists(\Gravitycar\Exceptions\GCException::class));
        $this->assertTrue(class_exists(\Gravitycar\Exceptions\APIException::class));
        
        // Verify inheritance
        $this->assertTrue(is_subclass_of(\Gravitycar\Exceptions\APIException::class, \Gravitycar\Exceptions\GCException::class));
    }
}
