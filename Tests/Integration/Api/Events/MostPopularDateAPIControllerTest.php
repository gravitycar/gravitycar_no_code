<?php

namespace Gravitycar\Tests\Integration\Api\Events;

use Gravitycar\Models\events\api\MostPopularDateAPIController;
use Gravitycar\Models\events\Events;
use Gravitycar\Exceptions\BadRequestException;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\ForbiddenException;

/**
 * Integration tests for MostPopularDateAPIController.
 * Covers tied dates, single winner, empty results, and access control.
 */
class MostPopularDateAPIControllerTest extends EventApiTestCase
{
    private MostPopularDateAPIController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new MostPopularDateAPIController(
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
        $this->assertStringContainsString('most-popular-date', $routes[0]['path']);
    }

    public function testReturnsSingleMostPopularDate(): void
    {
        $eventId = 'evt-1';
        $this->setCurrentUser(null);

        $eventModel = $this->createEventsMock($eventId);
        $eventModel->method('getMostPopularDates')->willReturn([
            ['proposed_date_id' => 'pd-1', 'proposed_date' => '2026-06-01 19:00:00', 'count' => 5],
        ]);

        $eventsNew = $this->createEventsMock(null);
        $eventsNew->method('findById')->with($eventId)->willReturn($eventModel);

        $this->modelFactory->method('new')->willReturn($eventsNew);

        $request = $this->createRequest(
            '/events/evt-1/most-popular-date',
            ['events', 'event_id', 'most-popular-date'],
            'GET'
        );

        $result = $this->controller->getMostPopularDate($request);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['data']['most_popular_dates']);
        $this->assertFalse($result['data']['tied']);
    }

    public function testReturnsTiedDates(): void
    {
        $eventId = 'evt-1';
        $this->setCurrentUser(null);

        $eventModel = $this->createEventsMock($eventId);
        $eventModel->method('getMostPopularDates')->willReturn([
            ['proposed_date_id' => 'pd-1', 'proposed_date' => '2026-06-01 19:00:00', 'count' => 3],
            ['proposed_date_id' => 'pd-2', 'proposed_date' => '2026-06-02 19:00:00', 'count' => 3],
        ]);

        $eventsNew = $this->createEventsMock(null);
        $eventsNew->method('findById')->willReturn($eventModel);

        $this->modelFactory->method('new')->willReturn($eventsNew);

        $request = $this->createRequest(
            '/events/evt-1/most-popular-date',
            ['events', 'event_id', 'most-popular-date'],
            'GET'
        );

        $result = $this->controller->getMostPopularDate($request);

        $this->assertCount(2, $result['data']['most_popular_dates']);
        $this->assertTrue($result['data']['tied']);
    }

    public function testReturnsEmptyWhenNoCommitments(): void
    {
        $eventId = 'evt-1';
        $this->setCurrentUser(null);

        $eventModel = $this->createEventsMock($eventId);
        $eventModel->method('getMostPopularDates')->willReturn([]);

        $eventsNew = $this->createEventsMock(null);
        $eventsNew->method('findById')->willReturn($eventModel);

        $this->modelFactory->method('new')->willReturn($eventsNew);

        $request = $this->createRequest(
            '/events/evt-1/most-popular-date',
            ['events', 'event_id', 'most-popular-date'],
            'GET'
        );

        $result = $this->controller->getMostPopularDate($request);

        $this->assertEmpty($result['data']['most_popular_dates']);
        $this->assertFalse($result['data']['tied']);
    }

    public function testGuestCanAccessMostPopularDate(): void
    {
        $this->setCurrentUser(null);

        $eventModel = $this->createEventsMock('evt-1');
        $eventModel->method('getMostPopularDates')->willReturn([]);

        $eventsNew = $this->createEventsMock(null);
        $eventsNew->method('findById')->willReturn($eventModel);

        $this->modelFactory->method('new')->willReturn($eventsNew);

        $request = $this->createRequest(
            '/events/evt-1/most-popular-date',
            ['events', 'event_id', 'most-popular-date'],
            'GET'
        );

        $result = $this->controller->getMostPopularDate($request);
        $this->assertTrue($result['success']);
    }

    public function testDeniesUninvitedAuthenticatedUser(): void
    {
        $user = $this->createRegularUser('user-99');
        $this->setCurrentUser($user);

        $eventModel = $this->createEventsMock('evt-1');

        $eventsNew = $this->createEventsMock(null);
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
            '/events/evt-1/most-popular-date',
            ['events', 'event_id', 'most-popular-date'],
            'GET'
        );

        $this->expectException(ForbiddenException::class);
        $this->controller->getMostPopularDate($request);
    }

    public function testThrowsNotFoundForMissingEvent(): void
    {
        $this->setCurrentUser(null);

        $eventsNew = $this->createEventsMock(null);
        $eventsNew->method('findById')->willReturn(null);

        $this->modelFactory->method('new')->willReturn($eventsNew);

        $request = $this->createRequest(
            '/events/nonexistent/most-popular-date',
            ['events', 'event_id', 'most-popular-date'],
            'GET'
        );

        $this->expectException(NotFoundException::class);
        $this->controller->getMostPopularDate($request);
    }

    /**
     * Create a mock of the concrete Events class (which has getMostPopularDates).
     */
    private function createEventsMock(?string $eventId): \PHPUnit\Framework\MockObject\MockObject
    {
        $mock = $this->getMockBuilder(Events::class)
            ->disableOriginalConstructor()
            ->getMock();
        if ($eventId !== null) {
            $mock->method('get')->willReturnCallback(
                fn(string $f) => $f === 'id' ? $eventId : null
            );
        }
        return $mock;
    }
}
