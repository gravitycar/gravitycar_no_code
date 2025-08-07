<?php
namespace Gravitycar\Tests\Unit\Validation;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Validation\AlphanumericValidation;

/**
 * Test suite for the AlphanumericValidation class.
 * Tests validation logic for alphanumeric characters with various input scenarios.
 */
class AlphanumericValidationTest extends UnitTestCase {

    private AlphanumericValidation $validator;

    protected function setUp(): void {
        parent::setUp();

        // Create the validator instance using the logger from base class
        $this->validator = new AlphanumericValidation($this->logger);
    }

    /**
     * Test basic alphanumeric validation with valid inputs
     */
    public function testValidAlphanumericValues(): void {
        // Test simple alphanumeric strings
        $this->assertTrue($this->validator->validate('abc123'));
        $this->assertTrue($this->validator->validate('ABC123'));
        $this->assertTrue($this->validator->validate('test'));
        $this->assertTrue($this->validator->validate('123'));
        $this->assertTrue($this->validator->validate('Test123'));
        $this->assertTrue($this->validator->validate('a1b2c3'));
    }

    /**
     * Test alphanumeric validation with spaces (should be valid as spaces are ignored)
     */
    public function testValidAlphanumericWithSpaces(): void {
        $this->assertTrue($this->validator->validate('abc 123'));
        $this->assertTrue($this->validator->validate('Test Value 123'));
        $this->assertTrue($this->validator->validate('Hello World'));
        $this->assertTrue($this->validator->validate('A B C 1 2 3'));
        $this->assertTrue($this->validator->validate(' leadingSpace'));
        $this->assertTrue($this->validator->validate('trailingSpace '));
        $this->assertTrue($this->validator->validate(' both sides '));
    }

    /**
     * Test invalid values that contain non-alphanumeric characters
     */
    public function testInvalidNonAlphanumericValues(): void {
        // Test special characters
        $this->assertFalse($this->validator->validate('abc!123'));
        $this->assertFalse($this->validator->validate('test@value'));
        $this->assertFalse($this->validator->validate('hello#world'));
        $this->assertFalse($this->validator->validate('test$123'));
        $this->assertFalse($this->validator->validate('value%test'));

        // Test punctuation
        $this->assertFalse($this->validator->validate('test.value'));
        $this->assertFalse($this->validator->validate('hello,world'));
        $this->assertFalse($this->validator->validate('test:value'));
        $this->assertFalse($this->validator->validate('hello;world'));
        $this->assertFalse($this->validator->validate('test?value'));

        // Test symbols
        $this->assertFalse($this->validator->validate('test-value'));
        $this->assertFalse($this->validator->validate('test_value'));
        $this->assertFalse($this->validator->validate('test+value'));
        $this->assertFalse($this->validator->validate('test=value'));
        $this->assertFalse($this->validator->validate('test|value'));
        $this->assertFalse($this->validator->validate('test\\value'));
        $this->assertFalse($this->validator->validate('test/value'));

        // Test brackets and parentheses
        $this->assertFalse($this->validator->validate('test(value)'));
        $this->assertFalse($this->validator->validate('test[value]'));
        $this->assertFalse($this->validator->validate('test{value}'));
        $this->assertFalse($this->validator->validate('test<value>'));
    }

    /**
     * Test edge cases and empty values
     */
    public function testEdgeCases(): void {
        // Empty string should be valid (handled by shouldValidateValue - returns early)
        $this->assertTrue($this->validator->validate(''));

        // Null should be valid (handled by shouldValidateValue - returns early)
        $this->assertTrue($this->validator->validate(null));

        // Only spaces - validation proceeds, but after removing spaces we get empty string
        // ctype_alnum('') returns false, so these should fail
        $this->assertFalse($this->validator->validate('   '));
        $this->assertFalse($this->validator->validate(' '));

        // Single character tests
        $this->assertTrue($this->validator->validate('a'));
        $this->assertTrue($this->validator->validate('1'));
        $this->assertTrue($this->validator->validate('Z'));
        $this->assertFalse($this->validator->validate('!'));
        $this->assertFalse($this->validator->validate('-'));
    }

    /**
     * Test numeric values passed as numbers instead of strings
     */
    public function testNumericValues(): void {
        // Integer values should be converted to string and validated
        $this->assertTrue($this->validator->validate(123));
        $this->assertTrue($this->validator->validate(0));

        // Float values will have decimal points, so they should fail
        $this->assertFalse($this->validator->validate(123.45));
        $this->assertTrue($this->validator->validate(0.0));
    }

    /**
     * Test boolean values
     */
    public function testBooleanValues(): void {
        // Boolean true becomes "1" which is alphanumeric
        $this->assertTrue($this->validator->validate(true));

        // Boolean false becomes "" (empty string) after conversion
        // However, false is not null or empty string, so shouldValidateValue returns true
        // Then ctype_alnum('') returns false, so validation fails
        $this->assertFalse($this->validator->validate(false));
    }

    /**
     * Test the validator's name and error message
     */
    public function testValidatorProperties(): void {
        // Test that the validator has the correct name
        $reflection = new \ReflectionClass($this->validator);
        $nameProperty = $reflection->getProperty('name');
        $nameProperty->setAccessible(true);
        $this->assertEquals('Alphanumeric', $nameProperty->getValue($this->validator));

        // Test that the validator has the correct error message
        $errorMessageProperty = $reflection->getProperty('errorMessage');
        $errorMessageProperty->setAccessible(true);
        $this->assertEquals('Value must contain only letters and numbers.', $errorMessageProperty->getValue($this->validator));
    }

    /**
     * Test JavaScript validation generation
     */
    public function testJavascriptValidation(): void {
        $jsValidation = $this->validator->getJavascriptValidation();

        // Check that JavaScript validation code is returned
        $this->assertIsString($jsValidation);
        $this->assertStringContainsString('function validateAlphanumeric', $jsValidation);
        $this->assertStringContainsString('alphanumericRegex', $jsValidation);
        $this->assertStringContainsString('Value must contain only letters and numbers.', $jsValidation);
        $this->assertStringContainsString('/^[a-zA-Z0-9]*$/', $jsValidation);
    }

    /**
     * Test validation with arrays (should fail as arrays aren't alphanumeric)
     */
    public function testArrayValues(): void {
        $this->assertFalse($this->validator->validate(['test', 'array']));
        $this->assertFalse($this->validator->validate([]));
    }

    /**
     * Test validation with objects (should fail)
     */
    public function testObjectValues(): void {
        $this->assertFalse($this->validator->validate(new \stdClass()));
        $this->assertFalse($this->validator->validate($this));
    }

    /**
     * Test various Unicode and international characters
     */
    public function testUnicodeCharacters(): void {
        // Accented characters should fail (not basic alphanumeric)
        $this->assertFalse($this->validator->validate('café'));
        $this->assertFalse($this->validator->validate('naïve'));
        $this->assertFalse($this->validator->validate('résumé'));

        // Non-Latin scripts should fail
        $this->assertFalse($this->validator->validate('こんにちは')); // Japanese
        $this->assertFalse($this->validator->validate('مرحبا')); // Arabic
        $this->assertFalse($this->validator->validate('привет')); // Russian
    }

    /**
     * Test whitespace variations
     */
    public function testWhitespaceVariations(): void {
        // Regular spaces are allowed
        $this->assertTrue($this->validator->validate('test value'));

        // Tabs should fail (not regular spaces)
        $this->assertFalse($this->validator->validate("test\tvalue"));

        // Newlines should fail
        $this->assertFalse($this->validator->validate("test\nvalue"));
        $this->assertFalse($this->validator->validate("test\r\nvalue"));

        // Other whitespace characters should fail
        $this->assertFalse($this->validator->validate("test\u{00A0}value")); // Non-breaking space
    }

    /**
     * Test very long strings
     */
    public function testLongStrings(): void {
        // Long valid alphanumeric string
        $longValid = str_repeat('abc123', 1000);
        $this->assertTrue($this->validator->validate($longValid));

        // Long string with one invalid character
        $longInvalid = str_repeat('abc123', 1000) . '!';
        $this->assertFalse($this->validator->validate($longInvalid));
    }

    /**
     * Test that validation doesn't throw exceptions with unusual inputs
     */
    public function testNoExceptionsThrown(): void {
        try {
            // These should not throw exceptions, just return false
            $this->validator->validate(null);
            $this->validator->validate('');
            $this->validator->validate([]);
            $this->validator->validate(new \stdClass());
            $this->validator->validate(123.45);
            $this->validator->validate(INF);
            $this->validator->validate(NAN);

            $this->assertTrue(true); // If we get here, no exceptions were thrown
        } catch (\Exception $e) {
            $this->fail('Validation should not throw exceptions, but got: ' . $e->getMessage());
        }
    }
}
