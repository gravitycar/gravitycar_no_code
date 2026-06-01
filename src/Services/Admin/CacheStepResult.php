<?php

declare(strict_types=1);

namespace Gravitycar\Services\Admin;

/**
 * CacheStepResult
 *
 * Immutable record of a single cache rebuild step's outcome. Used by:
 *   - CacheArchiver (archive, restore steps)
 *   - CacheRebuilder (clear, rebuild, validate, schema_update, permissions_update steps)
 *   - AdminService (aggregates into CacheRebuildResult)
 *   - AdminAPIController (serialized as SSE events via toArray())
 *   - application-update.php (printed to STDOUT)
 *
 * All construction is via named constructors to enforce valid status values.
 *
 * Valid step names: 'archive', 'clear', 'rebuild', 'validate',
 *                   'schema_update', 'permissions_update', 'restore'
 */
final class CacheStepResult
{
    private const STATUS_IN_PROGRESS = 'in_progress';
    private const STATUS_SUCCESS     = 'success';
    private const STATUS_FAILED      = 'failed';
    private const STATUS_SKIPPED     = 'skipped';

    private readonly string $stepName;
    private readonly string $component;
    private readonly string $status;
    private readonly ?string $errorMessage;

    private function __construct(
        string $stepName,
        string $component,
        string $status,
        ?string $errorMessage
    ) {
        $this->stepName     = $stepName;
        $this->component    = $component;
        $this->status       = $status;
        $this->errorMessage = $errorMessage;
    }

    /**
     * Creates a result indicating a step is currently in progress.
     */
    public static function inProgress(string $stepName, string $component): self
    {
        return new self($stepName, $component, self::STATUS_IN_PROGRESS, null);
    }

    /**
     * Creates a result indicating a step completed successfully.
     */
    public static function success(string $stepName, string $component): self
    {
        return new self($stepName, $component, self::STATUS_SUCCESS, null);
    }

    /**
     * Creates a result indicating a step failed with an error message.
     */
    public static function failed(string $stepName, string $component, string $error): self
    {
        return new self($stepName, $component, self::STATUS_FAILED, $error);
    }

    /**
     * Creates a result indicating a step was skipped (e.g., dry run mode).
     */
    public static function skipped(string $stepName, string $component): self
    {
        return new self($stepName, $component, self::STATUS_SKIPPED, null);
    }

    public function getStepName(): string
    {
        return $this->stepName;
    }

    public function getComponent(): string
    {
        return $this->component;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Returns the array shape used for SSE JSON serialization and
     * CacheRebuildResult::toArray().
     *
     * Shape:
     *   {
     *     "stepName":     "archive",
     *     "component":    "all",
     *     "status":       "success",
     *     "errorMessage": null
     *   }
     */
    public function toArray(): array
    {
        return [
            'stepName'     => $this->stepName,
            'component'    => $this->component,
            'status'       => $this->status,
            'errorMessage' => $this->errorMessage,
        ];
    }
}
