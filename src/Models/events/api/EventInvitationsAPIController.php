<?php

declare(strict_types=1);

namespace Gravitycar\Models\events\api;

use Gravitycar\Api\Request;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Models\api\Api\ModelBaseAPIController;

/**
 * Custom API controller for Event Invitations link operations.
 *
 * Extends ModelBaseAPIController to override link behavior for the
 * events_users_invitations ManyToMany relationship, auto-populating
 * invited_at and invited_by additional fields when inviting a user.
 */
class EventInvitationsAPIController extends ModelBaseAPIController
{
    protected array $rolesAndActions = [
        'admin' => ['update', 'create'],
        'user' => ['update'],
        'guest' => [],
    ];
    
    /**
     * @return array<int, array<string, mixed>>
     */
    public function registerRoutes(): array
    {
        return [
            [
                'method' => 'PUT',
                'path' => '/?/?/link/events_users_invitations/?',
                'parameterNames' => ['modelName', 'id', '', 'relationshipName', 'idToLink'],
                'apiClass' => static::class,
                'apiMethod' => 'linkInvitation',
            ],
        ];
    }

    /**
     * Link a user to an event as an invitee.
     *
     * Populates invited_at with the current timestamp and invited_by
     * with the current authenticated user's ID, then delegates to
     * RelationshipBase::add() with the additional data.
     */
    public function linkInvitation(Request $request): array
    {
        $eventId = $request->get('id');
        $userId = $request->get('idToLink');

        $this->validateId($eventId);
        $this->validateId($userId);

        $eventModel = $this->modelFactory->retrieve('Events', $eventId);
        if (!$eventModel) {
            throw new GCException('Event not found', ['event_id' => $eventId], 404);
        }

        $userModel = $this->modelFactory->retrieve('Users', $userId);
        if (!$userModel) {
            throw new GCException('User not found', ['user_id' => $userId], 404);
        }

        $relationship = $this->validateRelationshipExists($eventModel, 'events_users_invitations');

        // Check if already linked (idempotent)
        if ($relationship->has($eventModel, $userModel)) {
            return ['message' => 'User already invited'];
        }

        $additionalData = [
            'invited_at' => date('Y-m-d H:i:s'),
            'invited_by' => $this->currentUserProvider?->getCurrentUserId(),
        ];

        $success = $relationship->add($eventModel, $userModel, $additionalData);

        if (!$success) {
            throw new GCException('Failed to invite user', [
                'event_id' => $eventId,
                'user_id' => $userId,
            ], 500);
        }

        $this->logger->info('User invited to event', [
            'event_id' => $eventId,
            'user_id' => $userId,
            'invited_by' => $additionalData['invited_by'],
        ]);

        return ['message' => 'User invited successfully'];
    }
}
