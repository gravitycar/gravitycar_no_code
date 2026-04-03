<?php

namespace Gravitycar\Tests\Unit\Models;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Models\emailqueue\EmailQueue;
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
 * Unit tests for the EmailQueue model.
 *
 * Covers constants, retry backoff, metadata, and queue helper methods.
 */
class EmailQueueTest extends UnitTestCase
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

        $metadata = require __DIR__ . '/../../../src/Models/emailqueue/email_queue_metadata.php';
        $coreFields = require __DIR__ . '/../../../src/Metadata/templates/core_fields_metadata.php';
        $metadata['fields'] = array_merge($coreFields, $metadata['fields']);
        $this->mockMetadataEngine->method('resolveModelName')->willReturn('EmailQueue');
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

    private function createModel(): EmailQueue
    {
        return new EmailQueue(
            $this->logger,
            $this->mockMetadataEngine,
            $this->mockFieldFactory,
            $this->mockDatabaseConnector,
            $this->mockRelationshipFactory,
            $this->mockModelFactory,
            $this->mockCurrentUserProvider
        );
    }

    // --- Constants ---

    public function testMaxRetryCountIsThree(): void
    {
        $this->assertSame(3, EmailQueue::MAX_RETRY_COUNT);
    }

    public function testStatusConstants(): void
    {
        $this->assertSame('pending', EmailQueue::STATUS_PENDING);
        $this->assertSame('sent', EmailQueue::STATUS_SENT);
        $this->assertSame('failed', EmailQueue::STATUS_FAILED);
        $this->assertSame('cancelled', EmailQueue::STATUS_CANCELLED);
    }

    // --- Retry backoff ---

    public function testGetRetryBackoffFirstRetry(): void
    {
        $model = $this->createModel();
        $this->assertSame(300, $model->getRetryBackoffSeconds(1));
    }

    public function testGetRetryBackoffSecondRetry(): void
    {
        $model = $this->createModel();
        $this->assertSame(1800, $model->getRetryBackoffSeconds(2));
    }

    public function testGetRetryBackoffThirdRetry(): void
    {
        $model = $this->createModel();
        $this->assertSame(7200, $model->getRetryBackoffSeconds(3));
    }

    public function testGetRetryBackoffFallsBackToTwoHours(): void
    {
        $model = $this->createModel();
        $this->assertSame(7200, $model->getRetryBackoffSeconds(99));
    }

    // --- markAsSent returns false when not found ---

    public function testMarkAsSentReturnsFalseWhenRecordNotFound(): void
    {
        // findById returns null when no rows from DB
        $this->mockDatabaseConnector->method('findById')->willReturn(null);

        $model = $this->createModel();
        $this->assertFalse($model->markAsSent('nonexistent-id'));
    }

    // --- markAsFailedOrRetry returns false when not found ---

    public function testMarkAsFailedOrRetryReturnsFalseWhenNotFound(): void
    {
        $this->mockDatabaseConnector->method('findById')->willReturn(null);

        $model = $this->createModel();
        $this->assertFalse($model->markAsFailedOrRetry('nonexistent', 'error'));
    }

    // --- Metadata ---

    public function testMetadataHasCorrectName(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/emailqueue/email_queue_metadata.php';
        $this->assertSame('EmailQueue', $metadata['name']);
        $this->assertSame('email_queue', $metadata['table']);
    }

    public function testMetadataAdminOnlyAccess(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/emailqueue/email_queue_metadata.php';
        $this->assertSame(['*'], $metadata['rolesAndActions']['admin']);
        $this->assertSame([], $metadata['rolesAndActions']['user']);
        $this->assertSame([], $metadata['rolesAndActions']['guest']);
    }

    public function testMetadataStatusDefaultsPending(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/emailqueue/email_queue_metadata.php';
        $this->assertSame('pending', $metadata['fields']['status']['defaultValue']);
    }

    public function testMetadataRetryCountDefaultsToZero(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/emailqueue/email_queue_metadata.php';
        $this->assertSame(0, $metadata['fields']['retry_count']['defaultValue']);
    }

    public function testMetadataHasAllRequiredFields(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/emailqueue/email_queue_metadata.php';
        $fieldNames = array_keys($metadata['fields']);

        $expected = [
            'recipient_email', 'recipient_user_id', 'subject', 'body',
            'status', 'send_at', 'sent_at', 'retry_count', 'error_message',
            'related_event_id', 'related_reminder_id',
        ];

        foreach ($expected as $field) {
            $this->assertContains($field, $fieldNames, "Missing field: {$field}");
        }
    }

    public function testMetadataStatusHasAllOptions(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/emailqueue/email_queue_metadata.php';
        $options = array_keys($metadata['fields']['status']['options']);

        $this->assertContains('pending', $options);
        $this->assertContains('sent', $options);
        $this->assertContains('failed', $options);
        $this->assertContains('cancelled', $options);
    }

    public function testMetadataRelatedEventIdPointsToEvents(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/emailqueue/email_queue_metadata.php';
        $this->assertSame('RelatedRecord', $metadata['fields']['related_event_id']['type']);
        $this->assertSame('Events', $metadata['fields']['related_event_id']['relatedModel']);
    }

    public function testMetadataRelatedReminderIdPointsToEventReminders(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/emailqueue/email_queue_metadata.php';
        $this->assertSame('RelatedRecord', $metadata['fields']['related_reminder_id']['type']);
        $this->assertSame('EventReminders', $metadata['fields']['related_reminder_id']['relatedModel']);
    }

    public function testMetadataSentAtIsReadOnly(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/emailqueue/email_queue_metadata.php';
        $this->assertTrue($metadata['fields']['sent_at']['readOnly']);
    }
}
