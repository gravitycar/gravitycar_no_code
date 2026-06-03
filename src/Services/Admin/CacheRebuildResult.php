<?php

declare(strict_types=1);

namespace Gravitycar\Services\Admin;

/**
 * CacheRebuildResult
 *
 * Aggregate outcome of AdminService::performCacheRebuild(). Holds the
 * overall success flag, a human-readable summary message, and an ordered
 * list of CacheStepResult objects for every phase that was attempted.
 *
 * Construction:
 *   // All steps succeeded:
 *   $result = CacheRebuildResult::success($steps);
 *
 *   // A failure occurred (archive was restored):
 *   $result = CacheRebuildResult::failure($steps, 'Cache rebuild failed. Archive restored.');
 *
 *   // Build incrementally during AdminService processing:
 *   $result = CacheRebuildResult::success([]);
 *   $result->addStep(CacheStepResult::success('archive', 'all'));
 *   ...
 *   if ($result->hasFailures()) { ... }
 *
 * Consumed by:
 *   - AdminAPIController::handleCacheRebuild() — emits toArray() as the final SSE event
 *   - scripts/application-update.php — iterates getSteps() for STDOUT output; checks isSuccess() for exit code
 */
final class CacheRebuildResult
{
    private const DEFAULT_SUCCESS_MESSAGE = 'Cache rebuild completed successfully.';

    /** @var CacheStepResult[] */
    private array $steps;
    private bool $success;
    private string $message;

    private function __construct(bool $success, string $message, array $steps)
    {
        $this->success = $success;
        $this->message = $message;
        $this->steps   = $steps;
    }

    /**
     * Creates a result for a fully successful rebuild.
     *
     * @param CacheStepResult[] $steps
     */
    public static function success(array $steps = []): self
    {
        return new self(true, self::DEFAULT_SUCCESS_MESSAGE, $steps);
    }

    /**
     * Creates a result for a failed rebuild (archive was restored).
     *
     * @param CacheStepResult[] $steps
     */
    public static function failure(array $steps = [], string $message = 'Cache rebuild failed.'): self
    {
        return new self(false, $message, $steps);
    }

    /**
     * Appends a step result. Called by AdminService as each phase
     * completes during the rebuild sequence.
     */
    public function addStep(CacheStepResult $step): void
    {
        $this->steps[] = $step;
    }

    /**
     * Returns true only if the named constructor set success=true AND no step
     * has a 'failed' status. Both conditions must hold.
     *
     * This means failure([], 'msg') returns false even with no failed steps,
     * because the explicit $success flag is checked first.
     */
    public function isSuccess(): bool
    {
        return $this->success && !$this->hasFailures();
    }

    /**
     * Returns true if any step has a 'failed' status.
     */
    public function hasFailures(): bool
    {
        foreach ($this->steps as $step) {
            if ($step->isFailed()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the ordered list of step results.
     *
     * @return CacheStepResult[]
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Returns the human-readable summary message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Returns the serializable array shape for the final SSE 'done' event
     * and the JSON API response body.
     *
     * Shape:
     *   {
     *     "done":    true,
     *     "success": bool,
     *     "message": string,
     *     "steps":   [ { stepName, component, status, errorMessage }, ... ]
     *   }
     */
    public function toArray(): array
    {
        return [
            'done'    => true,
            'success' => $this->isSuccess(),
            'message' => $this->message,
            'steps'   => array_map(
                fn(CacheStepResult $s) => $s->toArray(),
                $this->steps
            ),
        ];
    }
}
