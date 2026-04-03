<?php
namespace Gravitycar\Models\eventcommitments;

use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;

/**
 * EventCommitments model for the Gravitycar event organizer feature.
 *
 * Records a specific user's availability for a specific proposed date
 * on a specific event. Enforces a composite unique constraint on
 * (event_id, user_id, proposed_date_id) at the application level.
 */
class EventCommitments extends ModelBase
{
    /**
     * Pure dependency injection constructor.
     */
    public function __construct(
        Logger $logger,
        MetadataEngineInterface $metadataEngine,
        FieldFactory $fieldFactory,
        DatabaseConnectorInterface $databaseConnector,
        RelationshipFactory $relationshipFactory,
        ModelFactory $modelFactory,
        CurrentUserProviderInterface $currentUserProvider
    ) {
        parent::__construct(
            $logger,
            $metadataEngine,
            $fieldFactory,
            $databaseConnector,
            $relationshipFactory,
            $modelFactory,
            $currentUserProvider
        );
    }

    /**
     * Override to add composite unique constraint validation before persistence.
     *
     * @return bool True if validation passes, false otherwise.
     */
    protected function validateForPersistence(): bool
    {
        if (!parent::validateForPersistence()) {
            return false;
        }

        return $this->validateUniqueCommitment();
    }

    /**
     * Ensure no existing record has the same (event_id, user_id, proposed_date_id).
     *
     * On create: checks if any non-deleted record with this combination exists.
     * On update: checks if any non-deleted record with this combination exists
     *            that is NOT the current record (by ID).
     *
     * @return bool True if the commitment is unique.
     * @throws GCException If a duplicate commitment is detected.
     */
    protected function validateUniqueCommitment(): bool
    {
        $eventId = $this->get('event_id');
        $userId = $this->get('user_id');
        $proposedDateId = $this->get('proposed_date_id');

        if (empty($eventId) || empty($userId) || empty($proposedDateId)) {
            return true; // Required validation handles missing fields
        }

        $existingId = $this->findExistingCommitment(
            $eventId,
            $userId,
            $proposedDateId
        );

        if ($existingId === null) {
            return true;
        }

        $currentId = $this->get('id');
        if ($currentId !== null && $existingId === $currentId) {
            return true; // Updating the same record is fine
        }

        $this->logger->warning('Duplicate commitment rejected', [
            'event_id' => $eventId,
            'user_id' => $userId,
            'proposed_date_id' => $proposedDateId,
        ]);

        throw new GCException(
            'A commitment already exists for this user on this proposed date',
            [
                'event_id' => $eventId,
                'user_id' => $userId,
                'proposed_date_id' => $proposedDateId,
            ]
        );
    }

    /**
     * Query for an existing commitment with the same ternary key.
     *
     * Uses ModelBase::findRaw() to query via the framework API.
     *
     * @param string $eventId The event UUID.
     * @param string $userId The user UUID.
     * @param string $proposedDateId The proposed date UUID.
     * @return string|null The ID of the existing record, or null if none found.
     */
    protected function findExistingCommitment(
        string $eventId,
        string $userId,
        string $proposedDateId
    ): ?string {
        $results = $this->findRaw(
            [
                'event_id' => $eventId,
                'user_id' => $userId,
                'proposed_date_id' => $proposedDateId,
            ],
            ['id']
        );

        if (empty($results)) {
            return null;
        }

        return (string) $results[0]['id'];
    }
}
