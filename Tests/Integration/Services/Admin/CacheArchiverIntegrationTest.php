<?php

declare(strict_types=1);

namespace Gravitycar\Tests\Integration\Services\Admin;

use Gravitycar\Core\Config;
use Gravitycar\Exceptions\AdminServiceException;
use Gravitycar\Services\Admin\CacheArchiver;
use Gravitycar\Tests\TestCase;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Integration tests for CacheArchiver using a real temporary filesystem.
 *
 * Each test creates an isolated temp directory so there is zero risk of
 * affecting the real cache/ directory. All temp directories and files are
 * cleaned up in tearDown().
 *
 * Tested behaviour:
 *   - archive()           creates a .tar file with chmod 600
 *   - restore()           extracts the archive back to the directory
 *   - deleteArchive()     removes the archive file
 *   - verify()            correctly identifies valid/missing/empty files
 *   - findStaleArchives() finds files matching cache_*.tar in the app root
 */
class CacheArchiverIntegrationTest extends TestCase
{
    private MockObject $mockConfig;

    /** Root temp directory for this test run — cleaned up in tearDown(). */
    private string $testRootDirPath;

    /** The simulated cache/ subdirectory inside testRootDirPath. */
    private string $testCacheDirPath;

    /** Original working directory — restored in tearDown(). */
    private string $originalCwdDirPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockConfig = $this->createMock(Config::class);

        // Create an isolated temp directory for this test
        $this->testRootDirPath  = sys_get_temp_dir() . '/gc_archiver_it_' . uniqid();
        $this->testCacheDirPath = $this->testRootDirPath . '/cache';

        mkdir($this->testRootDirPath, 0755, true);
        mkdir($this->testCacheDirPath, 0755, true);

        // CacheArchiver runs tar with the cache dir path from config. When the
        // path is absolute, tar strips the leading '/' and archives as a
        // relative path from '/'. On extraction (-C appRoot), tar would create
        // appRoot/tmp/.../cache rather than restoring to the original location.
        //
        // The real application always passes a relative path (e.g. 'cache') via
        // config so tar records it as 'cache/' and -C appRoot restores correctly.
        //
        // To replicate that in tests we change CWD to testRootDirPath and tell
        // the mock to return the relative path 'cache'. CacheArchiver resolves
        // appRootDirPath = dirname(realpath('cache')) = testRootDirPath.
        $this->originalCwdDirPath = getcwd();
        chdir($this->testRootDirPath);

        $this->mockConfig
            ->method('get')
            ->willReturnCallback(function (string $key, mixed $default = null): mixed {
                if ($key === 'cache.directory') {
                    // Relative path so tar stores 'cache/...' and restores correctly
                    return 'cache';
                }
                return $default;
            });
    }

    protected function tearDown(): void
    {
        // Restore the original working directory before cleanup
        if (isset($this->originalCwdDirPath)) {
            chdir($this->originalCwdDirPath);
        }
        $this->removeDirectory($this->testRootDirPath);
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Recursively removes a directory and all its contents.
     */
    private function removeDirectory(string $dirPath): void
    {
        if (!is_dir($dirPath)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dirPath);
    }

    /**
     * Creates a CacheArchiver wired to the isolated temp directory.
     */
    private function makeArchiver(): CacheArchiver
    {
        return new CacheArchiver($this->logger, $this->mockConfig);
    }

    /**
     * Writes a named file inside the test cache directory with the given content.
     */
    private function createCacheFile(string $filename, string $content = 'cache content'): string
    {
        $filePath = $this->testCacheDirPath . '/' . $filename;
        file_put_contents($filePath, $content);
        return $filePath;
    }

    // -------------------------------------------------------------------------
    // archive() — creates a real .tar with chmod 600
    // -------------------------------------------------------------------------

    /**
     * archive() must return a path that exists and is non-empty (AC-3).
     */
    public function testArchiveCreatesNonEmptyTarFile(): void
    {
        $this->createCacheFile('metadata_cache.php', '<?php return [];');

        $archiver     = $this->makeArchiver();
        $archivePath  = $archiver->archive();

        $this->assertFileExists($archivePath, 'archive() must create the tar file');
        $this->assertGreaterThan(0, filesize($archivePath), 'Archive must be non-empty');

        // Clean up
        if (file_exists($archivePath)) {
            unlink($archivePath);
        }
    }

    /**
     * AC-14c: archive() must set permissions to 0600 immediately after creation.
     */
    public function testArchiveSetsPermissionsTo0600(): void
    {
        $this->createCacheFile('api_routes.php', '<?php return [];');

        $archiver    = $this->makeArchiver();
        $archivePath = $archiver->archive();

        try {
            $permissions = fileperms($archivePath) & 0777;
            $this->assertSame(0600, $permissions, 'Archive file must have chmod 600');
        } finally {
            if (file_exists($archivePath)) {
                unlink($archivePath);
            }
        }
    }

    /**
     * archive() must use the timestamped filename pattern cache_YYYY_MM_DD_HH_MM_SS.tar.
     */
    public function testArchiveFilenameMatchesTimestampPattern(): void
    {
        $this->createCacheFile('some_cache.php', '<?php return [];');

        $archiver    = $this->makeArchiver();
        $archivePath = $archiver->archive();

        try {
            $filename = basename($archivePath);
            $this->assertMatchesRegularExpression(
                '/^cache_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}\.tar$/',
                $filename,
                'Archive filename must match pattern cache_YYYY_MM_DD_HH_MM_SS.tar'
            );
        } finally {
            if (file_exists($archivePath)) {
                unlink($archivePath);
            }
        }
    }

    /**
     * archive() must place the tar file in the application root (one level above cache/).
     */
    public function testArchivePlacesFileInApplicationRoot(): void
    {
        $this->createCacheFile('nav.php', '<?php return [];');

        $archiver    = $this->makeArchiver();
        $archivePath = $archiver->archive();

        try {
            $this->assertSame(
                $this->testRootDirPath,
                dirname($archivePath),
                'Archive must be placed in the application root directory'
            );
        } finally {
            if (file_exists($archivePath)) {
                unlink($archivePath);
            }
        }
    }

    /**
     * archive() must throw AdminServiceException when the cache directory does not exist.
     */
    public function testArchiveThrowsWhenCacheDirDoesNotExist(): void
    {
        $mockConfig = $this->createMock(Config::class);
        $mockConfig->method('get')->willReturn('/nonexistent/path/cache');

        $archiver = new CacheArchiver($this->logger, $mockConfig);

        $this->expectException(AdminServiceException::class);
        $archiver->archive();
    }

    // -------------------------------------------------------------------------
    // restore() — extracts archive contents correctly
    // -------------------------------------------------------------------------

    /**
     * restore() must recreate files that were in the archive.
     */
    public function testRestoreExtractsArchivedFilesCorrectly(): void
    {
        // Create a file in the cache dir and archive it
        $originalContent = '<?php return ["restored" => true];';
        $cacheFilePath   = $this->createCacheFile('metadata_cache.php', $originalContent);

        $archiver    = $this->makeArchiver();
        $archivePath = $archiver->archive();

        // Delete the original file to simulate cache clearing
        unlink($cacheFilePath);
        $this->assertFileDoesNotExist($cacheFilePath, 'Pre-condition: file must be deleted before restore');

        // Restore and verify the file reappears
        $archiver->restore($archivePath);

        $this->assertFileExists(
            $cacheFilePath,
            'restore() must recreate files from the archive'
        );
        $this->assertSame(
            $originalContent,
            file_get_contents($cacheFilePath),
            'Restored file content must match the original'
        );

        // Clean up
        if (file_exists($archivePath)) {
            unlink($archivePath);
        }
    }

    /**
     * restore() must throw AdminServiceException when the archive file does not exist.
     */
    public function testRestoreThrowsWhenArchiveFileNotFound(): void
    {
        $archiver = $this->makeArchiver();

        $this->expectException(AdminServiceException::class);
        $archiver->restore('/tmp/gc_nonexistent_archive_' . uniqid() . '.tar');
    }

    // -------------------------------------------------------------------------
    // deleteArchive() — removes the file
    // -------------------------------------------------------------------------

    /**
     * deleteArchive() must delete a file that exists.
     */
    public function testDeleteArchiveRemovesExistingFile(): void
    {
        // Create a temporary tar file to delete
        $tmpFilePath = $this->testRootDirPath . '/cache_2026_01_01_00_00_00.tar';
        file_put_contents($tmpFilePath, 'fake archive data');

        $this->assertFileExists($tmpFilePath, 'Pre-condition: file must exist');

        $archiver = $this->makeArchiver();
        $archiver->deleteArchive($tmpFilePath);

        $this->assertFileDoesNotExist($tmpFilePath, 'deleteArchive() must remove the file');
    }

    /**
     * deleteArchive() must not throw when the file does not exist (non-fatal per spec).
     */
    public function testDeleteArchiveDoesNotThrowForMissingFile(): void
    {
        $archiver = $this->makeArchiver();

        // Should complete without exception
        $archiver->deleteArchive('/tmp/gc_nonexistent_to_delete_' . uniqid() . '.tar');

        $this->addToAssertionCount(1); // Explicit assertion to avoid risky-test warning
    }

    /**
     * After a full archive→restore cycle, deleteArchive() removes the archive.
     */
    public function testDeleteArchiveRemovesFileAfterRestoreCycle(): void
    {
        $this->createCacheFile('routes.php', '<?php return [];');

        $archiver    = $this->makeArchiver();
        $archivePath = $archiver->archive();

        $this->assertFileExists($archivePath, 'Pre-condition: archive must exist');

        $archiver->deleteArchive($archivePath);

        $this->assertFileDoesNotExist(
            $archivePath,
            'deleteArchive() must remove the archive after restore cycle'
        );
    }

    // -------------------------------------------------------------------------
    // verify() — non-empty vs empty vs missing
    // -------------------------------------------------------------------------

    /**
     * verify() must return true for an existing file with content.
     */
    public function testVerifyReturnsTrueForExistingNonEmptyFile(): void
    {
        $tmpFilePath = $this->testRootDirPath . '/test_archive.tar';
        file_put_contents($tmpFilePath, 'archive data');

        $archiver = $this->makeArchiver();
        $result   = $archiver->verify($tmpFilePath);

        $this->assertTrue($result);

        unlink($tmpFilePath);
    }

    /**
     * verify() must return false when the file does not exist.
     */
    public function testVerifyReturnsFalseForNonExistentFile(): void
    {
        $archiver = $this->makeArchiver();
        $result   = $archiver->verify('/tmp/gc_verify_nonexistent_' . uniqid() . '.tar');

        $this->assertFalse($result);
    }

    /**
     * verify() must return false for a zero-byte file.
     */
    public function testVerifyReturnsFalseForEmptyFile(): void
    {
        $tmpFilePath = $this->testRootDirPath . '/empty_archive.tar';
        file_put_contents($tmpFilePath, '');

        $archiver = $this->makeArchiver();
        $result   = $archiver->verify($tmpFilePath);

        $this->assertFalse($result);

        unlink($tmpFilePath);
    }

    // -------------------------------------------------------------------------
    // findStaleArchives() — glob pattern cache_*.tar in app root
    // -------------------------------------------------------------------------

    /**
     * findStaleArchives() must return an empty array when no cache_*.tar files exist.
     */
    public function testFindStaleArchivesReturnsEmptyArrayWhenNonePresent(): void
    {
        $archiver = $this->makeArchiver();
        $result   = $archiver->findStaleArchives();

        $this->assertIsArray($result);
        $this->assertEmpty($result, 'No stale archives should be found in a clean temp dir');
    }

    /**
     * findStaleArchives() must find cache_*.tar files in the application root.
     */
    public function testFindStaleArchivesDetectsMatchingFiles(): void
    {
        // Create two fake stale archives in the test app root
        $staleFile1 = $this->testRootDirPath . '/cache_2025_01_01_00_00_00.tar';
        $staleFile2 = $this->testRootDirPath . '/cache_2025_06_15_12_30_00.tar';
        file_put_contents($staleFile1, 'stale tar 1');
        file_put_contents($staleFile2, 'stale tar 2');

        $archiver = $this->makeArchiver();
        $result   = $archiver->findStaleArchives();

        $this->assertContains($staleFile1, $result, 'First stale archive must be found');
        $this->assertContains($staleFile2, $result, 'Second stale archive must be found');
    }

    /**
     * findStaleArchives() must NOT return files that don't match the cache_*.tar pattern.
     */
    public function testFindStaleArchivesIgnoresNonMatchingFiles(): void
    {
        // Create a file that should NOT be matched
        $nonMatchingFile = $this->testRootDirPath . '/backup.tar';
        file_put_contents($nonMatchingFile, 'non-matching tar');

        $archiver = $this->makeArchiver();
        $result   = $archiver->findStaleArchives();

        $this->assertNotContains(
            $nonMatchingFile,
            $result,
            'findStaleArchives must only match files named cache_*.tar'
        );
    }

    /**
     * findStaleArchives() must return an array (never false) even in edge cases.
     */
    public function testFindStaleArchivesAlwaysReturnsArray(): void
    {
        $archiver = $this->makeArchiver();
        $result   = $archiver->findStaleArchives();

        $this->assertIsArray($result);
    }

    // -------------------------------------------------------------------------
    // Full round-trip: archive → modify cache → restore → verify → delete
    // -------------------------------------------------------------------------

    /**
     * End-to-end round-trip test:
     *   1. Create files in the cache dir.
     *   2. archive() — creates a .tar backup.
     *   3. Delete the files (simulate cache clearing).
     *   4. restore() — brings the files back.
     *   5. verify() — confirms the archive is still intact.
     *   6. deleteArchive() — cleans up.
     */
    public function testFullArchiveRestoreDeleteCycle(): void
    {
        // Arrange: populate the cache directory with test files
        $fileA = $this->createCacheFile('metadata_cache.php', '<?php return ["test" => 1];');
        $fileB = $this->createCacheFile('api_routes.php', '<?php return [];');

        $archiver = $this->makeArchiver();

        // Step 1: Archive
        $archivePath = $archiver->archive();
        $this->assertFileExists($archivePath, 'Archive must be created');

        // Step 2: Delete cached files (simulate clear phase)
        unlink($fileA);
        unlink($fileB);
        $this->assertFileDoesNotExist($fileA);
        $this->assertFileDoesNotExist($fileB);

        // Step 3: Restore
        $archiver->restore($archivePath);
        $this->assertFileExists($fileA, 'metadata_cache.php must be restored');
        $this->assertFileExists($fileB, 'api_routes.php must be restored');

        // Step 4: Verify archive is still intact
        $this->assertTrue($archiver->verify($archivePath), 'Archive must still be valid after restore');

        // Step 5: Delete archive
        $archiver->deleteArchive($archivePath);
        $this->assertFileDoesNotExist($archivePath, 'Archive must be deleted after cycle');
    }
}
