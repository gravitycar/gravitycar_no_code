<?php

declare(strict_types=1);

// CLI guard MUST be first — PHP_SAPI is a PHP built-in constant that needs no autoloader.
// Placing it here prevents any framework code from running in a web context.
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Error: This script must be run from the command line.\n");
    exit(2);
}

define('EXIT_SUCCESS', 0);
define('EXIT_FAILURE', 1);
define('EXIT_INVALID_ARGS', 2);

require_once __DIR__ . '/../vendor/autoload.php';

use Gravitycar\Core\Gravitycar;
use Gravitycar\Core\ContainerConfig;
use Gravitycar\Services\Admin\AdminService;
use Gravitycar\Services\Admin\CacheComponent;
use Gravitycar\Services\Admin\CacheRebuildOptions;
use Gravitycar\Services\Admin\CacheStepResult;

// ---------------------------------------------------------------------------
// Helper: format a step result as a single status line for STDOUT
// ---------------------------------------------------------------------------

/**
 * Formats a CacheStepResult as "[STATUS] stepName (component)".
 *
 * Status mapping:
 *   in_progress → RUNNING
 *   success     → OK
 *   failed      → FAILED
 *   skipped     → SKIPPED
 */
function buildStepLine(CacheStepResult $step): string
{
    $statusMap = [
        'in_progress' => 'RUNNING',
        'success'     => 'OK',
        'failed'      => 'FAILED',
        'skipped'     => 'SKIPPED',
    ];

    $status    = $statusMap[$step->getStatus()] ?? strtoupper($step->getStatus());
    $stepName  = $step->getStepName();
    $component = $step->getComponent();

    return "[{$status}] {$stepName} ({$component})";
}

// ---------------------------------------------------------------------------
// Main execution
// ---------------------------------------------------------------------------

try {
    // Parse CLI flags
    $shortOptions = 'vq';
    $longOptions  = [
        'metadata',
        'routes',
        'docs',
        'navigation',
        'all',
        'schema',
        'permissions',
        'dry-run',
    ];
    $opts = getopt($shortOptions, $longOptions);

    // Presence checks — getopt() maps present flags to false (no-value flags)
    $hasAll         = isset($opts['all']);
    $hasMetadata    = isset($opts['metadata']);
    $hasRoutes      = isset($opts['routes']);
    $hasDocs        = isset($opts['docs']);
    $hasNavigation  = isset($opts['navigation']);
    $hasSchema      = isset($opts['schema']);
    $hasPermissions = isset($opts['permissions']);
    $hasDryRun      = isset($opts['dry-run']);
    $isVerbose      = isset($opts['v']);
    $isQuiet        = isset($opts['q']);

    // -v and -q are mutually exclusive
    if ($isVerbose && $isQuiet) {
        fwrite(STDERR, "Error: -v (verbose) and -q (quiet) cannot be used together.\n");
        exit(EXIT_INVALID_ARGS);
    }

    // Bootstrap the framework. bootstrap() only warms the DI container and
    // router service — it does NOT dispatch an HTTP request. Safe in CLI context.
    $env = getenv('APP_ENV') ?: 'production';
    $gc  = new Gravitycar(['environment' => $env]);
    $gc->bootstrap();

    /** @var AdminService $adminService */
    $adminService = ContainerConfig::getContainer()->get('admin_service');

    // Build CacheRebuildOptions based on the flags provided
    $noComponentFlags = !$hasAll && !$hasMetadata && !$hasRoutes && !$hasDocs && !$hasNavigation;

    if ($noComponentFlags) {
        // AC-37: no component flags → behave as --all --schema --permissions
        $options = new CacheRebuildOptions(
            components:        CacheComponent::all(),
            updateSchema:      true,
            updatePermissions: true,
            dryRun:            $hasDryRun
        );
    } elseif ($hasAll) {
        // --all explicitly provided: all components, honour --schema/--permissions
        $options = CacheRebuildOptions::fromArray([
            'components'        => CacheComponent::all(),
            'updateSchema'      => $hasSchema,
            'updatePermissions' => $hasPermissions,
            'dryRun'            => $hasDryRun,
        ]);
    } else {
        // Specific component flags: build from the flags that are present
        $components = [];
        if ($hasMetadata)   { $components[] = CacheComponent::METADATA; }
        if ($hasRoutes)     { $components[] = CacheComponent::ROUTES; }
        if ($hasDocs)       { $components[] = CacheComponent::DOCS; }
        if ($hasNavigation) { $components[] = CacheComponent::NAVIGATION; }

        $options = CacheRebuildOptions::fromArray([
            'components'        => $components,
            'updateSchema'      => $hasSchema,
            'updatePermissions' => $hasPermissions,
            'dryRun'            => $hasDryRun,
        ]);
    }

    // $onStep callback: writes progress to STDOUT; errors always go to STDERR
    $onStep = function (CacheStepResult $step) use ($isQuiet, $isVerbose): void {
        if ($step->isFailed()) {
            $errorMessage = $step->getErrorMessage() ?? 'Unknown error';
            fwrite(STDERR, '[ERROR] ' . $step->getStepName() . ' (' . $step->getComponent() . '): ' . $errorMessage . "\n");
        }

        if ($isQuiet) {
            return; // Quiet mode: suppress STDOUT progress, not STDERR errors
        }

        fwrite(STDOUT, buildStepLine($step) . "\n");

        if ($isVerbose) {
            fwrite(STDOUT, json_encode($step->toArray(), JSON_PRETTY_PRINT) . "\n");
        }
    };

    $result = $adminService->performCacheRebuild($options, $onStep);

    if ($result->isSuccess()) {
        if (!$isQuiet) {
            fwrite(STDOUT, "\nCache rebuild completed successfully.\n");
        }
        exit(EXIT_SUCCESS);
    }

    fwrite(STDERR, "\nCache rebuild failed. Archive restored to pre-rebuild state.\n");
    exit(EXIT_FAILURE);

} catch (\InvalidArgumentException $e) {
    // Thrown by CacheRebuildOptions when components array is empty or contains
    // an unknown identifier — treated as invalid argument (exit 2).
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(EXIT_INVALID_ARGS);
} catch (\Throwable $e) {
    // Bootstrap failures, container resolution errors, archive phase exceptions, etc.
    fwrite(STDERR, 'Fatal error: ' . $e->getMessage() . "\n");
    exit(EXIT_FAILURE);
}
