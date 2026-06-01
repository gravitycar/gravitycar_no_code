<?php

declare(strict_types=1);

namespace Gravitycar\Services\Admin;

use InvalidArgumentException;

/**
 * CacheRebuildOptions
 *
 * Immutable value object that specifies which cache components to rebuild
 * and which secondary operations to perform. Passed to
 * AdminService::performCacheRebuild().
 *
 * Archiving is always performed before any files are cleared; it is not
 * a configurable option and is therefore not a property of this class.
 *
 * Usage:
 *   // Rebuild everything:
 *   $options = CacheRebuildOptions::all();
 *
 *   // Rebuild from API request body:
 *   $options = CacheRebuildOptions::fromArray($requestBody);
 *
 *   // Rebuild specific components only:
 *   $options = new CacheRebuildOptions(
 *       components: [CacheComponent::METADATA, CacheComponent::ROUTES],
 *       updateSchema: true
 *   );
 */
final class CacheRebuildOptions
{
    private readonly array $components;
    private readonly bool $updateSchema;
    private readonly bool $updatePermissions;
    private readonly bool $dryRun;

    public function __construct(
        array $components,
        bool $updateSchema = false,
        bool $updatePermissions = false,
        bool $dryRun = false
    ) {
        $this->validateComponents($components);
        $this->components        = $components;
        $this->updateSchema      = $updateSchema;
        $this->updatePermissions = $updatePermissions;
        $this->dryRun            = $dryRun;
    }

    /**
     * Returns options with all four components, updateSchema=true,
     * updatePermissions=true, dryRun=false.
     */
    public static function all(): self
    {
        return new self(
            components:        CacheComponent::all(),
            updateSchema:      true,
            updatePermissions: true,
            dryRun:            false
        );
    }

    /**
     * Constructs from an API request body or CLI parsed-options array.
     *
     * Expected keys:
     *   'components'        => string[] (required)
     *   'updateSchema'      => bool     (optional, default false)
     *   'updatePermissions' => bool     (optional, default false)
     *   'dryRun'            => bool     (optional, default false)
     *
     * @throws InvalidArgumentException if components is missing, empty, or
     *                                  contains an unknown value
     */
    public static function fromArray(array $data): self
    {
        return new self(
            components:        $data['components']        ?? [],
            updateSchema:      (bool)($data['updateSchema']      ?? false),
            updatePermissions: (bool)($data['updatePermissions'] ?? false),
            dryRun:            (bool)($data['dryRun']            ?? false)
        );
    }

    public function getComponents(): array
    {
        return $this->components;
    }

    public function isUpdateSchema(): bool
    {
        return $this->updateSchema;
    }

    public function isUpdatePermissions(): bool
    {
        return $this->updatePermissions;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    /**
     * Validates that $components is non-empty and all values are known identifiers.
     *
     * @throws InvalidArgumentException
     */
    private function validateComponents(array $components): void
    {
        if (empty($components)) {
            throw new InvalidArgumentException(
                'CacheRebuildOptions: components array must not be empty.'
            );
        }

        foreach ($components as $component) {
            if (!CacheComponent::isValid($component)) {
                throw new InvalidArgumentException(
                    "CacheRebuildOptions: unknown component identifier '{$component}'."
                );
            }
        }
    }
}
