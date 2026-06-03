<?php

declare(strict_types=1);

namespace Gravitycar\Tests\Unit\Api;

use Gravitycar\Api\AdminAPIController;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Core\Config;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Services\Admin\AdminService;
use Gravitycar\Services\Admin\CacheComponent;
use Gravitycar\Services\Admin\CacheRebuildResult;
use Gravitycar\Services\Admin\CacheStepResult;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for AdminAPIController.
 *
 * Tests that can be verified without triggering HTTP output functions:
 * - Route registration
 * - $rolesAndActions value
 * - Request validation logic (via a testable subclass that overrides getRawRequestBody)
 */
class AdminAPIControllerTest extends TestCase
{
    private MockObject $mockLogger;
    private MockObject $mockAdminService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLogger       = $this->createMock(Logger::class);
        $this->mockAdminService = $this->createMock(AdminService::class);
    }

    /**
     * Creates a testable controller subclass that overrides HTTP-output methods
     * so we can invoke handleCacheRebuild() without sending real HTTP headers.
     */
    private function makeTestableController(string $rawBody = '{}'): AdminAPIController
    {
        return new class(
            $this->mockLogger,
            null,
            null,
            null,
            null,
            null,
            $this->mockAdminService,
            $rawBody
        ) extends AdminAPIController {
            private string $rawBody;
            private bool $sseHeadersSet = false;
            private array $emittedEvents = [];
            private int $sentStatusCode  = 0;
            private string $sentJsonBody  = '';

            public function __construct(
                Logger $logger = null,
                ModelFactory $modelFactory = null,
                DatabaseConnectorInterface $databaseConnector = null,
                MetadataEngineInterface $metadataEngine = null,
                Config $config = null,
                CurrentUserProviderInterface $currentUserProvider = null,
                AdminService $adminService = null,
                string $rawBody = '{}'
            ) {
                parent::__construct($logger, $modelFactory, $databaseConnector, $metadataEngine, $config, $currentUserProvider, $adminService);
                $this->rawBody = $rawBody;
            }

            protected function getRawRequestBody(): string
            {
                return $this->rawBody;
            }

            public function wasSseHeadersSet(): bool
            {
                return $this->sseHeadersSet;
            }

            public function getEmittedEvents(): array
            {
                return $this->emittedEvents;
            }

            public function getSentStatusCode(): int
            {
                return $this->sentStatusCode;
            }

            public function getSentJsonBody(): string
            {
                return $this->sentJsonBody;
            }
        };
    }

    // -------------------------------------------------------------------------
    // registerRoutes()
    // -------------------------------------------------------------------------

    public function testRegisterRoutesReturnsExactlyOneRoute(): void
    {
        $controller = $this->makeTestableController();
        $routes     = $controller->registerRoutes();
        $this->assertCount(1, $routes);
    }

    public function testRegisterRoutesHasCorrectMethod(): void
    {
        $controller = $this->makeTestableController();
        $routes     = $controller->registerRoutes();
        $this->assertSame('POST', $routes[0]['method']);
    }

    public function testRegisterRoutesHasCorrectPath(): void
    {
        $controller = $this->makeTestableController();
        $routes     = $controller->registerRoutes();
        $this->assertSame('/admin/cache/rebuild', $routes[0]['path']);
    }

    public function testRegisterRoutesHasCorrectApiClass(): void
    {
        $controller = $this->makeTestableController();
        $routes     = $controller->registerRoutes();
        $this->assertSame(AdminAPIController::class, $routes[0]['apiClass']);
    }

    public function testRegisterRoutesHasCorrectApiMethod(): void
    {
        $controller = $this->makeTestableController();
        $routes     = $controller->registerRoutes();
        $this->assertSame('handleCacheRebuild', $routes[0]['apiMethod']);
    }

    public function testRegisterRoutesHasEmptyParameterNames(): void
    {
        $controller = $this->makeTestableController();
        $routes     = $controller->registerRoutes();
        $this->assertEmpty($routes[0]['parameterNames']);
    }

    public function testRegisterRoutesHasRbacAction(): void
    {
        $controller = $this->makeTestableController();
        $routes     = $controller->registerRoutes();
        $this->assertArrayHasKey('rbacAction', $routes[0]);
        $this->assertSame('rebuild', $routes[0]['rbacAction']);
    }

    // -------------------------------------------------------------------------
    // $rolesAndActions
    // -------------------------------------------------------------------------

    public function testRolesAndActionsIsAdminOnly(): void
    {
        $controller = $this->makeTestableController();
        $reflection = new ReflectionClass($controller);
        $property   = $reflection->getProperty('rolesAndActions');
        $property->setAccessible(true);
        $rolesAndActions = $property->getValue($controller);

        $this->assertArrayHasKey('admin', $rolesAndActions);
        $this->assertCount(1, $rolesAndActions, 'Only admin role should be allowed');
    }

    public function testRolesAndActionsAdminHasWildcardAction(): void
    {
        $controller = $this->makeTestableController();
        $reflection = new ReflectionClass($controller);
        $property   = $reflection->getProperty('rolesAndActions');
        $property->setAccessible(true);
        $rolesAndActions = $property->getValue($controller);

        $this->assertSame(['*'], $rolesAndActions['admin']);
    }

    // -------------------------------------------------------------------------
    // parseAndValidateRequestBody() — via reflection to avoid HTTP output
    // -------------------------------------------------------------------------

    public function testParseAndValidateReturnsNullForInvalidJson(): void
    {
        $controller = $this->makeTestableController('not-json');
        $reflection = new ReflectionClass($controller);
        $method     = $reflection->getMethod('parseAndValidateRequestBody');
        $method->setAccessible(true);

        // This will call http_response_code and header — in CLI test environment these
        // may not throw but will produce no visible output. The return value is what matters.
        $result = $method->invoke($controller);
        $this->assertNull($result);
    }

    public function testParseAndValidateReturnsNullForEmptyComponents(): void
    {
        $controller = $this->makeTestableController(json_encode(['components' => []]));
        $reflection = new ReflectionClass($controller);
        $method     = $reflection->getMethod('parseAndValidateRequestBody');
        $method->setAccessible(true);

        $result = $method->invoke($controller);
        $this->assertNull($result);
    }

    public function testParseAndValidateReturnsNullForUnknownComponent(): void
    {
        $controller = $this->makeTestableController(json_encode(['components' => ['not_a_component']]));
        $reflection = new ReflectionClass($controller);
        $method     = $reflection->getMethod('parseAndValidateRequestBody');
        $method->setAccessible(true);

        $result = $method->invoke($controller);
        $this->assertNull($result);
    }

    public function testParseAndValidateReturnsDataForValidComponents(): void
    {
        $body       = json_encode(['components' => [CacheComponent::METADATA]]);
        $controller = $this->makeTestableController($body);
        $reflection = new ReflectionClass($controller);
        $method     = $reflection->getMethod('parseAndValidateRequestBody');
        $method->setAccessible(true);

        $result = $method->invoke($controller);
        $this->assertIsArray($result);
        $this->assertSame([CacheComponent::METADATA], $result['components']);
    }

    public function testParseAndValidateReturnsDataForAllFourComponents(): void
    {
        $body = json_encode([
            'components' => CacheComponent::all(),
        ]);
        $controller = $this->makeTestableController($body);
        $reflection = new ReflectionClass($controller);
        $method     = $reflection->getMethod('parseAndValidateRequestBody');
        $method->setAccessible(true);

        $result = $method->invoke($controller);
        $this->assertIsArray($result);
        $this->assertCount(4, $result['components']);
    }

    // -------------------------------------------------------------------------
    // handleCacheRebuild() — does NOT set SSE headers for invalid request
    // Tested via parseAndValidateRequestBody() returning null (no SSE headers set)
    // -------------------------------------------------------------------------

    public function testInvalidJsonDoesNotProceedToSsePhase(): void
    {
        // Verify that parseAndValidateRequestBody returns null for bad JSON
        // (which means handleCacheRebuild returns early without calling performCacheRebuild)
        $controller = $this->makeTestableController('this is not json');
        $reflection = new ReflectionClass($controller);
        $method     = $reflection->getMethod('parseAndValidateRequestBody');
        $method->setAccessible(true);

        ob_start();
        $result = $method->invoke($controller);
        ob_end_clean();

        $this->assertNull($result);
    }

    public function testInvalidComponentDoesNotProceedToSsePhase(): void
    {
        $body       = json_encode(['components' => ['bogus_component']]);
        $controller = $this->makeTestableController($body);
        $reflection = new ReflectionClass($controller);
        $method     = $reflection->getMethod('parseAndValidateRequestBody');
        $method->setAccessible(true);

        ob_start();
        $result = $method->invoke($controller);
        ob_end_clean();

        $this->assertNull($result);
    }

    public function testValidBodyProceedsToSsePhase(): void
    {
        $body       = json_encode(['components' => [CacheComponent::METADATA]]);
        $controller = $this->makeTestableController($body);
        $reflection = new ReflectionClass($controller);
        $method     = $reflection->getMethod('parseAndValidateRequestBody');
        $method->setAccessible(true);

        $result = $method->invoke($controller);

        $this->assertIsArray($result);
        $this->assertNotNull($result);
    }
}
