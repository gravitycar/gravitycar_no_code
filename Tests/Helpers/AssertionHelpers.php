<?php

namespace Gravitycar\Tests\Helpers;

/**
 * Collection of assertion helpers for common test scenarios.
 */
class AssertionHelpers
{
    /**
     * Assert that an array contains all expected keys.
     */
    public static function assertArrayHasKeys(array $expectedKeys, array $array, string $message = ''): void
    {
        foreach ($expectedKeys as $key) {
            if (!array_key_exists($key, $array)) {
                throw new \PHPUnit\Framework\AssertionFailedError(
                    $message ?: "Failed asserting that array has key '{$key}'"
                );
            }
        }
    }

    /**
     * Assert that a string matches an email format.
     */
    public static function assertValidEmail(string $email, string $message = ''): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                $message ?: "Failed asserting that '{$email}' is a valid email address"
            );
        }
    }

    /**
     * Assert that a value is within a numeric range.
     */
    public static function assertInRange($value, $min, $max, string $message = ''): void
    {
        if ($value < $min || $value > $max) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                $message ?: "Failed asserting that {$value} is between {$min} and {$max}"
            );
        }
    }

    /**
     * Assert that an exception message contains specific text.
     */
    public static function assertExceptionMessageContains(\Exception $exception, string $expectedText, string $message = ''): void
    {
        if (strpos($exception->getMessage(), $expectedText) === false) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                $message ?: "Failed asserting that exception message contains '{$expectedText}'"
            );
        }
    }

    /**
     * Assert that a timestamp is recent (within last N seconds).
     */
    public static function assertRecentTimestamp(string $timestamp, int $withinSeconds = 60, string $message = ''): void
    {
        $time = strtotime($timestamp);
        $now = time();

        if (($now - $time) > $withinSeconds) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                $message ?: "Failed asserting that timestamp '{$timestamp}' is recent"
            );
        }
    }
}
