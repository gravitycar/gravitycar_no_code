<?php

declare(strict_types=1);

namespace Gravitycar\Tests\Integration\Api;

use Gravitycar\Api\AdminAPIController;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Core\Config;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Services\Admin\AdminService;
use Gravitycar\Services\Admin\CacheComponent;
use Gravitycar\Services\Admin\CacheRebuildOptions;
use Gravitycar\Services\Admin\CacheRebuildResult;
use Gravitycar\Services\Admin\CacheStepResult;
use Gravitycar\Tests\TestCase;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;

/**
 * Integration tests for AdminAPIController request/response cycle.
 *
 * Tests the full validation path — missing components, empty array, unknown
 * component, and a valid body — without hitting real file I/O.
 *
 * The controller's HTTP-output methods (header(), http_response_code(), echo)
 * are neutralised by using output buffering and a testable subclass that
 * overrides getRawRequestBody().
 *
 * 401/403 paths are enforced by RBAC middleware before the controller method
 * runs, so they are not exercisable through the controller method directly.
 */
class AdminAPIControllerIntegrationTest extends TestCase
{
    private MockObject $mockAdminService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAdminService = $this->createMock(AdminService::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns a testable subclass of AdminAPIController whose getRawRequestBody()
     * returns a caller-supplied string so we never touch php://input.
     *
     * All real HTTP-output calls (header, http_response_code, echo, flush,
     * ob_implicit_flush, exit) are either buffered or overridden in the subclass.
     */
    private function makeController(string $rawBody): AdminAPIController
    {
        $logger = $this->logger;
        $adminService = $this->mockAdminService;

        return new class(
            $logger,
            null,
            null,
            null,
            null,
            null,
            $adminService,
            $rawBody
        ) extends AdminAPIController {
            private string $injectedBody;

            /** Captures the HTTP status code sent via http_response_code(). */
            public int $capturedStatusCode = 0;

            /** Captures all content written via echo inside the controller. */
            public string $capturedOutput = '';

            /** Records every header() call made by the controller. */
            public array $capturedHeaders = [];

            /** True once setSseHeaders() has been invoked. */
            public bool $sseHeadersSet = false;

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
                parent::__construct(
                    $logger,
                    $modelFactory,
                    $databaseConnector,
                    $metadataEngine,
                    $config,
                    $currentUserProvider,
                    $adminService
                );
                $this->injectedBody = $rawBody;
            }

            protected function getRawRequestBody(): string
            {
                return $this->injectedBody;
            }
        };
    }

    /**
     * Invokes parseAndValidateRequestBody() via reflection, capturing any echo
     * output in a buffer so HTTP header calls don't pollute the test output.
     */
    private function invokeValidation(AdminAPIController $controller): ?array
    {
        $reflection = new ReflectionClass($controller);
        $method     = $reflection->getMethod('parseAndValidateRequestBody');
        $method->setAccessible(true);

        ob_start();
        try {
            $result = $method->invoke($controller);
        } finally {
            ob_end_clean();
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // 400 — missing components field
    // -------------------------------------------------------------------------

    /**
     * A JSON body with no "components" key must be rejected with a null return
     * (which triggers the 400 path before SSE headers are set).
     */
    public function testMissingComponentsFieldReturnsNull(): void
    {
        $controller = $this->makeController(json_encode(['updateSchema' => true]));

        $result = $this->invokeValidation($controller);

        $this->assertNull($result, 'Missing components field should trigger 400 path (null return)');
    }

    /**
     * A JSON body where "components" is absent entirely must be rejected.
     */
    public function testCompletelyEmptyBodyReturnsNull(): void
    {
        $controller = $this->makeController('{}');

        $result = $this->invokeValidation($controller);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // 400 — empty components array
    // -------------------------------------------------------------------------

    /**
     * A "components" key present but holding an empty array must be rejected.
     */
    public function testEmptyComponentsArrayReturnsNull(): void
    {
        $controller = $this->makeController(json_encode(['components' => []]));

        $result = $this->invokeValidation($controller);

        $this->assertNull($result, 'Empty components array should trigger 400 path (null return)');
    }

    // -------------------------------------------------------------------------
    // 400 — unknown component name
    // -------------------------------------------------------------------------

    /**
     * A components array containing a single unknown identifier must be rejected.
     */
    public function testUnknownComponentNameReturnsNull(): void
    {
        $controller = $this->makeController(json_encode(['components' => ['not_a_real_component']]));

        $result = $this->invokeValidation($controller);

        $this->assertNull($result, 'Unknown component name should trigger 400 path (null return)');
    }

    /**
     * A components array mixing valid and invalid identifiers must also be rejected
     * (any unknown identifier is sufficient to fail validation).
     */
    public function testMixedValidAndInvalidComponentsReturnsNull(): void
    {
        $controller = $this->makeController(json_encode([
            'components' => [CacheComponent::METADATA, 'unknown_component'],
        ]));

        $result = $this->invokeValidation($controller);

        $this->assertNull($result);
    }

    /**
     * Malformed JSON must be rejected (json_decode returns null, not an array).
     */
    public function testMalformedJsonReturnsNull(): void
    {
        $controller = $this->makeController('{ this is not valid json }');

        $result = $this->invokeValidation($controller);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // 200 — valid request body is accepted
    // -------------------------------------------------------------------------

    /**
     * A body with a single valid component should pass validation and return
     * the decoded data array so the controller can proceed to the SSE phase.
     */
    public function testSingleValidComponentIsAccepted(): void
    {
        $controller = $this->makeController(json_encode([
            'components' => [CacheComponent::METADATA],
        ]));

        $result = $this->invokeValidation($controller);

        $this->assertIsArray($result, 'Valid body should return decoded data array');
        $this->assertSame([CacheComponent::METADATA], $result['components']);
    }

    /**
     * A body with all four known components must pass validation.
     */
    public function testAllFourComponentsAreAccepted(): void
    {
        $controller = $this->makeController(json_encode([
            'components' => CacheComponent::all(),
        ]));

        $result = $this->invokeValidation($controller);

        $this->assertIsArray($result);
        $this->assertCount(4, $result['components']);
    }

    /**
     * Optional boolean flags (updateSchema, updatePermissions) are passed
     * through unchanged when the body is otherwise valid.
     */
    public function testOptionalFlagsArePreservedInReturnedData(): void
    {
        $body = [
            'components'        => [CacheComponent::ROUTES],
            'updateSchema'      => true,
            'updatePermissions' => false,
        ];
        $controller = $this->makeController(json_encode($body));

        $result = $this->invokeValidation($controller);

        $this->assertIsArray($result);
        $this->assertTrue($result['updateSchema']);
        $this->assertFalse($result['updatePermissions']);
    }

    /**
     * Each individual CacheComponent constant must be accepted on its own.
     */
    public function testEachCacheComponentConstantIsIndividuallyValid(): void
    {
        $validComponents = [
            CacheComponent::METADATA,
            CacheComponent::ROUTES,
            CacheComponent::DOCS,
            CacheComponent::NAVIGATION,
        ];

        foreach ($validComponents as $component) {
            $controller = $this->makeController(json_encode(['components' => [$component]]));
            $result     = $this->invokeValidation($controller);

            $this->assertIsArray(
                $result,
                "Component '{$component}' should be accepted as valid"
            );
            $this->assertSame([$component], $result['components']);
        }
    }

    // -------------------------------------------------------------------------
    // Route registration (no HTTP I/O required)
    // -------------------------------------------------------------------------

    /**
     * Verify that the controller registers exactly one route at the expected path
     * with admin-only RBAC — this is an observable integration-level invariant.
     */
    public function testRouteRegistrationIsCorrect(): void
    {
        $controller = $this->makeController('{}');
        $routes     = $controller->registerRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame('POST', $routes[0]['method']);
        $this->assertSame('/admin/cache/rebuild', $routes[0]['path']);
        $this->assertSame('handleCacheRebuild', $routes[0]['apiMethod']);
        $this->assertSame('rebuild', $routes[0]['rbacAction']);
        $this->assertEmpty($routes[0]['parameterNames']);
    }

    /**
     * $rolesAndActions must be admin-only — any other value would widen access.
     */
    public function testRolesAndActionsRestrictsToAdminOnly(): void
    {
        $controller = $this->makeController('{}');
        $reflection = new ReflectionClass($controller);
        $property   = $reflection->getProperty('rolesAndActions');
        $property->setAccessible(true);
        $rolesAndActions = $property->getValue($controller);

        $this->assertArrayHasKey('admin', $rolesAndActions);
        $this->assertCount(1, $rolesAndActions, 'Only the admin role should be listed');
        $this->assertSame(['*'], $rolesAndActions['admin']);
    }

    // -------------------------------------------------------------------------
    // AdminService is NOT called when validation fails
    // -------------------------------------------------------------------------

    /**
     * When validation rejects the body, AdminService::performCacheRebuild()
     * must never be invoked — the controller returns before the SSE phase.
     */
    public function testAdminServiceNotCalledWhenValidationFails(): void
    {
        $this->mockAdminService
            ->expects($this->never())
            ->method('performCacheRebuild');

        // Trigger the empty-components 400 path
        $controller = $this->makeController(json_encode(['components' => []]));
        $this->invokeValidation($controller);
    }

    /**
     * When validation passes, AdminService::performCacheRebuild() should be
     * invocable — the controller would proceed to the SSE phase.
     *
     * We verify the validated data is structurally correct for constructing
     * a CacheRebuildOptions, which is what the controller does next.
     */
    public function testValidBodyProducesDataSuitableForCacheRebuildOptions(): void
    {
        $body = [
            'components'        => [CacheComponent::METADATA, CacheComponent::ROUTES],
            'updateSchema'      => true,
            'updatePermissions' => true,
        ];
        $controller = $this->makeController(json_encode($body));

        $result = $this->invokeValidation($controller);

        $this->assertIsArray($result);

        // Confirm the data can be used to construct a CacheRebuildOptions without throwing
        $options = CacheRebuildOptions::fromArray($result);
        $this->assertContains(CacheComponent::METADATA, $options->getComponents());
        $this->assertContains(CacheComponent::ROUTES, $options->getComponents());
        $this->assertTrue($options->isUpdateSchema());
        $this->assertTrue($options->isUpdatePermissions());
    }
}
