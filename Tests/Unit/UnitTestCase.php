<?php

namespace Gravitycar\Tests\Unit;

use Gravitycar\Tests\TestCase;

/**
 * Base class for unit tests.
 * Provides utilities for testing individual components in isolation.
 */
abstract class UnitTestCase extends TestCase
{
    /**
     * Create a mock object with specified methods.
     */
    protected function createMockWithMethods(string $className, array $methods = []): object
    {
        $mock = $this->createMock($className);

        foreach ($methods as $method => $returnValue) {
            $mock->method($method)->willReturn($returnValue);
        }

        return $mock;
    }

    /**
     * Assert that a method was called on a mock object.
     */
    protected function assertMethodCalled(object $mock, string $method, int $times = 1): void
    {
        $mock->expects($this->exactly($times))->method($method);
    }

    /**
     * Create a stub that returns different values for consecutive calls.
     */
    protected function createStubWithConsecutiveReturns(string $className, string $method, array $returns): object
    {
        $stub = $this->createMock($className);
        $stub->method($method)->willReturnOnConsecutiveCalls(...$returns);
        return $stub;
    }
}
