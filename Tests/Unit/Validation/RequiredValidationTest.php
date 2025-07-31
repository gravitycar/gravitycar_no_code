<?php

namespace Gravitycar\Tests\Unit\Validation;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Validation\RequiredValidation;

/**
 * Test suite for the RequiredValidation class.
 * Tests validation logic for required fields.
 */
class RequiredValidationTest extends UnitTestCase
{
    private RequiredValidation $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new RequiredValidation($this->logger);
    }

    /**
     * Test constructor sets correct name and error message
     */
    public function testConstructor(): void
    {
        $this->assertEquals('This field is required.', $this->validator->getErrorMessage());
    }

    /**
     * Test validation with valid values
     */
    public function testValidatesRequiredValues(): void
    {
        // String values
        $this->assertTrue($this->validator->validate('test'));
        $this->assertTrue($this->validator->validate('a'));
        $this->assertTrue($this->validator->validate('Hello World'));

        // Numeric values
        $this->assertTrue($this->validator->validate(123));
        $this->assertTrue($this->validator->validate(456.78));
        $this->assertTrue($this->validator->validate(-1));

        // Special case: '0' and 0 should be valid (PHP truthy logic)
        $this->assertTrue($this->validator->validate('0'));
        $this->assertTrue($this->validator->validate(0));

        // Arrays
        $this->assertTrue($this->validator->validate(['item']));
        $this->assertTrue($this->validator->validate([1, 2, 3]));

        // Objects
        $this->assertTrue($this->validator->validate(new \stdClass()));

        // Boolean true
        $this->assertTrue($this->validator->validate(true));
    }

    /**
     * Test validation with empty/invalid values
     */
    public function testFailsForEmptyValues(): void
    {
        // Empty string
        $this->assertFalse($this->validator->validate(''));

        // Null
        $this->assertFalse($this->validator->validate(null));

        // Boolean false
        $this->assertFalse($this->validator->validate(false));

        // Empty array
        $this->assertFalse($this->validator->validate([]));
    }

    /**
     * Test edge cases and special values
     */
    public function testEdgeCases(): void
    {
        // Whitespace strings should be valid (not empty)
        $this->assertTrue($this->validator->validate(' '));
        $this->assertTrue($this->validator->validate('  '));
        $this->assertTrue($this->validator->validate("\t"));
        $this->assertTrue($this->validator->validate("\n"));

        // Zero string and number should be valid
        $this->assertTrue($this->validator->validate('0'));
        $this->assertTrue($this->validator->validate(0));
        $this->assertTrue($this->validator->validate(0.0));

        // Negative zero
        $this->assertTrue($this->validator->validate(-0));
    }

    /**
     * Test JavaScript validation generation
     */
    public function testJavascriptValidation(): void
    {
        $jsValidation = $this->validator->getJavascriptValidation();

        $this->assertIsString($jsValidation);
        $this->assertStringContainsString('function validateRequired', $jsValidation);
        $this->assertStringContainsString('This field is required.', $jsValidation);
        $this->assertStringContainsString("value === '0'", $jsValidation);
        $this->assertStringContainsString('value === 0', $jsValidation);
    }

    /**
     * Test validation with different data types
     */
    public function testDifferentDataTypes(): void
    {
        // String types
        $this->assertTrue($this->validator->validate('test string'));
        $this->assertFalse($this->validator->validate(''));

        // Integer types
        $this->assertTrue($this->validator->validate(42));
        $this->assertTrue($this->validator->validate(0)); // Special case

        // Float types
        $this->assertTrue($this->validator->validate(3.14));
        $this->assertTrue($this->validator->validate(0.0)); // Special case

        // Boolean types
        $this->assertTrue($this->validator->validate(true));
        $this->assertFalse($this->validator->validate(false));

        // Array types
        $this->assertTrue($this->validator->validate([1, 2, 3]));
        $this->assertFalse($this->validator->validate([]));

        // Object types
        $this->assertTrue($this->validator->validate(new \stdClass()));

        // Null
        $this->assertFalse($this->validator->validate(null));
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
            $this->validator->validate([]);
            $this->validator->validate(false);
            $this->validator->validate(0);
            $this->validator->validate('0');
            $this->validator->validate(new \stdClass());
            $this->validator->validate(INF);
            $this->validator->validate(NAN);

            $this->assertTrue(true); // If we get here, no exceptions were thrown
        } catch (\Exception $e) {
            $this->fail('Required validation should not throw exceptions, but got: ' . $e->getMessage());
        }
    }
}
