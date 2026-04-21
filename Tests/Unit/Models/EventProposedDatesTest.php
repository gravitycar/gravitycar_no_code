<?php

namespace Gravitycar\Tests\Unit\Models;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Models\eventproposeddates\EventProposedDates;
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
 * Unit tests for the EventProposedDates model and its metadata.
 */
class EventProposedDatesTest extends UnitTestCase
{
    private MetadataEngineInterface&MockObject $mockMetadataEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $metadata = require __DIR__ . '/../../../src/Models/eventproposeddates/event_proposed_dates_metadata.php';
        $this->mockMetadataEngine->method('resolveModelName')->willReturn('EventProposedDates');
        $this->mockMetadataEngine->method('getModelMetadata')->willReturn($metadata);
    }

    private function createFieldFactory(): FieldFactory&MockObject
    {
        $mockFieldFactory = $this->createMock(FieldFactory::class);
        $mockFieldFactory->method('createField')
            ->willReturnCallback(function ($fieldMeta, $tableName = null) {
                $mockField = $this->createMock(FieldBase::class);
                $mockField->method('getName')->willReturn($fieldMeta['name'] ?? 'unknown');
                $mockField->method('getValue')->willReturn(null);
                $mockField->method('validate')->willReturn(true);
                return $mockField;
            });
        return $mockFieldFactory;
    }

    public function testModelExtendsModelBase(): void
    {
        $mockCurrentUserProvider = $this->createMock(CurrentUserProviderInterface::class);
        $mockCurrentUserProvider->method('getCurrentUserId')->willReturn('test-user');
        $mockCurrentUserProvider->method('hasAuthenticatedUser')->willReturn(true);

        $model = new EventProposedDates(
            $this->logger,
            $this->mockMetadataEngine,
            $this->createFieldFactory(),
            $this->createMock(DatabaseConnectorInterface::class),
            $this->createMock(RelationshipFactory::class),
            $this->createMock(ModelFactory::class),
            $mockCurrentUserProvider
        );
        $this->assertInstanceOf(ModelBase::class, $model);
    }

    public function testMetadataHasCorrectName(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventproposeddates/event_proposed_dates_metadata.php';
        $this->assertSame('EventProposedDates', $metadata['name']);
        $this->assertSame('event_proposed_dates', $metadata['table']);
    }

    public function testMetadataHasEventIdRelatedRecord(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventproposeddates/event_proposed_dates_metadata.php';
        $this->assertSame('RelatedRecord', $metadata['fields']['event_id']['type']);
        $this->assertSame('Events', $metadata['fields']['event_id']['relatedModel']);
        $this->assertTrue($metadata['fields']['event_id']['required']);
    }

    public function testMetadataHasProposedDateField(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventproposeddates/event_proposed_dates_metadata.php';
        $this->assertSame('DateTime', $metadata['fields']['proposed_date']['type']);
        $this->assertTrue($metadata['fields']['proposed_date']['required']);
    }

    public function testMetadataRolesAndActions(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventproposeddates/event_proposed_dates_metadata.php';
        $this->assertSame(['*'], $metadata['rolesAndActions']['admin']);
        $this->assertSame([], $metadata['rolesAndActions']['user']);
        $this->assertSame([], $metadata['rolesAndActions']['guest']);
    }

    public function testMetadataDisplayColumns(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventproposeddates/event_proposed_dates_metadata.php';
        $this->assertSame(['proposed_date'], $metadata['displayColumns']);
    }
}
