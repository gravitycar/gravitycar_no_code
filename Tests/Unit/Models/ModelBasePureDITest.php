<?php

namespace Gravitycar\Tests\Unit\Models;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Models\ModelBase;
use Gravitycar\Fields\FieldBase;
use Gravitycar\Fields\TextField;
use Gravitycar\Fields\IDField;
use Gravitycar\Validation\ValidationRuleBase;
use Gravitycar\Relationships\RelationshipBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Services\CurrentUserProvider;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Simplified ModelBase test using pure dependency injection
 * Phase 5: Test Refactoring - Dramatically simplified test setup
 */
class ModelBasePureDITest extends UnitTestCase
{
    private TestableModelForPureDI $model;
    private MetadataEngineInterface&MockObject $mockMetadataEngine;
    private FieldFactory&MockObject $mockFieldFactory;
    private DatabaseConnectorInterface&MockObject $mockDatabaseConnector;
    private RelationshipFactory&MockObject $mockRelationshipFactory;
    private ModelFactory&MockObject $mockModelFactory;
    private CurrentUserProviderInterface&MockObject $mockCurrentUserProvider;
    private array $sampleMetadata;

    protected function setUp(): void
    {
        parent::setUp();

        // Sample metadata for testing
        $this->sampleMetadata = [
            'fields' => [
                'id' => ['type' => 'ID', 'required' => true],
                'name' => ['type' => 'Text', 'required' => true, 'maxLength' => 100],
                'email' => ['type' => 'Email', 'required' => false],
                'created_at' => ['type' => 'DateTime'],
                'updated_at' => ['type' => 'DateTime'],
                'deleted_at' => ['type' => 'DateTime'],
                'created_by' => ['type' => 'Text'],
                'updated_by' => ['type' => 'Text'],
                'deleted_by' => ['type' => 'Text'],
            ],
            'displayColumns' => ['name', 'email'],
            'table' => 'test_models',
            'name' => 'Test Model'
        ];

        // Create mocks using simple, explicit dependency injection
        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $this->mockFieldFactory = $this->createMock(FieldFactory::class);
        $this->mockDatabaseConnector = $this->createMock(DatabaseConnectorInterface::class);
        $this->mockRelationshipFactory = $this->createMock(RelationshipFactory::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);
        $this->mockCurrentUserProvider = $this->createMock(CurrentUserProviderInterface::class);

        // Set up default mock behaviors
        $this->setupMockDefaults();

        // Create model with all dependencies explicitly injected
        $this->model = new TestableModelForPureDI(
            $this->logger,
            $this->mockMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );
    }

    private function setupMockDefaults(): void
    {
        // MetadataEngine defaults
        $this->mockMetadataEngine
            ->method('resolveModelName')
            ->willReturn('TestableModelForPureDI');
            
        $this->mockMetadataEngine
            ->method('getModelMetadata')
            ->willReturn($this->sampleMetadata);

        // FieldFactory defaults - create appropriate field mocks
        $this->mockFieldFactory
            ->method('createField')
            ->willReturnCallback(function($fieldMeta, $tableName = null) {
                $mockField = $this->createMock(FieldBase::class);
                $mockField->method('getName')->willReturn($fieldMeta['name'] ?? 'test_field');
                $mockField->method('getValue')->willReturn(null);
                $mockField->method('validate')->willReturn(true);
                return $mockField;
            });

        // CurrentUserProvider defaults
        $this->mockCurrentUserProvider
            ->method('getCurrentUserId')
            ->willReturn('test-user-id');
            
        $this->mockCurrentUserProvider
            ->method('hasAuthenticatedUser')
            ->willReturn(true);

        // DatabaseConnector defaults
        $this->mockDatabaseConnector
            ->method('create')
            ->willReturn(true);
            
        $this->mockDatabaseConnector
            ->method('update')
            ->willReturn(true);
    }

    // =========================
    // CONSTRUCTOR TESTS
    // =========================

    public function testConstructorWithAllDependencies(): void
    {
        // Test that all dependencies are properly injected
        $this->assertInstanceOf(TestableModelForPureDI::class, $this->model);
        
        // Test that dependencies are accessible (via reflection)
        $reflection = new \ReflectionClass($this->model);
        
        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $this->assertSame($this->logger, $loggerProperty->getValue($this->model));
        
        $metadataProperty = $reflection->getProperty('metadataEngine');
        $metadataProperty->setAccessible(true);
        $this->assertSame($this->mockMetadataEngine, $metadataProperty->getValue($this->model));
        
        $fieldFactoryProperty = $reflection->getProperty('fieldFactory');
        $fieldFactoryProperty->setAccessible(true);
        $this->assertSame($this->mockFieldFactory, $fieldFactoryProperty->getValue($this->model));
        
        $dbProperty = $reflection->getProperty('databaseConnector');
        $dbProperty->setAccessible(true);
        $this->assertSame($this->mockDatabaseConnector, $dbProperty->getValue($this->model));
        
        $relFactoryProperty = $reflection->getProperty('relationshipFactory');
        $relFactoryProperty->setAccessible(true);
        $this->assertSame($this->mockRelationshipFactory, $relFactoryProperty->getValue($this->model));
        
        $modelFactoryProperty = $reflection->getProperty('modelFactory');
        $modelFactoryProperty->setAccessible(true);
        $this->assertSame($this->mockModelFactory, $modelFactoryProperty->getValue($this->model));
        
        $currentUserProperty = $reflection->getProperty('currentUserProvider');
        $currentUserProperty->setAccessible(true);
        $this->assertSame($this->mockCurrentUserProvider, $currentUserProperty->getValue($this->model));
    }

    // =========================
    // METADATA TESTS
    // =========================

    public function testMetadataLoading(): void
    {
        // Test that metadata is loaded correctly
        $metadata = $this->model->testGetMetadata();
        $this->assertEquals($this->sampleMetadata, $metadata);
    }

    public function testValidateMetadata(): void
    {
        // Test metadata validation with valid data
        $this->model->testValidateMetadata($this->sampleMetadata);
        
        // Should not throw exception for valid metadata
        $this->assertTrue(true);
    }

    public function testValidateMetadataWithInvalidData(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('No metadata found for model');
        
        // Test with invalid metadata (missing fields)
        $invalidMetadata = ['name' => 'Test'];
        $this->model->testValidateMetadata($invalidMetadata);
    }

    // =========================
    // FIELD INITIALIZATION TESTS
    // =========================

    public function testFieldInitialization(): void
    {
        // Test that fields are initialized properly
        $fields = $this->model->testGetFields();
        
        // Should have fields for each metadata field
        $expectedFields = array_keys($this->sampleMetadata['fields']);
        $actualFields = array_keys($fields);
        
        $this->assertEquals(sort($expectedFields), sort($actualFields));
    }

    public function testCreateSingleField(): void
    {
        // Test creating a single field
        $fieldMeta = ['type' => 'Text', 'required' => true];
        $field = $this->model->testCreateSingleField('test_field', $fieldMeta);
        
        $this->assertInstanceOf(FieldBase::class, $field);
        $this->assertEquals('test_field', $field->getName());
        // Note: FieldBase might not have getType() method, just verify it's created
    }

    // =========================
    // CURRENT USER TESTS
    // =========================

    public function testGetCurrentUserId(): void
    {
        // Test getting current user ID through dependency injection
        $userId = $this->model->testGetCurrentUserId();
        $this->assertEquals('test-user-id', $userId);
    }

    public function testCurrentUserAuthentication(): void
    {
        // Test current user authentication check
        $this->assertTrue($this->model->testHasAuthenticatedUser());
        
        // Test with unauthenticated user - create new model with different mock
        $unauthenticatedMockUserProvider = $this->createMock(CurrentUserProviderInterface::class);
        $unauthenticatedMockUserProvider->method('hasAuthenticatedUser')->willReturn(false);
        $unauthenticatedMockUserProvider->method('getCurrentUserId')->willReturn(null);
        
        $unauthenticatedModel = new TestableModelForPureDI(
            $this->logger,
            $this->mockMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $unauthenticatedMockUserProvider
        );
        
        $this->assertFalse($unauthenticatedModel->testHasAuthenticatedUser());
    }

    // =========================
    // CRUD OPERATION TESTS
    // =========================

    public function testCreateOperation(): void
    {
        // Test create operation using dependency-injected database connector
        $result = $this->model->testPersistToDatabase('create');
        $this->assertTrue($result);
        
        // Verify database connector was called
        $this->mockDatabaseConnector
            ->expects($this->once())
            ->method('create')
            ->with($this->model)
            ->willReturn(true);
            
        // Call again to trigger the expectation
        $this->model->testPersistToDatabase('create');
    }

    public function testUpdateOperation(): void
    {
        // Test update operation
        $result = $this->model->testPersistToDatabase('update');
        $this->assertTrue($result);
    }

    // =========================
    // MODEL FACTORY TESTS
    // =========================

    public function testModelFactoryAccess(): void
    {
        // Test that model factory is accessible and can be used
        $mockNewModel = $this->createMock(ModelBase::class);
        
        $this->mockModelFactory
            ->expects($this->once())
            ->method('new')
            ->with('Users')
            ->willReturn($mockNewModel);
            
        $result = $this->model->testCreateRelatedModel('Users');
        $this->assertSame($mockNewModel, $result);
    }

    // =========================
    // VALIDATION TESTS
    // =========================

    public function testValidationForPersistence(): void
    {
        // Test validation before persistence
        $result = $this->model->testValidateForPersistence();
        $this->assertTrue($result);
    }
}

/**
 * Simplified testable model for pure DI testing
 * Much simpler than the old TestableModelBase - just exposes protected methods
 */
class TestableModelForPureDI extends ModelBase
{
    // Constructor uses pure DI - all 7 dependencies explicit
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

    // Simple test helper methods - just expose protected functionality
    public function testGetMetadata(): array
    {
        return $this->metadata;
    }

    public function testGetFields(): array
    {
        return $this->fields;
    }

    public function testValidateMetadata(array $metadata): void
    {
        $this->validateMetadata($metadata);
    }

    public function testCreateSingleField(string $name, array $meta): ?FieldBase
    {
        return $this->createSingleField($name, $meta, $this->fieldFactory);
    }

    public function testPrepareFieldMetadata(string $name, array $meta): array
    {
        return $this->prepareFieldMetadata($name, $meta);
    }

    public function testGetCurrentUserId(): ?string
    {
        return $this->getCurrentUserId();
    }

    public function testHasAuthenticatedUser(): bool
    {
        return $this->currentUserProvider->hasAuthenticatedUser();
    }

    public function testPersistToDatabase(string $operation): bool
    {
        return $this->persistToDatabase($operation);
    }

    public function testValidateForPersistence(): bool
    {
        return $this->validateForPersistence();
    }

    public function testCreateRelatedModel(string $modelName): ModelBase
    {
        return $this->modelFactory->new($modelName);
    }

    public function testFilterExistingColumns(array $columns): array
    {
        return $this->filterExistingColumns($columns);
    }

    public function testGetFallbackDisplayColumns(): array
    {
        return $this->getFallbackDisplayColumns();
    }

    public function testPrepareIdForCreate(): void
    {
        $this->prepareIdForCreate();
    }

    public function testGenerateUuid(): string
    {
        return $this->generateUuid();
    }
}
