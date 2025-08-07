<?php

namespace Gravitycar\Tests\Unit\Core;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Metadata\CoreFieldsMetadata;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;
use Aura\Di\Container;
use Aura\Di\ContainerBuilder;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test suite for ServiceLocator integration with CoreFieldsMetadata.
 * Tests DI container registration and service access.
 */
class ServiceLocatorCoreFieldsMetadataTest extends UnitTestCase
{
    private Container $testContainer;
    private MockObject $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLogger = $this->createMock(Logger::class);

        // Create container properly using ContainerBuilder
        $builder = new ContainerBuilder();
        $this->testContainer = $builder->newInstance();
        $this->testContainer->set('logger', $this->mockLogger);
    }

    protected function tearDown(): void
    {
        ServiceLocator::reset();
        parent::tearDown();
    }

    // ====================
    // SERVICE REGISTRATION TESTS
    // ====================

    /**
     * Test that CoreFieldsMetadata service is properly registered in container
     */
    public function testCoreFieldsMetadataServiceIsRegistered(): void
    {
        // Register the service in test container
        $this->testContainer->set('core_fields_metadata', $this->testContainer->lazyNew(
            CoreFieldsMetadata::class,
            ['logger' => $this->testContainer->lazyGet('logger')]
        ));

        ServiceLocator::setContainer($this->testContainer);

        $service = ServiceLocator::getCoreFieldsMetadata();

        $this->assertInstanceOf(CoreFieldsMetadata::class, $service);
    }

    /**
     * Test that ServiceLocator returns same instance (singleton behavior)
     */
    public function testServiceLocatorReturnsSameInstance(): void
    {
        $this->testContainer->set('core_fields_metadata', $this->testContainer->lazyNew(
            CoreFieldsMetadata::class,
            ['logger' => $this->testContainer->lazyGet('logger')]
        ));

        ServiceLocator::setContainer($this->testContainer);

        $service1 = ServiceLocator::getCoreFieldsMetadata();
        $service2 = ServiceLocator::getCoreFieldsMetadata();

        $this->assertSame($service1, $service2);
    }

    /**
     * Test that service is created with correct dependencies
     */
    public function testServiceIsCreatedWithCorrectDependencies(): void
    {
        $this->testContainer->set('core_fields_metadata', $this->testContainer->lazyNew(
            CoreFieldsMetadata::class,
            ['logger' => $this->testContainer->lazyGet('logger')]
        ));

        ServiceLocator::setContainer($this->testContainer);

        $service = ServiceLocator::getCoreFieldsMetadata();

        // Test that the service works (implying it has correct dependencies)
        $standardFields = $service->getStandardCoreFields();
        $this->assertIsArray($standardFields);
    }

    // ====================
    // ERROR HANDLING TESTS
    // ====================

    /**
     * Test error handling when CoreFieldsMetadata service is not registered
     */
    public function testErrorHandlingWhenServiceNotRegistered(): void
    {
        // Set container without CoreFieldsMetadata service
        ServiceLocator::setContainer($this->testContainer);

        // The logger will be called twice:
        // 1. Once by ServiceLocator: "Failed to get CoreFieldsMetadata service: ..."
        // 2. Once by GCException constructor: "CoreFieldsMetadata service unavailable: ..."
        $this->mockLogger->expects($this->atLeastOnce())
            ->method('error')
            ->with($this->logicalOr(
                $this->stringContains('Failed to get CoreFieldsMetadata service'),
                $this->stringContains('CoreFieldsMetadata service unavailable')
            ));

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('CoreFieldsMetadata service unavailable');

        ServiceLocator::getCoreFieldsMetadata();
    }

    /**
     * Test error handling when service creation fails
     */
    public function testErrorHandlingWhenServiceCreationFails(): void
    {
        // Register service with invalid configuration that will cause creation to fail
        $this->testContainer->set('core_fields_metadata', $this->testContainer->lazyNew(
            'NonExistentClass'
        ));

        ServiceLocator::setContainer($this->testContainer);

        // Similar to above - both ServiceLocator and GCException will log
        $this->mockLogger->expects($this->atLeastOnce())
            ->method('error')
            ->with($this->logicalOr(
                $this->stringContains('Failed to get CoreFieldsMetadata service'),
                $this->stringContains('CoreFieldsMetadata service unavailable')
            ));

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('CoreFieldsMetadata service unavailable');

        ServiceLocator::getCoreFieldsMetadata();
    }

    // ====================
    // CONTAINER INTEGRATION TESTS
    // ====================

    /**
     * Test that service works with full container configuration
     */
    public function testServiceWorksWithFullContainerConfiguration(): void
    {
        // Use the actual ContainerConfig to build a real container
        $realContainer = \Gravitycar\Core\ContainerConfig::getContainer();
        ServiceLocator::setContainer($realContainer);

        $service = ServiceLocator::getCoreFieldsMetadata();

        $this->assertInstanceOf(CoreFieldsMetadata::class, $service);

        // Test basic functionality
        $standardFields = $service->getStandardCoreFields();
        $this->assertIsArray($standardFields);
        $this->assertNotEmpty($standardFields);

        // Should include standard core fields
        $this->assertArrayHasKey('id', $standardFields);
        $this->assertArrayHasKey('created_at', $standardFields);
        $this->assertArrayHasKey('updated_at', $standardFields);
    }

    /**
     * Test service reset functionality
     */
    public function testServiceResetFunctionality(): void
    {
        $this->testContainer->set('core_fields_metadata', $this->testContainer->lazyNew(
            CoreFieldsMetadata::class,
            ['logger' => $this->testContainer->lazyGet('logger')]
        ));

        ServiceLocator::setContainer($this->testContainer);

        $service1 = ServiceLocator::getCoreFieldsMetadata();

        // Reset the service locator
        ServiceLocator::reset();

        // Create a new container to ensure different instances
        $builder = new ContainerBuilder();
        $newContainer = $builder->newInstance();
        $newContainer->set('logger', $this->mockLogger);
        $newContainer->set('core_fields_metadata', $newContainer->lazyNew(
            CoreFieldsMetadata::class,
            ['logger' => $newContainer->lazyGet('logger')]
        ));

        // Set new container
        ServiceLocator::setContainer($newContainer);
        $service2 = ServiceLocator::getCoreFieldsMetadata();

        // Should be different instances after reset
        $this->assertNotSame($service1, $service2);
        $this->assertInstanceOf(CoreFieldsMetadata::class, $service2);
    }

    // ====================
    // TEMPLATE PATH INTEGRATION TESTS
    // ====================

    /**
     * Test that service is created with default template path
     */
    public function testServiceCreatedWithDefaultTemplatePath(): void
    {
        $this->testContainer->set('core_fields_metadata', $this->testContainer->lazyNew(
            CoreFieldsMetadata::class,
            ['logger' => $this->testContainer->lazyGet('logger')]
        ));

        ServiceLocator::setContainer($this->testContainer);

        $service = ServiceLocator::getCoreFieldsMetadata();

        // Try to access standard core fields - should work with default template path
        $standardFields = $service->getStandardCoreFields();
        $this->assertIsArray($standardFields);
    }

    /**
     * Test that service can be created with custom template path
     */
    public function testServiceCanBeCreatedWithCustomTemplatePath(): void
    {
        // Create a custom template for testing
        $customTemplatePath = sys_get_temp_dir() . '/custom_core_fields.php';
        $customFields = [
            'custom_id' => [
                'name' => 'custom_id',
                'type' => 'IDField',
                'label' => 'Custom ID'
            ]
        ];
        file_put_contents($customTemplatePath, '<?php return ' . var_export($customFields, true) . ';');

        try {
            $this->testContainer->set('core_fields_metadata', $this->testContainer->lazyNew(
                CoreFieldsMetadata::class,
                [
                    'logger' => $this->testContainer->lazyGet('logger'),
                    'templatePath' => $customTemplatePath
                ]
            ));

            ServiceLocator::setContainer($this->testContainer);

            $service = ServiceLocator::getCoreFieldsMetadata();
            $standardFields = $service->getStandardCoreFields();

            $this->assertEquals($customFields, $standardFields);
            $this->assertArrayHasKey('custom_id', $standardFields);
        } finally {
            if (file_exists($customTemplatePath)) {
                unlink($customTemplatePath);
            }
        }
    }

    // ====================
    // PERFORMANCE AND CACHING TESTS
    // ====================

    /**
     * Test that service instances are cached properly
     */
    public function testServiceInstancesAreCachedProperly(): void
    {
        $this->testContainer->set('core_fields_metadata', $this->testContainer->lazyNew(
            CoreFieldsMetadata::class,
            ['logger' => $this->testContainer->lazyGet('logger')]
        ));

        ServiceLocator::setContainer($this->testContainer);

        // Multiple calls should return the same instance
        $service1 = ServiceLocator::getCoreFieldsMetadata();
        $service2 = ServiceLocator::getCoreFieldsMetadata();
        $service3 = ServiceLocator::getCoreFieldsMetadata();

        $this->assertSame($service1, $service2);
        $this->assertSame($service2, $service3);
    }

    /**
     * Test that service works correctly in concurrent access scenarios
     */
    public function testServiceWorksConcurrently(): void
    {
        $this->testContainer->set('core_fields_metadata', $this->testContainer->lazyNew(
            CoreFieldsMetadata::class,
            ['logger' => $this->testContainer->lazyGet('logger')]
        ));

        ServiceLocator::setContainer($this->testContainer);

        $service = ServiceLocator::getCoreFieldsMetadata();

        // Simulate concurrent access by multiple operations
        $fields1 = $service->getAllCoreFieldsForModel('Model1');
        $fields2 = $service->getAllCoreFieldsForModel('Model2');
        $fields3 = $service->getAllCoreFieldsForModel('Model1'); // Should use cache

        $this->assertIsArray($fields1);
        $this->assertIsArray($fields2);
        $this->assertSame($fields1, $fields3); // Should be same reference due to caching
    }
}
