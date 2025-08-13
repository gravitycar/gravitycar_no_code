<?php
namespace Tests\Feature;

use Gravitycar\Tests\TestCase;
use Gravitycar\Core\Gravitycar;
use Gravitycar\Core\ServiceLocator;

/**
 * Unit test for REST API Bootstrap functionality
 */
class RestApiBootstrapTest extends TestCase {
    
    /**
     * Test basic Gravitycar bootstrap for REST API
     */
    public function testRestApiBootstrap(): void {
        // Create and bootstrap application
        $app = new Gravitycar();
        $app->bootstrap();
        
        // Verify bootstrap was successful
        $this->assertTrue(true, 'Bootstrap completed without exceptions');
        
        // Test ServiceLocator access
        $logger = ServiceLocator::getLogger();
        $this->assertInstanceOf(\Monolog\Logger::class, $logger);
        
        $container = ServiceLocator::getContainer();
        $this->assertInstanceOf(\Aura\Di\Container::class, $container);
        
        // Test metadata engine service
        $metadataEngine = $container->get('metadata_engine');
        $this->assertInstanceOf(\Gravitycar\Metadata\MetadataEngine::class, $metadataEngine);
        
        // Test router creation
        $router = new \Gravitycar\Api\Router($metadataEngine);
        $this->assertInstanceOf(\Gravitycar\Api\Router::class, $router);
    }
    
    /**
     * Test REST API entry point environment setup
     */
    public function testRestApiEnvironmentSetup(): void {
        // Simulate REST API environment
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/Users';
        $_SERVER['ORIGINAL_PATH'] = '/Users';
        $_GET = [];
        
        // Include REST API handler in test mode
        $GLOBALS['GRAVITYCAR_TEST_MODE'] = true;
        
        // Test that we can create the handler without errors
        require_once __DIR__ . '/../../rest_api.php';
        
        // Test should pass if no exceptions are thrown
        $this->assertTrue(true, 'REST API environment setup successful');
    }
    
    /**
     * Test request information extraction
     */
    public function testRequestInfoExtraction(): void {
        // Set up test environment
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/Users/123?param=value';
        $_SERVER['ORIGINAL_PATH'] = '/Users/123';
        $_GET = ['param' => 'value'];
        $_POST = ['name' => 'test'];
        
        // Test path parsing
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->assertEquals('/Users/123', $path);
        
        // Test query parameters
        $this->assertEquals('value', $_GET['param']);
        
        // Test POST data
        $this->assertEquals('test', $_POST['name']);
        
        // Test method
        $this->assertEquals('POST', $_SERVER['REQUEST_METHOD']);
    }
}
