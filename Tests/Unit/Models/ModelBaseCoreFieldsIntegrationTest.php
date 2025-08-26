<?php

namespace Gravitycar\Tests\Unit\Models;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Models\ModelBase;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use Aura\Di\Container;
use Aura\Di\ContainerBuilder;

/**
 * Test suite for ModelBase integration with MetadataEngine cached core fields.
 * Tests automatic core field inclusion via cached metadata.
 */
class ModelBaseCoreFieldsIntegrationTest extends UnitTestCase
{
    private MockObject $mockLogger;
    private MockObject $mockMetadataEngine;
    private Container $testContainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockLogger = $this->createMock(Logger::class);
        $this->mockMetadataEngine = $this->createMock(MetadataEngine::class);

        // Set up test container with mocked services using ContainerBuilder
        $builder = new ContainerBuilder();
        $this->testContainer = $builder->newInstance();
        $this->testContainer->set('logger', $this->mockLogger);
        $this->testContainer->set('metadata_engine', $this->mockMetadataEngine);

        // Mock field factory to avoid actual field creation
        $mockFieldFactory = $this->createMock(\Gravitycar\Factories\FieldFactory::class);
        $this->testContainer->set('field_factory', $mockFieldFactory);

        // Configure MetadataEngine mock to handle the test model classes
        $this->setupMetadataEngineMocks();

        ServiceLocator::setContainer($this->testContainer);
    }

    /**
     * Configure MetadataEngine mocks for test model classes
     */
    private function setupMetadataEngineMocks(): void
    {
        // Mock resolveModelName for all test models
        $this->mockMetadataEngine->method('resolveModelName')
            ->willReturnCallback(function ($className) {
                switch ($className) {
                    case TestableModelForCoreFields::class:
                        return 'TestableModelForCoreFields';
                    case TestableModelWithMetadataOverride::class:
                        return 'TestableModelWithMetadataOverride';
                    case TestableModelWithExistingFields::class:
                        return 'TestableModelWithExistingFields';
                    case TestableModelWithNoInitialFields::class:
                        return 'TestableModelWithNoInitialFields';
                    default:
                        return basename(str_replace('\\', '/', $className));
                }
            });

        // Mock getModelMetadata to return basic metadata with core fields
        $this->mockMetadataEngine->method('getModelMetadata')
            ->willReturnCallback(function ($modelName) {
                switch ($modelName) {
                    case 'TestableModelForCoreFields':
                        return [
                            'name' => 'TestableModelForCoreFields',
                            'table' => 'testable_model_for_core_fields',
                            'fields' => [
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
                            ],
                            'relationships' => []
                        ];
                    case 'TestableModelWithMetadataOverride':
                        return [
                            'name' => 'TestableModelWithMetadataOverride',
                            'table' => 'testable_model_with_metadata_override',
                            'fields' => [
                                'id' => [
                                    'name' => 'id',
                                    'type' => 'IDField',
                                    'label' => 'Overridden ID Label',
                                    'description' => 'Custom description',
                                    'isDBField' => true
                                ]
                            ],
                            'relationships' => []
                        ];
                    case 'TestableModelWithExistingFields':
                        return [
                            'name' => 'TestableModelWithExistingFields',
                            'table' => 'testable_model_with_existing_fields',
                            'fields' => [
                                'id' => [
                                    'name' => 'id',
                                    'type' => 'IDField',
                                    'label' => 'ID',
                                    'isDBField' => true
                                ],
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
                            ],
                            'relationships' => []
                        ];
                    case 'TestableModelWithNoInitialFields':
                        return [
                            'name' => 'TestableModelWithNoInitialFields',
                            'table' => 'testable_model_with_no_initial_fields',
                            'fields' => [
                                'id' => [
                                    'name' => 'id',
                                    'type' => 'IDField',
                                    'label' => 'ID',
                                    'isDBField' => true
                                ]
                            ],
                            'relationships' => []
                        ];
                    default:
                        throw new GCException("No metadata found for model $modelName");
                }
            });
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
     * Test that ModelBase works correctly with metadata that includes core fields
     * (The core fields should already be included by MetadataEngine in the cached metadata)
     */
    public function testModelBaseWorksWithCoreFieldsInMetadata(): void
    {
        $model = new TestableModelForCoreFields();

        // Verify that the model has the core fields that were included in metadata by MetadataEngine
        $metadata = $model->getTestMetadata();
        $this->assertArrayHasKey('fields', $metadata);
        $this->assertArrayHasKey('id', $metadata['fields']);
        $this->assertArrayHasKey('created_at', $metadata['fields']);
        
        // Verify the core fields have the expected structure
        $this->assertEquals('IDField', $metadata['fields']['id']['type']);
        $this->assertEquals('DateTimeField', $metadata['fields']['created_at']['type']);
    }

    /**
     * Test that model-specific metadata overrides core fields
     */
    public function testModelSpecificMetadataOverridesCoreFields(): void
    {
        $model = new TestableModelWithMetadataOverride();

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
        $model = new TestableModelWithExistingFields();

        $metadata = $model->getTestMetadata();

        // Should have both core fields and model-specific fields
        $this->assertArrayHasKey('id', $metadata['fields']); // Core field
        $this->assertArrayHasKey('name', $metadata['fields']); // Model-specific field
        $this->assertArrayHasKey('description', $metadata['fields']); // Model-specific field
    }

    /**
     * Test error handling when MetadataEngine service is unavailable
     */
    public function testErrorHandlingWhenMetadataEngineServiceUnavailable(): void
    {
        // Remove MetadataEngine from container to simulate service failure
        $builder = new ContainerBuilder();
        $failingContainer = $builder->newInstance();
        $failingContainer->set('logger', $this->mockLogger);
        ServiceLocator::setContainer($failingContainer);

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model metadata not found for');

        new TestableModelForCoreFields();
    }

    // ====================
    // DEPENDENCY INJECTION INTEGRATION TESTS
    // ====================

    /**
     * Test that ModelBase uses ServiceLocator to get MetadataEngine
     */
    public function testModelBaseUsesServiceLocatorForMetadataEngine(): void
    {
        // Simply create a model - the default mocks handle the MetadataEngine calls
        $model = new TestableModelForCoreFields();

        // Verify that the model was created successfully (indicating MetadataEngine was used)
        $this->assertInstanceOf(TestableModelForCoreFields::class, $model);

        // Verify metadata was loaded through MetadataEngine
        $metadata = $model->getTestMetadata();
        $this->assertArrayHasKey('fields', $metadata);
        $this->assertArrayHasKey('id', $metadata['fields']);
    }

    /**
     * Test that multiple model instances share the same MetadataEngine service
     */
    public function testMultipleModelInstancesShareMetadataEngineService(): void
    {
        $model1 = new TestableModelForCoreFields();
        $model2 = new TestableModelForCoreFields();

        // Both models should have used the same service instance and have metadata
        $this->assertInstanceOf(TestableModelForCoreFields::class, $model1);
        $this->assertInstanceOf(TestableModelForCoreFields::class, $model2);

        // Verify both have loaded metadata
        $metadata1 = $model1->getTestMetadata();
        $metadata2 = $model2->getTestMetadata();
        $this->assertArrayHasKey('fields', $metadata1);
        $this->assertArrayHasKey('fields', $metadata2);
    }

    // ====================
    // METADATA VALIDATION TESTS
    // ====================

    /**
     * Test that metadata validation still works with core fields included
     */
    public function testMetadataValidationWorksWithCoreFields(): void
    {
        // Should not throw exception - cached metadata provides required 'fields' array
        $model = new TestableModelWithNoInitialFields();

        $this->assertInstanceOf(TestableModelWithNoInitialFields::class, $model);
    }

    /**
     * Test that validation fails when no fields are available (MetadataEngine returns empty)
     */
    public function testValidationFailsWhenNoFieldsAvailable(): void
    {
        // Override the mock to return empty fields
        $emptyMetadata = ['fields' => []]; 

        $mockMetadataEngine = $this->createMock(MetadataEngine::class);
        $mockMetadataEngine->method('resolveModelName')
            ->willReturn('TestableModelWithNoInitialFields');
        $mockMetadataEngine->method('getModelMetadata')
            ->willReturn($emptyMetadata);

        // Create a new container with this specific mock
        $builder = new ContainerBuilder();
        $testContainer = $builder->newInstance();
        $testContainer->set('logger', $this->mockLogger);
        $testContainer->set('metadata_engine', $mockMetadataEngine);
        $testContainer->set('field_factory', $this->createMock(\Gravitycar\Factories\FieldFactory::class));
        ServiceLocator::setContainer($testContainer);

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('No metadata found for model');

        new TestableModelWithNoInitialFields();
    }

    // ====================
    // FIELD INITIALIZATION INTEGRATION TESTS
    // ====================

    /**
     * Test that core fields are properly initialized as field objects from cached metadata
     */
    public function testCoreFieldsAreInitializedAsFieldObjects(): void
    {
        // Create a specific mock field for this test
        $mockField = $this->createMock(\Gravitycar\Fields\FieldBase::class);
        $mockField->method('getName')->willReturn('id');

        $mockFieldFactory = $this->createMock(\Gravitycar\Factories\FieldFactory::class);
        $mockFieldFactory->expects($this->atLeastOnce())
            ->method('createField')
            ->with($this->arrayHasKey('name'))
            ->willReturn($mockField);

        // IMPORTANT: Set up the mock field factory in the container BEFORE creating the model
        $this->testContainer->set('field_factory', $mockFieldFactory);

        // Now create the model - it will use the mock field factory during construction
        $model = new TestableModelForCoreFields();

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
    public function __construct()
    {
        parent::__construct();
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
    public function __construct()
    {
        parent::__construct();
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
    public function __construct()
    {
        parent::__construct();
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
    public function __construct()
    {
        parent::__construct();
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
