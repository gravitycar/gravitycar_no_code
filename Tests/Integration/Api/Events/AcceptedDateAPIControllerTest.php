<?php

namespace Gravitycar\Tests\Integration\Api\Events;

use Gravitycar\Models\events\api\AcceptedDateAPIController;
use Gravitycar\Exceptions\BadRequestException;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\UnauthorizedException;
use Gravitycar\Exceptions\ForbiddenException;

/**
 * Integration tests for AcceptedDateAPIController.
 * Covers admin-only access, accepted date setting, and reminder recalculation.
 */
class AcceptedDateAPIControllerTest extends EventApiTestCase
{
    private AcceptedDateAPIController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new AcceptedDateAPIController(
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
        $this->assertCount(1, $routes);
        $this->assertEquals('PUT', $routes[0]['method']);
        $this->assertStringContainsString('accepted-date', $routes[0]['path']);
        $this->assertEquals('update', $routes[0]['rbacAction']);
    }

    public function testSetAcceptedDateAsAdmin(): void
    {
        $eventId = 'evt-1';
        $adminUser = $this->createAdminUser('admin-1');
        $this->setCurrentUser($adminUser);

        $eventModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventModel->method('set')->willReturnSelf();
        $eventModel->method('update')->willReturn(true);

        $remindersModel = $this->getMockBuilder(\Gravitycar\Models\eventreminders\EventReminders::class)
            ->disableOriginalConstructor()
            ->getMock();
        $remindersModel->method('recalculateRemindersForEvent')
            ->with($eventId, '2026-06-01 19:00:00')
            ->willReturn(2);

        $pdModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $pdModel->method('findRaw')->willReturn([
            ['proposed_date' => '2026-06-01 19:00:00'],
        ]);

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

        $this->modelFactory->method('new')->willReturnCallback(
            fn(string $n) => match ($n) {
                'EventProposedDates' => $pdModel,
                'EventReminders' => $remindersModel,
                default => $this->createMock(\Gravitycar\Models\ModelBase::class),
            }
        );

        $request = $this->createRequest(
            '/events/evt-1/accepted-date',
            ['events', 'event_id', 'accepted-date'],
            'PUT',
            ['proposed_date_id' => 'pd-1']
        );

        $result = $this->controller->setAcceptedDate($request);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals('2026-06-01 19:00:00', $result['data']['accepted_date']);
        $this->assertEquals(2, $result['data']['reminders_recalculated']);
    }

    public function testSetAcceptedDateDeniesRegularUser(): void
    {
        $user = $this->createRegularUser('user-1');
        $this->setCurrentUser($user);

        $userRetrieved = $this->createRegularUser('user-1');
        $this->modelFactory->method('retrieve')->willReturnCallback(
            function (string $name, string $id) use ($userRetrieved) {
                if ($name === 'Users') {
                    return $userRetrieved;
                }
                return $this->createModelMock(['id' => 'evt-1']);
            }
        );

        $request = $this->createRequest(
            '/events/evt-1/accepted-date',
            ['events', 'event_id', 'accepted-date'],
            'PUT',
            ['proposed_date_id' => 'pd-1']
        );

        $this->expectException(ForbiddenException::class);
        $this->controller->setAcceptedDate($request);
    }

    public function testSetAcceptedDateDeniesGuest(): void
    {
        $this->setCurrentUser(null);

        $request = $this->createRequest(
            '/events/evt-1/accepted-date',
            ['events', 'event_id', 'accepted-date'],
            'PUT',
            ['proposed_date_id' => 'pd-1']
        );

        $this->expectException(UnauthorizedException::class);
        $this->controller->setAcceptedDate($request);
    }

    public function testSetAcceptedDateThrowsNotFoundForMissingEvent(): void
    {
        $adminUser = $this->createAdminUser('admin-1');
        $this->setCurrentUser($adminUser);

        $adminRetrieved = $this->createAdminUser('admin-1');
        $this->modelFactory->method('retrieve')->willReturnCallback(
            function (string $name, string $id) use ($adminRetrieved) {
                if ($name === 'Users') {
                    return $adminRetrieved;
                }
                return null;
            }
        );

        $request = $this->createRequest(
            '/events/nonexistent/accepted-date',
            ['events', 'event_id', 'accepted-date'],
            'PUT',
            ['proposed_date_id' => 'pd-1']
        );

        $this->expectException(NotFoundException::class);
        $this->controller->setAcceptedDate($request);
    }

    public function testSetAcceptedDateRejectsMissingProposedDateId(): void
    {
        $adminUser = $this->createAdminUser('admin-1');
        $this->setCurrentUser($adminUser);

        $eventModel = $this->createModelMock(['id' => 'evt-1']);
        $adminRetrieved = $this->createAdminUser('admin-1');

        $this->modelFactory->method('retrieve')->willReturnCallback(
            function (string $name, string $id) use ($eventModel, $adminRetrieved) {
                if ($name === 'Events') {
                    return $eventModel;
                }
                if ($name === 'Users') {
                    return $adminRetrieved;
                }
                return null;
            }
        );

        $request = $this->createRequest(
            '/events/evt-1/accepted-date',
            ['events', 'event_id', 'accepted-date'],
            'PUT',
            []
        );

        $this->expectException(BadRequestException::class);
        $this->controller->setAcceptedDate($request);
    }

    public function testSetAcceptedDateRejectsInvalidProposedDate(): void
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

        $pdModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $pdModel->method('findRaw')->willReturn([]);

        $this->modelFactory->method('new')->willReturnCallback(
            fn(string $n) => match ($n) {
                'EventProposedDates' => $pdModel,
                default => $this->createMock(\Gravitycar\Models\ModelBase::class),
            }
        );

        $request = $this->createRequest(
            '/events/evt-1/accepted-date',
            ['events', 'event_id', 'accepted-date'],
            'PUT',
            ['proposed_date_id' => 'invalid-pd']
        );

        $this->expectException(BadRequestException::class);
        $this->controller->setAcceptedDate($request);
    }

    public function testReminderRecalcFailureDoesNotFailRequest(): void
    {
        $eventId = 'evt-1';
        $adminUser = $this->createAdminUser('admin-1');
        $this->setCurrentUser($adminUser);

        $eventModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventModel->method('set')->willReturnSelf();
        $eventModel->method('update')->willReturn(true);

        $remindersModel = $this->getMockBuilder(\Gravitycar\Models\eventreminders\EventReminders::class)
            ->disableOriginalConstructor()
            ->getMock();
        $remindersModel->method('recalculateRemindersForEvent')
            ->willThrowException(new \Gravitycar\Exceptions\GCException('DB error'));

        $pdModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $pdModel->method('findRaw')->willReturn([
            ['proposed_date' => '2026-06-01 19:00:00'],
        ]);

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

        $this->modelFactory->method('new')->willReturnCallback(
            fn(string $n) => match ($n) {
                'EventProposedDates' => $pdModel,
                'EventReminders' => $remindersModel,
                default => $this->createMock(\Gravitycar\Models\ModelBase::class),
            }
        );

        $request = $this->createRequest(
            '/events/evt-1/accepted-date',
            ['events', 'event_id', 'accepted-date'],
            'PUT',
            ['proposed_date_id' => 'pd-1']
        );

        $result = $this->controller->setAcceptedDate($request);

        $this->assertTrue($result['success']);
        $this->assertEquals(-1, $result['data']['reminders_recalculated']);
    }
}
