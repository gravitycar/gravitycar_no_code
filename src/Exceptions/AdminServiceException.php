<?php

declare(strict_types=1);

namespace Gravitycar\Exceptions;

/**
 * AdminServiceException
 *
 * Thrown by CacheArchiver, CacheRebuilder, and AdminService when a cache
 * rebuild operation fails. Carries an optional context array with diagnostic
 * information (e.g., archive file path, component name, command output).
 *
 * AdminService catches this exception and records the failure in
 * CacheRebuildResult rather than re-throwing it. This keeps the caller's
 * interface clean — performCacheRebuild() always returns a result.
 *
 * Typical context keys (convention, not enforced):
 *   - 'archiveFilePath' — path of the tar archive being operated on
 *   - 'component'       — which CacheComponent constant was being processed
 *   - 'exitCode'        — shell command exit code
 *   - 'output'          — shell command output
 *   - 'filePath'        — specific cache file that failed validation
 *   - 'step'            — name of the step that failed ('archive', 'clear', 'rebuild', 'validate')
 *
 * Usage examples:
 *
 *   // In CacheArchiver::create():
 *   throw new AdminServiceException(
 *       'Archive creation failed: tar command returned non-zero exit code',
 *       ['archiveFilePath' => $archiveFilePath, 'exitCode' => $exitCode]
 *   );
 *
 *   // In CacheRebuilder::validate():
 *   throw new AdminServiceException(
 *       'Syntax validation failed for cache file',
 *       ['filePath' => $filePath, 'component' => $component, 'output' => $output]
 *   );
 */
class AdminServiceException extends GCException
{
    // Inherits all functionality from GCException.
    // No additional methods needed — the exception type identifies the subsystem,
    // and $context carries step-specific diagnostic data.
}
