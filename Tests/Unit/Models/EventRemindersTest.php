<?php

namespace Gravitycar\Tests\Unit\Models;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Models\eventreminders\EventReminders;
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
 * Unit tests for the EventReminders model.
 *
 * Covers calculateRemindAt(), shouldRecalculate(), and metadata validation.
 */
class EventRemindersTest extends UnitTestCase
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

        $metadata = require __DIR__ . '/../../../src/Models/eventreminders/event_reminders_metadata.php';
        $coreFields = require __DIR__ . '/../../../src/Metadata/templates/core_fields_metadata.php';
        $metadata['fields'] = array_merge($coreFields, $metadata['fields']);
        $this->mockMetadataEngine->method('resolveModelName')->willReturn('EventReminders');
        $this->mockMetadataEngine->method('getModelMetadata')->willReturn($metadata);

        $this->mockFieldFactory->method('createField')
            ->willReturnCallback(function ($fieldMeta, $tableName = null) {
                $mockField = $this->createMock(FieldBase::class);
                $mockField->method('getName')->willReturn($fieldMeta['name'] ?? 'unknown');
                $mockField->method('getValue')->willReturn(null);
                $mockField->method('validate')->willReturn(true);
                return $mockField;
            });

        $this->mockCurrentUserProvider->method('getCurrentUserId')->willReturn('test-user');
        $this->mockCurrentUserProvider->method('hasAuthenticatedUser')->willReturn(true);
    }

    private function createModel(): EventReminders
    {
        return new EventReminders(
            $this->logger,
            $this->mockMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );
    }

    // --- calculateRemindAt() tests ---

    public function testCalculateRemindAtTwoWeeks(): void
    {
        $model = $this->createModel();
        $result = $model->calculateRemindAt('2_weeks', '2026-06-15 19:00:00');
        $this->assertSame('2026-06-01 19:00:00', $result);
    }

    public function testCalculateRemindAtOneWeek(): void
    {
        $model = $this->createModel();
        $result = $model->calculateRemindAt('1_week', '2026-06-15 19:00:00');
        $this->assertSame('2026-06-08 19:00:00', $result);
    }

    public function testCalculateRemindAtOneDay(): void
    {
        $model = $this->createModel();
        $result = $model->calculateRemindAt('1_day', '2026-06-15 19:00:00');
        $this->assertSame('2026-06-14 19:00:00', $result);
    }

    public function testCalculateRemindAtReturnsNullForNullAcceptedDate(): void
    {
        $model = $this->createModel();
        $result = $model->calculateRemindAt('2_weeks', null);
        $this->assertNull($result);
    }

    public function testCalculateRemindAtReturnsNullForCustomType(): void
    {
        $model = $this->createModel();
        $result = $model->calculateRemindAt('custom', '2026-06-15 19:00:00');
        $this->assertNull($result);
    }

    public function testCalculateRemindAtReturnsNullForUnknownType(): void
    {
        $model = $this->createModel();
        $result = $model->calculateRemindAt('bogus_type', '2026-06-15 19:00:00');
        $this->assertNull($result);
    }

    // --- shouldRecalculate() tests (via reflection since private) ---

    public function testShouldRecalculatePresetPending(): void
    {
        $model = $this->createModel();
        $method = new \ReflectionMethod($model, 'shouldRecalculate');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($model, [
            'reminder_type' => '1_week',
            'status' => 'pending',
        ]));
    }

    public function testShouldRecalculatePresetFailed(): void
    {
        $model = $this->createModel();
        $method = new \ReflectionMethod($model, 'shouldRecalculate');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($model, [
            'reminder_type' => '2_weeks',
            'status' => 'failed',
        ]));
    }

    public function testShouldRecalculateReturnsFalseForSent(): void
    {
        $model = $this->createModel();
        $method = new \ReflectionMethod($model, 'shouldRecalculate');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($model, [
            'reminder_type' => '1_day',
            'status' => 'sent',
        ]));
    }

    public function testShouldRecalculateReturnsFalseForCustom(): void
    {
        $model = $this->createModel();
        $method = new \ReflectionMethod($model, 'shouldRecalculate');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($model, [
            'reminder_type' => 'custom',
            'status' => 'pending',
        ]));
    }

    public function testShouldRecalculateReturnsFalseForCustomSent(): void
    {
        $model = $this->createModel();
        $method = new \ReflectionMethod($model, 'shouldRecalculate');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($model, [
            'reminder_type' => 'custom',
            'status' => 'sent',
        ]));
    }

    // --- recalculateRemindersForEvent: tested via integration with mocked DB ---

    public function testRecalculateRemindersReturnsZeroWhenNoReminders(): void
    {
        // findRaw (via databaseConnector->find) returns empty
        $this->mockDatabaseConnector->method('find')->willReturn([]);

        $model = $this->createModel();
        $result = $model->recalculateRemindersForEvent('evt-1', '2026-07-15 18:00:00');

        $this->assertSame(0, $result);
    }

    // --- Metadata validation ---

    public function testMetadataHasCorrectName(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventreminders/event_reminders_metadata.php';
        $this->assertSame('EventReminders', $metadata['name']);
    }

    public function testMetadataHasCorrectRoles(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventreminders/event_reminders_metadata.php';
        $this->assertSame(['*'], $metadata['rolesAndActions']['admin']);
        $this->assertSame([], $metadata['rolesAndActions']['user']);
        $this->assertSame([], $metadata['rolesAndActions']['guest']);
    }

    public function testMetadataReminderTypeHasCorrectOptions(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventreminders/event_reminders_metadata.php';
        $options = array_keys($metadata['fields']['reminder_type']['options']);
        $this->assertContains('2_weeks', $options);
        $this->assertContains('1_week', $options);
        $this->assertContains('1_day', $options);
        $this->assertContains('custom', $options);
    }

    public function testMetadataStatusDefaultsPending(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventreminders/event_reminders_metadata.php';
        $this->assertSame('pending', $metadata['fields']['status']['defaultValue']);
    }

    public function testMetadataStatusHasCorrectOptions(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventreminders/event_reminders_metadata.php';
        $options = array_keys($metadata['fields']['status']['options']);
        $this->assertContains('pending', $options);
        $this->assertContains('sent', $options);
        $this->assertContains('failed', $options);
    }

    public function testMetadataRemindAtIsNullable(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventreminders/event_reminders_metadata.php';
        $this->assertTrue($metadata['fields']['remind_at']['nullable']);
    }

    public function testMetadataHasRelationship(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/eventreminders/event_reminders_metadata.php';
        $this->assertContains('events_event_reminders', $metadata['relationships']);
    }
}
