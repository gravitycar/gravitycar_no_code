<?php

namespace Gravitycar\Tests\Unit\Models;

use Gravitycar\Tests\Unit\UnitTestCase;

/**
 * Metadata validation tests for Events and related models.
 * Validates structure, field types, and rolesAndActions match the spec.
 */
class EventsMetadataTest extends UnitTestCase
{
    // --- Events metadata ---

    public function testEventsMetadataLoads(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/events/events_metadata.php';
        $this->assertSame('Events', $metadata['name']);
        $this->assertSame('events', $metadata['table']);
    }

    public function testEventsMetadataHasRequiredFields(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/events/events_metadata.php';
        $fields = array_keys($metadata['fields']);

        $expected = ['name', 'description', 'location', 'duration_hours',
            'accepted_date', 'linked_model_name', 'linked_record_id'];

        foreach ($expected as $field) {
            $this->assertContains($field, $fields, "Missing field: {$field}");
        }
    }

    public function testEventsMetadataFieldTypes(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/events/events_metadata.php';

        $this->assertSame('Text', $metadata['fields']['name']['type']);
        $this->assertSame('Text', $metadata['fields']['description']['type']);
        $this->assertSame('Text', $metadata['fields']['location']['type']);
        $this->assertSame('Integer', $metadata['fields']['duration_hours']['type']);
        $this->assertSame('DateTime', $metadata['fields']['accepted_date']['type']);
        $this->assertSame('Text', $metadata['fields']['linked_model_name']['type']);
        $this->assertSame('ID', $metadata['fields']['linked_record_id']['type']);
    }

    public function testEventsMetadataDurationDefault(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/events/events_metadata.php';
        $this->assertSame(3, $metadata['fields']['duration_hours']['defaultValue']);
    }

    public function testEventsMetadataRolesAndActions(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/events/events_metadata.php';
        $this->assertSame(['*'], $metadata['rolesAndActions']['admin']);
        $this->assertSame(['list', 'read'], $metadata['rolesAndActions']['user']);
        $this->assertSame(['list', 'read'], $metadata['rolesAndActions']['guest']);
    }

    public function testEventsMetadataHasRelationships(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/events/events_metadata.php';
        $this->assertContains('events_event_proposed_dates', $metadata['relationships']);
        $this->assertContains('events_users_invitations', $metadata['relationships']);
        $this->assertContains('events_event_commitments', $metadata['relationships']);
        $this->assertContains('events_event_reminders', $metadata['relationships']);
    }

    public function testEventsMetadataDisplayColumns(): void
    {
        $metadata = require __DIR__ . '/../../../src/Models/events/events_metadata.php';
        $this->assertSame(['name'], $metadata['displayColumns']);
    }

    // --- Relationship metadata ---

    public function testEventsEventProposedDatesRelationship(): void
    {
        $metadata = require __DIR__ . '/../../../src/Relationships/events_event_proposed_dates/events_event_proposed_dates_metadata.php';
        $this->assertSame('events_event_proposed_dates', $metadata['name']);
        $this->assertSame('OneToMany', $metadata['type']);
        $this->assertSame('Events', $metadata['modelOne']);
        $this->assertSame('EventProposedDates', $metadata['modelMany']);
    }

    public function testEventsUsersInvitationsRelationship(): void
    {
        $metadata = require __DIR__ . '/../../../src/Relationships/events_users_invitations/events_users_invitations_metadata.php';
        $this->assertSame('events_users_invitations', $metadata['name']);
        $this->assertSame('ManyToMany', $metadata['type']);
        $this->assertSame('Events', $metadata['modelA']);
        $this->assertSame('Users', $metadata['modelB']);
        $this->assertArrayHasKey('invited_at', $metadata['additionalFields']);
        $this->assertArrayHasKey('invited_by', $metadata['additionalFields']);
    }

    public function testEventsEventCommitmentsRelationship(): void
    {
        $metadata = require __DIR__ . '/../../../src/Relationships/events_event_commitments/events_event_commitments_metadata.php';
        $this->assertSame('events_event_commitments', $metadata['name']);
        $this->assertSame('OneToMany', $metadata['type']);
        $this->assertSame('Events', $metadata['modelOne']);
        $this->assertSame('EventCommitments', $metadata['modelMany']);
    }

    public function testEventsEventRemindersRelationship(): void
    {
        $metadata = require __DIR__ . '/../../../src/Relationships/events_event_reminders/events_event_reminders_metadata.php';
        $this->assertSame('events_event_reminders', $metadata['name']);
        $this->assertSame('OneToMany', $metadata['type']);
        $this->assertSame('Events', $metadata['modelOne']);
        $this->assertSame('EventReminders', $metadata['modelMany']);
    }
}
