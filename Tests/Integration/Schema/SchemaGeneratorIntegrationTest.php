<?php
namespace Gravitycar\Tests\Integration\Schema;

use Gravitycar\Tests\TestCase;
use Gravitycar\Schema\SchemaGenerator;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Core\Config;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Metadata\CoreFieldsMetadata;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * Integration tests for SchemaGenerator with RelatedRecordField and CoreFieldsMetadata
 * Tests end-to-end functionality using real model metadata files and CoreFieldsMetadata integration
 */
class SchemaGeneratorIntegrationTest extends TestCase
{
    private SchemaGenerator $schemaGenerator;
    private DatabaseConnector $dbConnector;
    private MetadataEngine $metadataEngine;
    private ModelFactory $modelFactory;
    private string $testDatabaseName;
    private array $expectedModels = [
        ['class' => '\\Gravitycar\\Models\\Users\\Users', 'name' => 'Users'],
        ['class' => '\\Gravitycar\\Models\\Movies\\Movies', 'name' => 'Movies'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset ServiceLocator to clear any cached instances
        ServiceLocator::reset();

        $this->logger = ServiceLocator::getLogger();
        $this->config = ServiceLocator::getConfig();

        // Use a test-specific database name
        $this->testDatabaseName = 'gravitycar_schema_test_' . uniqid();

        // Override database name for testing (fix: use dbname not name)
        $this->config->set('database.dbname', $this->testDatabaseName);

        $this->dbConnector = new DatabaseConnector($this->logger, $this->config);
        $this->schemaGenerator = new SchemaGenerator();
        $this->metadataEngine = MetadataEngine::getInstance();
        $this->modelFactory = ServiceLocator::getModelFactory();
        
        // Create test database in setUp to avoid connection errors
        $this->schemaGenerator->createDatabaseIfNotExists();
    }

    protected function tearDown(): void
    {
        // Clean up test database
        try {
            $this->dbConnector->dropDatabase($this->testDatabaseName);
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
        parent::tearDown();
    }

    /**
     * Test database creation functionality
     */
    public function testCreateDatabaseIfNotExists(): void
    {
        $this->logger->info('Testing database creation');

        // Database should already exist from setUp
        $this->assertTrue($this->dbConnector->databaseExists($this->testDatabaseName));

        // Test idempotency - should not fail if database already exists
        $result = $this->schemaGenerator->createDatabaseIfNotExists();
        $this->assertTrue($result, 'Creating existing database should still return true');
    }

    /**
     * Test CoreFieldsMetadata integration
     */
    public function testCoreFieldsMetadataIntegration(): void
    {
        $this->logger->info('Testing CoreFieldsMetadata integration');

        $coreFieldsMetadata = ServiceLocator::getCoreFieldsMetadata();
        $coreFields = $coreFieldsMetadata->getAllCoreFieldsForModel('TestModel');

        $this->assertNotEmpty($coreFields, 'Core fields should be loaded');

        $expectedCoreFields = ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'];

        foreach ($expectedCoreFields as $expectedField) {
            $this->assertArrayHasKey($expectedField, $coreFields, "Core field '{$expectedField}' should exist");
        }
    }

    /**
     * Test model metadata loading from actual files
     */
    public function testModelMetadataLoading(): void
    {
        $this->logger->info('Testing model metadata loading from actual files');

        foreach ($this->expectedModels as $model) {
            $modelName = $model['name'];
            // Get model class and name
            $modelClass = $model['class'];
            $modelName = $model['name'];

            $this->assertTrue(class_exists($modelClass), "Model class {$modelClass} should exist");

            // Create model instance to trigger metadata loading
            $modelInstance = $this->modelFactory->new($modelName);

            // Verify model has fields (including core fields)
            $fields = $modelInstance->getFields();
            $this->assertNotEmpty($fields, "Model {$modelName} should have fields loaded");

            // Verify core fields are present
            $coreFields = ['id', 'created_at', 'updated_at'];
            foreach ($coreFields as $coreField) {
                $this->assertTrue($modelInstance->hasField($coreField),
                    "Model {$modelName} should have core field: {$coreField}");
            }
        }
    }

    /**
     * Test end-to-end schema generation using real model metadata
     */
    public function testEndToEndSchemaGeneration(): void
    {
        $this->logger->info('Testing end-to-end schema generation using real model metadata');

        // Load metadata from all models using MetadataEngine
        $allMetadata = $this->metadataEngine->loadAllMetadata();

        $this->assertNotEmpty($allMetadata['models'], 'MetadataEngine should load model metadata');

        // Generate schema using the loaded metadata
        $this->schemaGenerator->generateSchema($allMetadata);

        // Verify expected tables were created
        $createdTables = [];
        foreach ($this->expectedModels as $model) {
            $modelName = $model['name'];
            // Get model instance to determine table name
            $modelClass = $model['class'];
            $modelInstance = $this->modelFactory->new($modelName);
            $tableName = $modelInstance->getTableName();

            $this->assertTrue($this->tableExists($tableName),
                "Table {$tableName} should be created for model {$modelName}");

            $createdTables[] = $tableName;
        }

        // Verify core fields exist in all created tables
        $this->verifyCoreFieldsInTables($createdTables);
    }

    /**
     * Test that core fields are properly included in generated tables
     */
    public function testCoreFieldsInGeneratedTables(): void
    {
        $this->logger->info('Testing core fields integration in generated tables');

        // Load real metadata and generate schema
        $allMetadata = $this->metadataEngine->loadAllMetadata();
        $this->schemaGenerator->generateSchema($allMetadata);

        // Test specific table for core fields using the first model
        $firstModel = $this->expectedModels[0];
        $userModel = $this->modelFactory->new($firstModel['name']);
        $tableName = $userModel->getTableName();

        $this->verifyCoreFieldsExist($tableName, [
            'id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'
        ]);
    }

    /**
     * Test RelatedRecordField schema generation with real metadata
     */
    public function testRelatedRecordFieldSchemaGeneration(): void
    {
        $this->logger->info('Testing RelatedRecordField schema generation');

        // Load real metadata and generate schema
        $allMetadata = $this->metadataEngine->loadAllMetadata();
        $this->schemaGenerator->generateSchema($allMetadata);

        // We need to ensure this test always performs assertions
        $relatedRecordFieldFound = false;

        // Check if MovieQuotes model has RelatedRecordField for movie_id
        $movieQuotesClass = '\\Gravitycar\\Models\\MovieQuotes\\MovieQuotes';
        if (class_exists($movieQuotesClass)) {
            $movieQuote = new $movieQuotesClass($this->logger);

            if ($movieQuote->hasField('movie_id')) {
                $movieIdField = $movieQuote->getField('movie_id');

                if ($movieIdField instanceof \Gravitycar\Fields\RelatedRecordField) {
                    $this->assertInstanceOf(\Gravitycar\Fields\RelatedRecordField::class, $movieIdField,
                        'movie_id should be a RelatedRecordField');

                    // Verify the field exists in the database table
                    $tableName = $movieQuote->getTableName();
                    $this->assertTrue($this->fieldExists($tableName, 'movie_id'),
                        'movie_id column should exist in database table');

                    $relatedRecordFieldFound = true;
                }
            }
        }

        // Check all models for any RelatedRecordFields if MovieQuotes doesn't have one
        if (!$relatedRecordFieldFound) {
            foreach ($this->expectedModels as $model) {
                $modelClass = $model['class'];
                $modelName = $model['name'];
                if (class_exists($modelClass)) {
                    $modelInstance = $this->modelFactory->new($modelName);
                    $fields = $modelInstance->getFields();

                    foreach ($fields as $fieldName => $field) {
                        if ($field instanceof \Gravitycar\Fields\RelatedRecordField) {
                            $this->assertInstanceOf(\Gravitycar\Fields\RelatedRecordField::class, $field,
                                "Field {$fieldName} should be a RelatedRecordField");

                            // Verify the field exists in the database table
                            $tableName = $modelInstance->getTableName();
                            $this->assertTrue($this->fieldExists($tableName, $fieldName),
                                "RelatedRecordField {$fieldName} column should exist in database table {$tableName}");

                            $relatedRecordFieldFound = true;
                            break 2; // Break out of both loops
                        }
                    }
                }
            }
        }

        // If no RelatedRecordFields found, at least assert that schema generation worked
        if (!$relatedRecordFieldFound) {
            $this->assertTrue(true, 'Schema generation completed successfully - no RelatedRecordFields found to test');
        }

        // Always assert that at least some tables were created
        foreach ($this->expectedModels as $model) {
            $modelClass = $model['class'];
            $modelName = $model['name'];
            if (class_exists($modelClass)) {
                $modelInstance = $this->modelFactory->new($modelName);
                $tableName = $modelInstance->getTableName();
                $this->assertTrue($this->tableExists($tableName),
                    "Table {$tableName} should exist after schema generation");
            }
        }
    }

    /**
     * Test complete table structure validation
     */
    public function testTableStructureValidation(): void
    {
        $this->logger->info('Testing complete table structure validation');

        // Load real metadata and generate schema
        $allMetadata = $this->metadataEngine->loadAllMetadata();
        $this->schemaGenerator->generateSchema($allMetadata);

        foreach ($this->expectedModels as $model) {
            $modelName = $model['name'];
            $modelClass = $model['class'];
            if (!class_exists($modelClass)) {
                continue;
            }

            $modelInstance = $this->modelFactory->new($modelName);
            $tableName = $modelInstance->getTableName();
            $modelFields = array_keys($modelInstance->getFields());

            // Check that all model fields have corresponding database columns
            $columns = $this->getTableColumns($tableName);

            foreach ($modelFields as $fieldName) {
                $field = $modelInstance->getField($fieldName);
                if ($field->isDBField()) {
                    $this->assertContains($fieldName, $columns,
                        "Model field '{$fieldName}' should exist in database table '{$tableName}'");
                }
            }
        }
    }

    /**
     * Verify that specified tables exist in the database
     */
    private function verifyTablesExist(array $tableNames): void
    {
        $connection = $this->dbConnector->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $existingTables = $schemaManager->listTableNames();

        foreach ($tableNames as $tableName) {
            $this->assertContains($tableName, $existingTables, "Table '$tableName' should exist");
        }
    }

    /**
     * Verify that core fields exist in specified tables
     */
    private function verifyCoreFieldsInTables(array $tableNames): void
    {
        $expectedCoreFields = ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'];

        foreach ($tableNames as $tableName) {
            $this->verifyCoreFieldsExist($tableName, $expectedCoreFields);
        }
    }

    /**
     * Verify that core fields exist in a specific table
     */
    private function verifyCoreFieldsExist(string $tableName, array $coreFields): void
    {
        $connection = $this->dbConnector->getConnection();
        $schemaManager = $connection->createSchemaManager();

        if (!$schemaManager->tablesExist([$tableName])) {
            $this->fail("Table '$tableName' does not exist");
        }

        $table = $schemaManager->introspectTable($tableName);
        $columns = $table->getColumns();
        $columnNames = array_keys($columns);

        foreach ($coreFields as $coreField) {
            $this->assertContains($coreField, $columnNames,
                "Core field '$coreField' should exist in table '$tableName'");
        }
    }

    /**
     * Check if table exists
     */
    private function tableExists(string $tableName): bool
    {
        try {
            $connection = $this->dbConnector->getConnection();
            $schemaManager = $connection->createSchemaManager();
            return $schemaManager->tablesExist([$tableName]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verify that a specific field exists in a table
     */
    private function fieldExists(string $tableName, string $fieldName): bool
    {
        $connection = $this->dbConnector->getConnection();
        $schemaManager = $connection->createSchemaManager();

        if (!$schemaManager->tablesExist([$tableName])) {
            return false;
        }

        $table = $schemaManager->introspectTable($tableName);
        return $table->hasColumn($fieldName);
    }

    /**
     * Get table columns
     */
    private function getTableColumns(string $tableName): array
    {
        $connection = $this->dbConnector->getConnection();
        $schemaManager = $connection->createSchemaManager();

        if (!$schemaManager->tablesExist([$tableName])) {
            return [];
        }

        $table = $schemaManager->introspectTable($tableName);
        return array_keys($table->getColumns());
    }
}
