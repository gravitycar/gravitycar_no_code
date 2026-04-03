<?php

namespace Gravitycar\Tests\Integration\Api\Events;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Api\Request;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Core\Config;
use Gravitycar\Models\ModelBase;
use Monolog\Logger;
use Monolog\Handler\TestHandler;

/**
 * Base test case for event API controller integration tests.
 * Provides shared mocks and helper methods for creating
 * test users, events, and invitation data.
 */
abstract class EventApiTestCase extends TestCase
{
    protected Logger $logger;
    protected TestHandler $testHandler;
    protected MockObject $modelFactory;
    protected MockObject $databaseConnector;
    protected MockObject $metadataEngine;
    protected MockObject $config;
    protected MockObject $currentUserProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testHandler = new TestHandler();
        $this->logger = new Logger('test');
        $this->logger->pushHandler($this->testHandler);

        $this->modelFactory = $this->createMock(ModelFactory::class);
        $this->databaseConnector = $this->createMock(DatabaseConnectorInterface::class);
        $this->metadataEngine = $this->createMock(MetadataEngineInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->currentUserProvider = $this->createMock(CurrentUserProviderInterface::class);
    }

    /**
     * Create a mock Request with path params and optional body data.
     */
    protected function createRequest(
        string $url,
        array $paramNames,
        string $method = 'GET',
        array $requestData = []
    ): Request {
        return new Request($url, $paramNames, $method, $requestData);
    }

    /**
     * Create a mock ModelBase that returns specified field values.
     */
    protected function createModelMock(array $fieldValues): MockObject
    {
        $model = $this->createMock(ModelBase::class);
        $model->method('get')->willReturnCallback(
            fn(string $field) => $fieldValues[$field] ?? null
        );
        return $model;
    }

    /**
     * Create a mock user model with an admin role.
     */
    protected function createAdminUser(string $userId = 'admin-1'): MockObject
    {
        $user = $this->createModelMock(['id' => $userId]);
        $adminRole = $this->createModelMock(['name' => 'admin']);
        $user->method('getRelatedModels')
            ->with('users_roles')
            ->willReturn([$adminRole]);
        return $user;
    }

    /**
     * Create a mock user model with a regular user role.
     */
    protected function createRegularUser(string $userId = 'user-1'): MockObject
    {
        $user = $this->createModelMock(['id' => $userId]);
        $userRole = $this->createModelMock(['name' => 'user']);
        $user->method('getRelatedModels')
            ->with('users_roles')
            ->willReturn([$userRole]);
        return $user;
    }

    /**
     * Set the current user on the currentUserProvider mock.
     */
    protected function setCurrentUser(?MockObject $user): void
    {
        $this->currentUserProvider->method('getCurrentUser')
            ->willReturn($user);
    }

    /**
     * Configure modelFactory->retrieve to return an event model.
     */
    protected function stubEventRetrieval(
        string $eventId,
        ?MockObject $eventModel
    ): void {
        $this->modelFactory->method('retrieve')
            ->willReturnCallback(function (string $name, string $id) use ($eventId, $eventModel) {
                if ($name === 'Events' && $id === $eventId) {
                    return $eventModel;
                }
                return null;
            });
    }

    /**
     * Configure isUserAdmin by setting up Users retrieve + roles.
     */
    protected function stubUserAdmin(string $userId, bool $isAdmin): void
    {
        $user = $isAdmin
            ? $this->createAdminUser($userId)
            : $this->createRegularUser($userId);

        $this->modelFactory->method('retrieve')
            ->willReturnCallback(function (string $name, string $id) use ($userId, $user) {
                if ($name === 'Users' && $id === $userId) {
                    return $user;
                }
                return null;
            });
    }
}
