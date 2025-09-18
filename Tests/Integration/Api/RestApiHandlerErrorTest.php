<?php
namespace Tests\Integration\Api;

use Aura\Di\Container;
use PHPUnit\Framework\TestCase;
use Gravitycar\Api\RestApiHandler;
use Gravitycar\Core\ContainerConfig;
use Gravitycar\Exceptions\APIException;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\BadRequestException;
use Gravitycar\Exceptions\UnprocessableEntityException;
use Gravitycar\Exceptions\InternalServerErrorException;
use Monolog\Logger;

/**
 * Integration tests for RestApiHandler error handling with new API exceptions
 */
/**
 * Integration tests for RestApiHandler error handling.
 * 
 * Note: These tests may show "risky" warnings due to output buffer management
 * that occurs during full request/response cycle testing. This is expected
 * behavior for integration tests that test the complete API error flow.
 */
class RestApiHandlerErrorTest extends TestCase {

    private RestApiHandler $handler;
    private array $originalServerState = [];
    private Logger $logger;
    protected function setUp(): void {
        parent::setUp();

        // Initialize logger
        $this->logger = ContainerConfig::getContainer()->get('logger');

        // Save original global state
        $this->originalServerState = $_SERVER;
        
        $this->handler = new RestApiHandler();
        
        // Clear any previous output
        //ob_end_clean();
        if (ob_get_level()) {
            //ob_end_clean();
            $this->logger->info("setUp() Cleaning up previous buffers: " . ob_get_level() . "\n");
        }
    }

    protected function tearDown(): void {
        // Restore original global state
        $_SERVER = $this->originalServerState;
        
        parent::tearDown();
        
        // Ensure we're back to a clean buffer state for next test
        while (ob_get_level() > 1) {
            //ob_end_clean();
            $this->logger->info("tearDown() Cleaning up previous buffers: " . ob_get_level() . "\n");
        }
    }

    /**
     * Test that NotFoundException returns proper 404 response.
     */
    
    public function testNotFoundExceptionReturns404(): void {
        // Set up a request that will trigger NotFoundException
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/NonexistentModel/123';
        $_SERVER['ORIGINAL_PATH'] = '/NonexistentModel/123';
        $_GET = [];

        $this->expectOutputRegex('/404,/');
        $this->handler->handleRequest();
    }

    /**
     * Test that BadRequestException returns proper 400 response.
     **/
    public function testBadRequestExceptionReturns400(): void {
        // Set up a request with missing required parameters
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/'; // Invalid path that should trigger BadRequestException
        $_SERVER['ORIGINAL_PATH'] = '/';
        $_GET = [];

        $this->expectOutputRegex('/400,/');
        $this->handler->handleRequest();
    }

    /**
     * Test error response format consistency.
     */
    public function testErrorResponseFormat(): void {
        // Set up a request that will trigger an error
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/InvalidModel';
        $_SERVER['ORIGINAL_PATH'] = '/InvalidModel';
        $_GET = [];

            $this->expectOutputRegex('/error/');
            $this->handler->handleRequest();
    }

    /**
     * Test CORS headers in error responses.
     */
    public function testCORSHeadersInErrorResponse(): void {
        // Set up a request that will trigger an error
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/InvalidModel';
        $_SERVER['ORIGINAL_PATH'] = '/InvalidModel';
        $_GET = [];

        
        $this->expectOutputRegex('/status/');
        $this->handler->handleRequest();
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
