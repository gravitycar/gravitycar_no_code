<?php

declare(strict_types=1);

namespace Gravitycar\Services\Admin;

use Gravitycar\Core\Config;
use Monolog\Logger;

/**
 * AdminService
 *
 * Top-level orchestrator for the cache rebuild lifecycle. Sequences the four
 * phases — archive → clear → rebuild → validate — by delegating to
 * CacheArchiver and CacheRebuilder. Emits per-step progress via the $onStep
 * callback for SSE streaming and CLI output.
 *
 * AdminService performs no direct file I/O; it only sequences phases,
 * handles failures, and triggers archive restore on any error.
 *
 * Usage:
 *   $result = $adminService->performCacheRebuild(
 *       CacheRebuildOptions::all(),
 *       fn(CacheStepResult $step) => $this->emitStep($step)
 *   );
 */
class AdminService
{
    private Logger $logger;
    private Config $config;
    private CacheArchiver $archiver;
    private CacheRebuilder $rebuilder;

    public function __construct(
        Logger $logger,
        Config $config,
        CacheArchiver $archiver,
        CacheRebuilder $rebuilder
    ) {
        $this->logger    = $logger;
        $this->config    = $config;
        $this->archiver  = $archiver;
        $this->rebuilder = $rebuilder;
        $this->scanAndLogStaleArchives();
    }

    /**
     * Performs the full cache rebuild lifecycle:
     *   1. Archive (safety backup)
     *   2. Clear (remove existing cache files, per component)
     *   3. Rebuild (regenerate cache files, per component)
     *   4. Validate (php -l syntax check, per component)
     *   5. Delete archive on success; restore + delete on any failure
     *
     * The $onStep callback is invoked before and after each step to enable
     * real-time SSE streaming and CLI progress output.
     *
     * @throws \InvalidArgumentException if $options->getComponents() is empty.
     * @throws \Throwable if the archive phase fails (no archive to restore).
     */
    public function performCacheRebuild(CacheRebuildOptions $options, callable $onStep): CacheRebuildResult
    {
        if (empty($options->getComponents())) {
            throw new \InvalidArgumentException('No components specified for cache rebuild.');
        }

        if ($options->isDryRun()) {
            return $this->performDryRun($options, $onStep);
        }

        $this->logger->info('Starting cache rebuild', ['components' => $options->getComponents()]);

        $result      = CacheRebuildResult::success([]);
        $archivePath = $this->runArchivePhase($result, $onStep);

        try {
            $this->runClearPhase($options, $result, $onStep);
            $this->runRebuildPhase($options, $result, $onStep);
            $this->runValidatePhase($options, $result, $onStep);
        } catch (\Throwable $e) {
            $this->logger->error('Cache rebuild failed — restoring archive', [
                'error'       => $e->getMessage(),
                'archivePath' => $archivePath,
            ]);
            $failureResult = CacheRebuildResult::failure(
                $result->getSteps(),
                'Cache rebuild failed — archive restored.'
            );
            $this->runRestorePhase($archivePath, $failureResult, $onStep);
            $this->archiver->deleteArchive($archivePath);
            return $failureResult;
        }

        $this->archiver->deleteArchive($archivePath);

        $this->logger->info('Cache rebuild completed successfully');
        return $result;
    }

    // -------------------------------------------------------------------------
    // Private phase helpers
    // -------------------------------------------------------------------------

    /**
     * Creates the safety archive before any files are cleared.
     * Re-throws on failure so the caller can emit the final done:false SSE event
     * and call exit(0). No restore is needed because no archive was completed.
     *
     * @throws \Throwable if archive creation fails.
     */
    private function runArchivePhase(CacheRebuildResult $result, callable $onStep): string
    {
        $inProgressStep = CacheStepResult::inProgress('archive', 'all');
        $result->addStep($inProgressStep);
        $onStep($inProgressStep);

        try {
            $archivePath = $this->archiver->archive();
        } catch (\Throwable $e) {
            $this->logger->error('Archive phase failed', ['error' => $e->getMessage()]);
            $failStep = CacheStepResult::failed('archive', 'all', $e->getMessage());
            $result->addStep($failStep);
            $onStep($failStep);
            throw $e;
        }

        $successStep = CacheStepResult::success('archive', 'all');
        $result->addStep($successStep);
        $onStep($successStep);

        return $archivePath;
    }

    /**
     * Clears cache files per component, emitting in_progress/success steps
     * for each component individually before and after the clear operation.
     *
     * @throws \Throwable propagated to performCacheRebuild() to trigger restore.
     */
    private function runClearPhase(CacheRebuildOptions $options, CacheRebuildResult $result, callable $onStep): void
    {
        foreach ($options->getComponents() as $component) {
            $inProgressStep = CacheStepResult::inProgress('clear', $component);
            $result->addStep($inProgressStep);
            $onStep($inProgressStep);

            try {
                $this->rebuilder->clear([$component]);
            } catch (\Throwable $e) {
                $failedStep = CacheStepResult::failed('clear', $component, $e->getMessage());
                $result->addStep($failedStep);
                $onStep($failedStep);
                throw $e;
            }

            $successStep = CacheStepResult::success('clear', $component);
            $result->addStep($successStep);
            $onStep($successStep);
        }
    }

    /**
     * Rebuilds cache files for each component. CacheRebuilder fires $onStep
     * internally for each sub-step; wrap it so steps are also added to $result.
     *
     * @throws \Throwable propagated to performCacheRebuild() to trigger restore.
     */
    private function runRebuildPhase(CacheRebuildOptions $options, CacheRebuildResult $result, callable $onStep): void
    {
        $this->rebuilder->rebuild(
            $options->getComponents(),
            $options->isUpdateSchema(),
            $options->isUpdatePermissions(),
            function (CacheStepResult $step) use ($result, $onStep): void {
                $result->addStep($step);
                $onStep($step);
            }
        );
    }

    /**
     * Validates generated PHP cache files per component, emitting in_progress/
     * success steps for each component individually.
     *
     * @throws \Throwable propagated to performCacheRebuild() to trigger restore.
     */
    private function runValidatePhase(CacheRebuildOptions $options, CacheRebuildResult $result, callable $onStep): void
    {
        foreach ($options->getComponents() as $component) {
            $inProgressStep = CacheStepResult::inProgress('validate', $component);
            $result->addStep($inProgressStep);
            $onStep($inProgressStep);

            try {
                $this->rebuilder->validate([$component]);
            } catch (\Throwable $e) {
                $failedStep = CacheStepResult::failed('validate', $component, $e->getMessage());
                $result->addStep($failedStep);
                $onStep($failedStep);
                throw $e;
            }

            $successStep = CacheStepResult::success('validate', $component);
            $result->addStep($successStep);
            $onStep($successStep);
        }
    }

    /**
     * Attempts to restore the archive after a failed rebuild. Emits in_progress
     * then success/failed steps. Does not re-throw on restore failure.
     */
    private function runRestorePhase(string $archivePath, CacheRebuildResult $result, callable $onStep): void
    {
        $inProgressStep = CacheStepResult::inProgress('restore', 'all');
        $result->addStep($inProgressStep);
        $onStep($inProgressStep);

        try {
            $this->archiver->restore($archivePath);
            $outcomeStep = CacheStepResult::success('restore', 'all');
        } catch (\Throwable $restoreEx) {
            $this->logger->error('Restore failed', ['error' => $restoreEx->getMessage()]);
            $outcomeStep = CacheStepResult::failed('restore', 'all', $restoreEx->getMessage());
        }

        $result->addStep($outcomeStep);
        $onStep($outcomeStep);
    }

    /**
     * Handles the dry-run path: emits all steps as 'skipped' with no file I/O.
     */
    private function performDryRun(CacheRebuildOptions $options, callable $onStep): CacheRebuildResult
    {
        $this->logger->info('Dry run — no files will be modified', [
            'components' => $options->getComponents(),
        ]);

        $result = CacheRebuildResult::success([]);
        $steps  = $this->buildDryRunSteps($options);

        foreach ($steps as $step) {
            $result->addStep($step);
            $onStep($step);
            $this->logger->info('Dry run step (skipped)', $step->toArray());
        }

        return $result;
    }

    /**
     * Builds the ordered list of skipped steps for a dry run.
     *
     * @return CacheStepResult[]
     */
    private function buildDryRunSteps(CacheRebuildOptions $options): array
    {
        $steps      = [];
        $components = $options->getComponents();

        $steps[] = CacheStepResult::skipped('archive', 'all');

        foreach ($components as $component) {
            $steps[] = CacheStepResult::skipped('clear', $component);
        }

        foreach ($components as $component) {
            $steps[] = CacheStepResult::skipped('rebuild', $component);
        }

        if (in_array(CacheComponent::METADATA, $components, strict: true)) {
            if ($options->isUpdateSchema()) {
                $steps[] = CacheStepResult::skipped('schema_update', CacheComponent::METADATA);
            }
            if ($options->isUpdatePermissions()) {
                $steps[] = CacheStepResult::skipped('permissions_update', CacheComponent::METADATA);
            }
        }

        foreach ($components as $component) {
            $steps[] = CacheStepResult::skipped('validate', $component);
        }

        return $steps;
    }

    // -------------------------------------------------------------------------
    // Construction-time helpers
    // -------------------------------------------------------------------------

    /**
     * Scans for stale cache archives from previous interrupted runs and logs
     * a warning for each one found. Does not auto-delete them.
     */
    private function scanAndLogStaleArchives(): void
    {
        $staleArchives = $this->archiver->findStaleArchives();
        foreach ($staleArchives as $archiveFilePath) {
            $this->logger->warning('Stale cache archive found — manual cleanup may be required', [
                'archiveFilePath' => $archiveFilePath,
            ]);
        }
    }
}
