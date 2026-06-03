<?php

declare(strict_types=1);

namespace Gravitycar\Services\Admin;

use Gravitycar\Core\Config;
use Gravitycar\Exceptions\AdminServiceException;
use Monolog\Logger;

/**
 * CacheArchiver
 *
 * Encapsulates all tar archive operations for the cache rebuild safety net.
 * Creates a timestamped backup before any cache files are cleared, verifies
 * archive integrity, restores the archive on failure, deletes it on success,
 * and scans for stale archives from previous interrupted runs.
 *
 * Archive files are placed in the application root directory (one level above
 * the cache/ directory). Archive filenames follow the pattern:
 *   cache_YYYY_MM_DD_HH_MM_SS.tar
 *
 * Usage:
 *   $archiver = new CacheArchiver($logger, $config);
 *   $archiveFilePath = $archiver->archive();
 *   // ... do rebuild work ...
 *   $archiver->deleteArchive($archiveFilePath); // on success
 *   // or
 *   $archiver->restore($archiveFilePath);       // on failure
 */
class CacheArchiver
{
    private Logger $logger;
    private Config $config;
    private string $appRootDirPath;
    private string $cacheDirAbsPath;

    public function __construct(Logger $logger, Config $config)
    {
        $this->logger = $logger;
        $this->config = $config;

        $cacheDirPath           = $this->config->get('cache.directory', 'cache');
        $resolvedPath           = realpath($cacheDirPath);
        $this->appRootDirPath   = $resolvedPath !== false
            ? dirname($resolvedPath)
            : dirname($cacheDirPath);
        $this->cacheDirAbsPath  = $resolvedPath !== false
            ? $resolvedPath
            : $this->appRootDirPath . DIRECTORY_SEPARATOR . basename($cacheDirPath);
    }

    /**
     * Creates a timestamped tar archive of the entire cache/ directory in the
     * application root. Sets permissions to 0600 and verifies the archive.
     *
     * @return string The full path to the created archive file.
     * @throws AdminServiceException if tar fails, chmod fails, or verification fails.
     */
    public function archive(): string
    {
        $archiveFilename = 'cache_' . date('Y_m_d_H_i_s') . '.tar';
        $archiveFilePath = $this->appRootDirPath . DIRECTORY_SEPARATOR . $archiveFilename;

        $this->logger->info('Creating cache archive', [
            'archiveFilePath'  => $archiveFilePath,
            'cacheDirAbsPath'  => $this->cacheDirAbsPath,
        ]);

        exec(
            'tar -cf ' . escapeshellarg($archiveFilePath)
                . ' -C ' . escapeshellarg($this->appRootDirPath)
                . ' ' . escapeshellarg(basename($this->cacheDirAbsPath))
                . ' 2>&1',
            $output,
            $exitCode
        );

        if ($exitCode !== 0) {
            throw new AdminServiceException(
                'Archive creation failed: tar command returned non-zero exit code',
                [
                    'archiveFilePath' => $archiveFilePath,
                    'exitCode'        => $exitCode,
                    'output'          => implode("\n", $output),
                ]
            );
        }

        if (!chmod($archiveFilePath, 0600)) {
            throw new AdminServiceException(
                'Archive creation failed: could not set permissions on archive file',
                ['archiveFilePath' => $archiveFilePath]
            );
        }

        if (!$this->verify($archiveFilePath)) {
            throw new AdminServiceException(
                'Archive creation failed: archive file verification failed after creation',
                ['archiveFilePath' => $archiveFilePath]
            );
        }

        $this->logger->info('Cache archive created successfully', [
            'archiveFilePath' => $archiveFilePath,
            'fileSize'        => filesize($archiveFilePath),
        ]);

        return $archiveFilePath;
    }

    /**
     * Restores the entire archive to the application root, recreating the
     * cache/ directory structure as it was when the archive was created.
     *
     * @throws AdminServiceException if the archive file is not found or tar fails.
     */
    public function restore(string $archiveFilePath): void
    {
        $this->logger->info('Restoring cache from archive', [
            'archiveFilePath' => $archiveFilePath,
        ]);

        if (!file_exists($archiveFilePath)) {
            throw new AdminServiceException(
                'Restore failed: archive file not found',
                ['archiveFilePath' => $archiveFilePath]
            );
        }

        exec(
            'tar -xf ' . escapeshellarg($archiveFilePath) . ' -C ' . escapeshellarg($this->appRootDirPath) . ' 2>&1',
            $output,
            $exitCode
        );

        if ($exitCode !== 0) {
            throw new AdminServiceException(
                'Restore failed: tar extract command returned non-zero exit code',
                [
                    'archiveFilePath' => $archiveFilePath,
                    'exitCode'        => $exitCode,
                    'output'          => implode("\n", $output),
                ]
            );
        }

        $this->logger->info('Cache archive restored successfully', [
            'archiveFilePath' => $archiveFilePath,
        ]);
    }

    /**
     * Deletes an archive file. Logs a warning if the file is not found (non-fatal).
     */
    public function deleteArchive(string $archiveFilePath): void
    {
        $this->logger->info('Deleting cache archive', [
            'archiveFilePath' => $archiveFilePath,
        ]);

        if (!file_exists($archiveFilePath)) {
            $this->logger->warning('Archive file not found for deletion — skipping', [
                'archiveFilePath' => $archiveFilePath,
            ]);
            return;
        }

        unlink($archiveFilePath);

        $this->logger->info('Cache archive deleted', [
            'archiveFilePath' => $archiveFilePath,
        ]);
    }

    /**
     * Returns true if the archive file exists and has a non-zero size.
     */
    public function verify(string $archiveFilePath): bool
    {
        return file_exists($archiveFilePath) && filesize($archiveFilePath) > 0;
    }

    /**
     * Scans the application root for leftover cache_*.tar files from previous
     * interrupted runs. Returns an array of file paths (may be empty).
     *
     * AdminService logs a warning for each file returned by this method.
     *
     * @return string[]
     */
    public function findStaleArchives(): array
    {
        $this->logger->info('Scanning for stale cache archives', [
            'appRootDirPath' => $this->appRootDirPath,
        ]);

        $pattern = $this->appRootDirPath . DIRECTORY_SEPARATOR . 'cache_*.tar';
        $found   = glob($pattern);

        return $found !== false ? $found : [];
    }
}
