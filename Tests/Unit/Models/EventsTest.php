<?php

namespace Gravitycar\Tests\Unit\Models;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Models\events\Events;
use Gravitycar\Fields\FieldBase;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Models\ModelBase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the Events model.
 *
 * Covers isActive(), getMostPopularDates(), and getDefaultOrderBy().
 */
class EventsTest extends UnitTestCase
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

        $metadata = $this->getMetadataWithCoreFields(
            require __DIR__ . '/../../../src/Models/events/events_metadata.php'
        );
        $this->mockMetadataEngine->method('resolveModelName')->willReturn('Events');
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

    private function getMetadataWithCoreFields(array $metadata): array
    {
        $coreFields = require __DIR__ . '/../../../src/Metadata/templates/core_fields_metadata.php';
        $metadata['fields'] = array_merge($coreFields, $metadata['fields']);
        return $metadata;
    }

    private function createEventsModel(): Events
    {
        return new Events(
            $this->logger,
            $this->mockMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );
    }

    // --- isActive() tests ---

    public function testIsActiveReturnsFalseWhenAcceptedDateIsSet(): void
    {
        $model = $this->createEventsModel();
        $model->set('accepted_date', '2026-05-01 19:00:00');
        $model->set('id', 'test-uuid-1');

        $this->assertFalse($model->isActive());
    }

    public function testIsActiveReturnsFalseWhenNoId(): void
    {
        $model = $this->createEventsModel();
        $this->assertFalse($model->isActive());
    }

    public function testIsActiveReturnsTrueWhenFutureProposedDatesExist(): void
    {
        $mockProposedDatesModel = $this->createMock(ModelBase::class);
        $mockProposedDatesModel->method('findRaw')->willReturn([
            ['id' => 'pd-uuid-1'],
        ]);

        $this->mockModelFactory->method('new')
            ->with('EventProposedDates')
            ->willReturn($mockProposedDatesModel);

        $model = $this->createEventsModel();
        $model->set('id', 'test-uuid-1');

        $this->assertTrue($model->isActive());
    }

    public function testIsActiveReturnsFalseWhenNoFutureProposedDates(): void
    {
        $mockProposedDatesModel = $this->createMock(ModelBase::class);
        $mockProposedDatesModel->method('findRaw')->willReturn([]);

        $this->mockModelFactory->method('new')
            ->with('EventProposedDates')
            ->willReturn($mockProposedDatesModel);

        $model = $this->createEventsModel();
        $model->set('id', 'test-uuid-1');

        $this->assertFalse($model->isActive());
    }

    // --- getDefaultOrderBy() tests ---

    public function testGetDefaultOrderByContainsCaseWhenAndCreatedAtDesc(): void
    {
        $model = $this->createEventsModel();
        $orderBy = $model->getDefaultOrderBy();

        $this->assertStringContainsString('CASE WHEN', $orderBy);
        $this->assertStringContainsString('accepted_date IS NULL', $orderBy);
        $this->assertStringContainsString('event_proposed_dates', $orderBy);
        $this->assertStringContainsString('created_at DESC', $orderBy);
    }

    // --- getMostPopularDates() tests ---

    public function testGetMostPopularDatesReturnsEmptyWhenNoId(): void
    {
        $model = $this->createEventsModel();
        $this->assertSame([], $model->getMostPopularDates());
    }

    public function testGetMostPopularDatesReturnsEmptyWhenNoCommitments(): void
    {
        $this->mockDatabaseConnector->method('executeQuery')->willReturn([]);

        $model = $this->createEventsModel();
        $model->set('id', 'test-uuid-1');

        $this->assertSame([], $model->getMostPopularDates());
    }

    public function testGetMostPopularDatesReturnsSingleWinner(): void
    {
        $this->mockDatabaseConnector->method('executeQuery')->willReturn([
            ['proposed_date_id' => 'pd-1', 'vote_count' => '5'],
            ['proposed_date_id' => 'pd-2', 'vote_count' => '3'],
        ]);

        $mockProposedDatesModel = $this->createMock(ModelBase::class);
        $mockProposedDatesModel->method('findRaw')->willReturn([
            ['id' => 'pd-1', 'proposed_date' => '2026-06-15 19:00:00'],
        ]);

        $this->mockModelFactory->method('new')
            ->with('EventProposedDates')
            ->willReturn($mockProposedDatesModel);

        $model = $this->createEventsModel();
        $model->set('id', 'test-uuid-1');

        $result = $model->getMostPopularDates();

        $this->assertCount(1, $result);
        $this->assertSame('pd-1', $result[0]['proposed_date_id']);
        $this->assertSame(5, $result[0]['vote_count']);
    }

    public function testGetMostPopularDatesReturnsTiedDates(): void
    {
        $this->mockDatabaseConnector->method('executeQuery')->willReturn([
            ['proposed_date_id' => 'pd-1', 'vote_count' => '5'],
            ['proposed_date_id' => 'pd-2', 'vote_count' => '5'],
            ['proposed_date_id' => 'pd-3', 'vote_count' => '3'],
        ]);

        $mockProposedDatesModel = $this->createMock(ModelBase::class);
        $mockProposedDatesModel->method('findRaw')->willReturn([
            ['id' => 'pd-1', 'proposed_date' => '2026-06-15 19:00:00'],
            ['id' => 'pd-2', 'proposed_date' => '2026-06-16 19:00:00'],
        ]);

        $this->mockModelFactory->method('new')
            ->with('EventProposedDates')
            ->willReturn($mockProposedDatesModel);

        $model = $this->createEventsModel();
        $model->set('id', 'test-uuid-1');

        $result = $model->getMostPopularDates();

        $this->assertCount(2, $result);
        $this->assertSame(5, $result[0]['vote_count']);
        $this->assertSame(5, $result[1]['vote_count']);
    }
}
