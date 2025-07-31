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
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Database\DatabaseConnector;
use Monolog\Logger;

/**
 * Comprehensive test suite for the refactored ModelBase class.
 * Tests all extracted methods and their interactions.
 */
class ModelBaseTest extends UnitTestCase
{
    private TestableModelBase $model;
    private array $sampleMetadata;
    private string $tempMetadataFile;

    protected function setUp(): void
    {
        parent::setUp();

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
                'deleted_by' => ['type' => 'Text']
            ],
            'displayColumns' => ['name', 'email'],
            'table' => 'test_models',
            'name' => 'Test Model'
        ];

        // Create a temporary metadata file for testing
        $this->createTempMetadataFile();

        // Create testable model instance
        $this->model = new TestableModelBase($this->logger);
        $this->model->setMockMetadataContent($this->sampleMetadata);

        // Set up mock field factory for the model
        $this->setupMockFieldFactoryForModel($this->model);

        // Now that all mocks are set up, initialize the model
        $this->model->initializeModelForTesting();
    }

    protected function tearDown(): void
    {
        // Clean up temporary metadata file
        if (file_exists($this->tempMetadataFile)) {
            unlink($this->tempMetadataFile);
        }
        parent::tearDown();
    }

    private function createTempMetadataFile(): void
    {
        $this->tempMetadataFile = tempnam(sys_get_temp_dir(), 'test_metadata_');
        $metadataContent = '<?php return ' . var_export($this->sampleMetadata, true) . ';';
        file_put_contents($this->tempMetadataFile, $metadataContent);
    }

    // ====================
    // CONSTRUCTOR TESTS
    // ====================

    /**
     * Test constructor with logger only
     */
    public function testConstructorWithLogger(): void
    {
        $model = new TestableModelBase($this->logger);
        $model->setMockMetadataContent($this->sampleMetadata);

        $this->assertInstanceOf(TestableModelBase::class, $model);
        $this->assertSame($this->logger, $model->getLogger());
    }

    /**
     * Test constructor triggers metadata loading
     */
    public function testConstructorTriggersMetadataLoading(): void
    {
        $model = $this->getMockBuilder(TestableModelBase::class)
            ->setConstructorArgs([$this->logger])
            ->onlyMethods(['ingestMetadata'])
            ->getMock();

        $model->expects($this->once())
            ->method('ingestMetadata');

        // Set up mock metadata so constructor doesn't fail
        $model->setMockMetadataContent($this->sampleMetadata);

        // Set up mock field factory for the mock model
        $this->setupMockFieldFactoryForModel($model);

        // Trigger the initialization that would normally happen in constructor
        $model->testInitializeModel();
    }

    /**
     * Test constructor with missing metadata throws exception
     */
    public function testConstructorWithMissingMetadataThrowsException(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Mock field factory must be set for testing');

        // Create model without setting mock metadata content
        $model = new TestableModelBase($this->logger);

        // Try to initialize the model, which should trigger metadata validation and throw exception
        $model->testInitializeModel();
    }

    // ====================
    // METADATA INGESTION TESTS
    // ====================

    /**
     * Test ingestMetadata loads from files
     */
    public function testIngestMetadataLoadsFromFiles(): void
    {
        $model = new TestableModelBase($this->logger);
        $model->setMockMetadataFilePaths([$this->tempMetadataFile]);

        $model->testIngestMetadata();

        $actualMetadata = $model->getMetadata();

        // Verify the original fields are present
        $this->assertArrayHasKey('fields', $actualMetadata);
        $this->assertArrayHasKey('displayColumns', $actualMetadata);
        $this->assertArrayHasKey('table', $actualMetadata);
        $this->assertArrayHasKey('name', $actualMetadata);

        // Verify original fields from sample metadata are present
        $originalFields = ['id', 'name', 'email', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'];
        foreach ($originalFields as $fieldName) {
            $this->assertArrayHasKey($fieldName, $actualMetadata['fields'], "Field '$fieldName' should be present");
        }

        // Verify core fields have been automatically added (these are the additional fields from CoreFieldsMetadata)
        $coreDisplayFields = ['created_by_name', 'updated_by_name', 'deleted_by_name'];
        foreach ($coreDisplayFields as $fieldName) {
            $this->assertArrayHasKey($fieldName, $actualMetadata['fields'], "Core field '$fieldName' should be present");
        }

        // Verify other metadata properties
        $this->assertEquals($this->sampleMetadata['displayColumns'], $actualMetadata['displayColumns']);
        $this->assertEquals($this->sampleMetadata['table'], $actualMetadata['table']);
        $this->assertEquals($this->sampleMetadata['name'], $actualMetadata['name']);
    }

    /**
     * Test loadMetadataFromFiles with existing files
     */
    public function testLoadMetadataFromFiles(): void
    {
        $model = new TestableModelBase($this->logger);

        // Create temporary metadata files
        $tempFile1 = tempnam(sys_get_temp_dir(), 'test_metadata_1');
        $tempFile2 = tempnam(sys_get_temp_dir(), 'test_metadata_2');

        file_put_contents($tempFile1, '<?php return ["fields" => ["id" => ["type" => "ID"]]];');
        file_put_contents($tempFile2, '<?php return ["fields" => ["name" => ["type" => "Text"]]];');

        $result = $model->testLoadMetadataFromFiles([$tempFile1, $tempFile2]);

        $expected = [
            'fields' => [
                'id' => ['type' => 'ID'],
                'name' => ['type' => 'Text']
            ]
        ];

        $this->assertEquals($expected, $result);

        // Cleanup
        unlink($tempFile1);
        unlink($tempFile2);
    }

    /**
     * Test loadMetadataFromFiles with non-existent files
     */
    public function testLoadMetadataFromFilesWithNonExistentFiles(): void
    {
        $model = new TestableModelBase($this->logger);

        $result = $model->testLoadMetadataFromFiles(['/non/existent/file.php']);

        $this->assertEquals([], $result);
    }

    /**
     * Test mergeMetadataArrays
     */
    public function testMergeMetadataArrays(): void
    {
        $model = new TestableModelBase($this->logger);
        $model->setMockMetadataContent([]); // Prevent constructor from throwing

        $existing = [
            'fields' => ['id' => ['type' => 'ID']],
            'table' => 'old_table'
        ];

        $new = [
            'fields' => ['name' => ['type' => 'Text']],
            'displayColumns' => ['name']
        ];

        $result = $model->testMergeMetadataArrays($existing, $new);

        $expected = [
            'fields' => [
                'id' => ['type' => 'ID'],
                'name' => ['type' => 'Text']
            ],
            'table' => 'old_table',
            'displayColumns' => ['name']
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * Test validateMetadata with valid metadata
     */
    public function testValidateMetadataWithValidData(): void
    {
        $model = new TestableModelBase($this->logger);
        $model->setMockMetadataContent([]); // Prevent constructor from throwing

        $validMetadata = ['fields' => ['id' => ['type' => 'ID']]];

        // Should not throw exception
        $model->testValidateMetadata($validMetadata);
        $this->assertTrue(true); // If we get here, validation passed
    }

    /**
     * Test validateMetadata with missing fields
     */
    public function testValidateMetadataWithMissingFields(): void
    {
        $model = new TestableModelBase($this->logger);
        $model->setMockMetadataContent([]); // Prevent constructor from throwing

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('No metadata found for model');

        $model->testValidateMetadata([]);
    }

    /**
     * Test validateMetadata with non-array fields
     */
    public function testValidateMetadataWithNonArrayFields(): void
    {
        $model = new TestableModelBase($this->logger);
        $model->setMockMetadataContent([]); // Prevent constructor from throwing

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model metadata missing fields definition');

        $model->testValidateMetadata(['fields' => 'not_an_array']);
    }

    // ====================
    // FIELD INITIALIZATION TESTS
    // ====================

    /**
     * Test initializeFields with valid field metadata
     */
    public function testInitializeFieldsWithValidMetadata(): void
    {
        $mockFieldFactory = $this->createMock(FieldFactory::class);
        $mockField = $this->createMock(FieldBase::class);

        $mockFieldFactory->expects($this->exactly(9)) // 9 fields in sample metadata
            ->method('createField')
            ->willReturn($mockField);

        $this->model->setMockFieldFactory($mockFieldFactory);
        $this->model->testInitializeFields();

        $fields = $this->model->getFields();
        $this->assertCount(9, $fields);
        $this->assertArrayHasKey('id', $fields);
        $this->assertArrayHasKey('name', $fields);
    }

    /**
     * Test createSingleField with successful field creation
     */
    public function testCreateSingleFieldSuccess(): void
    {
        $mockFieldFactory = $this->createMock(FieldFactory::class);
        $mockField = $this->createMock(FieldBase::class);

        $mockFieldFactory->expects($this->once())
            ->method('createField')
            ->with(['name' => 'test_field', 'type' => 'Text'])
            ->willReturn($mockField);

        $result = $this->model->testCreateSingleField('test_field', ['type' => 'Text'], $mockFieldFactory);

        $this->assertSame($mockField, $result);
    }

    /**
     * Test createSingleField with field creation exception
     */
    public function testCreateSingleFieldWithException(): void
    {
        $mockFieldFactory = $this->createMock(FieldFactory::class);

        $mockFieldFactory->expects($this->once())
            ->method('createField')
            ->willThrowException(new \Exception('Field creation failed'));

        $result = $this->model->testCreateSingleField('test_field', ['type' => 'Invalid'], $mockFieldFactory);

        $this->assertNull($result);
        $this->assertLoggedMessage('warning', 'Failed to create field test_field');
    }

    /**
     * Test prepareFieldMetadata
     */
    public function testPrepareFieldMetadata(): void
    {
        $fieldMeta = ['type' => 'Text', 'required' => true];
        $result = $this->model->testPrepareFieldMetadata('test_field', $fieldMeta);

        $expected = ['type' => 'Text', 'required' => true, 'name' => 'test_field'];
        $this->assertEquals($expected, $result);
    }

    // ====================
    // DISPLAY COLUMNS TESTS
    // ====================

    /**
     * Test getDisplayColumns with valid columns
     */
    public function testGetDisplayColumnsWithValidColumns(): void
    {
        $result = $this->model->getDisplayColumns();

        $this->assertEquals(['name', 'email'], $result);
    }

    /**
     * Test getDisplayColumns with non-array displayColumns
     */
    public function testGetDisplayColumnsWithNonArrayDisplayColumns(): void
    {
        $metadata = $this->sampleMetadata;
        $metadata['displayColumns'] = 'not_an_array';

        $model = new TestableModelBase($this->logger);
        $model->setMockMetadataContent($metadata);
        $this->setupMockFieldFactoryForModel($model);
        $model->initializeModelForTesting();

        $result = $model->getDisplayColumns();

        $this->assertEquals(['name'], $result);
        $this->assertLoggedMessage('warning', 'Model displayColumns should be an array');
    }

    /**
     * Test filterExistingColumns with mixed valid/invalid columns
     */
    public function testFilterExistingColumnsWithMixedColumns(): void
    {
        $columns = ['name', 'email', 'non_existent_field', 'id'];

        $result = $this->model->testFilterExistingColumns($columns);

        $this->assertEquals(['name', 'email', 'id'], $result);
        $this->assertLoggedMessage('warning', "Display column 'non_existent_field' does not exist as a field");
    }

    /**
     * Test getFallbackDisplayColumns with name field available
     */
    public function testGetFallbackDisplayColumnsWithNameField(): void
    {
        $result = $this->model->testGetFallbackDisplayColumns();

        $this->assertEquals(['name'], $result);
    }

    /**
     * Test getFallbackDisplayColumns without name field
     */
    public function testGetFallbackDisplayColumnsWithoutNameField(): void
    {
        $metadata = $this->sampleMetadata;
        unset($metadata['fields']['name']);

        $model = new TestableModelBase($this->logger);
        $model->setMockMetadataContent($metadata);
        $this->setupMockFieldFactoryForModel($model);
        $model->initializeModelForTesting();

        $result = $model->testGetFallbackDisplayColumns();

        // Should return the first available field (id in this case)
        $this->assertEquals(['id'], $result);
        $this->assertLoggedMessage('warning', 'No valid display columns found, using fallback');
    }

    // ====================
    // PERSISTENCE TESTS
    // ====================

    /**
     * Test validateForPersistence with valid model
     */
    public function testValidateForPersistenceWithValidModel(): void
    {
        $this->model->setMockValidationErrors([]);

        $result = $this->model->testValidateForPersistence();

        $this->assertTrue($result);
    }

    /**
     * Test validateForPersistence with validation errors
     */
    public function testValidateForPersistenceWithErrors(): void
    {
        $errors = ['name' => ['Name is required']];
        $this->model->setMockValidationErrors($errors);

        $result = $this->model->testValidateForPersistence();

        $this->assertFalse($result);
        $this->assertLoggedMessage('error', 'Cannot persist model with validation errors');
    }

    /**
     * Test prepareIdForCreate when ID is not set
     */
    public function testPrepareIdForCreateWithoutId(): void
    {
        $this->model->testPrepareIdForCreate();

        $id = $this->model->get('id');
        $this->assertNotNull($id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id);
    }

    /**
     * Test prepareIdForCreate when ID is already set
     */
    public function testPrepareIdForCreateWithExistingId(): void
    {
        $existingId = 'existing-id-123';
        $this->model->set('id', $existingId);

        $this->model->testPrepareIdForCreate();

        $this->assertEquals($existingId, $this->model->get('id'));
    }

    /**
     * Test persistToDatabase with create operation
     */
    public function testPersistToDatabaseWithCreate(): void
    {
        $mockDbConnector = $this->createMock(DatabaseConnector::class);
        $mockDbConnector->expects($this->once())
            ->method('create')
            ->with($this->model)
            ->willReturn(true);

        $this->model->setMockDatabaseConnector($mockDbConnector);

        $result = $this->model->testPersistToDatabase('create');

        $this->assertTrue($result);
    }

    /**
     * Test persistToDatabase with update operation
     */
    public function testPersistToDatabaseWithUpdate(): void
    {
        $mockDbConnector = $this->createMock(DatabaseConnector::class);
        $mockDbConnector->expects($this->once())
            ->method('update')
            ->with($this->model)
            ->willReturn(true);

        $this->model->setMockDatabaseConnector($mockDbConnector);

        $result = $this->model->testPersistToDatabase('update');

        $this->assertTrue($result);
    }

    /**
     * Test persistToDatabase with invalid operation
     */
    public function testPersistToDatabaseWithInvalidOperation(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Unknown persistence operation: invalid');

        $this->model->testPersistToDatabase('invalid');
    }

    // ====================
    // INTEGRATION TESTS
    // ====================

    /**
     * Test create method integration
     */
    public function testCreateMethodIntegration(): void
    {
        $mockDbConnector = $this->createMock(DatabaseConnector::class);
        $mockDbConnector->expects($this->once())
            ->method('create')
            ->willReturn(true);

        $this->model->setMockDatabaseConnector($mockDbConnector);
        $this->model->setMockValidationErrors([]);

        $result = $this->model->create();

        $this->assertTrue($result);
        $this->assertNotNull($this->model->get('id'));
        $this->assertNotNull($this->model->get('created_at'));
    }

    /**
     * Test update method integration
     */
    public function testUpdateMethodIntegration(): void
    {
        $this->model->set('id', 'test-id-123');

        $mockDbConnector = $this->createMock(DatabaseConnector::class);
        $mockDbConnector->expects($this->once())
            ->method('update')
            ->willReturn(true);

        $this->model->setMockDatabaseConnector($mockDbConnector);
        $this->model->setMockValidationErrors([]);

        $result = $this->model->update();

        $this->assertTrue($result);
        $this->assertNotNull($this->model->get('updated_at'));
    }

    /**
     * Test update method without ID throws exception
     */
    public function testUpdateMethodWithoutIdThrowsException(): void
    {
        $this->model->setMockValidationErrors([]);

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Cannot update model without ID field set');

        $this->model->update();
    }

    // ====================
    // UTILITY TESTS
    // ====================

    /**
     * Test generateUuid creates valid UUIDs
     */
    public function testGenerateUuidCreatesValidUuids(): void
    {
        $uuid1 = $this->model->testGenerateUuid();
        $uuid2 = $this->model->testGenerateUuid();

        // Should be different
        $this->assertNotEquals($uuid1, $uuid2);

        // Should match UUID v4 format
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid1);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid2);
    }

    /**
     * Test getTableName with metadata table
     */
    public function testGetTableNameWithMetadataTable(): void
    {
        $tableName = $this->model->getTableName();

        $this->assertEquals('test_models', $tableName);
    }

    /**
     * Test getTableName without metadata table
     */
    public function testGetTableNameWithoutMetadataTable(): void
    {
        $metadata = $this->sampleMetadata;
        unset($metadata['table']);

        $model = new TestableModelBase($this->logger);
        $model->setMockMetadataContent($metadata);

        $tableName = $model->getTableName();

        // Should use lowercased class name
        $this->assertEquals(strtolower(TestableModelBase::class), $tableName);
    }

    /**
     * Test field get/set operations
     */
    public function testFieldGetSetOperations(): void
    {
        // Debug field initialization
        $debug = $this->model->debugFieldsInitialization();

        // Add debug output to understand what's happening
        if ($debug['actual_fields_count'] === 0) {
            $this->fail('Fields not initialized properly. Debug info: ' . json_encode($debug));
        }

        $this->model->set('name', 'Test Name');
        $this->assertEquals('Test Name', $this->model->get('name'));

        $this->model->set('email', 'test@example.com');
        $this->assertEquals('test@example.com', $this->model->get('email'));
    }

    /**
     * Test setting non-existent field throws exception
     */
    public function testSetNonExistentFieldThrowsException(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Field non_existent not found in model');

        $this->model->set('non_existent', 'value');
    }

    /**
     * Test hasField method
     */
    public function testHasFieldMethod(): void
    {
        $this->assertTrue($this->model->hasField('name'));
        $this->assertTrue($this->model->hasField('email'));
        $this->assertFalse($this->model->hasField('non_existent'));
    }

    /**
     * Test getMetaDataFilePaths returns correct path
     */
    public function testGetMetaDataFilePathsReturnsCorrectPath(): void
    {
        $paths = $this->model->testGetMetaDataFilePaths();

        $expectedPath = 'src/Models/TestableModelBase/testablemodelbase_metadata.php';
        $this->assertEquals([$expectedPath], $paths);
    }

    private function createMockFieldFactory(): FieldFactory
    {
        // Create a mock FieldFactory using PHPUnit's mocking system
        $mockFieldFactory = $this->createMock(FieldFactory::class);

        // Configure the mock to return a mock FieldBase when createField is called
        $mockFieldFactory->method('createField')
            ->willReturnCallback(function(array $metadata) {
                // Create a simple mock field that implements the basic field interface
                $mockField = new class($metadata) extends FieldBase {
                    protected array $metadata;
                    protected $value = null;

                    public function __construct(array $metadata) {
                        $this->metadata = $metadata;
                        // Skip parent constructor to avoid dependencies
                    }

                    public function getName(): string {
                        return $this->metadata['name'] ?? 'mock_field';
                    }

                    public function getValue() {
                        return $this->value;
                    }

                    public function setValue($value): void {
                        $this->value = $value;
                    }

                    public function validate(): bool {
                        return true;
                    }

                    public function getValidationErrors(): array {
                        return [];
                    }

                    public function render(): string {
                        return (string) $this->value;
                    }

                    public function getType(): string {
                        return $this->metadata['type'] ?? 'Text';
                    }

                    // Add missing abstract methods from FieldBase
                    protected function setUpValidationRules(): void {
                        // Mock implementation - do nothing
                    }
                };

                return $mockField;
            });

        return $mockFieldFactory;
    }

    private function setupMockFieldFactoryForModel(TestableModelBase $model): void
    {
        $mockFieldFactory = $this->createMockFieldFactory();
        $model->setMockFieldFactory($mockFieldFactory);
    }
}

/**
 * Testable concrete implementation of ModelBase for unit testing
 */
class TestableModelBase extends ModelBase
{
    private ?FieldFactory $mockFieldFactory = null;
    private ?DatabaseConnector $mockDatabaseConnector = null;
    private array $mockValidationErrors = [];
    private array $mockMetadataContent = [];
    private array $mockMetadataFilePaths = [];

    public function __construct(Logger $logger)
    {
        // Initialize basic properties first
        $this->logger = $logger;
        $this->metadata = [];
        $this->fields = [];
        $this->relationships = [];
        $this->validationRules = [];
        $this->deleted = false;
        $this->deletedAt = null;
        $this->deletedBy = null;

        // Only call full initialization if we have mock metadata content set
        // This allows the test for missing metadata to work properly
        if (!empty($this->mockMetadataContent)) {
            $this->initializeModel();
        }
    }

    // Override metadata loading for testing
    protected function loadMetadataFromFiles(array $filePaths): array
    {
        // If mock metadata file paths are set, use the parent implementation
        if (!empty($this->mockMetadataFilePaths)) {
            return parent::loadMetadataFromFiles($this->mockMetadataFilePaths);
        }

        // If mock metadata content is set, return it
        if (!empty($this->mockMetadataContent)) {
            return $this->mockMetadataContent;
        }

        // Default to parent implementation for real file testing
        return parent::loadMetadataFromFiles($filePaths);
    }

    // Override field initialization for testing
    protected function initializeFields(): void
    {
        if (!isset($this->metadata['fields'])) {
            throw new GCException('Model metadata missing fields definition',
                ['model_class' => static::class, 'metadata' => $this->metadata]);
        }

        // Use mock field factory if available, otherwise throw an exception
        // since we can't create real field factories in unit tests
        if (!$this->mockFieldFactory) {
            throw new GCException('Mock field factory must be set for testing',
                ['model_class' => static::class]);
        }

        foreach ($this->metadata['fields'] as $fieldName => $fieldMeta) {
            $field = $this->createSingleField($fieldName, $fieldMeta, $this->mockFieldFactory);
            if ($field !== null) {
                $this->fields[$fieldName] = $field;
            }
        }
    }


    // Override database connector access
    protected function persistToDatabase(string $operation): bool
    {
        if ($this->mockDatabaseConnector) {
            return match($operation) {
                'create' => $this->mockDatabaseConnector->create($this),
                'update' => $this->mockDatabaseConnector->update($this),
                default => throw new GCException("Unknown persistence operation: $operation", [
                    'operation' => $operation,
                    'model_class' => static::class
                ])
            };
        }

        return parent::persistToDatabase($operation);
    }

    // Override validation errors for testing
    protected function getValidationErrors(): array
    {
        return $this->mockValidationErrors;
    }

    // Test helper methods
    public function setMockFieldFactory(FieldFactory $factory): void
    {
        $this->mockFieldFactory = $factory;
    }

    public function setMockDatabaseConnector(DatabaseConnector $connector): void
    {
        $this->mockDatabaseConnector = $connector;
    }

    public function setMockValidationErrors(array $errors): void
    {
        $this->mockValidationErrors = $errors;
    }

    public function setMockMetadataContent(array $metadata): void
    {
        $this->mockMetadataContent = $metadata;
        $this->metadata = $metadata;

        // Don't initialize the model automatically here since mock factory might not be set yet
        // The test will need to call initializeModel() explicitly after setting up mocks
    }

    public function setMockMetadataFilePaths(array $filePaths): void
    {
        $this->mockMetadataFilePaths = $filePaths;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    // Add method to initialize model for testing after all mocks are set up
    public function initializeModelForTesting(): void
    {
        if (!empty($this->metadata['fields']) && empty($this->fields) && $this->mockFieldFactory) {
            $this->initializeFields();
        }
    }

    // Add a method to check if fields were properly initialized
    public function debugFieldsInitialization(): array
    {
        return [
            'metadata_fields_count' => count($this->metadata['fields'] ?? []),
            'actual_fields_count' => count($this->fields),
            'field_names' => array_keys($this->fields),
            'has_mock_factory' => $this->mockFieldFactory !== null
        ];
    }

    // Add method to expose protected initializeModel for testing
    public function testInitializeModel(): void
    {
        $this->initializeModel();
    }

    // Expose protected methods for testing
    public function testIngestMetadata(): void
    {
        $this->ingestMetadata();
    }

    public function testLoadMetadataFromFiles(array $filePaths): array
    {
        return $this->loadMetadataFromFiles($filePaths);
    }

    public function testMergeMetadataArrays(array $existing, array $new): array
    {
        return $this->mergeMetadataArrays($existing, $new);
    }

    public function testValidateMetadata(array $metadata): void
    {
        $this->validateMetadata($metadata);
    }

    public function testInitializeFields(): void
    {
        $this->initializeFields();
    }

    public function testCreateSingleField(string $fieldName, array $fieldMeta, $fieldFactory): ?FieldBase
    {
        return $this->createSingleField($fieldName, $fieldMeta, $fieldFactory);
    }

    public function testPrepareFieldMetadata(string $fieldName, array $fieldMeta): array
    {
        return $this->prepareFieldMetadata($fieldName, $fieldMeta);
    }

    public function testFilterExistingColumns(array $columns): array
    {
        return $this->filterExistingColumns($columns);
    }

    public function testGetFallbackDisplayColumns(): array
    {
        return $this->getFallbackDisplayColumns();
    }

    public function testValidateForPersistence(): bool
    {
        return $this->validateForPersistence();
    }

    public function testPrepareIdForCreate(): void
    {
        $this->prepareIdForCreate();
    }

    public function testPersistToDatabase(string $operation): bool
    {
        return $this->persistToDatabase($operation);
    }

    public function testGenerateUuid(): string
    {
        return $this->generateUuid();
    }

    public function testGetMetaDataFilePaths(): array
    {
        return $this->getMetaDataFilePaths();
    }
}
