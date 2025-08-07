<?php

namespace Gravitycar\Tests\Unit\Models;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Models\ModelBase;
use Gravitycar\Metadata\CoreFieldsMetadata;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use Aura\Di\Container;
use Aura\Di\ContainerBuilder;

/**
 * Test suite for ModelBase integration with CoreFieldsMetadata.
 * Tests automatic core field inclusion and DI integration.
 */
class ModelBaseCoreFieldsIntegrationTest extends UnitTestCase
{
    private MockObject $mockLogger;
    private MockObject $mockCoreFieldsMetadata;
    private Container $testContainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLogger = $this->createMock(Logger::class);
        $this->mockCoreFieldsMetadata = $this->createMock(CoreFieldsMetadata::class);

        // Set up test container with mocked CoreFieldsMetadata using ContainerBuilder
        $builder = new ContainerBuilder();
        $this->testContainer = $builder->newInstance();
        $this->testContainer->set('logger', $this->mockLogger);
        $this->testContainer->set('core_fields_metadata', $this->mockCoreFieldsMetadata);

        // Mock field factory to avoid actual field creation
        $mockFieldFactory = $this->createMock(\Gravitycar\Factories\FieldFactory::class);
        $this->testContainer->set('field_factory', $mockFieldFactory);

        ServiceLocator::setContainer($this->testContainer);
    }

    protected function tearDown(): void
    {
        ServiceLocator::reset();
        parent::tearDown();
    }

    // ====================
    // CORE FIELDS INCLUSION TESTS
    // ====================

    /**
     * Test that ModelBase automatically includes core fields during initialization
     */
    public function testModelBaseIncludesCoreFieldsAutomatically(): void
    {
        $testCoreFields = [
            'id' => [
                'name' => 'id',
                'type' => 'IDField',
                'label' => 'ID',
                'isDBField' => true
            ],
            'created_at' => [
                'name' => 'created_at',
                'type' => 'DateTimeField',
                'label' => 'Created At',
                'isDBField' => true
            ]
        ];

        $this->mockCoreFieldsMetadata->expects($this->once())
            ->method('getAllCoreFieldsForModel')
            ->with(TestableModelForCoreFields::class)
            ->willReturn($testCoreFields);

        $this->mockLogger->expects($this->once())
            ->method('debug')
            ->with(
                'Included core fields metadata',
                $this->logicalAnd(
                    $this->arrayHasKey('model_class'),
                    $this->arrayHasKey('core_fields_added'),
                    $this->arrayHasKey('total_fields')
                )
            );

        $model = new TestableModelForCoreFields($this->mockLogger);

        // Verify core fields were included in metadata
        $metadata = $model->getTestMetadata();
        $this->assertArrayHasKey('fields', $metadata);
        $this->assertArrayHasKey('id', $metadata['fields']);
        $this->assertArrayHasKey('created_at', $metadata['fields']);
    }

    /**
     * Test that model-specific metadata overrides core fields
     */
    public function testModelSpecificMetadataOverridesCoreFields(): void
    {
        $testCoreFields = [
            'id' => [
                'name' => 'id',
                'type' => 'IDField',
                'label' => 'Core ID Label',
                'isDBField' => true
            ]
        ];

        $this->mockCoreFieldsMetadata->expects($this->once())
            ->method('getAllCoreFieldsForModel')
            ->willReturn($testCoreFields);

        $model = new TestableModelWithMetadataOverride($this->mockLogger);

        $metadata = $model->getTestMetadata();

        // Model-specific metadata should override core field metadata
        $this->assertEquals('Overridden ID Label', $metadata['fields']['id']['label']);
        $this->assertEquals('Custom description', $metadata['fields']['id']['description']);

        // Core properties should still be present
        $this->assertEquals('IDField', $metadata['fields']['id']['type']);
    }

    /**
     * Test that core fields are merged with existing model fields
     */
    public function testCoreFieldsMergedWithExistingModelFields(): void
    {
        $testCoreFields = [
            'id' => [
                'name' => 'id',
                'type' => 'IDField',
                'label' => 'ID',
                'isDBField' => true
            ]
        ];

        $this->mockCoreFieldsMetadata->expects($this->once())
            ->method('getAllCoreFieldsForModel')
            ->willReturn($testCoreFields);

        $model = new TestableModelWithExistingFields($this->mockLogger);

        $metadata = $model->getTestMetadata();

        // Should have both core fields and model-specific fields
        $this->assertArrayHasKey('id', $metadata['fields']); // Core field
        $this->assertArrayHasKey('name', $metadata['fields']); // Model-specific field
        $this->assertArrayHasKey('description', $metadata['fields']); // Model-specific field
    }

    /**
     * Test error handling when CoreFieldsMetadata service is unavailable
     */
    public function testErrorHandlingWhenCoreFieldsMetadataServiceUnavailable(): void
    {
        // Remove CoreFieldsMetadata from container to simulate service failure
        $builder = new ContainerBuilder();
        $failingContainer = $builder->newInstance();
        $failingContainer->set('logger', $this->mockLogger);
        ServiceLocator::setContainer($failingContainer);

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('CoreFieldsMetadata service unavailable');

        new TestableModelForCoreFields($this->mockLogger);
    }

    // ====================
    // DEPENDENCY INJECTION INTEGRATION TESTS
    // ====================

    /**
     * Test that ModelBase uses ServiceLocator to get CoreFieldsMetadata
     */
    public function testModelBaseUsesServiceLocatorForCoreFieldsMetadata(): void
    {
        $testCoreFields = ['id' => ['name' => 'id', 'type' => 'IDField']];

        // Verify that the service is called through ServiceLocator
        $this->mockCoreFieldsMetadata->expects($this->once())
            ->method('getAllCoreFieldsForModel')
            ->willReturn($testCoreFields);

        new TestableModelForCoreFields($this->mockLogger);
    }

    /**
     * Test that multiple model instances share the same CoreFieldsMetadata service
     */
    public function testMultipleModelInstancesShareCoreFieldsMetadataService(): void
    {
        $testCoreFields = ['id' => ['name' => 'id', 'type' => 'IDField']];

        // Should be called twice (once per model instance)
        $this->mockCoreFieldsMetadata->expects($this->exactly(2))
            ->method('getAllCoreFieldsForModel')
            ->willReturn($testCoreFields);

        $model1 = new TestableModelForCoreFields($this->mockLogger);
        $model2 = new TestableModelForCoreFields($this->mockLogger);

        // Both models should have used the same service instance
        $this->assertInstanceOf(TestableModelForCoreFields::class, $model1);
        $this->assertInstanceOf(TestableModelForCoreFields::class, $model2);
    }

    // ====================
    // METADATA VALIDATION TESTS
    // ====================

    /**
     * Test that metadata validation still works with core fields included
     */
    public function testMetadataValidationWorksWithCoreFields(): void
    {
        $testCoreFields = [
            'id' => [
                'name' => 'id',
                'type' => 'IDField',
                'label' => 'ID',
                'isDBField' => true
            ]
        ];

        $this->mockCoreFieldsMetadata->expects($this->once())
            ->method('getAllCoreFieldsForModel')
            ->willReturn($testCoreFields);

        // Should not throw exception - core fields provide required 'fields' array
        $model = new TestableModelWithNoInitialFields($this->mockLogger);

        $this->assertInstanceOf(TestableModelWithNoInitialFields::class, $model);
    }

    /**
     * Test that validation fails when no fields are available (core fields service fails)
     */
    public function testValidationFailsWhenNoFieldsAvailable(): void
    {
        $this->mockCoreFieldsMetadata->expects($this->once())
            ->method('getAllCoreFieldsForModel')
            ->willReturn([]); // Empty core fields

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('No metadata found for model');

        new TestableModelWithNoInitialFields($this->mockLogger);
    }

    // ====================
    // FIELD INITIALIZATION INTEGRATION TESTS
    // ====================

    /**
     * Test that core fields are properly initialized as field objects
     */
    public function testCoreFieldsAreInitializedAsFieldObjects(): void
    {
        $testCoreFields = [
            'id' => [
                'name' => 'id',
                'type' => 'IDField',
                'label' => 'ID',
                'isDBField' => true
            ]
        ];

        $this->mockCoreFieldsMetadata->expects($this->once())
            ->method('getAllCoreFieldsForModel')
            ->willReturn($testCoreFields);

        // Create a specific mock field for this test
        $mockField = $this->createMock(\Gravitycar\Fields\FieldBase::class);
        $mockField->method('getName')->willReturn('id');

        $mockFieldFactory = $this->createMock(\Gravitycar\Factories\FieldFactory::class);
        $mockFieldFactory->expects($this->once())
            ->method('createField')
            ->with($this->arrayHasKey('name'))
            ->willReturn($mockField);

        // IMPORTANT: Set up the mock field factory in the container BEFORE creating the model
        $this->testContainer->set('field_factory', $mockFieldFactory);

        // Now create the model - it will use the mock field factory during construction
        $model = new TestableModelForCoreFields($this->mockLogger);

        // Verify field was created and added
        $fields = $model->getFields();
        $this->assertArrayHasKey('id', $fields);
        $this->assertInstanceOf(\Gravitycar\Fields\FieldBase::class, $fields['id']);
    }
}

/**
 * Test model class for testing core fields integration
 */
class TestableModelForCoreFields extends ModelBase
{
    public function __construct(Logger $logger)
    {
        // Register this test model with empty additional core fields to ensure it has metadata
        if (\Gravitycar\Core\ServiceLocator::hasService('CoreFieldsMetadata')) {
            $coreFieldsMetadata = \Gravitycar\Core\ServiceLocator::getCoreFieldsMetadata();
            $coreFieldsMetadata->registerModelSpecificCoreFields(static::class, []);
        }

        parent::__construct($logger);
    }

    protected function getMetaDataFilePaths(): array
    {
        // Return empty array to simulate no metadata file
        return [];
    }

    protected function loadMetadataFromFiles(array $filePaths): array
    {
        // Return empty metadata - core fields will be added automatically
        return [];
    }

    public function getTestMetadata(): array
    {
        return $this->metadata;
    }

    public function getTableName(): string
    {
        return 'testable_models';
    }

    public function clearFieldsForTesting(): void
    {
        $this->fields = [];
    }
}

/**
 * Test model with metadata that overrides core fields
 */
class TestableModelWithMetadataOverride extends ModelBase
{
    public function __construct(Logger $logger)
    {
        // Register this test model with empty additional core fields to ensure it has metadata
        if (\Gravitycar\Core\ServiceLocator::hasService('CoreFieldsMetadata')) {
            $coreFieldsMetadata = \Gravitycar\Core\ServiceLocator::getCoreFieldsMetadata();
            $coreFieldsMetadata->registerModelSpecificCoreFields(static::class, []);
        }

        parent::__construct($logger);
    }

    protected function getMetaDataFilePaths(): array
    {
        return [];
    }

    protected function loadMetadataFromFiles(array $filePaths): array
    {
        // Return metadata that overrides core field properties but includes all required fields
        return [
            'fields' => [
                'id' => [
                    'name' => 'id',
                    'type' => 'IDField',  // Include the type to ensure it's not lost during merging
                    'label' => 'Overridden ID Label',
                    'description' => 'Custom description',
                    'isDBField' => true
                ]
            ]
        ];
    }

    public function getTestMetadata(): array
    {
        return $this->metadata;
    }

    public function getTableName(): string
    {
        return 'override_models';
    }
}

/**
 * Test model with existing model-specific fields
 */
class TestableModelWithExistingFields extends ModelBase
{
    public function __construct(Logger $logger)
    {
        // Register this test model with empty additional core fields to ensure it has metadata
        if (\Gravitycar\Core\ServiceLocator::hasService('CoreFieldsMetadata')) {
            $coreFieldsMetadata = \Gravitycar\Core\ServiceLocator::getCoreFieldsMetadata();
            $coreFieldsMetadata->registerModelSpecificCoreFields(static::class, []);
        }

        parent::__construct($logger);
    }

    protected function getMetaDataFilePaths(): array
    {
        return [];
    }

    protected function loadMetadataFromFiles(array $filePaths): array
    {
        return [
            'fields' => [
                'name' => [
                    'name' => 'name',
                    'type' => 'TextField',
                    'label' => 'Name',
                    'isDBField' => true
                ],
                'description' => [
                    'name' => 'description',
                    'type' => 'TextField',
                    'label' => 'Description',
                    'isDBField' => true
                ]
            ]
        ];
    }

    public function getTestMetadata(): array
    {
        return $this->metadata;
    }

    public function getTableName(): string
    {
        return 'existing_fields_models';
    }
}

/**
 * Test model with no initial fields (relies entirely on core fields)
 */
class TestableModelWithNoInitialFields extends ModelBase
{
    public function __construct(Logger $logger)
    {
        // Register this test model with empty additional core fields to ensure it has metadata
        if (\Gravitycar\Core\ServiceLocator::hasService('CoreFieldsMetadata')) {
            $coreFieldsMetadata = \Gravitycar\Core\ServiceLocator::getCoreFieldsMetadata();
            $coreFieldsMetadata->registerModelSpecificCoreFields(static::class, []);
        }

        parent::__construct($logger);
    }

    protected function getMetaDataFilePaths(): array
    {
        return [];
    }

    protected function loadMetadataFromFiles(array $filePaths): array
    {
        return []; // No fields initially
    }

    public function getTableName(): string
    {
        return 'no_initial_fields_models';
    }
}
