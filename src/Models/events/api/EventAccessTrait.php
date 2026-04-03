<?php

namespace Gravitycar\Models\events\api;

use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\ForbiddenException;
use Gravitycar\Exceptions\UnauthorizedException;

/**
 * Shared authorization helpers for event API controllers.
 * Provides invitation-gated access checks and admin role detection.
 * Requires the using class to have $modelFactory and getCurrentUser() from ApiControllerBase.
 */
trait EventAccessTrait
{
    /**
     * Validate that the current user can modify commitments for this event.
     *
     * @param string $eventId The event ID to validate access for
     * @return array Access info: eventId, currentUserId, isAdmin
     * @throws NotFoundException If event does not exist
     * @throws UnauthorizedException If user is not authenticated
     * @throws ForbiddenException If user is not invited and not admin
     */
    protected function validateCommitmentAccess(string $eventId): array
    {
        $eventsModel = $this->modelFactory->retrieve('Events', $eventId);
        if ($eventsModel === null) {
            throw new NotFoundException(
                'Event not found',
                ['event_id' => $eventId]
            );
        }

        $currentUser = $this->getCurrentUser();
        if ($currentUser === null) {
            throw new UnauthorizedException(
                'Authentication required to modify commitments'
            );
        }
        $currentUserId = $currentUser->get('id');

        $isAdmin = $this->isUserAdmin($currentUserId);
        if (!$isAdmin) {
            $isInvited = $this->isUserInvited($eventId, $currentUserId);
            if (!$isInvited) {
                throw new ForbiddenException(
                    'You are not invited to this event',
                    ['event_id' => $eventId, 'user_id' => $currentUserId]
                );
            }
        }

        return [
            'eventId' => $eventId,
            'currentUserId' => $currentUserId,
            'isAdmin' => $isAdmin,
        ];
    }

    /**
     * Check if a user is invited to the given event.
     *
     * @param string $eventId The event ID
     * @param string $userId The user ID to check
     * @return bool True if the user is invited
     */
    protected function isUserInvited(string $eventId, string $userId): bool
    {
        $eventsModel = $this->modelFactory->new('Events');
        $eventsModel->findById($eventId);

        $usersModel = $this->modelFactory->new('Users');
        $usersModel->findById($userId);

        return $eventsModel->hasRelation('events_users_invitations', $usersModel);
    }

    /**
     * Check if a user has the admin role.
     *
     * @param string $userId The user ID to check
     * @return bool True if the user is an admin
     */
    protected function isUserAdmin(string $userId): bool
    {
        $usersModel = $this->modelFactory->retrieve('Users', $userId);
        if ($usersModel === null) {
            return false;
        }

        $roleModels = $usersModel->getRelatedModels('users_roles');
        foreach ($roleModels as $roleModel) {
            if ($roleModel->get('name') === 'admin') {
                return true;
            }
        }

        return false;
    }
}
