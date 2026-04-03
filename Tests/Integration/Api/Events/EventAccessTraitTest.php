<?php

namespace Gravitycar\Tests\Integration\Api\Events;

use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Models\events\api\EventAccessTrait;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\UnauthorizedException;
use Gravitycar\Exceptions\ForbiddenException;

/**
 * Integration tests for EventAccessTrait methods.
 * Uses a concrete test stub that applies the trait to ApiControllerBase.
 */
class EventAccessTraitTest extends EventApiTestCase
{
    private EventAccessTraitStub $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new EventAccessTraitStub(
            $this->logger,
            $this->modelFactory,
            $this->databaseConnector,
            $this->metadataEngine,
            $this->config,
            $this->currentUserProvider
        );
    }

    public function testValidateCommitmentAccessForInvitedUser(): void
    {
        $eventId = 'evt-1';
        $userId = 'user-1';
        $user = $this->createRegularUser($userId);
        $this->setCurrentUser($user);

        $eventModel = $this->createModelMock(['id' => $eventId]);
        $invitedUser = $this->createRegularUser($userId);

        $eventsNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventsNew->method('findById')->willReturn($eventModel);
        $eventsNew->method('hasRelation')->willReturn(true);

        $usersNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $usersNew->method('findById')->willReturn($invitedUser);

        $this->modelFactory->method('retrieve')->willReturnCallback(
            function (string $name, string $id) use ($eventId, $eventModel, $userId, $invitedUser) {
                if ($name === 'Events' && $id === $eventId) {
                    return $eventModel;
                }
                if ($name === 'Users' && $id === $userId) {
                    return $invitedUser;
                }
                return null;
            }
        );
        $this->modelFactory->method('new')->willReturnCallback(
            fn(string $n) => match ($n) {
                'Events' => $eventsNew,
                'Users' => $usersNew,
                default => $this->createMock(\Gravitycar\Models\ModelBase::class),
            }
        );

        $result = $this->controller->callValidateCommitmentAccess($eventId);

        $this->assertEquals($eventId, $result['eventId']);
        $this->assertEquals($userId, $result['currentUserId']);
        $this->assertFalse($result['isAdmin']);
    }

    public function testValidateCommitmentAccessForAdmin(): void
    {
        $eventId = 'evt-1';
        $adminUser = $this->createAdminUser('admin-1');
        $this->setCurrentUser($adminUser);

        $eventModel = $this->createModelMock(['id' => $eventId]);
        $adminRetrieved = $this->createAdminUser('admin-1');

        $this->modelFactory->method('retrieve')->willReturnCallback(
            function (string $name, string $id) use ($eventId, $eventModel, $adminRetrieved) {
                if ($name === 'Events' && $id === $eventId) {
                    return $eventModel;
                }
                if ($name === 'Users') {
                    return $adminRetrieved;
                }
                return null;
            }
        );

        $result = $this->controller->callValidateCommitmentAccess($eventId);

        $this->assertTrue($result['isAdmin']);
    }

    public function testValidateCommitmentAccessThrowsForMissingEvent(): void
    {
        $this->setCurrentUser($this->createRegularUser('user-1'));

        $this->modelFactory->method('retrieve')->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->controller->callValidateCommitmentAccess('nonexistent');
    }

    public function testValidateCommitmentAccessThrowsForGuest(): void
    {
        $this->setCurrentUser(null);

        $eventModel = $this->createModelMock(['id' => 'evt-1']);
        $this->modelFactory->method('retrieve')->willReturn($eventModel);

        $this->expectException(UnauthorizedException::class);
        $this->controller->callValidateCommitmentAccess('evt-1');
    }

    public function testValidateCommitmentAccessThrowsForUninvitedUser(): void
    {
        $userId = 'user-99';
        $user = $this->createRegularUser($userId);
        $this->setCurrentUser($user);

        $eventModel = $this->createModelMock(['id' => 'evt-1']);
        $uninvitedUser = $this->createRegularUser($userId);

        $eventsNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventsNew->method('findById')->willReturn($eventModel);
        $eventsNew->method('hasRelation')->willReturn(false);

        $usersNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $usersNew->method('findById')->willReturn($uninvitedUser);

        $this->modelFactory->method('retrieve')->willReturnCallback(
            function (string $name, string $id) use ($eventModel, $uninvitedUser) {
                if ($name === 'Events') {
                    return $eventModel;
                }
                if ($name === 'Users') {
                    return $uninvitedUser;
                }
                return null;
            }
        );
        $this->modelFactory->method('new')->willReturnCallback(
            fn(string $n) => match ($n) {
                'Events' => $eventsNew,
                'Users' => $usersNew,
                default => $this->createMock(\Gravitycar\Models\ModelBase::class),
            }
        );

        $this->expectException(ForbiddenException::class);
        $this->controller->callValidateCommitmentAccess('evt-1');
    }

    public function testIsUserAdminReturnsFalseForMissingUser(): void
    {
        $this->modelFactory->method('retrieve')->willReturn(null);

        $result = $this->controller->callIsUserAdmin('nonexistent');

        $this->assertFalse($result);
    }

    public function testIsUserAdminReturnsTrueForAdmin(): void
    {
        $adminUser = $this->createAdminUser('admin-1');
        $this->modelFactory->method('retrieve')->willReturn($adminUser);

        $result = $this->controller->callIsUserAdmin('admin-1');

        $this->assertTrue($result);
    }

    public function testIsUserAdminReturnsFalseForRegularUser(): void
    {
        $regularUser = $this->createRegularUser('user-1');
        $this->modelFactory->method('retrieve')->willReturn($regularUser);

        $result = $this->controller->callIsUserAdmin('user-1');

        $this->assertFalse($result);
    }
}

/**
 * Concrete stub to expose EventAccessTrait protected methods for testing.
 */
class EventAccessTraitStub extends ApiControllerBase
{
    use EventAccessTrait;

    protected array $rolesAndActions = [
        'admin' => ['*'],
        'user' => ['*'],
        'guest' => [],
    ];

    public function registerRoutes(): array
    {
        return [];
    }

    public function callValidateCommitmentAccess(string $eventId): array
    {
        return $this->validateCommitmentAccess($eventId);
    }

    public function callIsUserAdmin(string $userId): bool
    {
        return $this->isUserAdmin($userId);
    }

    public function callIsUserInvited(string $eventId, string $userId): bool
    {
        return $this->isUserInvited($eventId, $userId);
    }
}
