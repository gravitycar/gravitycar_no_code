<?php

namespace Gravitycar\Tests\Unit\Validation;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Validation\DateTimeValidation;

/**
 * Test suite for the DateTimeValidation class.
 * Tests validation logic for date-time format (Y-m-d H:i:s).
 */
class DateTimeValidationTest extends UnitTestCase
{
    private DateTimeValidation $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new DateTimeValidation($this->logger);
    }

    /**
     * Test constructor sets correct name and error message
     */
    public function testConstructor(): void
    {
        $this->assertEquals('Invalid date-time format.', $this->validator->getErrorMessage());
    }

    /**
     * Test validation with valid date-time formats
     */
    public function testValidDateTimeFormats(): void
    {
        // Standard valid date-time formats
        $this->assertTrue($this->validator->validate('2023-12-25 14:30:00'));
        $this->assertTrue($this->validator->validate('2024-01-01 00:00:00'));
        $this->assertTrue($this->validator->validate('2024-12-31 23:59:59'));

        // Edge dates
        $this->assertTrue($this->validator->validate('2024-02-29 12:00:00')); // Leap year
        $this->assertTrue($this->validator->validate('2023-02-28 23:59:59')); // Non-leap year

        // Various months and days
        $this->assertTrue($this->validator->validate('2023-01-01 01:01:01'));
        $this->assertTrue($this->validator->validate('2023-06-15 12:30:45'));
        $this->assertTrue($this->validator->validate('2023-12-31 00:00:00'));
    }

    /**
     * Test validation with invalid date-time formats
     */
    public function testInvalidDateTimeFormats(): void
    {
        // Wrong format patterns
        $this->assertFalse($this->validator->validate('2023/12/25 14:30:00')); // Wrong date separators
        $this->assertFalse($this->validator->validate('2023-12-25T14:30:00')); // ISO format with T
        $this->assertFalse($this->validator->validate('25-12-2023 14:30:00')); // DD-MM-YYYY format
        $this->assertFalse($this->validator->validate('12/25/2023 14:30:00')); // US format

        // Missing components
        $this->assertFalse($this->validator->validate('2023-12-25'));         // Date only
        $this->assertFalse($this->validator->validate('14:30:00'));           // Time only
        $this->assertFalse($this->validator->validate('2023-12-25 14:30'));   // Missing seconds

        // Invalid component values
        $this->assertFalse($this->validator->validate('2023-13-25 14:30:00')); // Invalid month
        $this->assertFalse($this->validator->validate('2023-12-32 14:30:00')); // Invalid day
        $this->assertFalse($this->validator->validate('2023-12-25 25:30:00')); // Invalid hour
        $this->assertFalse($this->validator->validate('2023-12-25 14:60:00')); // Invalid minute
        $this->assertFalse($this->validator->validate('2023-12-25 14:30:60')); // Invalid second

        // Invalid leap year dates
        $this->assertFalse($this->validator->validate('2023-02-29 12:00:00')); // Not a leap year

        // Completely invalid formats
        $this->assertFalse($this->validator->validate('not-a-date-time'));
        $this->assertFalse($this->validator->validate('2023-12-25-14-30-00'));
        $this->assertFalse($this->validator->validate(''));
    }

    /**
     * Test edge cases and special scenarios
     */
    public function testEdgeCases(): void
    {
        // Empty string - should be invalid (not a valid datetime)
        $this->assertFalse($this->validator->validate(''));

        // Null - should be valid (handled by shouldValidateValue)
        $this->assertTrue($this->validator->validate(null));

        // Non-string types - should be invalid
        $this->assertFalse($this->validator->validate(123));
        $this->assertFalse($this->validator->validate([]));
        $this->assertFalse($this->validator->validate(new \stdClass()));
        $this->assertFalse($this->validator->validate(true));
        $this->assertFalse($this->validator->validate(false));

        // String with extra characters
        $this->assertFalse($this->validator->validate('2023-12-25 14:30:00 extra'));
        $this->assertFalse($this->validator->validate(' 2023-12-25 14:30:00'));
        $this->assertFalse($this->validator->validate('2023-12-25 14:30:00 '));
    }

    /**
     * Test leap year handling
     */
    public function testLeapYearHandling(): void
    {
        // Valid leap year dates
        $this->assertTrue($this->validator->validate('2024-02-29 12:00:00')); // 2024 is a leap year
        $this->assertTrue($this->validator->validate('2020-02-29 00:00:00')); // 2020 is a leap year
        $this->assertTrue($this->validator->validate('2000-02-29 23:59:59')); // 2000 is a leap year

        // Invalid leap year dates
        $this->assertFalse($this->validator->validate('2023-02-29 12:00:00')); // 2023 is not a leap year
        $this->assertFalse($this->validator->validate('2021-02-29 12:00:00')); // 2021 is not a leap year
        $this->assertFalse($this->validator->validate('1900-02-29 12:00:00')); // 1900 is not a leap year

        // Valid February dates for non-leap years
        $this->assertTrue($this->validator->validate('2023-02-28 23:59:59'));
        $this->assertTrue($this->validator->validate('2021-02-28 12:00:00'));
    }

    /**
     * Test month-specific day limits
     */
    public function testMonthDayLimits(): void
    {
        // 31-day months
        $this->assertTrue($this->validator->validate('2023-01-31 12:00:00'));  // January
        $this->assertTrue($this->validator->validate('2023-03-31 12:00:00'));  // March
        $this->assertTrue($this->validator->validate('2023-05-31 12:00:00'));  // May
        $this->assertTrue($this->validator->validate('2023-07-31 12:00:00'));  // July
        $this->assertTrue($this->validator->validate('2023-08-31 12:00:00'));  // August
        $this->assertTrue($this->validator->validate('2023-10-31 12:00:00'));  // October
        $this->assertTrue($this->validator->validate('2023-12-31 12:00:00'));  // December

        // 30-day months
        $this->assertTrue($this->validator->validate('2023-04-30 12:00:00'));  // April
        $this->assertTrue($this->validator->validate('2023-06-30 12:00:00'));  // June
        $this->assertTrue($this->validator->validate('2023-09-30 12:00:00'));  // September
        $this->assertTrue($this->validator->validate('2023-11-30 12:00:00'));  // November

        // Invalid days for 30-day months
        $this->assertFalse($this->validator->validate('2023-04-31 12:00:00')); // April has only 30 days
        $this->assertFalse($this->validator->validate('2023-06-31 12:00:00')); // June has only 30 days
        $this->assertFalse($this->validator->validate('2023-09-31 12:00:00')); // September has only 30 days
        $this->assertFalse($this->validator->validate('2023-11-31 12:00:00')); // November has only 30 days
    }

    /**
     * Test time component validation
     */
    public function testTimeComponentValidation(): void
    {
        // Valid time ranges
        $this->assertTrue($this->validator->validate('2023-12-25 00:00:00')); // Minimum time
        $this->assertTrue($this->validator->validate('2023-12-25 23:59:59')); // Maximum time
        $this->assertTrue($this->validator->validate('2023-12-25 12:30:45')); // Mid-day time

        // Invalid hours
        $this->assertFalse($this->validator->validate('2023-12-25 24:00:00')); // 24 is invalid
        $this->assertFalse($this->validator->validate('2023-12-25 25:30:00')); // 25 is invalid

        // Invalid minutes
        $this->assertFalse($this->validator->validate('2023-12-25 12:60:00')); // 60 minutes is invalid
        $this->assertFalse($this->validator->validate('2023-12-25 12:99:00')); // 99 minutes is invalid

        // Invalid seconds
        $this->assertFalse($this->validator->validate('2023-12-25 12:30:60')); // 60 seconds is invalid
        $this->assertFalse($this->validator->validate('2023-12-25 12:30:99')); // 99 seconds is invalid
    }

    /**
     * Test JavaScript validation generation
     */
    public function testJavascriptValidation(): void
    {
        $jsValidation = $this->validator->getJavascriptValidation();

        $this->assertIsString($jsValidation);
        $this->assertStringContainsString('function validateDateTime', $jsValidation);
        $this->assertStringContainsString('Invalid date-time format.', $jsValidation);
        $this->assertStringContainsString('dateTimeRegex', $jsValidation);
        $this->assertStringContainsString('new Date', $jsValidation);

        // Should handle empty values gracefully in JavaScript
        $this->assertStringContainsString("value === ''", $jsValidation);

        // Should check for valid date parsing
        $this->assertStringContainsString('getFullYear', $jsValidation);
        $this->assertStringContainsString('getMonth', $jsValidation);
        $this->assertStringContainsString('getDate', $jsValidation);
    }

    /**
     * Test validation doesn't throw exceptions
     */
    public function testNoExceptionsThrown(): void
    {
        try {
            // Test with various potentially problematic inputs
            $this->validator->validate(null);
            $this->validator->validate('');
            $this->validator->validate('invalid-datetime');
            $this->validator->validate('2023-13-45 25:70:99');
            $this->validator->validate([]);
            $this->validator->validate(new \stdClass());
            $this->validator->validate(123);
            $this->validator->validate(true);

            $this->assertTrue(true); // If we get here, no exceptions were thrown
        } catch (\Exception $e) {
            $this->fail('DateTime validation should not throw exceptions, but got: ' . $e->getMessage());
        }
    }
}
