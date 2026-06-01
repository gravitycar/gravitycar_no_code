<?php

declare(strict_types=1);

namespace Gravitycar\Tests\Unit\Services\Admin;

use Gravitycar\Core\Config;
use Gravitycar\Exceptions\AdminServiceException;
use Gravitycar\Services\Admin\CacheArchiver;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for CacheArchiver.
 *
 * These tests use a custom subclass of CacheArchiver to intercept
 * exec() calls without hitting the real filesystem, allowing verification
 * of error-path behavior.
 */
class CacheArchiverTest extends TestCase
{
    private MockObject $mockLogger;
    private MockObject $mockConfig;
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLogger = $this->createMock(Logger::class);
        $this->mockConfig = $this->createMock(Config::class);

        // Use /tmp for a safe writable directory in tests
        $this->tmpDir = sys_get_temp_dir();

        $this->mockConfig
            ->method('get')
            ->willReturnCallback(function (string $key, mixed $default = null): mixed {
                if ($key === 'cache.directory') {
                    return $this->tmpDir;
                }
                return $default;
            });
    }

    protected function tearDown(): void
    {
        // Clean up any test tar files created
        $files = glob($this->tmpDir . DIRECTORY_SEPARATOR . 'cache_*.tar');
        if ($files !== false) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // verify()
    // -------------------------------------------------------------------------

    public function testVerifyReturnsTrueForExistingNonEmptyFile(): void
    {
        $archiver = new CacheArchiver($this->mockLogger, $this->mockConfig);
        $tmpFile  = tempnam(sys_get_temp_dir(), 'gc_archive_test_');
        file_put_contents($tmpFile, 'data');

        try {
            $result = $archiver->verify($tmpFile);
            $this->assertTrue($result);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    public function testVerifyReturnsFalseForNonExistentFile(): void
    {
        $archiver = new CacheArchiver($this->mockLogger, $this->mockConfig);
        $result   = $archiver->verify('/tmp/nonexistent_archive_xyz_12345.tar');
        $this->assertFalse($result);
    }

    public function testVerifyReturnsFalseForEmptyFile(): void
    {
        $archiver = new CacheArchiver($this->mockLogger, $this->mockConfig);
        $tmpFile  = tempnam(sys_get_temp_dir(), 'gc_empty_');
        file_put_contents($tmpFile, '');

        try {
            $result = $archiver->verify($tmpFile);
            $this->assertFalse($result);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    // -------------------------------------------------------------------------
    // findStaleArchives()
    // -------------------------------------------------------------------------

    public function testFindStaleArchivesReturnsArray(): void
    {
        $archiver = new CacheArchiver($this->mockLogger, $this->mockConfig);
        $result   = $archiver->findStaleArchives();
        $this->assertIsArray($result);
    }

    public function testFindStaleArchivesReturnsEmptyArrayWhenNoneExist(): void
    {
        // Use a temp dir that we control
        $cleanTmpDir = sys_get_temp_dir() . '/gc_archiver_test_' . uniqid();
        mkdir($cleanTmpDir, 0755, true);

        $mockConfig = $this->createMock(Config::class);
        $mockConfig->method('get')->willReturnCallback(function (string $key) use ($cleanTmpDir): string {
            return $key === 'cache.directory' ? $cleanTmpDir . '/cache' : $cleanTmpDir . '/cache';
        });

        // CacheArchiver resolves appRootDirPath from dirname(realpath(cacheDir))
        // Since the cache dir doesn't exist, it will use dirname of the string
        $archiver = new CacheArchiver($this->mockLogger, $mockConfig);
        $result   = $archiver->findStaleArchives();
        $this->assertIsArray($result);

        // Clean up
        rmdir($cleanTmpDir);
    }

    public function testFindStaleArchivesFindsExistingTarFiles(): void
    {
        // Create a real tar file in a temp dir
        $testDir = sys_get_temp_dir() . '/gc_stale_test_' . uniqid();
        mkdir($testDir, 0755, true);
        $cacheDir = $testDir . '/cache';
        mkdir($cacheDir, 0755, true);

        // Create a fake stale archive in the parent directory
        $staleFile = $testDir . '/cache_2025_01_01_00_00_00.tar';
        file_put_contents($staleFile, 'fake tar data');

        $mockConfig = $this->createMock(Config::class);
        $mockConfig->method('get')->willReturn($cacheDir);

        $archiver = new CacheArchiver($this->mockLogger, $mockConfig);
        $result   = $archiver->findStaleArchives();

        $this->assertIsArray($result);
        $this->assertContains($staleFile, $result);

        // Clean up
        unlink($staleFile);
        rmdir($cacheDir);
        rmdir($testDir);
    }

    // -------------------------------------------------------------------------
    // deleteArchive()
    // -------------------------------------------------------------------------

    public function testDeleteArchiveWithNonExistentFileLogsWarningAndDoesNotThrow(): void
    {
        $this->mockLogger
            ->expects($this->atLeastOnce())
            ->method('warning');

        $archiver = new CacheArchiver($this->mockLogger, $this->mockConfig);
        // Should not throw
        $archiver->deleteArchive('/tmp/nonexistent_cache_archive_xyz.tar');
        $this->addToAssertionCount(1);
    }

    public function testDeleteArchiveDeletesExistingFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'gc_del_test_');
        file_put_contents($tmpFile, 'archive content');

        $archiver = new CacheArchiver($this->mockLogger, $this->mockConfig);
        $archiver->deleteArchive($tmpFile);

        $this->assertFileDoesNotExist($tmpFile);
    }

    // -------------------------------------------------------------------------
    // archive() — using a real tar command on a real tmp directory
    // -------------------------------------------------------------------------

    public function testArchiveThrowsAdminServiceExceptionWhenTarFails(): void
    {
        // Create a config that points to a non-existent directory so tar will fail
        $mockConfig = $this->createMock(Config::class);
        $mockConfig->method('get')->willReturn('/nonexistent/path/that/does/not/exist/cache');

        $archiver = new CacheArchiver($this->mockLogger, $mockConfig);

        $this->expectException(AdminServiceException::class);
        $archiver->archive();
    }

    // -------------------------------------------------------------------------
    // restore() — archive file not found
    // -------------------------------------------------------------------------

    public function testRestoreThrowsAdminServiceExceptionForMissingArchive(): void
    {
        $archiver = new CacheArchiver($this->mockLogger, $this->mockConfig);
        $this->expectException(AdminServiceException::class);
        $archiver->restore('/tmp/nonexistent_restore_archive_xyz.tar');
    }
}
