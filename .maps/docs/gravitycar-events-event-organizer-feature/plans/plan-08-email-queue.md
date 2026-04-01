# Implementation Plan: Email_Queue Model

## Spec Context

The Email_Queue model provides reliable email delivery infrastructure for the events feature. All outbound emails (reminders, notifications) are queued here with status tracking, retry support, and references to related events/reminders. This is a standalone framework model with no dependencies on other new models. Only admins can access it.

Catalog item: 8 - Email_Queue Model
Specification section: Models - Email_Queue
Acceptance criteria addressed: AC-21

## Dependencies

- **Blocked by**: None
- **Uses**: `ModelBase` (src/Models/ModelBase.php), `FieldFactory`, `MetadataEngine`, `DatabaseConnector`, `SchemaGenerator` (auto-generates DB table from metadata)
- **Blocks**: Item 14 (Email Reminder Cron Job)

## File Changes

### New Files
- `src/Models/email_queue/email_queue_metadata.php` -- Metadata definition for the Email_Queue model
- `src/Models/email_queue/EmailQueue.php` -- Model class extending ModelBase
- `tests/Models/EmailQueueTest.php` -- Unit tests

### Modified Files
- None

## Implementation Details

### 1. Email_Queue Metadata

**File**: `src/Models/email_queue/email_queue_metadata.php`

This file returns a PHP array defining the model's name, table, fields, roles, relationships, and UI configuration. Follow the exact pattern from `src/Models/users/users_metadata.php`.

```php
<?php
return [
    'name' => 'Email_Queue',
    'table' => 'email_queue',
    'displayColumns' => ['recipient_email', 'subject', 'status'],
    'fields' => [
        'recipient_email' => [
            'name' => 'recipient_email',
            'type' => 'Text',
            'label' => 'Recipient Email',
            'required' => true,
            'validationRules' => ['Required', 'Email'],
        ],
        'recipient_user_id' => [
            'name' => 'recipient_user_id',
            'type' => 'RelatedRecord',
            'label' => 'Recipient User',
            'required' => false,
            'relatedModel' => 'Users',
            'validationRules' => [],
        ],
        'subject' => [
            'name' => 'subject',
            'type' => 'Text',
            'label' => 'Subject',
            'required' => true,
            'validationRules' => ['Required'],
        ],
        'body' => [
            'name' => 'body',
            'type' => 'BigText',
            'label' => 'Body',
            'required' => true,
            'validationRules' => ['Required'],
        ],
        'status' => [
            'name' => 'status',
            'type' => 'Enum',
            'label' => 'Status',
            'required' => true,
            'defaultValue' => 'pending',
            'options' => [
                'pending' => 'Pending',
                'sent' => 'Sent',
                'failed' => 'Failed',
                'cancelled' => 'Cancelled',
            ],
            'validationRules' => ['Required', 'Options'],
        ],
        'send_at' => [
            'name' => 'send_at',
            'type' => 'DateTime',
            'label' => 'Scheduled Send Time',
            'required' => true,
            'validationRules' => ['Required', 'DateTime'],
        ],
        'sent_at' => [
            'name' => 'sent_at',
            'type' => 'DateTime',
            'label' => 'Sent At',
            'required' => false,
            'readOnly' => true,
            'validationRules' => ['DateTime'],
        ],
        'retry_count' => [
            'name' => 'retry_count',
            'type' => 'Integer',
            'label' => 'Retry Count',
            'required' => false,
            'defaultValue' => 0,
            'validationRules' => [],
        ],
        'error_message' => [
            'name' => 'error_message',
            'type' => 'Text',
            'label' => 'Error Message',
            'required' => false,
            'validationRules' => [],
        ],
        'related_event_id' => [
            'name' => 'related_event_id',
            'type' => 'RelatedRecord',
            'label' => 'Related Event',
            'required' => false,
            'relatedModel' => 'Events',
            'validationRules' => [],
        ],
        'related_reminder_id' => [
            'name' => 'related_reminder_id',
            'type' => 'RelatedRecord',
            'label' => 'Related Reminder',
            'required' => false,
            'relatedModel' => 'Event_Reminders',
            'validationRules' => [],
        ],
    ],
    'rolesAndActions' => [
        'admin' => ['*'],
        'user' => [],
        'guest' => [],
    ],
    'validationRules' => [],
    'relationships' => [],
    'apiRoutes' => [],
    'ui' => [
        'listFields' => [
            'recipient_email',
            'subject',
            'status',
            'send_at',
            'sent_at',
            'retry_count',
        ],
        'createFields' => [
            'recipient_email',
            'recipient_user_id',
            'subject',
            'body',
            'status',
            'send_at',
            'related_event_id',
            'related_reminder_id',
        ],
        'editFields' => [
            'recipient_email',
            'recipient_user_id',
            'subject',
            'body',
            'status',
            'send_at',
            'sent_at',
            'retry_count',
            'error_message',
            'related_event_id',
            'related_reminder_id',
        ],
    ],
];
```

### 2. EmailQueue Model Class

**File**: `src/Models/email_queue/EmailQueue.php`

**Namespace**: `Gravitycar\Models\email_queue`

**Extends**: `Gravitycar\Models\ModelBase`

The model class follows the standard 7-parameter DI constructor pattern (see `Books.php`). It adds domain-specific helper methods for queue operations that the cron job (Item 14) will use.

**Constants**:
```php
public const MAX_RETRY_COUNT = 3;
public const STATUS_PENDING = 'pending';
public const STATUS_SENT = 'sent';
public const STATUS_FAILED = 'failed';
public const STATUS_CANCELLED = 'cancelled';

// Retry backoff intervals in seconds
private const RETRY_BACKOFF_SECONDS = [
    1 => 300,    // 1st retry: 5 minutes
    2 => 1800,   // 2nd retry: 30 minutes
    3 => 7200,   // 3rd retry: 2 hours
];
```

**Exports (public methods)**:

- `findPendingEmails(): array` -- Returns Email_Queue records where status='pending' and send_at <= now. Used by the cron job to find emails ready to send.

```php
public function findPendingEmails(): array
{
    $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $conditions = [
        'status' => ['operator' => 'equals', 'value' => self::STATUS_PENDING],
        'send_at' => ['operator' => 'lessThanOrEqual', 'value' => $now],
    ];
    return $this->find($conditions);
}
```

- `markAsSent(string $emailId): bool` -- Sets status to 'sent' and sent_at to current UTC timestamp.

```php
public function markAsSent(string $emailId): bool
{
    $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $record = $this->findById($emailId);
    if (!$record) {
        return false;
    }
    $record->set('status', self::STATUS_SENT);
    $record->set('sent_at', $now);
    return $record->save();
}
```

- `markAsFailedOrRetry(string $emailId, string $errorMessage): bool` -- Increments retry_count, stores error_message. If retry_count >= MAX_RETRY_COUNT, sets status to 'failed'. Otherwise keeps status as 'pending' and updates send_at to next retry time using exponential backoff.

```php
public function markAsFailedOrRetry(string $emailId, string $errorMessage): bool
{
    $record = $this->findById($emailId);
    if (!$record) {
        return false;
    }

    $currentRetryCount = (int) $record->get('retry_count');
    $newRetryCount = $currentRetryCount + 1;
    $record->set('retry_count', $newRetryCount);
    $record->set('error_message', $errorMessage);

    if ($newRetryCount >= self::MAX_RETRY_COUNT) {
        $record->set('status', self::STATUS_FAILED);
        return $record->save();
    }

    // Calculate next retry time using backoff
    $backoffSeconds = self::RETRY_BACKOFF_SECONDS[$newRetryCount] ?? 7200;
    $nextRetry = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
        ->modify("+{$backoffSeconds} seconds")
        ->format('Y-m-d H:i:s');
    $record->set('send_at', $nextRetry);

    return $record->save();
}
```

- `cancelByEventId(string $eventId): int` -- Cancels all pending emails for a given event. Returns count of cancelled records.

```php
public function cancelByEventId(string $eventId): int
{
    $conditions = [
        'related_event_id' => ['operator' => 'equals', 'value' => $eventId],
        'status' => ['operator' => 'equals', 'value' => self::STATUS_PENDING],
    ];
    $pendingEmails = $this->find($conditions);
    $count = 0;
    foreach ($pendingEmails as $email) {
        $email->set('status', self::STATUS_CANCELLED);
        if ($email->save()) {
            $count++;
        }
    }
    return $count;
}
```

- `getRetryBackoffSeconds(int $retryCount): int` -- Returns the backoff interval in seconds for the given retry count. Public for testability.

```php
public function getRetryBackoffSeconds(int $retryCount): int
{
    return self::RETRY_BACKOFF_SECONDS[$retryCount] ?? 7200;
}
```

## Error Handling

- `findById` returns null if record not found -- `markAsSent` and `markAsFailedOrRetry` return `false` in this case
- `save()` may throw exceptions from DBAL -- let them propagate; the cron job (Item 14) will catch and log
- Invalid status transitions (e.g., marking an already-sent email as failed) are not enforced at the model level; the cron job is responsible for only calling these methods on appropriate records

## Unit Test Specifications

**File**: `tests/Models/EmailQueueTest.php`

### Metadata Loading

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Model loads metadata | Construct EmailQueue | Model name is 'Email_Queue', table is 'email_queue' | Verify metadata file is correct |
| All fields present | Construct EmailQueue | 11 model-specific fields exist | Verify no fields are missing |
| Admin has full access | Check rolesAndActions | admin => ['*'] | AC-21 admin access |
| User has no access | Check rolesAndActions | user => [] | AC-21 admin-only |
| Guest has no access | Check rolesAndActions | guest => [] | AC-21 admin-only |

### Constants

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| MAX_RETRY_COUNT | Read constant | 3 | Spec: 3 retry attempts |
| STATUS constants | Read constants | pending, sent, failed, cancelled | Spec: 4 status values |
| Retry backoff 1st | getRetryBackoffSeconds(1) | 300 (5 min) | Spec: 1st retry after 5 min |
| Retry backoff 2nd | getRetryBackoffSeconds(2) | 1800 (30 min) | Spec: 2nd retry after 30 min |
| Retry backoff 3rd | getRetryBackoffSeconds(3) | 7200 (2 hr) | Spec: 3rd retry after 2 hr |

### Key Scenario: markAsFailedOrRetry with retry exhaustion

**Setup**: Create an EmailQueue record with retry_count = 2, status = 'pending'
**Action**: Call markAsFailedOrRetry($id, 'SMTP timeout')
**Expected**: retry_count becomes 3, status becomes 'failed', error_message is 'SMTP timeout'

### Key Scenario: markAsFailedOrRetry with retries remaining

**Setup**: Create an EmailQueue record with retry_count = 0, status = 'pending'
**Action**: Call markAsFailedOrRetry($id, 'Connection refused')
**Expected**: retry_count becomes 1, status remains 'pending', send_at updated to ~5 minutes from now, error_message is 'Connection refused'

### Key Scenario: markAsSent

**Setup**: Create an EmailQueue record with status = 'pending'
**Action**: Call markAsSent($id)
**Expected**: status becomes 'sent', sent_at is set to current UTC time

### Key Scenario: cancelByEventId

**Setup**: Create 3 EmailQueue records for the same event_id: 2 pending, 1 sent
**Action**: Call cancelByEventId($eventId)
**Expected**: Returns 2, the 2 pending records have status 'cancelled', the sent record is unchanged

## Notes

- The `body` field uses `BigText` type since email bodies will contain full HTML content that exceeds standard `Text` field limits.
- The `recipient_email` field is `Text` type with an `Email` validation rule (not `Email` field type) because the Email field type may have component-generator behavior we don't need. If the framework's `Email` field type is more appropriate, the builder should use it instead -- check the existing Email field type behavior.
- The `related_event_id` and `related_reminder_id` fields use `RelatedRecord` to reference Events and Event_Reminders models respectively. These models may not exist yet when this model is built (Email_Queue has no blockers), but the metadata references will resolve once those models are created. The SchemaGenerator should still create the column; foreign key constraints may need to be deferred or handled gracefully.
- The `sent_at` field is `readOnly` since it should only be set programmatically by `markAsSent()`, not via admin UI editing.
- This model is in Phase 1 (no dependencies) and will be used by Item 14 (Email Reminder Cron Job).
