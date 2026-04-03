<?php

namespace Gravitycar\Tests\Integration\Api\Events;

use Gravitycar\Models\events\api\IcsExportAPIController;
use Gravitycar\Exceptions\BadRequestException;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\ForbiddenException;

/**
 * Integration tests for IcsExportAPIController.
 * Covers admin/invited user access, guest denial, ICS generation,
 * and missing accepted_date error case.
 */
class IcsExportAPIControllerTest extends EventApiTestCase
{
    private IcsExportAPIController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new IcsExportAPIController(
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
        $this->assertEquals('GET', $routes[0]['method']);
        $this->assertStringContainsString('/ics', $routes[0]['path']);
    }

    public function testIcsExportAsAdminWithAcceptedDate(): void
    {
        $eventId = 'evt-1';
        $adminUser = $this->createAdminUser('admin-1');
        $this->setCurrentUser($adminUser);

        $eventModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventModel->method('get')->willReturnCallback(
            fn(string $f) => match ($f) {
                'id' => $eventId,
                'name' => 'Book Club',
                'description' => 'Read great books',
                'location' => 'Library',
                'accepted_date' => '2026-06-01 19:00:00',
                'duration_hours' => 2,
                default => null,
            }
        );

        $eventsNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventsNew->method('findById')->with($eventId)->willReturn($eventModel);

        $adminRetrieved = $this->createAdminUser('admin-1');

        $this->modelFactory->method('new')->willReturn($eventsNew);
        $this->modelFactory->method('retrieve')->willReturnCallback(
            function (string $name, string $id) use ($adminRetrieved) {
                if ($name === 'Users') {
                    return $adminRetrieved;
                }
                return null;
            }
        );

        $request = $this->createRequest(
            '/events/evt-1/ics',
            ['events', 'event_id', 'ics'],
            'GET'
        );

        $result = $this->controller->getIcs($request);

        $this->assertTrue($result['raw_response']);
        $this->assertEquals(200, $result['status']);
        $this->assertStringContainsString('text/calendar', $result['content_type']);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $result['body']);
        $this->assertStringContainsString('Book Club', $result['body']);
        $this->assertArrayHasKey('Content-Disposition', $result['headers']);
        $this->assertStringContainsString('.ics', $result['headers']['Content-Disposition']);
    }

    public function testIcsExportReturns404WhenNoAcceptedDate(): void
    {
        $eventId = 'evt-1';
        $adminUser = $this->createAdminUser('admin-1');
        $this->setCurrentUser($adminUser);

        $eventModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventModel->method('get')->willReturnCallback(
            fn(string $f) => match ($f) {
                'id' => $eventId,
                'accepted_date' => null,
                default => null,
            }
        );

        $eventsNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventsNew->method('findById')->willReturn($eventModel);

        $adminRetrieved = $this->createAdminUser('admin-1');

        $this->modelFactory->method('new')->willReturn($eventsNew);
        $this->modelFactory->method('retrieve')->willReturnCallback(
            function (string $name, string $id) use ($adminRetrieved) {
                if ($name === 'Users') {
                    return $adminRetrieved;
                }
                return null;
            }
        );

        $request = $this->createRequest(
            '/events/evt-1/ics',
            ['events', 'event_id', 'ics'],
            'GET'
        );

        $this->expectException(NotFoundException::class);
        $this->controller->getIcs($request);
    }

    public function testIcsExportDeniesGuest(): void
    {
        $eventId = 'evt-1';
        $this->setCurrentUser(null);

        $eventModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventModel->method('get')->willReturnCallback(
            fn(string $f) => $f === 'id' ? $eventId : null
        );

        $eventsNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventsNew->method('findById')->willReturn($eventModel);

        $this->modelFactory->method('new')->willReturn($eventsNew);

        $request = $this->createRequest(
            '/events/evt-1/ics',
            ['events', 'event_id', 'ics'],
            'GET'
        );

        $this->expectException(ForbiddenException::class);
        $this->controller->getIcs($request);
    }

    public function testIcsExportDeniesUninvitedUser(): void
    {
        $eventId = 'evt-1';
        $user = $this->createRegularUser('user-99');
        $this->setCurrentUser($user);

        $eventModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventModel->method('get')->willReturnCallback(
            fn(string $f) => $f === 'id' ? $eventId : null
        );

        $eventsNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventsNew->method('findById')->willReturn($eventModel);
        $eventsNew->method('hasRelation')->willReturn(false);

        $usersNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $usersNew->method('findById')->willReturn($user);

        $uninvitedRetrieved = $this->createRegularUser('user-99');

        $this->modelFactory->method('new')->willReturnCallback(
            fn(string $n) => match ($n) {
                'Events' => $eventsNew,
                'Users' => $usersNew,
                default => $this->createMock(\Gravitycar\Models\ModelBase::class),
            }
        );
        $this->modelFactory->method('retrieve')->willReturnCallback(
            function (string $name, string $id) use ($uninvitedRetrieved) {
                if ($name === 'Users') {
                    return $uninvitedRetrieved;
                }
                return null;
            }
        );

        $request = $this->createRequest(
            '/events/evt-1/ics',
            ['events', 'event_id', 'ics'],
            'GET'
        );

        $this->expectException(ForbiddenException::class);
        $this->controller->getIcs($request);
    }

    public function testIcsExportThrowsNotFoundForMissingEvent(): void
    {
        $adminUser = $this->createAdminUser('admin-1');
        $this->setCurrentUser($adminUser);

        $eventsNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventsNew->method('findById')->willReturn(null);

        $this->modelFactory->method('new')->willReturn($eventsNew);

        $request = $this->createRequest(
            '/events/nonexistent/ics',
            ['events', 'event_id', 'ics'],
            'GET'
        );

        $this->expectException(NotFoundException::class);
        $this->controller->getIcs($request);
    }

    public function testRolesAndActionsExcludeGuest(): void
    {
        $rolesAndActions = $this->controller->getRolesAndActions();
        $this->assertEmpty($rolesAndActions['guest']);
        $this->assertContains('read', $rolesAndActions['admin']);
        $this->assertContains('read', $rolesAndActions['user']);
    }
}
