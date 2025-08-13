<?php

namespace Gravitycar\Tests\Unit\Database;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Fields\FieldBase;
use Gravitycar\Fields\TextField;
use Gravitycar\Fields\IDField;
use Gravitycar\Fields\RelatedRecordField;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Factories\FieldFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Monolog\Logger;

/**
 * Comprehensive test suite for the refactored DatabaseConnector class.
 * Tests all extracted methods and their interactions.
 */
class DatabaseConnectorTest extends UnitTestCase
{
    private DatabaseConnector $connector;
    private array $dbParams;
    private $mockConnection;
    private $mockQueryBuilder;
    private $mockResult;
    private TestableModel $testModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbParams = [
            'driver' => 'pdo_mysql',
            'host' => 'localhost',
            'dbname' => 'test_db',
            'user' => 'test_user',
            'password' => 'test_pass'
        ];

        // Create mock connection and query builder
        $this->mockConnection = $this->createMock(Connection::class);
        $this->mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $this->mockResult = $this->createMock(Result::class);

        // Set up mock connection to return query builder
        $this->mockConnection->method('createQueryBuilder')
            ->willReturn($this->mockQueryBuilder);

        // Create DatabaseConnector instance
        $this->connector = new TestableDatabaseConnector($this->logger, $this->dbParams);
        $this->connector->setMockConnection($this->mockConnection);

        // Create a test model for testing with mock fields
        $this->testModel = new TestableModel($this->logger);
        $mockFields = [
            'id' => $this->createMockField('id', IDField::class),
            'name' => $this->createMockField('name', TextField::class)
        ];
        $this->testModel->setFields($mockFields);
    }

    // ====================
    // CONSTRUCTOR AND CONNECTION TESTS
    // ====================

    /**
     * Test constructor sets properties correctly
     */
    public function testConstructorSetsProperties(): void
    {
        $connector = new DatabaseConnector($this->logger, $this->dbParams);

        $this->assertInstanceOf(DatabaseConnector::class, $connector);
    }

    /**
     * Test getConnection returns connection instance
     */
    public function testGetConnectionReturnsConnection(): void
    {
        $connection = $this->connector->getConnection();

        $this->assertSame($this->mockConnection, $connection);
    }

    /**
     * Test testConnection with successful connection
     */
    public function testTestConnectionSuccess(): void
    {
        $this->mockConnection->method('connect')->willReturn(true);
        $this->mockConnection->method('isConnected')->willReturn(true);

        $result = $this->connector->testConnection();

        $this->assertTrue($result);
    }

    /**
     * Test testConnection with failed connection
     */
    public function testTestConnectionFailure(): void
    {
        $this->mockConnection->method('connect')
            ->willThrowException(new \Exception('Connection failed'));

        $result = $this->connector->testConnection();

        $this->assertFalse($result);
        $this->assertLoggedMessage('error', 'Database connection test failed');
    }

    // ====================
    // SETUP QUERY BUILDER TESTS
    // ====================

    /**
     * Test setupQueryBuilder returns correct model information
     */
    public function testSetupQueryBuilder(): void
    {
        // Mock ServiceLocator to return our test model
        $mockServiceLocator = $this->createMock(ServiceLocator::class);
        $mockServiceLocator->method('get')
            ->with(TestableModel::class)
            ->willReturn($this->testModel);

        $result = $this->connector->testSetupQueryBuilder(TestableModel::class);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('tableName', $result);
        $this->assertArrayHasKey('mainAlias', $result);
        $this->assertArrayHasKey('modelFields', $result);

        $this->assertInstanceOf(TestableModel::class, $result['model']);
        $this->assertEquals('test_models', $result['tableName']);
        $this->assertEquals('test_models', $result['mainAlias']);
        $this->assertIsArray($result['modelFields']);
    }

    // ====================
    // SELECT CLAUSE BUILDING TESTS
    // ====================

    /**
     * Test buildSelectClause with regular fields
     */
    public function testBuildSelectClauseWithRegularFields(): void
    {
        $fields = ['id', 'name'];
        $modelFields = [
            'id' => $this->createMockField('id', IDField::class),
            'name' => $this->createMockField('name', TextField::class)
        ];

        $this->mockQueryBuilder->expects($this->once())
            ->method('select')
            ->with('test_models.id, test_models.name');

        $this->connector->testBuildSelectClause(
            $this->mockQueryBuilder,
            $this->testModel,
            $modelFields,
            $fields
        );
    }

    /**
     * Test buildSelectClause with empty fields (select all)
     */
    public function testBuildSelectClauseWithAllFields(): void
    {
        $modelFields = [
            'id' => $this->createMockField('id', IDField::class),
            'name' => $this->createMockField('name', TextField::class)
        ];

        $this->mockQueryBuilder->expects($this->once())
            ->method('select')
            ->with('test_models.id, test_models.name');

        $this->connector->testBuildSelectClause(
            $this->mockQueryBuilder,
            $this->testModel,
            $modelFields,
            [] // Empty fields array should select all
        );
    }

    /**
     * Test buildSelectClause with RelatedRecordField
     */
    public function testBuildSelectClauseWithRelatedRecordField(): void
    {
        $relatedField = $this->createMock(RelatedRecordField::class);
        $relatedField->method('getName')->willReturn('related_id');

        // Add the correct method name that DatabaseConnector actually calls
        $relatedField->method('getRelatedModelInstance')->willThrowException(new \Exception('Failed to get related model'));

        $modelFields = [
            'id' => $this->createMockField('id', IDField::class),
            'related_id' => $relatedField
        ];

        // Mock the handleRelatedRecordField method to return expected fields
        // This simulates the fallback behavior when getRelatedModelInstance fails
        $this->connector->setMockRelatedRecordFields(['test_models.related_id']);

        $this->mockQueryBuilder->expects($this->once())
            ->method('select')
            ->with('test_models.id, test_models.related_id');

        $this->connector->testBuildSelectClause(
            $this->mockQueryBuilder,
            $this->testModel,
            $modelFields,
            ['id', 'related_id']
        );
    }

    // ====================
    // CRITERIA APPLICATION TESTS
    // ====================

    /**
     * Test applyCriteria with simple criteria
     */
    public function testApplyCriteriaWithSimpleCriteria(): void
    {
        $criteria = [
            'name' => 'Test Name',
            'status' => 'active'
        ];

        // PHPUnit 10 compatible - separate expectations
        $this->mockQueryBuilder->expects($this->exactly(2))
            ->method('andWhere');

        $this->mockQueryBuilder->expects($this->exactly(2))
            ->method('setParameter');

        $this->connector->testApplyCriteria($this->mockQueryBuilder, $criteria, 'test_models');
    }

    /**
     * Test applyCriteria with array values (IN clause)
     */
    public function testApplyCriteriaWithArrayValues(): void
    {
        $criteria = [
            'status' => ['active', 'pending', 'inactive']
        ];

        $this->mockQueryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('test_models.status IN (:status)');

        $this->mockQueryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('status', ['active', 'pending', 'inactive']);

        $this->connector->testApplyCriteria($this->mockQueryBuilder, $criteria, 'test_models');
    }

    // ====================
    // QUERY PARAMETERS TESTS
    // ====================

    /**
     * Test applyQueryParameters with ORDER BY
     */
    public function testApplyQueryParametersWithOrderBy(): void
    {
        $parameters = [
            'orderBy' => ['name' => 'ASC', 'created_at' => 'DESC']
        ];

        // PHPUnit 10 compatible - just verify method is called correct number of times
        $this->mockQueryBuilder->expects($this->exactly(2))
            ->method('orderBy');

        $this->connector->testApplyQueryParameters($this->mockQueryBuilder, $parameters, 'test_models');
    }

    /**
     * Test applyQueryParameters with LIMIT and OFFSET
     */
    public function testApplyQueryParametersWithLimitAndOffset(): void
    {
        $parameters = [
            'limit' => 10,
            'offset' => 20
        ];

        $this->mockQueryBuilder->expects($this->once())
            ->method('setMaxResults')
            ->with(10);

        $this->mockQueryBuilder->expects($this->once())
            ->method('setFirstResult')
            ->with(20);

        $this->connector->testApplyQueryParameters($this->mockQueryBuilder, $parameters, 'test_models');
    }

    // ====================
    // FIND METHOD TESTS
    // ====================

    /**
     * Test find method returns raw database rows
     */
    public function testFindReturnsRawRows(): void
    {
        $expectedRows = [
            ['id' => 1, 'name' => 'Test 1'],
            ['id' => 2, 'name' => 'Test 2']
        ];

        // Set up mock query builder chain
        $this->mockQueryBuilder->method('from')->willReturnSelf();
        $this->mockQueryBuilder->method('select')->willReturnSelf();
        $this->mockQueryBuilder->method('executeQuery')->willReturn($this->mockResult);

        $this->mockResult->method('fetchAllAssociative')->willReturn($expectedRows);

        // Mock ServiceLocator
        $this->connector->setMockServiceLocator($this->testModel);

        $result = $this->connector->find(TestableModel::class);

        $this->assertEquals($expectedRows, $result);
        $this->assertLoggedMessage('debug', 'Database find operation completed');
    }

    /**
     * Test findById returns single row or null
     */
    public function testFindByIdReturnsSingleRow(): void
    {
        $expectedRow = ['id' => 1, 'name' => 'Test 1'];

        // Mock the find method to return array with one row
        $connector = $this->getMockBuilder(TestableDatabaseConnector::class)
            ->setConstructorArgs([$this->logger, $this->dbParams])
            ->onlyMethods(['find'])
            ->getMock();

        $connector->method('find')
            ->with(TestableModel::class, ['id' => 1], [], ['limit' => 1])
            ->willReturn([$expectedRow]);

        $result = $connector->findById(TestableModel::class, 1);

        $this->assertEquals($expectedRow, $result);
    }

    /**
     * Test findById returns null when no results
     */
    public function testFindByIdReturnsNullWhenNoResults(): void
    {
        // Mock the find method to return empty array
        $connector = $this->getMockBuilder(TestableDatabaseConnector::class)
            ->setConstructorArgs([$this->logger, $this->dbParams])
            ->onlyMethods(['find'])
            ->getMock();

        $connector->method('find')
            ->with(TestableModel::class, ['id' => 999], [], ['limit' => 1])
            ->willReturn([]);

        $result = $connector->findById(TestableModel::class, 999);

        $this->assertNull($result);
    }

    // ====================
    // CRUD OPERATION TESTS
    // ====================

    /**
     * Test create method with valid data
     */
    public function testCreateWithValidData(): void
    {
        $this->mockQueryBuilder->method('insert')->willReturnSelf();
        $this->mockQueryBuilder->method('setValue')->willReturnSelf();
        $this->mockQueryBuilder->method('setParameter')->willReturnSelf();
        $this->mockQueryBuilder->method('executeStatement')->willReturn(1);

        $this->mockConnection->method('lastInsertId')->willReturn('123');

        $result = $this->connector->create($this->testModel);

        $this->assertTrue($result);
        $this->assertLoggedMessage('info', 'Model created successfully');
    }

    /**
     * Test create method with no data throws exception
     */
    public function testCreateWithNoDataThrowsException(): void
    {
        // Create a model with no database fields
        $emptyModel = new EmptyTestableModel($this->logger);

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('No database fields to insert');

        $this->connector->create($emptyModel);
    }

    /**
     * Test update method with valid data
     */
    public function testUpdateWithValidData(): void
    {
        $this->testModel->set('id', '123');
        $this->testModel->set('name', 'Updated Name');

        $this->mockQueryBuilder->method('update')->willReturnSelf();
        $this->mockQueryBuilder->method('set')->willReturnSelf();
        $this->mockQueryBuilder->method('setParameter')->willReturnSelf();
        $this->mockQueryBuilder->method('where')->willReturnSelf();
        $this->mockQueryBuilder->method('executeStatement')->willReturn(1);

        $result = $this->connector->update($this->testModel);

        $this->assertTrue($result);
        $this->assertLoggedMessage('info', 'Model updated successfully');
    }

    /**
     * Test update method without ID throws exception
     */
    public function testUpdateWithoutIdThrowsException(): void
    {
        // Create a model with an empty ID field for this specific test
        $testModelWithoutId = new TestableModel($this->logger);

        // Create mock ID field that returns empty value
        $emptyIdField = $this->createMock(IDField::class);
        $emptyIdField->method('getName')->willReturn('id');
        $emptyIdField->method('isDBField')->willReturn(true);
        $emptyIdField->method('getValue')->willReturn(''); // Empty ID value

        // Create regular name field
        $nameField = $this->createMockField('name', TextField::class);

        $mockFields = [
            'id' => $emptyIdField,
            'name' => $nameField
        ];
        $testModelWithoutId->setFields($mockFields);

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Cannot update model without ID');
        $this->connector->update($testModelWithoutId);
    }

    // ====================
    // HELPER METHODS
    // ====================

    private function createMockField(string $name, string $class): FieldBase
    {
        $field = $this->createMock($class);
        $field->method('getName')->willReturn($name);
        $field->method('isDBField')->willReturn(true);

        // Add getValue method to return a test value for database operations
        $field->method('getValue')->willReturnCallback(function() use ($name) {
            // Return test values based on field name
            return match($name) {
                'id' => '123',
                'name' => 'Test Name',
                default => 'test_value'
            };
        });

        return $field;
    }

    /**
     * Test that non-database fields are properly filtered from SELECT clause
     */
    public function testBuildSelectClauseFiltersNonDBFields(): void
    {
        // Create a mock DB field (should be included)
        $dbField = $this->createMock(TextField::class);
        $dbField->method('isDBField')->willReturn(true);

        // Create a mock non-DB field (should be excluded)
        $nonDbField = $this->createMock(TextField::class);
        $nonDbField->method('isDBField')->willReturn(false);

        // Set up model fields
        $fields = [
            'name' => $dbField,
            'computed_field' => $nonDbField
        ];

        $this->testModel->setFields($fields);

        // Mock QueryBuilder to capture what gets selected
        $capturedSelect = null;
        $this->mockQueryBuilder->method('select')
            ->willReturnCallback(function($select) use (&$capturedSelect) {
                $capturedSelect = $select;
                return $this->mockQueryBuilder;
            });

        // Test with empty fields array (should select all eligible fields)
        $this->connector->testBuildSelectClause(
            $this->mockQueryBuilder,
            $this->testModel,
            $fields,
            [] // empty = select all
        );

        // Should only include the database field
        $this->assertEquals('test_models.name', $capturedSelect);

        // Reset for next test
        $capturedSelect = null;

        // Test with specific fields requested
        $this->connector->testBuildSelectClause(
            $this->mockQueryBuilder,
            $this->testModel,
            $fields,
            ['name', 'computed_field'] // both requested
        );

        // Should still only include the database field
        $this->assertEquals('test_models.name', $capturedSelect);
    }

    /**
     * Test that WHERE criteria properly validates database fields
     */
    public function testApplyCriteriaFiltersNonDBFields(): void
    {
        // Create mock fields
        $dbField = $this->createMock(TextField::class);
        $dbField->method('isDBField')->willReturn(true);

        $nonDbField = $this->createMock(TextField::class);
        $nonDbField->method('isDBField')->willReturn(false);

        $modelFields = [
            'name' => $dbField,
            'computed_field' => $nonDbField
        ];

        $criteria = [
            'name' => 'test',
            'computed_field' => 'should_be_ignored'
        ];

        // Mock query builder expectations - should only get WHERE for database field
        $this->mockQueryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('test_alias.name = :name');

        $this->mockQueryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('name', 'test');

        $this->connector->testApplyCriteria(
            $this->mockQueryBuilder,
            $criteria,
            'test_alias',
            $modelFields
        );
    }

    /**
     * Test that ORDER BY properly validates database fields
     */
    public function testApplyQueryParametersFiltersNonDBFieldsFromOrderBy(): void
    {
        // Create mock fields
        $dbField = $this->createMock(TextField::class);
        $dbField->method('isDBField')->willReturn(true);

        $nonDbField = $this->createMock(TextField::class);
        $nonDbField->method('isDBField')->willReturn(false);

        $modelFields = [
            'name' => $dbField,
            'computed_field' => $nonDbField
        ];

        $parameters = [
            'orderBy' => [
                'name' => 'ASC',
                'computed_field' => 'DESC'  // should be ignored
            ]
        ];

        // Mock query builder expectations - should only get ORDER BY for database field
        $this->mockQueryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('test_alias.name', 'ASC');

        $this->connector->testApplyQueryParameters(
            $this->mockQueryBuilder,
            $parameters,
            'test_alias',
            $modelFields
        );
    }
}

/**
 * Testable DatabaseConnector that allows method exposure and mocking
 */
class TestableDatabaseConnector extends DatabaseConnector
{
    private $mockConnection;
    private $mockServiceLocator;
    private array $mockRelatedRecordFields = [];

    public function setMockConnection($connection): void
    {
        $this->mockConnection = $connection;
    }

    public function setMockServiceLocator($model): void
    {
        $this->mockServiceLocator = $model;
    }

    public function setMockRelatedRecordFields(array $fields): void
    {
        $this->mockRelatedRecordFields = $fields;
    }

    public function getConnection(): \Doctrine\DBAL\Connection
    {
        return $this->mockConnection ?? parent::getConnection();
    }

    // Expose protected methods for testing
    public function testSetupQueryBuilder(string $modelClass): array
    {
        if ($this->mockServiceLocator) {
            return [
                'model' => $this->mockServiceLocator,
                'tableName' => $this->mockServiceLocator->getTableName(),
                'mainAlias' => $this->mockServiceLocator->getAlias(),
                'modelFields' => $this->mockServiceLocator->getFields()
            ];
        }
        return $this->setupQueryBuilder($modelClass);
    }

    public function testBuildSelectClause($queryBuilder, $tempModel, $modelFields, $fields): void
    {
        // Use the actual parent implementation which includes field validation
        $this->buildSelectClause($queryBuilder, $tempModel, $modelFields, $fields);
    }

    public function testApplyCriteria($queryBuilder, array $criteria, string $mainAlias, array $modelFields = []): void
    {
        $this->applyCriteria($queryBuilder, $criteria, $mainAlias, $modelFields);
    }

    public function testApplyQueryParameters($queryBuilder, array $parameters, string $mainAlias, array $modelFields = []): void
    {
        $this->applyQueryParameters($queryBuilder, $parameters, $mainAlias, $modelFields);
    }

    protected function handleRelatedRecordField($queryBuilder, $mainModel, $field): array
    {
        return $this->mockRelatedRecordFields;
    }
}

/**
 * Test model for DatabaseConnector testing
 */
class TestableModel
{
    private Logger $logger;
    private array $data = [];
    private array $fields = [];

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        // Don't initialize fields here - they'll be set by the test
    }

    public function setFields(array $fields): void
    {
        $this->fields = $fields;
    }

    public function getTableName(): string
    {
        return 'test_models';
    }

    public function getAlias(): string
    {
        return 'test_models';
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function hasField(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    public function get(string $name)
    {
        // First check if we have a field for this name and get its value
        if (isset($this->fields[$name]) && method_exists($this->fields[$name], 'getValue')) {
            return $this->fields[$name]->getValue();
        }

        // Fallback to internal data array
        return $this->data[$name] ?? null;
    }

    public function set(string $name, $value): void
    {
        $this->data[$name] = $value;
    }
}

/**
 * Empty test model with no database fields
 */
class EmptyTestableModel extends TestableModel
{
    public function getFields(): array
    {
        return [];
    }
}
