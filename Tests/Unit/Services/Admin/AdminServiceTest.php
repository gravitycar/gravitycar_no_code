<?php

declare(strict_types=1);

namespace Gravitycar\Tests\Unit\Services\Admin;

use Gravitycar\Core\Config;
use Gravitycar\Exceptions\AdminServiceException;
use Gravitycar\Services\Admin\AdminService;
use Gravitycar\Services\Admin\CacheArchiver;
use Gravitycar\Services\Admin\CacheComponent;
use Gravitycar\Services\Admin\CacheRebuilder;
use Gravitycar\Services\Admin\CacheRebuildOptions;
use Gravitycar\Services\Admin\CacheRebuildResult;
use Gravitycar\Services\Admin\CacheStepResult;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AdminService orchestrator.
 *
 * All dependencies (CacheArchiver, CacheRebuilder) are mocked to keep
 * these tests purely unit-level with no file system or shell access.
 */
class AdminServiceTest extends TestCase
{
    private MockObject $mockLogger;
    private MockObject $mockConfig;
    private MockObject $mockArchiver;
    private MockObject $mockRebuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockLogger    = $this->createMock(Logger::class);
        $this->mockConfig    = $this->createMock(Config::class);
        $this->mockRebuilder = $this->createMock(CacheRebuilder::class);
        // mockArchiver is created fresh in makeService() or specific tests
    }

    /**
     * Creates a fresh AdminService with a new CacheArchiver mock that returns
     * $staleArchives from findStaleArchives().
     */
    private function makeService(array $staleArchives = []): AdminService
    {
        $this->mockArchiver = $this->createMock(CacheArchiver::class);
        $this->mockArchiver->method('findStaleArchives')->willReturn($staleArchives);

        return new AdminService(
            $this->mockLogger,
            $this->mockConfig,
            $this->mockArchiver,
            $this->mockRebuilder
        );
    }

    private function makeOptions(bool $dryRun = false): CacheRebuildOptions
    {
        return new CacheRebuildOptions(
            [CacheComponent::METADATA],
            false,
            false,
            $dryRun
        );
    }

    // -------------------------------------------------------------------------
    // Construction-time stale archive scan
    // -------------------------------------------------------------------------

    public function testConstructorLogsWarningForEachStaleArchive(): void
    {
        $this->mockLogger
            ->expects($this->exactly(2))
            ->method('warning');

        // Pass stale archives to makeService() — they are returned by findStaleArchives()
        $this->makeService(['/tmp/cache_old1.tar', '/tmp/cache_old2.tar']);
    }

    public function testConstructorDoesNotLogWarningWhenNoStaleArchives(): void
    {
        $this->mockLogger
            ->expects($this->never())
            ->method('warning');

        $this->makeService([]);
    }

    // -------------------------------------------------------------------------
    // Dry-run path
    // -------------------------------------------------------------------------

    public function testDryRunReturnsSuccessResult(): void
    {
        $service = $this->makeService();
        // mockArchiver is now created by makeService(); set expectation after
        $this->mockArchiver->expects($this->never())->method('archive');

        $options = $this->makeOptions(dryRun: true);
        $steps   = [];

        $result = $service->performCacheRebuild($options, function (CacheStepResult $step) use (&$steps): void {
            $steps[] = $step;
        });

        $this->assertInstanceOf(CacheRebuildResult::class, $result);
        $this->assertTrue($result->isSuccess());
    }

    public function testDryRunEmitsAllStepsAsSkipped(): void
    {
        $service = $this->makeService();
        $options = new CacheRebuildOptions(
            [CacheComponent::METADATA, CacheComponent::ROUTES],
            false,
            false,
            true
        );
        $steps = [];

        $service->performCacheRebuild($options, function (CacheStepResult $step) use (&$steps): void {
            $steps[] = $step;
        });

        foreach ($steps as $step) {
            $this->assertSame('skipped', $step->getStatus(), "Step '{$step->getStepName()}' should be skipped");
        }
    }

    public function testDryRunDoesNotCallArchiver(): void
    {
        $service = $this->makeService();
        // mockArchiver created by makeService; set expectations after
        $this->mockArchiver->expects($this->never())->method('archive');
        $this->mockArchiver->expects($this->never())->method('restore');
        $this->mockArchiver->expects($this->never())->method('deleteArchive');

        $service->performCacheRebuild($this->makeOptions(dryRun: true), function (CacheStepResult $step): void {
        });
    }

    public function testDryRunDoesNotCallRebuilder(): void
    {
        $this->mockRebuilder->expects($this->never())->method('clear');
        $this->mockRebuilder->expects($this->never())->method('rebuild');
        $this->mockRebuilder->expects($this->never())->method('validate');

        $service = $this->makeService();
        $service->performCacheRebuild($this->makeOptions(dryRun: true), function (CacheStepResult $step): void {
        });
    }

    // -------------------------------------------------------------------------
    // Happy path — phases called in order
    // -------------------------------------------------------------------------

    public function testPerformCacheRebuildCallsArchiveThenClearThenRebuildThenValidate(): void
    {
        $callOrder = [];

        // mockRebuilder set up before makeService (since makeService doesn't touch mockRebuilder)
        $this->mockRebuilder
            ->expects($this->once())
            ->method('clear')
            ->willReturnCallback(function () use (&$callOrder): void {
                $callOrder[] = 'clear';
            });

        $this->mockRebuilder
            ->expects($this->once())
            ->method('rebuild')
            ->willReturnCallback(function () use (&$callOrder): void {
                $callOrder[] = 'rebuild';
            });

        $this->mockRebuilder
            ->expects($this->once())
            ->method('validate')
            ->willReturnCallback(function () use (&$callOrder): void {
                $callOrder[] = 'validate';
            });

        // makeService() creates mockArchiver; set expectations after
        $service = $this->makeService();

        $this->mockArchiver
            ->expects($this->once())
            ->method('archive')
            ->willReturnCallback(function () use (&$callOrder): string {
                $callOrder[] = 'archive';
                return '/tmp/cache_test.tar';
            });

        $this->mockArchiver
            ->expects($this->once())
            ->method('deleteArchive');

        $service->performCacheRebuild($this->makeOptions(), function (CacheStepResult $step): void {
        });

        $this->assertSame(['archive', 'clear', 'rebuild', 'validate'], $callOrder);
    }

    public function testPerformCacheRebuildReturnsSuccessResult(): void
    {
        $service = $this->makeService();
        $this->mockArchiver->method('archive')->willReturn('/tmp/archive.tar');

        $result  = $service->performCacheRebuild($this->makeOptions(), function (CacheStepResult $step): void {
        });

        $this->assertInstanceOf(CacheRebuildResult::class, $result);
        $this->assertTrue($result->isSuccess());
    }

    // -------------------------------------------------------------------------
    // Failure path — rebuild throws, restore is called
    // -------------------------------------------------------------------------

    public function testOnRebuildExceptionRestoreIsCalledWithCorrectArchivePath(): void
    {
        $archivePath = '/tmp/cache_restore_test.tar';

        $this->mockRebuilder
            ->method('clear')
            ->willThrowException(new \RuntimeException('clear failed'));

        $service = $this->makeService();
        $this->mockArchiver->method('archive')->willReturn($archivePath);
        $this->mockArchiver
            ->expects($this->once())
            ->method('restore')
            ->with($archivePath);

        $service->performCacheRebuild($this->makeOptions(), function (CacheStepResult $step): void {
        });
    }

    public function testOnRebuildExceptionDeleteArchiveIsCalledAfterRestore(): void
    {
        $archivePath = '/tmp/cache_cleanup_test.tar';

        $this->mockRebuilder
            ->method('clear')
            ->willThrowException(new \RuntimeException('failed'));

        $service = $this->makeService();
        $this->mockArchiver->method('archive')->willReturn($archivePath);
        $this->mockArchiver->method('restore');
        $this->mockArchiver
            ->expects($this->once())
            ->method('deleteArchive')
            ->with($archivePath);

        $service->performCacheRebuild($this->makeOptions(), function (CacheStepResult $step): void {
        });
    }

    public function testOnRebuildExceptionRestoreStepIsRecorded(): void
    {
        $this->mockRebuilder
            ->method('clear')
            ->willThrowException(new \RuntimeException('clear error'));

        $service = $this->makeService();
        $this->mockArchiver->method('archive')->willReturn('/tmp/archive.tar');

        $steps = [];
        $service->performCacheRebuild($this->makeOptions(), function (CacheStepResult $step) use (&$steps): void {
            $steps[] = $step;
        });

        $stepNames = array_map(fn(CacheStepResult $s) => $s->getStepName(), $steps);
        $this->assertContains('restore', $stepNames);
    }

    public function testOnRebuildExceptionResultHasRestoreStep(): void
    {
        // When a rebuild phase throws, AdminService adds a restore inProgress/success
        // step (restore doesn't fail in this test). The result should contain a 'restore' step.
        $this->mockRebuilder
            ->method('clear')
            ->willThrowException(new \RuntimeException('clear error'));

        $service = $this->makeService();
        $this->mockArchiver->method('archive')->willReturn('/tmp/archive.tar');

        $result = $service->performCacheRebuild($this->makeOptions(), function (CacheStepResult $step): void {
        });

        $stepNames = array_map(
            fn(CacheStepResult $s) => $s->getStepName(),
            $result->getSteps()
        );
        $this->assertContains('restore', $stepNames);
        $this->assertFalse($result->isSuccess(), 'Result must report failure when a phase throws');
    }

    // -------------------------------------------------------------------------
    // Archive failure propagates (not caught by AdminService)
    // -------------------------------------------------------------------------

    public function testArchiveFailurePropagatesAsThrowable(): void
    {
        $service = $this->makeService();
        $this->mockArchiver
            ->method('archive')
            ->willThrowException(new \RuntimeException('archive failed'));

        $this->expectException(\Throwable::class);
        $service->performCacheRebuild($this->makeOptions(), function (CacheStepResult $step): void {
        });
    }

    public function testArchiveFailureDoesNotCallRestore(): void
    {
        $service = $this->makeService();
        $this->mockArchiver
            ->method('archive')
            ->willThrowException(new \RuntimeException('archive failed'));
        $this->mockArchiver->expects($this->never())->method('restore');

        try {
            $service->performCacheRebuild($this->makeOptions(), function (CacheStepResult $step): void {
            });
        } catch (\Throwable $e) {
            // expected
        }
    }

    // -------------------------------------------------------------------------
    // Result structure
    // -------------------------------------------------------------------------

    public function testResultToArrayHasDoneTrue(): void
    {
        $service = $this->makeService();
        $this->mockArchiver->method('archive')->willReturn('/tmp/archive.tar');

        $result = $service->performCacheRebuild($this->makeOptions(), function (CacheStepResult $step): void {
        });

        $array = $result->toArray();
        $this->assertTrue($array['done']);
    }
}
