<?php

namespace Gravitycar\Tests\Unit\Validation;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Validation\EmailValidation;

/**
 * Test suite for the EmailValidation class.
 * Tests validation logic for email address format.
 */
class EmailValidationTest extends UnitTestCase
{
    private EmailValidation $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new EmailValidation($this->logger);
    }

    /**
     * Test constructor sets correct name and error message
     */
    public function testConstructor(): void
    {
        $this->assertEquals('Invalid email address.', $this->validator->getErrorMessage());
    }

    /**
     * Test validation with valid email addresses
     */
    public function testValidEmailAddresses(): void
    {
        // Basic valid emails
        $this->assertTrue($this->validator->validate('test@example.com'));
        $this->assertTrue($this->validator->validate('user@domain.org'));
        $this->assertTrue($this->validator->validate('admin@company.net'));

        // Email with subdomains
        $this->assertTrue($this->validator->validate('user@mail.example.com'));
        $this->assertTrue($this->validator->validate('test@subdomain.domain.co.uk'));

        // Email with numbers
        $this->assertTrue($this->validator->validate('user123@example.com'));
        $this->assertTrue($this->validator->validate('test@example123.com'));

        // Email with special characters (allowed in local part)
        $this->assertTrue($this->validator->validate('user.name@example.com'));
        $this->assertTrue($this->validator->validate('user+tag@example.com'));
        $this->assertTrue($this->validator->validate('user_name@example.com'));
        $this->assertTrue($this->validator->validate('user-name@example.com'));

        // Short domain extensions
        $this->assertTrue($this->validator->validate('test@example.co'));
        $this->assertTrue($this->validator->validate('test@example.io'));

        // Complex but valid emails
        $this->assertTrue($this->validator->validate('first.last+tag@sub.domain.example.com'));
    }

    /**
     * Test validation with invalid email addresses
     */
    public function testInvalidEmailAddresses(): void
    {
        // Missing @ symbol
        $this->assertFalse($this->validator->validate('userexample.com'));
        $this->assertFalse($this->validator->validate('user.example.com'));

        // Missing domain
        $this->assertFalse($this->validator->validate('user@'));

        // Missing local part
        $this->assertFalse($this->validator->validate('@example.com'));

        // Multiple @ symbols
        $this->assertFalse($this->validator->validate('user@@example.com'));
        $this->assertFalse($this->validator->validate('user@domain@example.com'));

        // Invalid domain format
        $this->assertFalse($this->validator->validate('user@.com'));
        $this->assertFalse($this->validator->validate('user@domain.'));
        $this->assertFalse($this->validator->validate('user@domain..com'));

        // Spaces (not allowed)
        $this->assertFalse($this->validator->validate('user @example.com'));
        $this->assertFalse($this->validator->validate('user@ example.com'));
        $this->assertFalse($this->validator->validate('user@example .com'));

        // Invalid characters
        $this->assertFalse($this->validator->validate('user<>@example.com'));
        $this->assertFalse($this->validator->validate('user@example<>.com'));

        // Too short domain
        $this->assertFalse($this->validator->validate('user@a'));
    }

    /**
     * Test edge cases and special scenarios
     */
    public function testEdgeCases(): void
    {
        // Empty string - should be invalid for email format
        $this->assertFalse($this->validator->validate(''));

        // Null - should be invalid
        $this->assertFalse($this->validator->validate(null));

        // Non-string types
        $this->assertFalse($this->validator->validate(123));
        $this->assertFalse($this->validator->validate([]));
        $this->assertFalse($this->validator->validate(new \stdClass()));
        $this->assertFalse($this->validator->validate(true));
        $this->assertFalse($this->validator->validate(false));

        // Very long email (testing practical limits)
        $longLocal = str_repeat('a', 64);
        $longDomain = str_repeat('b', 63) . '.com';
        $longEmail = $longLocal . '@' . $longDomain;
        // This should still be valid as it's within RFC limits
        $this->assertTrue($this->validator->validate($longEmail));
    }

    /**
     * Test international domain names and special cases
     */
    public function testInternationalAndSpecialCases(): void
    {
        // Valid single letter domains
        $this->assertTrue($this->validator->validate('test@x.co'));

        // Valid with dashes in domain
        $this->assertTrue($this->validator->validate('user@my-domain.com'));

        // Valid with numbers in domain
        $this->assertTrue($this->validator->validate('user@domain123.com'));

        // Invalid - domain starting with dash
        $this->assertFalse($this->validator->validate('user@-domain.com'));

        // Invalid - domain ending with dash
        $this->assertFalse($this->validator->validate('user@domain-.com'));
    }

    /**
     * Test JavaScript validation generation
     */
    public function testJavascriptValidation(): void
    {
        $jsValidation = $this->validator->getJavascriptValidation();

        $this->assertIsString($jsValidation);
        $this->assertStringContainsString('function validateEmail', $jsValidation);
        $this->assertStringContainsString('Invalid email address.', $jsValidation);
        $this->assertStringContainsString('emailRegex', $jsValidation);
        $this->assertStringContainsString('test(value)', $jsValidation);

        // Should handle empty values gracefully in JavaScript
        $this->assertStringContainsString("value === ''", $jsValidation);
    }

    /**
     * Test real-world email examples
     */
    public function testRealWorldEmails(): void
    {
        // Common email providers
        $this->assertTrue($this->validator->validate('user@gmail.com'));
        $this->assertTrue($this->validator->validate('user@yahoo.com'));
        $this->assertTrue($this->validator->validate('user@hotmail.com'));
        $this->assertTrue($this->validator->validate('user@outlook.com'));

        // Business emails
        $this->assertTrue($this->validator->validate('contact@company.com'));
        $this->assertTrue($this->validator->validate('support@business.org'));
        $this->assertTrue($this->validator->validate('info@startup.io'));

        // Academic emails
        $this->assertTrue($this->validator->validate('student@university.edu'));
        $this->assertTrue($this->validator->validate('professor@college.ac.uk'));

        // Government emails
        $this->assertTrue($this->validator->validate('citizen@government.gov'));
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
            $this->validator->validate('invalid-email');
            $this->validator->validate('@@@');
            $this->validator->validate([]);
            $this->validator->validate(new \stdClass());
            $this->validator->validate(123);
            $this->validator->validate(true);

            $this->assertTrue(true); // If we get here, no exceptions were thrown
        } catch (\Exception $e) {
            $this->fail('Email validation should not throw exceptions, but got: ' . $e->getMessage());
        }
    }
}
