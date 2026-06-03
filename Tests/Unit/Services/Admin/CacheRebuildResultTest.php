<?php

declare(strict_types=1);

namespace Gravitycar\Tests\Unit\Services\Admin;

use Gravitycar\Services\Admin\CacheRebuildResult;
use Gravitycar\Services\Admin\CacheStepResult;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CacheRebuildResult aggregate outcome object.
 */
class CacheRebuildResultTest extends TestCase
{
    // -------------------------------------------------------------------------
    // success() factory
    // -------------------------------------------------------------------------

    public function testSuccessWithEmptyStepsIsSuccess(): void
    {
        $result = CacheRebuildResult::success([]);
        $this->assertTrue($result->isSuccess());
    }

    public function testSuccessWithSuccessStepsIsSuccess(): void
    {
        $step = CacheStepResult::success('archive', 'all');
        $result = CacheRebuildResult::success([$step]);
        $this->assertTrue($result->isSuccess());
    }

    public function testSuccessReturnsDefaultMessage(): void
    {
        $result = CacheRebuildResult::success([]);
        $this->assertSame('Cache rebuild completed successfully.', $result->getMessage());
    }

    // -------------------------------------------------------------------------
    // failure() factory
    // -------------------------------------------------------------------------

    public function testFailureWithEmptyStepsIsNotSuccess(): void
    {
        $result = CacheRebuildResult::failure([], 'Cache rebuild failed.');
        $this->assertFalse($result->isSuccess());
    }

    public function testFailurePreservesMessage(): void
    {
        $result = CacheRebuildResult::failure([], 'Something went wrong');
        $this->assertSame('Something went wrong', $result->getMessage());
    }

    public function testFailureDefaultMessage(): void
    {
        $result = CacheRebuildResult::failure();
        $this->assertSame('Cache rebuild failed.', $result->getMessage());
    }

    // -------------------------------------------------------------------------
    // addStep() and automatic success flag update
    // -------------------------------------------------------------------------

    public function testAddingFailedStepToSuccessResultMakesItNotSuccess(): void
    {
        $result = CacheRebuildResult::success([]);
        $failedStep = CacheStepResult::failed('clear', 'metadata', 'unlink error');
        $result->addStep($failedStep);

        $this->assertFalse($result->isSuccess());
    }

    public function testAddingSuccessStepDoesNotAffectSuccessFlag(): void
    {
        $result = CacheRebuildResult::success([]);
        $result->addStep(CacheStepResult::success('archive', 'all'));
        $this->assertTrue($result->isSuccess());
    }

    public function testStepsAreOrderedAsAdded(): void
    {
        $result = CacheRebuildResult::success([]);
        $step1 = CacheStepResult::success('archive', 'all');
        $step2 = CacheStepResult::success('clear', 'metadata');
        $result->addStep($step1);
        $result->addStep($step2);

        $steps = $result->getSteps();
        $this->assertSame('archive', $steps[0]->getStepName());
        $this->assertSame('clear', $steps[1]->getStepName());
    }

    // -------------------------------------------------------------------------
    // hasFailures()
    // -------------------------------------------------------------------------

    public function testHasFailuresReturnsFalseWithNoSteps(): void
    {
        $result = CacheRebuildResult::success([]);
        $this->assertFalse($result->hasFailures());
    }

    public function testHasFailuresReturnsFalseWithOnlySuccessSteps(): void
    {
        $result = CacheRebuildResult::success([
            CacheStepResult::success('archive', 'all'),
            CacheStepResult::success('clear', 'metadata'),
        ]);
        $this->assertFalse($result->hasFailures());
    }

    public function testHasFailuresReturnsTrueWhenAnyStepFailed(): void
    {
        $result = CacheRebuildResult::success([
            CacheStepResult::success('archive', 'all'),
            CacheStepResult::failed('clear', 'metadata', 'error'),
        ]);
        $this->assertTrue($result->hasFailures());
    }

    public function testHasFailuresReturnsFalseForSkippedSteps(): void
    {
        $result = CacheRebuildResult::success([
            CacheStepResult::skipped('archive', 'all'),
            CacheStepResult::skipped('clear', 'metadata'),
        ]);
        $this->assertFalse($result->hasFailures());
    }

    // -------------------------------------------------------------------------
    // toArray()
    // -------------------------------------------------------------------------

    public function testToArrayAlwaysHasDoneTrue(): void
    {
        $result = CacheRebuildResult::success([]);
        $array = $result->toArray();
        $this->assertArrayHasKey('done', $array);
        $this->assertTrue($array['done']);
    }

    public function testToArrayDoneIsFirstKey(): void
    {
        $result = CacheRebuildResult::success([]);
        $array = $result->toArray();
        $keys = array_keys($array);
        $this->assertSame('done', $keys[0]);
    }

    public function testToArrayHasAllRequiredKeys(): void
    {
        $result = CacheRebuildResult::success([]);
        $array = $result->toArray();
        $this->assertArrayHasKey('done', $array);
        $this->assertArrayHasKey('success', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('steps', $array);
    }

    public function testToArrayStepsAreSerializedAsArrays(): void
    {
        $step = CacheStepResult::success('archive', 'all');
        $result = CacheRebuildResult::success([$step]);
        $array = $result->toArray();
        $this->assertIsArray($array['steps'][0]);
        $this->assertSame('archive', $array['steps'][0]['stepName']);
    }

    public function testToArraySuccessFalseForFailureResult(): void
    {
        $result = CacheRebuildResult::failure([], 'msg');
        $array = $result->toArray();
        $this->assertFalse($array['success']);
    }

    // -------------------------------------------------------------------------
    // getMessage()
    // -------------------------------------------------------------------------

    public function testGetMessageReturnsCorrectMessage(): void
    {
        $result = CacheRebuildResult::failure([], 'Custom failure message');
        $this->assertSame('Custom failure message', $result->getMessage());
    }

    // -------------------------------------------------------------------------
    // getSteps()
    // -------------------------------------------------------------------------

    public function testGetStepsReturnsEmptyArrayInitially(): void
    {
        $result = CacheRebuildResult::success([]);
        $this->assertSame([], $result->getSteps());
    }

    public function testGetStepsReturnsAddedSteps(): void
    {
        $step = CacheStepResult::success('restore', 'all');
        $result = CacheRebuildResult::success([]);
        $result->addStep($step);
        $steps = $result->getSteps();
        $this->assertCount(1, $steps);
        $this->assertSame($step, $steps[0]);
    }
}
