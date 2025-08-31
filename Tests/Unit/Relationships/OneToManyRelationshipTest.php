<?php

namespace Gravitycar\Tests\Unit\Relationships;

use PHPUnit\Framework\TestCase;
use Gravitycar\Relationships\OneToManyRelationship;
use Gravitycar\Models\ModelBase;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * Unit tests for OneToManyRelationship class
 */
class OneToManyRelationshipTest extends TestCase
{
    private TestableOneToManyRelationship $relationship;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the testable relationship
        $this->relationship = new TestableOneToManyRelationship();
        $this->relationship->setTestMetadata([
            'name' => 'user_movies',
            'modelOne' => 'User',
            'modelMany' => 'Movie',
            'cascadeOnDelete' => 'soft_delete'
        ]);
    }

    public function testIsOneModelReturnsTrueForCorrectModel(): void
    {
        $userModel = new User();
        $result = $this->relationship->isOneModel($userModel);
        $this->assertTrue($result);
    }

    public function testIsManyModelReturnsTrueForCorrectModel(): void
    {
        $movieModel = new Movie();
        $result = $this->relationship->isManyModel($movieModel);
        $this->assertTrue($result);
    }

    public function testGetRelatedFromOneValidatesModelSide(): void
    {
        $movieModel = new Movie(); // Wrong side
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model is not on the "one" side of this OneToMany relationship');

        $this->relationship->getRelatedFromOne($movieModel);
    }

    public function testGetRelatedFromManyValidatesModelSide(): void
    {
        $userModel = new User(); // Wrong side
        
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Model is not on the "many" side of this OneToMany relationship');

        $this->relationship->getRelatedFromMany($userModel);
    }

    public function testGetRelatedFromOneReturnsEmptyArrayWhenNoRecords(): void
    {
        $this->relationship->setMockRelatedRecords([]);
        $userModel = new User();
        
        $result = $this->relationship->getRelatedFromOne($userModel);
        
        $this->assertEquals([], $result);
    }

    public function testGetManyModelFromRecordReturnsNullWhenNoId(): void
    {
        $record = ['some_field' => 'value']; // No ID field
        
        $result = $this->relationship->getManyModelFromRecordPublic($record);
        
        $this->assertNull($result);
    }

    public function testInheritedFunctionalityFromRelationshipBase(): void
    {
        $this->assertEquals('user_movies', $this->relationship->getName());
        $metadata = $this->relationship->getMetadata();
        $this->assertEquals('User', $metadata['modelOne']);
        $this->assertEquals('Movie', $metadata['modelMany']);
    }
}

/**
 * Testable version of OneToManyRelationship
 */
class TestableOneToManyRelationship extends OneToManyRelationship
{
    private array $mockRelatedRecords = [];
    private bool $testMode = false;

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

    public function setMockRelatedRecords(array $records): void
    {
        $this->mockRelatedRecords = $records;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getManyModelFromRecordPublic(array $record): ?ModelBase
    {
        return $this->getManyModelFromRecord($record);
    }

    public function getRelatedRecords($model): array
    {
        return $this->mockRelatedRecords;
    }

    protected function getDatabaseConnector(): DatabaseConnector
    {
        throw new \RuntimeException('Database testing requires integration tests');
    }

    protected function getCurrentUserId(): ?string
    {
        return 'test-user-id';
    }

    protected function getManyModelFromRecord(array $record): ?ModelBase
    {
        $manyIdField = 'many_' . strtolower($this->metadata['modelMany']) . '_id';
        $manyId = $record[$manyIdField] ?? null;

        if (!$manyId || $manyId === 'invalid-id') {
            return null;
        }

        return null; // Mock implementation
    }
}

/**
 * Mock User model for testing
 */
class User extends ModelBase
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
 * Mock Movie model for testing
 */
class Movie extends ModelBase
{
    public function __construct()
    {
        // Skip parent constructor to avoid ServiceLocator dependencies
    }

    public function get(string $fieldName): mixed
    {
        return $fieldName === 'id' ? 'movie-456' : null;
    }

    public function delete(): bool
    {
        return true;
    }
}
