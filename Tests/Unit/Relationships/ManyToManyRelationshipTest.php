<?php

namespace Gravitycar\Tests\Unit\Relationships;

use PHPUnit\Framework\TestCase;
use Gravitycar\Relationships\ManyToManyRelationship;
use Gravitycar\Relationships\RelationshipBase;
use Gravitycar\Models\ModelBase;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * Unit tests for ManyToManyRelationship class
 */
class ManyToManyRelationshipTest extends TestCase
{
    private TestableManyToManyRelationship $relationship;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the testable relationship
        $this->relationship = new TestableManyToManyRelationship();
        $this->relationship->setTestMetadata([
            'name' => 'users_roles',
            'type' => 'ManyToMany',
            'modelA' => 'UserModel',
            'modelB' => 'RoleModel',
            'constraints' => [],
            'additionalFields' => ['assigned_at', 'status'],
            'fields' => [
                'id' => ['type' => 'IDField', 'required' => true],
                'usermodel_id' => ['type' => 'IntegerField', 'required' => true],
                'rolemodel_id' => ['type' => 'IntegerField', 'required' => true],
                'assigned_at' => ['type' => 'DateTimeField'],
                'status' => ['type' => 'TextField'],
                'created_at' => ['type' => 'DateTimeField'],
                'updated_at' => ['type' => 'DateTimeField'],
                'deleted_at' => ['type' => 'DateTimeField'],
                'created_by' => ['type' => 'TextField'],
                'updated_by' => ['type' => 'TextField'],
                'deleted_by' => ['type' => 'TextField']
            ]
        ]);
    }

    /**
     * Test getRelatedWithData returns correct records
     */
    public function testGetRelatedWithDataSuccess(): void
    {
        $userModel = new TestUserModel();
        
        $expectedRecords = [
            [
                'id' => 'rel-1',
                'usermodel_id' => 'user-123',
                'rolemodel_id' => 'role-456',
                'assigned_at' => '2025-01-01 10:00:00',
                'status' => 'active'
            ]
        ];

        $this->relationship->setMockFindResult($expectedRecords);

        $result = $this->relationship->getRelatedWithData($userModel, ['status' => 'active']);

        $this->assertEquals($expectedRecords, $result);
    }

    /**
     * Test getRelatedWithData with no additional fields
     */
    public function testGetRelatedWithDataNoAdditionalFields(): void
    {
        $userModel = new TestUserModel();
        
        $this->relationship->setMockFindResult([]);

        $result = $this->relationship->getRelatedWithData($userModel);

        $this->assertEquals([], $result);
    }

    /**
     * Test addMultipleRelations succeeds
     */
    public function testAddMultipleRelationsSuccess(): void
    {
        $userModel = new TestUserModel();
        $relatedModels = [
            new TestRoleModel('role-456'),
            new TestRoleModel('role-789')
        ];

        $this->relationship->setMockAddResult(true);

        $result = $this->relationship->addMultipleRelations($userModel, $relatedModels, ['status' => 'active']);

        $this->assertTrue($result);
    }

    /**
     * Test addMultipleRelations with partial success
     */
    public function testAddMultipleRelationsPartialSuccess(): void
    {
        $userModel = new TestUserModel();
        $relatedModels = [
            new TestRoleModel('role-456'),
            new TestRoleModel('role-789')
        ];

        // First add succeeds, second fails
        $this->relationship->setMockAddResults([true, false]);

        $result = $this->relationship->addMultipleRelations($userModel, $relatedModels);

        $this->assertTrue($result); // Should return true if at least one succeeds
    }

    /**
     * Test addMultipleRelations with all failures
     */
    public function testAddMultipleRelationsAllFail(): void
    {
        $userModel = new TestUserModel();
        $relatedModels = [new TestRoleModel('role-456')];

        $this->relationship->setMockAddResult(false);

        $result = $this->relationship->addMultipleRelations($userModel, $relatedModels);

        $this->assertFalse($result);
    }

    /**
     * Test addMultipleRelations throws exception on error
     */
    public function testAddMultipleRelationsThrowsException(): void
    {
        $userModel = new TestUserModel();
        $relatedModels = [new TestRoleModel('role-456')];
        
        $this->relationship->setMockAddException(new \Exception('Database error'));

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Failed to add multiple ManyToMany relationships: Database error');

        $this->relationship->addMultipleRelations($userModel, $relatedModels);
    }

    /**
     * Test hasAnyRelations returns true when relations exist
     */
    public function testHasAnyRelationsReturnsTrue(): void
    {
        $userModel = new TestUserModel();
        $this->relationship->setMockActiveRelatedCount(3);

        $result = $this->relationship->hasAnyRelations($userModel);

        $this->assertTrue($result);
    }

    /**
     * Test hasAnyRelations returns false when no relations exist
     */
    public function testHasAnyRelationsReturnsFalse(): void
    {
        $userModel = new TestUserModel();
        $this->relationship->setMockActiveRelatedCount(0);

        $result = $this->relationship->hasAnyRelations($userModel);

        $this->assertFalse($result);
    }

    /**
     * Test updateRelation succeeds with valid data
     */
    public function testUpdateRelationSuccess(): void
    {
        $userModel = new TestUserModel();
        $roleModel = new TestRoleModel('role-456');
        $additionalData = ['status' => 'inactive', 'note' => 'Updated role'];

        // Mock existing relationship record
        $existingRecord = [
            'id' => 'rel-1',
            'usermodel_id' => 'user-123',
            'rolemodel_id' => 'role-456',
            'status' => 'active'
        ];

        $this->relationship->setMockFindResult([$existingRecord]);
        $this->relationship->setMockUpdateResult(true);

        $result = $this->relationship->updateRelation($userModel, $roleModel, $additionalData);

        $this->assertTrue($result);
    }

    /**
     * Test updateRelation with empty data returns true
     */
    public function testUpdateRelationEmptyDataReturnsTrue(): void
    {
        $userModel = new TestUserModel();
        $roleModel = new TestRoleModel('role-456');

        $result = $this->relationship->updateRelation($userModel, $roleModel, []);

        $this->assertTrue($result);
    }

    /**
     * Test updateRelation returns false when relationship not found
     */
    public function testUpdateRelationNotFound(): void
    {
        $userModel = new TestUserModel();
        $roleModel = new TestRoleModel('role-456');

        $this->relationship->setMockFindResult([]); // No existing relationship

        $result = $this->relationship->updateRelation($userModel, $roleModel, ['status' => 'inactive']);

        $this->assertFalse($result);
    }

    /**
     * Test updateRelation throws exception on database error
     */
    public function testUpdateRelationThrowsException(): void
    {
        $userModel = new TestUserModel();
        $roleModel = new TestRoleModel('role-456');

        $this->relationship->setMockFindException(new \Exception('Database connection failed'));

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Failed to update ManyToMany relationship: Database connection failed');

        $this->relationship->updateRelation($userModel, $roleModel, ['status' => 'inactive']);
    }

    /**
     * Test handleModelDeletion with RESTRICT action and existing relations
     */
    public function testHandleModelDeletionRestrictWithRelations(): void
    {
        $userModel = new TestUserModel();
        $this->relationship->setMockActiveRelatedCount(2);

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Cannot delete model with existing ManyToMany relationships');

        $this->relationship->handleModelDeletion($userModel, RelationshipBase::CASCADE_RESTRICT);
    }

    /**
     * Test handleModelDeletion with RESTRICT action and no relations
     */
    public function testHandleModelDeletionRestrictNoRelations(): void
    {
        $userModel = new TestUserModel();
        $this->relationship->setMockActiveRelatedCount(0);

        $result = $this->relationship->handleModelDeletion($userModel, RelationshipBase::CASCADE_RESTRICT);

        $this->assertTrue($result);
    }

    /**
     * Test handleModelDeletion with CASCADE action
     */
    public function testHandleModelDeletionCascade(): void
    {
        $userModel = new TestUserModel();
        $this->relationship->setMockBulkSoftDeleteResult(true);

        $result = $this->relationship->handleModelDeletion($userModel, RelationshipBase::CASCADE_CASCADE);

        $this->assertTrue($result);
    }

    /**
     * Test handleModelDeletion with SOFT_DELETE action
     */
    public function testHandleModelDeletionSoftDelete(): void
    {
        $userModel = new TestUserModel();
        $this->relationship->setMockBulkSoftDeleteResult(true);

        $result = $this->relationship->handleModelDeletion($userModel, RelationshipBase::CASCADE_SOFT_DELETE);

        $this->assertTrue($result);
    }

    /**
     * Test handleModelDeletion with unknown cascade action
     */
    public function testHandleModelDeletionUnknownAction(): void
    {
        $userModel = new TestUserModel();

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Unknown cascade action: unknown');

        $this->relationship->handleModelDeletion($userModel, 'unknown');
    }

    /**
     * Test that relationship inherits functionality from RelationshipBase
     */
    public function testInheritedFunctionality(): void
    {
        $this->assertEquals('users_roles', $this->relationship->getName());
        $this->assertEquals('ManyToMany', $this->relationship->getType());
        
        $metadata = $this->relationship->getRelationshipMetadata();
        $this->assertEquals('UserModel', $metadata['modelA']);
        $this->assertEquals('RoleModel', $metadata['modelB']);
    }
}

/**
 * Testable version of ManyToManyRelationship that mocks dependencies
 */
class TestableManyToManyRelationship extends ManyToManyRelationship
{
    private bool $testMode = false;
    private array $mockFindResult = [];
    private ?\Exception $mockFindException = null;
    private ?bool $mockAddResult = null;
    private array $mockAddResults = [];
    private int $mockAddCallCount = 0;
    private ?\Exception $mockAddException = null;
    private int $mockActiveRelatedCount = 0;
    private bool $mockBulkSoftDeleteResult = false;
    private bool $mockUpdateResult = false;

    public function __construct(?string $relationshipName = null)
    {
        // Skip parent constructor in test mode to avoid ServiceLocator dependencies
        if (!$this->testMode) {
            $this->relationshipName = $relationshipName;
            $this->logger = $this->logger ?? new Logger('test');
        }
    }

    public function setTestMetadata(array $metadata): void
    {
        $this->testMode = true;
        $this->metadata = $metadata;
        $this->metadataLoaded = true;
    }

    public function setMockFindResult(array $result): void
    {
        $this->mockFindResult = $result;
    }

    public function setMockFindException(\Exception $exception): void
    {
        $this->mockFindException = $exception;
    }

    public function setMockAddResult(bool $result): void
    {
        $this->mockAddResult = $result;
    }

    public function setMockAddResults(array $results): void
    {
        $this->mockAddResults = $results;
        $this->mockAddCallCount = 0;
    }

    public function setMockAddException(\Exception $exception): void
    {
        $this->mockAddException = $exception;
    }

    public function setMockActiveRelatedCount(int $count): void
    {
        $this->mockActiveRelatedCount = $count;
    }

    public function setMockBulkSoftDeleteResult(bool $result): void
    {
        $this->mockBulkSoftDeleteResult = $result;
    }

    public function setMockUpdateResult(bool $result): void
    {
        $this->mockUpdateResult = $result;
    }

    public function getRelationshipMetadata(): array
    {
        return $this->metadata;
    }

    public function getName(): string
    {
        return $this->metadata['name'] ?? 'test_relationship';
    }

    public function getType(): string
    {
        return $this->metadata['type'] ?? 'ManyToMany';
    }

    protected function getDatabaseConnector(): DatabaseConnector
    {
        // Create a mock database connector
        $mockDb = $this->createMock(DatabaseConnector::class);
        
        if ($this->mockFindException) {
            $mockDb->method('find')->willThrowException($this->mockFindException);
        } else {
            $mockDb->method('find')->willReturn($this->mockFindResult);
        }
        
        $mockDb->method('update')->willReturn($this->mockUpdateResult);
        
        return $mockDb;
    }

    protected function getCurrentUserId(): ?string
    {
        return 'test-user-id';
    }

    protected function getActiveRelatedCount(ModelBase $model): int
    {
        return $this->mockActiveRelatedCount;
    }

    protected function bulkSoftDeleteRelationships(ModelBase $model): bool
    {
        return $this->mockBulkSoftDeleteResult;
    }

    public function add(ModelBase $modelA, ModelBase $modelB, array $additionalData = []): bool
    {
        if ($this->mockAddException) {
            throw $this->mockAddException;
        }

        if (!empty($this->mockAddResults)) {
            $result = $this->mockAddResults[$this->mockAddCallCount] ?? false;
            $this->mockAddCallCount++;
            return $result;
        }

        return $this->mockAddResult ?? false;
    }

    public function getModelIdField(ModelBase $model): string
    {
        $className = get_class($model);
        return strtolower($className) . '_id';
    }

    public function hasField(string $fieldName): bool
    {
        return isset($this->metadata['fields'][$fieldName]);
    }

    public function set(string $fieldName, $value): void
    {
        // Mock implementation - do nothing
    }

    public function populateFromRow(array $row): void
    {
        // Mock implementation - do nothing
    }

    private function createMock(string $className)
    {
        // Simple mock creation without PHPUnit dependencies
        return new class($className) {
            private string $className;
            private array $methodResults = [];
            
            public function __construct(string $className) {
                $this->className = $className;
            }
            
            public function method(string $methodName) {
                return $this;
            }
            
            public function willReturn($value) {
                return $this;
            }
            
            public function willThrowException(\Exception $exception) {
                return $this;
            }
            
            public function __call(string $method, array $arguments) {
                return $this->methodResults[$method] ?? null;
            }
        };
    }
}

/**
 * Mock UserModel for testing
 */
class TestUserModel extends ModelBase
{
    public function __construct()
    {
        // Skip parent constructor to avoid ServiceLocator dependencies
    }

    public function get(string $fieldName): mixed
    {
        return $fieldName === 'id' ? 'user-123' : null;
    }

    public function delete(): bool
    {
        return true;
    }
}

/**
 * Mock RoleModel for testing
 */
class TestRoleModel extends ModelBase
{
    private string $id;

    public function __construct(string $id = 'role-456')
    {
        // Skip parent constructor to avoid ServiceLocator dependencies
        $this->id = $id;
    }

    public function get(string $fieldName): mixed
    {
        return $fieldName === 'id' ? $this->id : null;
    }

    public function delete(): bool
    {
        return true;
    }
}
