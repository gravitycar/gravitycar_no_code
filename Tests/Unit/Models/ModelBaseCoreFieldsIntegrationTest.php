<?php

namespace Gravitycar\Tests\Unit\Models;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Models\ModelBase;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test suite for ModelBase integration with MetadataEngine cached core fields using pure DI.
 * Tests automatic core field inclusion via cached metadata.
 */
class ModelBaseCoreFieldsIntegrationTest extends UnitTestCase
{
    private MetadataEngineInterface&MockObject $mockMetadataEngine;
    private FieldFactory&MockObject $mockFieldFactory;
    private DatabaseConnectorInterface&MockObject $mockDatabaseConnector;
    private RelationshipFactory&MockObject $mockRelationshipFactory;
    private ModelFactory&MockObject $mockModelFactory;
    private CurrentUserProviderInterface&MockObject $mockCurrentUserProvider;

    protected function setUp(): void
    {
        parent::setUp();

        // Create all mocks
        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $this->mockFieldFactory = $this->createMock(FieldFactory::class);
        $this->mockDatabaseConnector = $this->createMock(DatabaseConnectorInterface::class);
        $this->mockRelationshipFactory = $this->createMock(RelationshipFactory::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);
        $this->mockCurrentUserProvider = $this->createMock(CurrentUserProviderInterface::class);

        // Configure MetadataEngine mock to handle the test model classes
        $this->setupMetadataEngineMocks();

        // Setup default behaviors for other mocks
        $this->setupOtherMockDefaults();
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

    private function setupOtherMockDefaults(): void
    {
        // FieldFactory - create mock fields
        $this->mockFieldFactory->method('createField')
            ->willReturnCallback(function($fieldMeta, $tableName = null) {
                $mockField = $this->createMock(\Gravitycar\Fields\FieldBase::class);
                $mockField->method('getName')->willReturn($fieldMeta['name'] ?? 'test_field');
                return $mockField;
            });

        // CurrentUserProvider
        $this->mockCurrentUserProvider->method('getCurrentUserId')->willReturn('test-user');
        $this->mockCurrentUserProvider->method('hasAuthenticatedUser')->willReturn(true);

        // DatabaseConnector
        $this->mockDatabaseConnector->method('create')->willReturn(true);
        $this->mockDatabaseConnector->method('update')->willReturn(true);
    }

    protected function tearDown(): void
    {
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
        $model = new TestableModelForCoreFields(
            $this->logger,
            $this->mockMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );

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
        $model = new TestableModelWithMetadataOverride(
            $this->logger,
            $this->mockMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );

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
        $model = new TestableModelWithExistingFields(
            $this->logger,
            $this->mockMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );

        $metadata = $model->getTestMetadata();

        // Should have both core fields and model-specific fields
        $this->assertArrayHasKey('id', $metadata['fields']); // Core field
        $this->assertArrayHasKey('name', $metadata['fields']); // Model-specific field
        $this->assertArrayHasKey('description', $metadata['fields']); // Model-specific field
    }

    /**
     * Test error handling when MetadataEngine returns empty metadata
     */
    public function testErrorHandlingWhenMetadataEngineReturnsEmpty(): void
    {
        // Create a specific MetadataEngine mock that returns empty metadata
        $emptyMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $emptyMetadataEngine->method('resolveModelName')->willReturn('TestModel');
        $emptyMetadataEngine->method('getModelMetadata')->willReturn([]);

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('No metadata found for model');

        new TestableModelWithNoInitialFields(
            $this->logger,
            $emptyMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );
    }

    // ====================
    // DEPENDENCY INJECTION INTEGRATION TESTS
    // ====================

    /**
     * Test that ModelBase uses MetadataEngine correctly via dependency injection
     */
    public function testModelBaseUsesMetadataEngineViaDI(): void
    {
        $model = new TestableModelForCoreFields(
            $this->logger,
            $this->mockMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );

        // Verify that the model was created successfully (indicating MetadataEngine was used)
        $this->assertInstanceOf(TestableModelForCoreFields::class, $model);

        // Verify metadata was loaded through MetadataEngine
        $metadata = $model->getTestMetadata();
        $this->assertArrayHasKey('fields', $metadata);
        $this->assertArrayHasKey('id', $metadata['fields']);
    }

    /**
     * Test that multiple model instances work independently with their own dependencies
     */
    public function testMultipleModelInstancesWorkIndependently(): void
    {
        $model1 = new TestableModelForCoreFields(
            $this->logger,
            $this->mockMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );
        
        $model2 = new TestableModelForCoreFields(
            $this->logger,
            $this->mockMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );

        // Both models should work independently
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
        $model = new TestableModelWithNoInitialFields(
            $this->logger,
            $this->mockMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );

        $this->assertInstanceOf(TestableModelWithNoInitialFields::class, $model);
    }

    /**
     * Test that validation fails when no fields are available (MetadataEngine returns empty)
     */
    public function testValidationFailsWhenNoFieldsAvailable(): void
    {
        // Create specific mock that returns empty fields
        $emptyMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $emptyMetadataEngine->method('resolveModelName')->willReturn('TestableModelWithNoInitialFields');
        $emptyMetadataEngine->method('getModelMetadata')->willReturn(['fields' => []]);

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('No metadata found for model');

        new TestableModelWithNoInitialFields(
            $this->logger,
            $emptyMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );
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

        $specificFieldFactory = $this->createMock(FieldFactory::class);
        $specificFieldFactory->expects($this->atLeastOnce())
            ->method('createField')
            ->with($this->arrayHasKey('name'))
            ->willReturn($mockField);

        // Create the model with the specific field factory
        $model = new TestableModelForCoreFields(
            $this->logger,
            $this->mockMetadataEngine,
            $specificFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );

        // Verify field was created and added
        $fields = $model->getFields();
        $this->assertArrayHasKey('id', $fields);
        $this->assertInstanceOf(\Gravitycar\Fields\FieldBase::class, $fields['id']);
    }
}

/**
 * Test model class for testing core fields integration using pure DI
 */
class TestableModelForCoreFields extends ModelBase
{
    public function __construct(
        Logger $logger,
        MetadataEngineInterface $metadataEngine,
        FieldFactory $fieldFactory,
        DatabaseConnectorInterface $databaseConnector,
        RelationshipFactory $relationshipFactory,
        ModelFactory $modelFactory,
        CurrentUserProviderInterface $currentUserProvider
    ) {
        parent::__construct(
            $logger,
            $metadataEngine,
            $fieldFactory,
            $databaseConnector,
            $relationshipFactory,
            $modelFactory,
            $currentUserProvider
        );
    }

    public function getTestMetadata(): array
    {
        return $this->metadata;
    }

    public function getTableName(): string
    {
        return 'testable_models';
    }
}

/**
 * Test model with metadata that overrides core fields
 */
class TestableModelWithMetadataOverride extends ModelBase
{
    public function __construct(
        Logger $logger,
        MetadataEngineInterface $metadataEngine,
        FieldFactory $fieldFactory,
        DatabaseConnectorInterface $databaseConnector,
        RelationshipFactory $relationshipFactory,
        ModelFactory $modelFactory,
        CurrentUserProviderInterface $currentUserProvider
    ) {
        parent::__construct(
            $logger,
            $metadataEngine,
            $fieldFactory,
            $databaseConnector,
            $relationshipFactory,
            $modelFactory,
            $currentUserProvider
        );
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
    public function __construct(
        Logger $logger,
        MetadataEngineInterface $metadataEngine,
        FieldFactory $fieldFactory,
        DatabaseConnectorInterface $databaseConnector,
        RelationshipFactory $relationshipFactory,
        ModelFactory $modelFactory,
        CurrentUserProviderInterface $currentUserProvider
    ) {
        parent::__construct(
            $logger,
            $metadataEngine,
            $fieldFactory,
            $databaseConnector,
            $relationshipFactory,
            $modelFactory,
            $currentUserProvider
        );
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
    public function __construct(
        Logger $logger,
        MetadataEngineInterface $metadataEngine,
        FieldFactory $fieldFactory,
        DatabaseConnectorInterface $databaseConnector,
        RelationshipFactory $relationshipFactory,
        ModelFactory $modelFactory,
        CurrentUserProviderInterface $currentUserProvider
    ) {
        parent::__construct(
            $logger,
            $metadataEngine,
            $fieldFactory,
            $databaseConnector,
            $relationshipFactory,
            $modelFactory,
            $currentUserProvider
        );
    }

    public function getTableName(): string
    {
        return 'no_initial_fields_models';
    }
}
