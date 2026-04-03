<?php

namespace Gravitycar\Tests\Unit\Models;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Models\eventcommitments\EventCommitments;
use Gravitycar\Fields\FieldBase;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Models\ModelBase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the EventCommitments model.
 *
 * Covers unique constraint validation and metadata structure.
 */
class EventCommitmentsTest extends UnitTestCase
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

        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $this->mockFieldFactory = $this->createMock(FieldFactory::class);
        $this->mockDatabaseConnector = $this->createMock(DatabaseConnectorInterface::class);
        $this->mockRelationshipFactory = $this->createMock(RelationshipFactory::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);
        $this->mockCurrentUserProvider = $this->createMock(CurrentUserProviderInterface::class);

        $metadata = require __DIR__ . '/../../../src/Models/eventcommitments/event_commitments_metadata.php';
        $coreFields = require __DIR__ . '/../../../src/Metadata/templates/core_fields_metadata.php';
        $metadata['fields'] = array_merge($coreFields, $metadata['fields']);
        $this->mockMetadataEngine->method('resolveModelName')->willReturn('EventCommitments');
        $this->mockMetadataEngine->method('getModelMetadata')->willReturn($metadata);

        $this->mockFieldFactory->method('createField')
            ->willReturnCallback(function ($fieldMeta, $tableName = null) {
                $value = null;
                $mockField = $this->createMock(FieldBase::class);
                $mockField->method('getName')->willReturn($fieldMeta['name'] ?? 'unknown');
                $mockField->method('getValue')->willReturnCallback(function () use (&$value) {
                    return $value;
                });
                $mockField->method('setValue')->willReturnCallback(function ($v) use (&$value) {
                    $value = $v;
                });
                $mockField->method('validate')->willReturn(true);
                return $mockField;
            });

        $this->mockCurrentUserProvider->method('getCurrentUserId')->willReturn('test-user');
        $this->mockCurrentUserProvider->method('hasAuthenticatedUser')->willReturn(true);
    }

    private function createModel(): EventCommitments
    {
        return new EventCommitments(
            $this->logger,
            $this->mockMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );
    }

    // --- Metadata validation ---

    public function testMetadataHasCorrectName(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventcommitments/event_commitments_metadata.php';
        $this->assertSame('EventCommitments', $metadata['name']);
    }

    public function testMetadataHasRequiredFields(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventcommitments/event_commitments_metadata.php';
        $fieldNames = array_keys($metadata['fields']);

        $this->assertContains('event_id', $fieldNames);
        $this->assertContains('user_id', $fieldNames);
        $this->assertContains('proposed_date_id', $fieldNames);
        $this->assertContains('is_available', $fieldNames);
    }

    public function testMetadataEventIdIsRelatedRecordToEvents(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventcommitments/event_commitments_metadata.php';
        $this->assertSame('RelatedRecord', $metadata['fields']['event_id']['type']);
        $this->assertSame('Events', $metadata['fields']['event_id']['relatedModel']);
    }

    public function testMetadataUserIdIsRelatedRecordToUsers(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventcommitments/event_commitments_metadata.php';
        $this->assertSame('RelatedRecord', $metadata['fields']['user_id']['type']);
        $this->assertSame('Users', $metadata['fields']['user_id']['relatedModel']);
    }

    public function testMetadataProposedDateIdIsRelatedRecord(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventcommitments/event_commitments_metadata.php';
        $this->assertSame('RelatedRecord', $metadata['fields']['proposed_date_id']['type']);
        $this->assertSame('EventProposedDates', $metadata['fields']['proposed_date_id']['relatedModel']);
    }

    public function testMetadataIsAvailableIsBooleanWithDefaultFalse(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventcommitments/event_commitments_metadata.php';
        $this->assertSame('Boolean', $metadata['fields']['is_available']['type']);
        $this->assertFalse($metadata['fields']['is_available']['defaultValue']);
    }

    public function testMetadataRolesAndActions(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventcommitments/event_commitments_metadata.php';
        $this->assertSame(['*'], $metadata['rolesAndActions']['admin']);
        $this->assertSame(['create', 'read', 'update', 'list'], $metadata['rolesAndActions']['user']);
        $this->assertSame([], $metadata['rolesAndActions']['guest']);
    }

    public function testMetadataHasUniqueConstraint(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventcommitments/event_commitments_metadata.php';
        $this->assertArrayHasKey('uniqueConstraints', $metadata);
        $this->assertArrayHasKey('uniq_event_user_date', $metadata['uniqueConstraints']);
        $this->assertSame(
            ['event_id', 'user_id', 'proposed_date_id'],
            $metadata['uniqueConstraints']['uniq_event_user_date']
        );
    }

    // --- Model instantiation ---

    public function testModelExtendsModelBase(): void
    {
        $model = $this->createModel();
        $this->assertInstanceOf(ModelBase::class, $model);
    }

    // --- Unique constraint validation via protected method reflection ---

    public function testValidateUniqueCommitmentPassesWhenFieldsEmpty(): void
    {
        $model = $this->createModel();
        // Fields are null by default - validation skips the check

        $reflection = new \ReflectionMethod($model, 'validateUniqueCommitment');
        $reflection->setAccessible(true);

        $this->assertTrue($reflection->invoke($model));
    }

    public function testValidateUniqueCommitmentPassesWhenNoExistingRecord(): void
    {
        // Mock databaseConnector->find() to return empty (no existing commitment)
        $this->mockDatabaseConnector->method('find')->willReturn([]);

        $model = $this->createModel();
        $model->set('event_id', 'evt-1');
        $model->set('user_id', 'usr-1');
        $model->set('proposed_date_id', 'pd-1');

        $reflection = new \ReflectionMethod($model, 'validateUniqueCommitment');
        $reflection->setAccessible(true);

        $this->assertTrue($reflection->invoke($model));
    }

    public function testValidateUniqueCommitmentThrowsOnDuplicate(): void
    {
        // findRaw (via databaseConnector->find) returns an existing record
        $this->mockDatabaseConnector->method('find')->willReturn([
            ['id' => 'existing-uuid-123'],
        ]);

        $model = $this->createModel();
        $model->set('event_id', 'evt-1');
        $model->set('user_id', 'usr-1');
        $model->set('proposed_date_id', 'pd-1');
        // No id set = create scenario

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('A commitment already exists');

        $reflection = new \ReflectionMethod($model, 'validateUniqueCommitment');
        $reflection->setAccessible(true);
        $reflection->invoke($model);
    }

    public function testValidateUniqueCommitmentAllowsSelfUpdate(): void
    {
        // findRaw (via databaseConnector->find) returns the same record ID
        $this->mockDatabaseConnector->method('find')->willReturn([
            ['id' => 'commit-uuid-1'],
        ]);

        $model = $this->createModel();
        $model->set('id', 'commit-uuid-1');
        $model->set('event_id', 'evt-1');
        $model->set('user_id', 'usr-1');
        $model->set('proposed_date_id', 'pd-1');

        $reflection = new \ReflectionMethod($model, 'validateUniqueCommitment');
        $reflection->setAccessible(true);

        $this->assertTrue($reflection->invoke($model));
    }
}
