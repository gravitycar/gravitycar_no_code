<?php

namespace Gravitycar\Tests\Integration\Api\Events;

use Gravitycar\Models\events\api\CommitmentsAPIController;
use Gravitycar\Exceptions\BadRequestException;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\UnauthorizedException;
use Gravitycar\Exceptions\ForbiddenException;

/**
 * Integration tests for CommitmentsAPIController.
 * Covers upsert logic, accept-all, access control, and input validation.
 */
class CommitmentsAPIControllerTest extends EventApiTestCase
{
    private CommitmentsAPIController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new CommitmentsAPIController(
            $this->logger,
            $this->modelFactory,
            $this->databaseConnector,
            $this->metadataEngine,
            $this->config,
            $this->currentUserProvider
        );
    }

    public function testRouteRegistration(): void
    {
        $routes = $this->controller->registerRoutes();
        $this->assertCount(2, $routes);
        $this->assertEquals('PUT', $routes[0]['method']);
        $this->assertStringContainsString('commitments', $routes[0]['path']);
        $this->assertEquals('POST', $routes[1]['method']);
        $this->assertStringContainsString('accept-all', $routes[1]['path']);
    }

    public function testUpsertCommitmentsCreatesNewRecords(): void
    {
        $eventId = 'evt-1';
        $userId = 'user-1';
        $user = $this->createRegularUser($userId);
        $this->setCurrentUser($user);

        $eventModel = $this->createModelMock(['id' => $eventId]);
        $invitedUser = $this->createRegularUser($userId);

        $this->stubRetrieveCallbacks($eventId, $eventModel, $userId, $invitedUser);
        $this->stubNewCallbacks($eventId, $userId);

        $request = $this->createRequest(
            '/events/evt-1/commitments',
            ['events', 'event_id', 'commitments'],
            'PUT',
            [
                'commitments' => [
                    ['proposed_date_id' => 'pd-1', 'is_available' => true],
                ],
            ]
        );

        $result = $this->controller->upsertCommitments($request);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals(1, $result['data']['created']);
        $this->assertEquals(0, $result['data']['updated']);
    }

    public function testUpsertCommitmentsUpdatesExistingRecord(): void
    {
        $eventId = 'evt-1';
        $userId = 'user-1';
        $user = $this->createRegularUser($userId);
        $this->setCurrentUser($user);

        $eventModel = $this->createModelMock(['id' => $eventId]);
        $invitedUser = $this->createRegularUser($userId);

        $this->stubRetrieveCallbacks($eventId, $eventModel, $userId, $invitedUser);
        $this->stubNewCallbacksWithExisting($eventId, $userId, 'pd-1');

        $request = $this->createRequest(
            '/events/evt-1/commitments',
            ['events', 'event_id', 'commitments'],
            'PUT',
            [
                'commitments' => [
                    ['proposed_date_id' => 'pd-1', 'is_available' => false],
                ],
            ]
        );

        $result = $this->controller->upsertCommitments($request);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['data']['created']);
        $this->assertEquals(1, $result['data']['updated']);
    }

    public function testUpsertCommitmentsDeniesGuest(): void
    {
        $this->setCurrentUser(null);

        $eventModel = $this->createModelMock(['id' => 'evt-1']);
        $this->modelFactory->method('retrieve')->willReturn($eventModel);

        $request = $this->createRequest(
            '/events/evt-1/commitments',
            ['events', 'event_id', 'commitments'],
            'PUT',
            ['commitments' => [['proposed_date_id' => 'pd-1', 'is_available' => true]]]
        );

        $this->expectException(UnauthorizedException::class);
        $this->controller->upsertCommitments($request);
    }

    public function testUpsertCommitmentsDeniesUninvitedUser(): void
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

        $request = $this->createRequest(
            '/events/evt-1/commitments',
            ['events', 'event_id', 'commitments'],
            'PUT',
            ['commitments' => [['proposed_date_id' => 'pd-1', 'is_available' => true]]]
        );

        $this->expectException(ForbiddenException::class);
        $this->controller->upsertCommitments($request);
    }

    public function testUpsertCommitmentsRejectsEmptyArray(): void
    {
        $userId = 'user-1';
        $user = $this->createRegularUser($userId);
        $this->setCurrentUser($user);

        $eventModel = $this->createModelMock(['id' => 'evt-1']);
        $invitedUser = $this->createRegularUser($userId);
        $this->stubRetrieveCallbacks('evt-1', $eventModel, $userId, $invitedUser);
        $this->stubNewCallbacks('evt-1', $userId);

        $request = $this->createRequest(
            '/events/evt-1/commitments',
            ['events', 'event_id', 'commitments'],
            'PUT',
            ['commitments' => []]
        );

        $this->expectException(BadRequestException::class);
        $this->controller->upsertCommitments($request);
    }

    public function testUpsertCommitmentsRejectsMissingProposedDateId(): void
    {
        $userId = 'user-1';
        $user = $this->createRegularUser($userId);
        $this->setCurrentUser($user);

        $eventModel = $this->createModelMock(['id' => 'evt-1']);
        $invitedUser = $this->createRegularUser($userId);
        $this->stubRetrieveCallbacks('evt-1', $eventModel, $userId, $invitedUser);
        $this->stubNewCallbacks('evt-1', $userId);

        $request = $this->createRequest(
            '/events/evt-1/commitments',
            ['events', 'event_id', 'commitments'],
            'PUT',
            ['commitments' => [['is_available' => true]]]
        );

        $this->expectException(BadRequestException::class);
        $this->controller->upsertCommitments($request);
    }

    public function testAcceptAllMarksAllDatesAvailable(): void
    {
        $eventId = 'evt-1';
        $userId = 'user-1';
        $user = $this->createRegularUser($userId);
        $this->setCurrentUser($user);

        $eventModel = $this->createModelMock(['id' => $eventId]);
        $invitedUser = $this->createRegularUser($userId);
        $this->stubRetrieveCallbacks($eventId, $eventModel, $userId, $invitedUser);

        $pdModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $pdModel->method('findRaw')->willReturn([
            ['id' => 'pd-1'],
            ['id' => 'pd-2'],
            ['id' => 'pd-3'],
        ]);

        $commModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $commModel->method('findFirst')->willReturn(null);
        $commModel->method('set')->willReturnSelf();
        $commModel->method('create')->willReturn(true);

        $eventsNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventsNew->method('findById')->willReturn($eventModel);
        $eventsNew->method('hasRelation')->willReturn(true);

        $usersNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $usersNew->method('findById')->willReturn($invitedUser);

        $this->modelFactory->method('new')->willReturnCallback(
            fn(string $n) => match ($n) {
                'EventProposedDates' => $pdModel,
                'EventCommitments' => $commModel,
                'Events' => $eventsNew,
                'Users' => $usersNew,
                default => $this->createMock(\Gravitycar\Models\ModelBase::class),
            }
        );

        $request = $this->createRequest(
            '/events/evt-1/accept-all',
            ['events', 'event_id', 'accept-all'],
            'POST'
        );

        $result = $this->controller->acceptAll($request);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['data']['created']);
    }

    public function testAcceptAllWithNoProposedDatesReturnsZero(): void
    {
        $eventId = 'evt-1';
        $userId = 'user-1';
        $user = $this->createRegularUser($userId);
        $this->setCurrentUser($user);

        $eventModel = $this->createModelMock(['id' => $eventId]);
        $invitedUser = $this->createRegularUser($userId);
        $this->stubRetrieveCallbacks($eventId, $eventModel, $userId, $invitedUser);

        $pdModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $pdModel->method('findRaw')->willReturn([]);

        $eventsNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventsNew->method('findById')->willReturn($eventModel);
        $eventsNew->method('hasRelation')->willReturn(true);

        $usersNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $usersNew->method('findById')->willReturn($invitedUser);

        $this->modelFactory->method('new')->willReturnCallback(
            fn(string $n) => match ($n) {
                'EventProposedDates' => $pdModel,
                'Events' => $eventsNew,
                'Users' => $usersNew,
                default => $this->createMock(\Gravitycar\Models\ModelBase::class),
            }
        );

        $request = $this->createRequest(
            '/events/evt-1/accept-all',
            ['events', 'event_id', 'accept-all'],
            'POST'
        );

        $result = $this->controller->acceptAll($request);

        $this->assertEquals(0, $result['data']['created']);
        $this->assertEquals(0, $result['data']['updated']);
    }

    /**
     * Helper to stub retrieve calls for event + user.
     */
    private function stubRetrieveCallbacks(
        string $eventId,
        $eventModel,
        string $userId,
        $userModel
    ): void {
        $this->modelFactory->method('retrieve')->willReturnCallback(
            function (string $name, string $id) use ($eventId, $eventModel, $userId, $userModel) {
                if ($name === 'Events' && $id === $eventId) {
                    return $eventModel;
                }
                if ($name === 'Users' && $id === $userId) {
                    return $userModel;
                }
                return null;
            }
        );
    }

    /**
     * Helper to stub new calls for upsert (no existing commitment).
     */
    private function stubNewCallbacks(string $eventId, string $userId): void
    {
        $eventsNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventsNew->method('findById')->willReturn(
            $this->createModelMock(['id' => $eventId])
        );
        $eventsNew->method('hasRelation')->willReturn(true);

        $usersNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $usersNew->method('findById')->willReturn(
            $this->createRegularUser($userId)
        );

        $pdModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $pdModel->method('findRaw')->willReturn([['id' => 'pd-1']]);

        $commModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $commModel->method('findFirst')->willReturn(null);
        $commModel->method('set')->willReturnSelf();
        $commModel->method('create')->willReturn(true);

        $this->modelFactory->method('new')->willReturnCallback(
            fn(string $n) => match ($n) {
                'Events' => $eventsNew,
                'Users' => $usersNew,
                'EventProposedDates' => $pdModel,
                'EventCommitments' => $commModel,
                default => $this->createMock(\Gravitycar\Models\ModelBase::class),
            }
        );
    }

    /**
     * Helper to stub new calls for upsert (existing commitment for given pdId).
     */
    private function stubNewCallbacksWithExisting(
        string $eventId,
        string $userId,
        string $existingPdId
    ): void {
        $eventsNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventsNew->method('findById')->willReturn(
            $this->createModelMock(['id' => $eventId])
        );
        $eventsNew->method('hasRelation')->willReturn(true);

        $usersNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $usersNew->method('findById')->willReturn(
            $this->createRegularUser($userId)
        );

        $pdModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $pdModel->method('findRaw')->willReturn([['id' => $existingPdId]]);

        $existingCommitment = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $existingCommitment->method('set')->willReturnSelf();
        $existingCommitment->method('update')->willReturn(true);

        $commModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $commModel->method('findFirst')->willReturn($existingCommitment);

        $this->modelFactory->method('new')->willReturnCallback(
            fn(string $n) => match ($n) {
                'Events' => $eventsNew,
                'Users' => $usersNew,
                'EventProposedDates' => $pdModel,
                'EventCommitments' => $commModel,
                default => $this->createMock(\Gravitycar\Models\ModelBase::class),
            }
        );
    }
}
