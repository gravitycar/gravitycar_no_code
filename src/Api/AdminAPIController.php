<?php

declare(strict_types=1);

namespace Gravitycar\Api;

use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Api\Request;
use Gravitycar\Core\ContainerConfig;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Core\Config;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Services\Admin\AdminService;
use Gravitycar\Services\Admin\CacheComponent;
use Gravitycar\Services\Admin\CacheRebuildOptions;
use Gravitycar\Services\Admin\CacheStepResult;
use Monolog\Logger;

/**
 * AdminAPIController
 *
 * Exposes the cache rebuild operation as an authenticated, admin-only POST
 * endpoint that streams progress as Server-Sent Events (SSE). Bridges the
 * HTTP layer to AdminService::performCacheRebuild(), which performs all real
 * work. Auth and RBAC enforcement happen before the controller method is called.
 *
 * Route: POST /api/admin/cache/rebuild
 * Access: admin role only
 */
class AdminAPIController extends ApiControllerBase
{
    /**
     * Admin-only access — overrides the base class open-access default.
     */
    protected array $rolesAndActions = ['admin' => ['*']];

    private AdminService $adminService;

    /**
     * 7-param constructor following the TMDBController / NavigationAPIController pattern.
     * All parameters are nullable with null defaults so Aura DI can wire them by name.
     * AdminService falls back to the DI container if not explicitly injected.
     */
    public function __construct(
        Logger $logger = null,
        ModelFactory $modelFactory = null,
        DatabaseConnectorInterface $databaseConnector = null,
        MetadataEngineInterface $metadataEngine = null,
        Config $config = null,
        CurrentUserProviderInterface $currentUserProvider = null,
        AdminService $adminService = null
    ) {
        parent::__construct($logger, $modelFactory, $databaseConnector, $metadataEngine, $config, $currentUserProvider);
        $this->adminService = $adminService ?? ContainerConfig::getContainer()->get('admin_service');
    }

    /**
     * Registers the single admin cache-rebuild route.
     *
     * The path does not include the /api prefix; the Router prepends it during
     * dispatch (same pattern used by NavigationAPIController and TMDBController).
     */
    public function registerRoutes(): array
    {
        return [
            [
                'method'         => 'POST',
                'path'           => '/admin/cache/rebuild',
                'apiClass'       => self::class,
                'apiMethod'      => 'handleCacheRebuild',
                'parameterNames' => [],
                'rbacAction'     => 'rebuild',
            ],
        ];
    }

    /**
     * Handles POST /api/admin/cache/rebuild.
     *
     * Execution order:
     *   1. Parse and validate request body (returns 400 JSON on failure, BEFORE SSE headers).
     *   2. Set SSE headers.
     *   3. Build CacheRebuildOptions.
     *   4. Invoke AdminService::performCacheRebuild() with an $onStep SSE-emit callback.
     *   5. Emit final done event; exit(0) to prevent the Router from appending output.
     */
    public function handleCacheRebuild(): void
    {
        $data = $this->parseAndValidateRequestBody();
        if ($data === null) {
            return; // 400 already sent
        }

        $this->setSseHeaders();

        $options = CacheRebuildOptions::fromArray($data);

        $onStep = function (CacheStepResult $step): void {
            $this->emitEvent($step->toArray());
        };

        try {
            $result     = $this->adminService->performCacheRebuild($options, $onStep);
            $finalEvent = array_merge($result->toArray(), ['done' => true]);
            $this->emitEvent($finalEvent);
        } catch (\Throwable $e) {
            // Archive phase failure: SSE headers are already sent, so communicate
            // the error as a done:false SSE event rather than letting the Router
            // generate a 500 response.
            $this->logger?->error('Cache rebuild aborted — archive phase threw', [
                'error' => $e->getMessage(),
            ]);
            $this->emitEvent([
                'done'    => true,
                'success' => false,
                'message' => 'Cache rebuild failed: ' . $e->getMessage(),
                'steps'   => [],
            ]);
        }

        exit(0); // Prevent Router from appending null / overwriting Content-Type
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Reads and validates the JSON request body.
     *
     * Returns the decoded data array on success.
     * On failure: sends a 400 JSON response and returns null (no SSE headers set yet).
     */
    private function parseAndValidateRequestBody(): ?array
    {
        $rawBody = $this->getRawRequestBody();
        $data    = json_decode($rawBody, true);

        if (!is_array($data)) {
            $this->sendJsonError(400, 'Invalid JSON request body');
            return null;
        }

        $components = $data['components'] ?? [];

        if (empty($components)) {
            $this->sendJsonError(400, 'components array must not be empty');
            return null;
        }

        foreach ($components as $component) {
            if (!CacheComponent::isValid($component)) {
                $this->sendJsonError(400, "Unknown cache component: {$component}");
                return null;
            }
        }

        return $data;
    }

    /**
     * Reads the raw HTTP request body.
     * Extracted into a protected method so tests can override it without
     * mocking global functions.
     */
    protected function getRawRequestBody(): string
    {
        return (string) file_get_contents('php://input');
    }

    /**
     * Sends a 400-class JSON error response.
     * Called BEFORE SSE headers are set, so the response is a plain JSON body.
     */
    private function sendJsonError(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
    }

    /**
     * Sets the Server-Sent Events response headers and enables implicit flushing.
     * Must be called after all validation is complete (no 400 errors remain).
     */
    private function setSseHeaders(): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        ob_implicit_flush(true);
    }

    /**
     * Emits a single SSE data frame and flushes the output buffer immediately.
     * Each call produces one "data: {...}\n\n" line pair per the SSE spec.
     */
    private function emitEvent(array $data): void
    {
        echo 'data: ' . json_encode($data) . "\n\n";
        flush();
    }
}
