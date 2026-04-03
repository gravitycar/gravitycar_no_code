<?php

namespace Gravitycar\Tests\Integration\Api\Events;

use Gravitycar\Models\events\api\ChartAPIController;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\ForbiddenException;
use Gravitycar\Exceptions\BadRequestException;

/**
 * Integration tests for ChartAPIController.
 * Covers access control (admin, invited user, uninvited user, guest),
 * data assembly, and error handling for the Chart of Goodness endpoint.
 */
class ChartAPIControllerTest extends EventApiTestCase
{
    private ChartAPIController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ChartAPIController(
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
        $this->assertEquals('/events/{event_id}/chart', $routes[0]['path']);
        $this->assertEquals('read', $routes[0]['rbacAction']);
    }

    public function testGetChartAsAdminReturnsFullData(): void
    {
        $eventId = 'evt-1';
        $adminUser = $this->createAdminUser('admin-1');
        $this->setCurrentUser($adminUser);

        $eventModel = $this->createModelMock([
            'id' => $eventId,
            'name' => 'Book Club',
            'description' => 'Monthly reading',
            'location' => 'Library',
            'duration_hours' => 2,
            'accepted_date' => null,
            'linked_model_name' => null,
            'linked_record_id' => null,
            'created_by' => 'admin-1',
        ]);

        $eventsNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventsNew->method('findById')->with($eventId)->willReturn($eventModel);
        $eventsNew->method('getRelatedModels')
            ->with('events_users_invitations')
            ->willReturn([]);

        $pdModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $pdModel->method('findRaw')->willReturn([
            ['id' => 'pd-1', 'proposed_date' => '2026-05-01 19:00:00'],
        ]);

        $commModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $commModel->method('findRaw')->willReturn([]);

        $usersRetrieved = $this->createAdminUser('admin-1');

        $this->modelFactory->method('new')->willReturnCallback(
            function (string $name) use ($eventsNew, $pdModel, $commModel) {
                return match ($name) {
                    'Events' => $eventsNew,
                    'EventProposedDates' => $pdModel,
                    'EventCommitments' => $commModel,
                    default => $this->createMock(\Gravitycar\Models\ModelBase::class),
                };
            }
        );
        $this->modelFactory->method('retrieve')->willReturnCallback(
            function (string $name, string $id) use ($usersRetrieved) {
                if ($name === 'Users') {
                    return $usersRetrieved;
                }
                return null;
            }
        );

        $this->metadataEngine->method('getModelMetadata')
            ->with('Users')
            ->willReturn(['displayColumns' => ['username']]);

        $request = $this->createRequest(
            '/events/evt-1/chart',
            ['events', 'event_id', 'chart'],
            'GET'
        );

        $result = $this->controller->getChart($request);

        $this->assertTrue($result['success']);
        $this->assertEquals(200, $result['status']);
        $this->assertEquals('Book Club', $result['data']['event']['name']);
        $this->assertTrue($result['data']['is_admin']);
        $this->assertEquals('admin-1', $result['data']['current_user_id']);
        $this->assertCount(1, $result['data']['proposed_dates']);
    }

    public function testGetChartAsGuestReturnsReadOnlyData(): void
    {
        $eventId = 'evt-2';
        $this->setCurrentUser(null);

        $eventModel = $this->createModelMock([
            'id' => $eventId,
            'name' => 'Movie Night',
            'description' => '',
            'location' => '',
            'duration_hours' => 3,
            'accepted_date' => null,
            'linked_model_name' => null,
            'linked_record_id' => null,
            'created_by' => 'admin-1',
        ]);

        $eventsNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventsNew->method('findById')->with($eventId)->willReturn($eventModel);
        $eventsNew->method('getRelatedModels')->willReturn([]);

        $pdModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $pdModel->method('findRaw')->willReturn([]);

        $commModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $commModel->method('findRaw')->willReturn([]);

        $this->modelFactory->method('new')->willReturnCallback(
            function (string $name) use ($eventsNew, $pdModel, $commModel) {
                return match ($name) {
                    'Events' => $eventsNew,
                    'EventProposedDates' => $pdModel,
                    'EventCommitments' => $commModel,
                    default => $this->createMock(\Gravitycar\Models\ModelBase::class),
                };
            }
        );

        $this->metadataEngine->method('getModelMetadata')
            ->with('Users')
            ->willReturn(['displayColumns' => ['username']]);

        $request = $this->createRequest(
            '/events/evt-2/chart',
            ['events', 'event_id', 'chart'],
            'GET'
        );

        $result = $this->controller->getChart($request);

        $this->assertTrue($result['success']);
        $this->assertNull($result['data']['current_user_id']);
        $this->assertFalse($result['data']['is_admin']);
    }

    public function testGetChartDeniesUninvitedUser(): void
    {
        $eventId = 'evt-3';
        $user = $this->createRegularUser('user-99');
        $this->setCurrentUser($user);

        $eventModel = $this->createModelMock(['id' => $eventId]);

        $eventsNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventsNew->method('findById')->willReturn($eventModel);
        $eventsNew->method('hasRelation')->willReturn(false);

        $usersNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $usersNew->method('findById')->willReturn($user);

        $usersRetrieved = $this->createRegularUser('user-99');

        $this->modelFactory->method('new')->willReturnCallback(
            function (string $name) use ($eventsNew, $usersNew) {
                return match ($name) {
                    'Events' => $eventsNew,
                    'Users' => $usersNew,
                    default => $this->createMock(\Gravitycar\Models\ModelBase::class),
                };
            }
        );
        $this->modelFactory->method('retrieve')->willReturnCallback(
            function (string $name, string $id) use ($usersRetrieved) {
                if ($name === 'Users') {
                    return $usersRetrieved;
                }
                return null;
            }
        );

        $request = $this->createRequest(
            '/events/evt-3/chart',
            ['events', 'event_id', 'chart'],
            'GET'
        );

        $this->expectException(ForbiddenException::class);
        $this->controller->getChart($request);
    }

    public function testGetChartThrowsNotFoundForMissingEvent(): void
    {
        $this->setCurrentUser(null);

        $eventsNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventsNew->method('findById')->willReturn(null);

        $this->modelFactory->method('new')->willReturn($eventsNew);

        $request = $this->createRequest(
            '/events/nonexistent/chart',
            ['events', 'event_id', 'chart'],
            'GET'
        );

        $this->expectException(NotFoundException::class);
        $this->controller->getChart($request);
    }

    public function testGetChartThrowsBadRequestWhenEventIdMissing(): void
    {
        $request = $this->createRequest(
            '/events//chart',
            ['events', 'event_id', 'chart'],
            'GET'
        );

        $this->expectException(BadRequestException::class);
        $this->controller->getChart($request);
    }

    public function testCommitmentsIndexedByUserAndDate(): void
    {
        $eventId = 'evt-4';
        $this->setCurrentUser(null);

        $eventModel = $this->createModelMock([
            'id' => $eventId, 'name' => 'E', 'description' => '',
            'location' => '', 'duration_hours' => 3,
            'accepted_date' => null, 'linked_model_name' => null,
            'linked_record_id' => null, 'created_by' => 'a',
        ]);

        $eventsNew = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventsNew->method('findById')->willReturn($eventModel);
        $eventsNew->method('getRelatedModels')->willReturn([]);

        $pdModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $pdModel->method('findRaw')->willReturn([]);

        $commModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $commModel->method('findRaw')->willReturn([
            ['user_id' => 'u1', 'proposed_date_id' => 'pd1', 'is_available' => 1],
            ['user_id' => 'u1', 'proposed_date_id' => 'pd2', 'is_available' => 0],
        ]);

        $this->modelFactory->method('new')->willReturnCallback(
            fn(string $n) => match ($n) {
                'Events' => $eventsNew,
                'EventProposedDates' => $pdModel,
                'EventCommitments' => $commModel,
                default => $this->createMock(\Gravitycar\Models\ModelBase::class),
            }
        );
        $this->metadataEngine->method('getModelMetadata')
            ->willReturn(['displayColumns' => ['username']]);

        $request = $this->createRequest(
            '/events/evt-4/chart',
            ['events', 'event_id', 'chart'],
            'GET'
        );

        $result = $this->controller->getChart($request);
        $commitments = $result['data']['commitments'];

        $this->assertTrue($commitments['u1:pd1']);
        $this->assertFalse($commitments['u1:pd2']);
    }
}
