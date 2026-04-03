<?php

namespace Gravitycar\Tests\Integration\Api\Events;

use Gravitycar\Models\events\api\SmartRouteAPIController;

/**
 * Integration tests for SmartRouteAPIController.
 * Covers guest redirect, single-event redirect, multi-event list redirect,
 * and zero-event list redirect scenarios.
 */
class SmartRouteAPIControllerTest extends EventApiTestCase
{
    private SmartRouteAPIController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new SmartRouteAPIController(
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
        $this->assertStringContainsString('smart-route', $routes[0]['path']);
    }

    public function testGuestRedirectsToEventsList(): void
    {
        $this->setCurrentUser(null);

        $request = $this->createRequest(
            '/events/smart-route',
            [],
            'GET'
        );

        $result = $this->controller->getSmartRoute($request);

        $this->assertTrue($result['success']);
        $this->assertEquals('/events', $result['data']['redirect_to']);
        $this->assertEquals(0, $result['data']['upcoming_count']);
    }

    public function testSingleUpcomingEventRedirectsToChart(): void
    {
        $futureDate = (new \DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');

        $eventModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventModel->method('get')->willReturnCallback(
            fn(string $f) => match ($f) {
                'id' => 'evt-1',
                'accepted_date' => $futureDate,
                default => null,
            }
        );

        $user = $this->createSmartRouteUser('user-1', [$eventModel]);
        $this->setCurrentUser($user);

        $request = $this->createRequest('/events/smart-route', [], 'GET');

        $result = $this->controller->getSmartRoute($request);

        $this->assertEquals('/events/evt-1/chart', $result['data']['redirect_to']);
        $this->assertEquals(1, $result['data']['upcoming_count']);
    }

    public function testMultipleUpcomingEventsRedirectsToList(): void
    {
        $futureDate = (new \DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');

        $event1 = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $event1->method('get')->willReturnCallback(
            fn(string $f) => match ($f) {
                'id' => 'evt-1',
                'accepted_date' => $futureDate,
                default => null,
            }
        );

        $event2 = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $event2->method('get')->willReturnCallback(
            fn(string $f) => match ($f) {
                'id' => 'evt-2',
                'accepted_date' => $futureDate,
                default => null,
            }
        );

        $user = $this->createSmartRouteUser('user-1', [$event1, $event2]);
        $this->setCurrentUser($user);

        $request = $this->createRequest('/events/smart-route', [], 'GET');

        $result = $this->controller->getSmartRoute($request);

        $this->assertEquals('/events', $result['data']['redirect_to']);
        $this->assertEquals(2, $result['data']['upcoming_count']);
    }

    public function testNoUpcomingEventsRedirectsToList(): void
    {
        $user = $this->createSmartRouteUser('user-1', []);
        $this->setCurrentUser($user);

        $request = $this->createRequest('/events/smart-route', [], 'GET');

        $result = $this->controller->getSmartRoute($request);

        $this->assertEquals('/events', $result['data']['redirect_to']);
        $this->assertEquals(0, $result['data']['upcoming_count']);
    }

    public function testPastEventsNotCountedAsUpcoming(): void
    {
        $pastDate = (new \DateTimeImmutable('-30 days'))->format('Y-m-d H:i:s');

        $pastEvent = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $pastEvent->method('get')->willReturnCallback(
            fn(string $f) => match ($f) {
                'id' => 'evt-old',
                'accepted_date' => $pastDate,
                default => null,
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

        $user = $this->createSmartRouteUser('user-1', [$pastEvent]);
        $this->setCurrentUser($user);

        $request = $this->createRequest('/events/smart-route', [], 'GET');

        $result = $this->controller->getSmartRoute($request);

        $this->assertEquals('/events', $result['data']['redirect_to']);
        $this->assertEquals(0, $result['data']['upcoming_count']);
    }

    public function testEventWithFutureProposedDateIsUpcoming(): void
    {
        $eventModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $eventModel->method('get')->willReturnCallback(
            fn(string $f) => match ($f) {
                'id' => 'evt-1',
                'accepted_date' => null,
                default => null,
            }
        );

        $pdModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $pdModel->method('findRaw')->willReturn([['id' => 'pd-1']]);

        $this->modelFactory->method('new')->willReturnCallback(
            fn(string $n) => match ($n) {
                'EventProposedDates' => $pdModel,
                default => $this->createMock(\Gravitycar\Models\ModelBase::class),
            }
        );

        $user = $this->createSmartRouteUser('user-1', [$eventModel]);
        $this->setCurrentUser($user);

        $request = $this->createRequest('/events/smart-route', [], 'GET');

        $result = $this->controller->getSmartRoute($request);

        $this->assertEquals('/events/evt-1/chart', $result['data']['redirect_to']);
        $this->assertEquals(1, $result['data']['upcoming_count']);
    }

    public function testResponseIncludesTimestamp(): void
    {
        $this->setCurrentUser(null);

        $request = $this->createRequest('/events/smart-route', [], 'GET');

        $result = $this->controller->getSmartRoute($request);
        $this->assertArrayHasKey('timestamp', $result);
    }

    /**
     * Create a user mock that supports both users_roles and events_users_invitations
     * relationship lookups. SmartRouteAPIController calls getRelatedModels with
     * events_users_invitations on the user, which conflicts with the standard
     * createRegularUser helper that only supports users_roles.
     */
    private function createSmartRouteUser(string $userId, array $invitedEvents): \PHPUnit\Framework\MockObject\MockObject
    {
        $userRole = $this->createModelMock(['name' => 'user']);
        $user = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $user->method('get')->willReturnCallback(
            fn(string $f) => $f === 'id' ? $userId : null
        );
        $user->method('getRelatedModels')->willReturnCallback(
            function (string $rel) use ($userRole, $invitedEvents) {
                return match ($rel) {
                    'users_roles' => [$userRole],
                    'events_users_invitations' => $invitedEvents,
                    default => [],
                };
            }
        );
        return $user;
    }
}
