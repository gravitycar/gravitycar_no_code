<?php

declare(strict_types=1);

namespace Gravitycar\Tests\Unit\Services\Admin;

use Gravitycar\Services\Admin\CacheStepResult;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CacheStepResult immutable value object.
 */
class CacheStepResultTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Named constructors — status values
    // -------------------------------------------------------------------------

    public function testInProgressProducesCorrectStatus(): void
    {
        $result = CacheStepResult::inProgress('archive', 'all');
        $this->assertSame('in_progress', $result->getStatus());
    }

    public function testSuccessProducesCorrectStatus(): void
    {
        $result = CacheStepResult::success('clear', 'metadata');
        $this->assertSame('success', $result->getStatus());
    }

    public function testFailedProducesCorrectStatus(): void
    {
        $result = CacheStepResult::failed('rebuild', 'routes', 'something went wrong');
        $this->assertSame('failed', $result->getStatus());
    }

    public function testSkippedProducesCorrectStatus(): void
    {
        $result = CacheStepResult::skipped('validate', 'docs');
        $this->assertSame('skipped', $result->getStatus());
    }

    // -------------------------------------------------------------------------
    // Named constructors — field values
    // -------------------------------------------------------------------------

    public function testInProgressPreservesStepNameAndComponent(): void
    {
        $result = CacheStepResult::inProgress('rebuild', 'navigation');
        $this->assertSame('rebuild', $result->getStepName());
        $this->assertSame('navigation', $result->getComponent());
    }

    public function testSuccessHasNullErrorMessage(): void
    {
        $result = CacheStepResult::success('archive', 'all');
        $this->assertNull($result->getErrorMessage());
    }

    public function testFailedPreservesErrorMessage(): void
    {
        $result = CacheStepResult::failed('clear', 'metadata', 'unlink failed');
        $this->assertSame('unlink failed', $result->getErrorMessage());
    }

    public function testSkippedHasNullErrorMessage(): void
    {
        $result = CacheStepResult::skipped('archive', 'all');
        $this->assertNull($result->getErrorMessage());
    }

    // -------------------------------------------------------------------------
    // Valid step name: 'restore'
    // -------------------------------------------------------------------------

    public function testRestoreIsAValidStepName(): void
    {
        $result = CacheStepResult::success('restore', 'all');
        $this->assertSame('restore', $result->getStepName());
        $this->assertSame('success', $result->getStatus());
    }

    // -------------------------------------------------------------------------
    // isFailed()
    // -------------------------------------------------------------------------

    public function testIsFailedReturnsTrueForFailedStatus(): void
    {
        $result = CacheStepResult::failed('validate', 'routes', 'error');
        $this->assertTrue($result->isFailed());
    }

    public function testIsFailedReturnsFalseForSuccessStatus(): void
    {
        $result = CacheStepResult::success('validate', 'routes');
        $this->assertFalse($result->isFailed());
    }

    public function testIsFailedReturnsFalseForInProgressStatus(): void
    {
        $result = CacheStepResult::inProgress('validate', 'routes');
        $this->assertFalse($result->isFailed());
    }

    public function testIsFailedReturnsFalseForSkippedStatus(): void
    {
        $result = CacheStepResult::skipped('validate', 'routes');
        $this->assertFalse($result->isFailed());
    }

    // -------------------------------------------------------------------------
    // isSuccess()
    // -------------------------------------------------------------------------

    public function testIsSuccessReturnsTrueForSuccessStatus(): void
    {
        $result = CacheStepResult::success('archive', 'all');
        $this->assertTrue($result->isSuccess());
    }

    public function testIsSuccessReturnsFalseForFailedStatus(): void
    {
        $result = CacheStepResult::failed('archive', 'all', 'err');
        $this->assertFalse($result->isSuccess());
    }

    // -------------------------------------------------------------------------
    // toArray()
    // -------------------------------------------------------------------------

    public function testToArrayHasCorrectShape(): void
    {
        $result = CacheStepResult::success('archive', 'all');
        $array = $result->toArray();

        $this->assertArrayHasKey('stepName', $array);
        $this->assertArrayHasKey('component', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('errorMessage', $array);
    }

    public function testToArrayHasCorrectValuesForSuccess(): void
    {
        $result = CacheStepResult::success('archive', 'all');
        $array = $result->toArray();

        $this->assertSame('archive', $array['stepName']);
        $this->assertSame('all', $array['component']);
        $this->assertSame('success', $array['status']);
        $this->assertNull($array['errorMessage']);
    }

    public function testToArrayHasErrorMessageForFailed(): void
    {
        $result = CacheStepResult::failed('rebuild', 'metadata', 'engine error');
        $array = $result->toArray();

        $this->assertSame('failed', $array['status']);
        $this->assertSame('engine error', $array['errorMessage']);
    }

    public function testToArrayHasExactlyFourKeys(): void
    {
        $result = CacheStepResult::skipped('clear', 'docs');
        $array = $result->toArray();

        $this->assertCount(4, $array);
    }
}
