<?php

declare(strict_types=1);

namespace Gravitycar\Services\Admin;

/**
 * CacheComponent
 *
 * Defines the four named cache component identifiers used throughout the
 * cache rebuild system. Each constant value matches the cache file identifier
 * used in CacheArchiver, CacheRebuilder, and CacheRebuildOptions.
 *
 * Component-to-cache-file mapping:
 *   METADATA   → cache/metadata_cache.php
 *   ROUTES     → cache/api_routes.php
 *   DOCS       → cache/documentation/ (directory)
 *   NAVIGATION → cache/navigation_cache_*.php (multiple files)
 *
 * This class cannot be instantiated. All access is via static constants and methods.
 */
final class CacheComponent
{
    public const METADATA   = 'metadata';
    public const ROUTES     = 'routes';
    public const DOCS       = 'docs';
    public const NAVIGATION = 'navigation';

    /** @var string[] All valid component identifiers */
    private const ALL_COMPONENTS = [
        self::METADATA,
        self::ROUTES,
        self::DOCS,
        self::NAVIGATION,
    ];

    /**
     * Returns all four component identifiers.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return self::ALL_COMPONENTS;
    }

    /**
     * Returns true if $component is one of the four valid identifiers.
     *
     * Uses strict comparison to prevent type coercions (e.g., 0 == 'metadata'
     * would pass a loose check but is rejected here).
     */
    public static function isValid(string $component): bool
    {
        return in_array($component, self::ALL_COMPONENTS, strict: true);
    }
}
