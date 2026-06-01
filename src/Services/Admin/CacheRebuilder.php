<?php

declare(strict_types=1);

namespace Gravitycar\Services\Admin;

use Gravitycar\Core\Config;
use Gravitycar\Core\ContainerConfig;
use Gravitycar\Exceptions\AdminServiceException;
use Monolog\Logger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * CacheRebuilder
 *
 * Handles all cache file operations for the cache rebuild workflow:
 *   - clear()    — removes existing cache files for selected components
 *   - rebuild()  — delegates to engine services to regenerate each component
 *   - validate() — runs `php -l` syntax validation on generated PHP files
 *
 * AdminService calls these methods in sequence after CacheArchiver creates
 * the archive. Exceptions from engine services propagate up to AdminService,
 * which is responsible for catching them and triggering restore.
 *
 * Component-to-cache-path mapping:
 *   METADATA   → cache/metadata_cache.php         (single file)
 *   ROUTES     → cache/api_routes.php             (single file)
 *   DOCS       → cache/documentation/             (directory, recursive)
 *   NAVIGATION → cache/navigation_cache_*.php     (glob pattern, flat)
 */
class CacheRebuilder
{
    private const COMPONENT_CACHE_PATHS = [
        CacheComponent::METADATA   => 'cache/metadata_cache.php',
        CacheComponent::ROUTES     => 'cache/api_routes.php',
        CacheComponent::DOCS       => 'cache/documentation/',
        CacheComponent::NAVIGATION => null,
    ];

    private const NAVIGATION_CACHE_GLOB = 'cache/navigation_cache_*.php';

    private Logger $logger;
    private Config $config;

    public function __construct(Logger $logger, Config $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    // Engine services are resolved lazily to avoid triggering Aura DI's reflection
    // resolver on services that have deep dependency chains containing case-sensitive
    // class names (a pre-existing framework issue on Linux file systems).
    protected function getMetadataEngine(): \Gravitycar\Metadata\MetadataEngine
    {
        return ContainerConfig::getContainer()->get('metadata_engine');
    }

    protected function getApiRouteRegistry(): \Gravitycar\Api\APIRouteRegistry
    {
        return ContainerConfig::getContainer()->get('api_route_registry');
    }

    protected function getOpenAPIGenerator(): \Gravitycar\Services\OpenAPIGenerator
    {
        return ContainerConfig::getContainer()->get('openapi_generator');
    }

    protected function getNavigationBuilder(): \Gravitycar\Services\NavigationBuilder
    {
        return ContainerConfig::getContainer()->get('navigation_builder');
    }

    protected function getSchemaGenerator(): \Gravitycar\Schema\SchemaGenerator
    {
        return ContainerConfig::getContainer()->get('schema_generator');
    }

    protected function getPermissionsBuilder(): \Gravitycar\Services\PermissionsBuilder
    {
        return ContainerConfig::getContainer()->get('permissions_builder');
    }

    /**
     * Clears cache files for each selected component.
     *
     * Uses RecursiveDirectoryIterator for the DOCS directory to ensure
     * subdirectory contents (e.g. cache/documentation/models/) are deleted.
     * Only files are removed — directories are retained.
     *
     * @param string[] $components List of CacheComponent constants to clear.
     * @throws AdminServiceException if any file deletion fails.
     */
    public function clear(array $components): void
    {
        $this->logger->info('Clearing cache files', ['components' => $components]);

        foreach ($components as $component) {
            $this->logger->info('Clearing component cache', ['component' => $component]);
            $this->clearComponent($component);
            $this->logger->info('Component cache cleared', ['component' => $component]);
        }

        $this->logger->info('Cache clear complete', ['components' => $components]);
    }

    /**
     * Rebuilds cache for each selected component by calling the appropriate
     * engine service. Iterates in canonical order (METADATA → ROUTES → DOCS →
     * NAVIGATION) regardless of the order passed in $components.
     *
     * After METADATA is rebuilt, optionally runs schema update and/or
     * permissions update if the corresponding flags are set.
     *
     * The $onStep callable receives a CacheStepResult before and after each
     * step, enabling real-time SSE streaming and CLI progress output.
     *
     * @param string[]        $components         CacheComponent constants to rebuild.
     * @param bool            $updateSchema        If true and METADATA rebuilt, run SchemaGenerator.
     * @param bool            $updatePermissions   If true and METADATA rebuilt, run PermissionsBuilder.
     * @param callable        $onStep              Callback: fn(CacheStepResult): void
     */
    public function rebuild(
        array $components,
        bool $updateSchema,
        bool $updatePermissions,
        callable $onStep
    ): void {
        $this->logger->info('Rebuilding cache components', ['components' => $components]);

        $metadata = null;

        foreach (CacheComponent::all() as $component) {
            if (!in_array($component, $components, strict: true)) {
                continue;
            }

            $onStep(CacheStepResult::inProgress('rebuild', $component));
            $metadata = $this->rebuildComponent($component);
            $onStep(CacheStepResult::success('rebuild', $component));

            if ($component === CacheComponent::METADATA) {
                $this->runSchemaAndPermissions($metadata, $updateSchema, $updatePermissions, $onStep);
            }
        }

        $this->logger->info('Cache rebuild complete', ['components' => $components]);
    }

    /**
     * Validates generated PHP cache files for each selected component by
     * running `php -l` on each file. Throws AdminServiceException if any
     * file fails syntax validation.
     *
     * @param string[] $components CacheComponent constants to validate.
     * @throws AdminServiceException if any file fails php -l syntax check.
     */
    public function validate(array $components): void
    {
        $this->logger->info('Validating rebuilt cache files', ['components' => $components]);

        foreach ($components as $component) {
            $files = $this->collectFilesForComponent($component);
            foreach ($files as $filePath) {
                $this->validateFile($filePath, $component);
            }
        }

        $this->logger->info('Cache validation complete', ['components' => $components]);
    }

    // -------------------------------------------------------------------------
    // Private helpers — clear
    // -------------------------------------------------------------------------

    private function clearComponent(string $component): void
    {
        match ($component) {
            CacheComponent::METADATA   => $this->clearFile(self::COMPONENT_CACHE_PATHS[CacheComponent::METADATA]),
            CacheComponent::ROUTES     => $this->clearFile(self::COMPONENT_CACHE_PATHS[CacheComponent::ROUTES]),
            CacheComponent::DOCS       => $this->clearDirectory(self::COMPONENT_CACHE_PATHS[CacheComponent::DOCS]),
            CacheComponent::NAVIGATION => $this->clearGlobPattern(self::NAVIGATION_CACHE_GLOB),
        };
    }

    private function clearFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        if (!unlink($filePath)) {
            throw new AdminServiceException(
                'Failed to delete cache file',
                ['filePath' => $filePath]
            );
        }
    }

    private function clearDirectory(string $dirPath): void
    {
        if (!is_dir($dirPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && !unlink($file->getRealPath())) {
                throw new AdminServiceException(
                    'Failed to delete file during cache clear',
                    ['filePath' => $file->getRealPath(), 'component' => CacheComponent::DOCS]
                );
            }
        }
    }

    private function clearGlobPattern(string $pattern): void
    {
        $files = glob($pattern);

        if ($files === false) {
            return;
        }

        foreach ($files as $filePath) {
            if (!unlink($filePath)) {
                throw new AdminServiceException(
                    'Failed to delete navigation cache file',
                    ['filePath' => $filePath, 'component' => CacheComponent::NAVIGATION]
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers — rebuild
    // -------------------------------------------------------------------------

    private function rebuildComponent(string $component): ?array
    {
        return match ($component) {
            CacheComponent::METADATA   => $this->getMetadataEngine()->loadAllMetadata(),
            CacheComponent::ROUTES     => $this->rebuildRoutes(),
            CacheComponent::DOCS       => $this->rebuildDocs(),
            CacheComponent::NAVIGATION => $this->rebuildNavigation(),
        };
    }

    private function rebuildRoutes(): null
    {
        $this->getApiRouteRegistry()->rebuildCache();
        return null;
    }

    private function rebuildDocs(): null
    {
        $this->getOpenAPIGenerator()->generateSpecification();
        return null;
    }

    private function rebuildNavigation(): null
    {
        $this->getNavigationBuilder()->buildAllRoleNavigationCaches();
        return null;
    }

    private function runSchemaAndPermissions(
        ?array $metadata,
        bool $updateSchema,
        bool $updatePermissions,
        callable $onStep
    ): void {
        if ($updateSchema && $metadata !== null) {
            $onStep(CacheStepResult::inProgress('schema_update', CacheComponent::METADATA));
            $this->getSchemaGenerator()->generateSchema($metadata);
            $onStep(CacheStepResult::success('schema_update', CacheComponent::METADATA));
        }

        if ($updatePermissions) {
            $onStep(CacheStepResult::inProgress('permissions_update', CacheComponent::METADATA));
            $this->getPermissionsBuilder()->buildAllPermissions();
            $onStep(CacheStepResult::success('permissions_update', CacheComponent::METADATA));
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers — validate
    // -------------------------------------------------------------------------

    private function collectFilesForComponent(string $component): array
    {
        return match ($component) {
            CacheComponent::METADATA   => $this->filterExisting([self::COMPONENT_CACHE_PATHS[CacheComponent::METADATA]]),
            CacheComponent::ROUTES     => $this->filterExisting([self::COMPONENT_CACHE_PATHS[CacheComponent::ROUTES]]),
            CacheComponent::DOCS       => $this->collectPhpFilesInDir(self::COMPONENT_CACHE_PATHS[CacheComponent::DOCS]),
            CacheComponent::NAVIGATION => $this->filterExisting(glob(self::NAVIGATION_CACHE_GLOB) ?: []),
        };
    }

    private function filterExisting(array $paths): array
    {
        return array_values(array_filter($paths, 'file_exists'));
    }

    private function collectPhpFilesInDir(string $dirPath): array
    {
        if (!is_dir($dirPath)) {
            return [];
        }

        $files    = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getRealPath();
            }
        }

        return $files;
    }

    private function validateFile(string $filePath, string $component): void
    {
        $this->logger->info('Validating file syntax', ['filePath' => $filePath]);

        exec('php -l ' . escapeshellarg($filePath), $output, $exitCode);

        if ($exitCode !== 0) {
            throw new AdminServiceException(
                'Cache file failed syntax validation',
                [
                    'filePath'  => $filePath,
                    'component' => $component,
                    'output'    => implode("\n", $output),
                ]
            );
        }

        $this->logger->info('File syntax valid', ['filePath' => $filePath]);
    }
}
