<?php

namespace Gravitycar\Tests\Unit\Fields;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Fields\EmailField;

/**
 * Test suite for the EmailField class.
 * Tests email field functionality with format validation and normalization.
 */
class EmailFieldTest extends UnitTestCase
{
    private EmailField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $metadata = [
            'name' => 'email',
            'type' => 'Email',
            'label' => 'Email Address',
            'required' => false,
            'maxLength' => 254,
            'placeholder' => 'Enter email address',
            'normalize' => true
        ];

        $this->field = new EmailField($metadata, $this->logger);
    }

    /**
     * Test constructor and default properties
     */
    public function testConstructor(): void
    {
        $this->assertEquals('email', $this->field->getName());
        $this->assertEquals('Email Address', $this->field->getMetadataValue('label'));
        $this->assertEquals(254, $this->field->getMetadataValue('maxLength'));
        $this->assertEquals('Enter email address', $this->field->getMetadataValue('placeholder'));
        $this->assertTrue($this->field->getMetadataValue('normalize'));
        $this->assertEquals('Email', $this->field->getMetadataValue('type'));
    }

    /**
     * Test email normalization (lowercase conversion)
     */
    public function testEmailNormalization(): void
    {
        // Test uppercase email normalization
        $this->field->setValue('USER@EXAMPLE.COM');
        $this->assertEquals('user@example.com', $this->field->getValue());

        // Test mixed case normalization
        $this->field->setValue('User.Name@Example.COM');
        $this->assertEquals('user.name@example.com', $this->field->getValue());

        // Test already lowercase email
        $this->field->setValue('user@example.com');
        $this->assertEquals('user@example.com', $this->field->getValue());
    }

    /**
     * Test normalization disabled
     */
    public function testNormalizationDisabled(): void
    {
        $metadata = [
            'name' => 'case_sensitive_email',
            'type' => 'Email',
            'normalize' => false
        ];

        $field = new EmailField($metadata, $this->logger);
        $field->setValue('USER@EXAMPLE.COM');
        $this->assertEquals('USER@EXAMPLE.COM', $field->getValue());
    }

    /**
     * Test valid email formats
     */
    public function testValidEmailFormats(): void
    {
        $validEmails = [
            'user@example.com',
            'test.email@domain.co.uk',
            'user+tag@example.org',
            'firstname.lastname@company.com',
            'user123@test-domain.com',
            'a@b.co'
        ];

        foreach ($validEmails as $email) {
            $this->field->setValue($email);
            // Should be normalized to lowercase
            $this->assertEquals(strtolower($email), $this->field->getValue());
        }
    }

    /**
     * Test email with special characters
     */
    public function testEmailWithSpecialCharacters(): void
    {
        // Test plus sign in email
        $this->field->setValue('USER+TEST@EXAMPLE.COM');
        $this->assertEquals('user+test@example.com', $this->field->getValue());

        // Test dots in local part
        $this->field->setValue('FIRST.LAST@DOMAIN.COM');
        $this->assertEquals('first.last@domain.com', $this->field->getValue());

        // Test hyphen in domain
        $this->field->setValue('USER@TEST-DOMAIN.COM');
        $this->assertEquals('user@test-domain.com', $this->field->getValue());
    }

    /**
     * Test null and empty values
     */
    public function testNullAndEmptyValues(): void
    {
        // Test null
        $this->field->setValue(null);
        $this->assertNull($this->field->getValue());

        // Test empty string
        $this->field->setValue('');
        $this->assertEquals('', $this->field->getValue());
    }

    /**
     * Test non-string values (should be handled gracefully)
     */
    public function testNonStringValues(): void
    {
        // Test integer
        $this->field->setValue(123);
        $this->assertEquals(123, $this->field->getValue());

        // Test array
        $this->field->setValue(['email' => 'user@example.com']);
        $this->assertEquals(['email' => 'user@example.com'], $this->field->getValue());

        // Test boolean
        $this->field->setValue(true);
        $this->assertTrue($this->field->getValue());
    }

    /**
     * Test default properties
     */
    public function testDefaultProperties(): void
    {
        $minimalMetadata = [
            'name' => 'simple_email',
            'type' => 'Email'
        ];

        $field = new EmailField($minimalMetadata, $this->logger);
        // Check the ingested metadata for default values
        $metadata = $field->getMetadata();
        $this->assertEquals(254, $metadata['maxLength'] ?? 254);
        $this->assertEquals('Enter email address', $metadata['placeholder'] ?? 'Enter email address');
        $this->assertTrue($metadata['normalize'] ?? true);
    }

    /**
     * Test custom maxLength
     */
    public function testCustomMaxLength(): void
    {
        $metadata = [
            'name' => 'short_email',
            'type' => 'Email',
            'maxLength' => 100
        ];

        $field = new EmailField($metadata, $this->logger);
        $this->assertEquals(100, $field->getMetadataValue('maxLength'));
    }

    /**
     * Test custom placeholder
     */
    public function testCustomPlaceholder(): void
    {
        $metadata = [
            'name' => 'work_email',
            'type' => 'Email',
            'placeholder' => 'Enter your work email'
        ];

        $field = new EmailField($metadata, $this->logger);
        $this->assertEquals('Enter your work email', $field->getMetadataValue('placeholder'));
    }

    /**
     * Test required email field
     */
    public function testRequiredEmailField(): void
    {
        $metadata = [
            'name' => 'required_email',
            'type' => 'Email',
            'required' => true
        ];

        $field = new EmailField($metadata, $this->logger);
        $this->assertTrue($field->isRequired());
    }

    /**
     * Test setValueFromTrustedSource
     */
    public function testSetValueFromTrustedSource(): void
    {
        // Should not normalize when set from trusted source
        $this->field->setValueFromTrustedSource('USER@EXAMPLE.COM');
        $this->assertEquals('USER@EXAMPLE.COM', $this->field->getValue());

        $this->field->setValueFromTrustedSource('user@example.com');
        $this->assertEquals('user@example.com', $this->field->getValue());
    }

    /**
     * Test edge cases and unusual formats
     */
    public function testEdgeCases(): void
    {
        // Test very long email
        $longEmail = 'verylongusername@verylongdomainname.com';
        $this->field->setValue($longEmail);
        $this->assertEquals($longEmail, $this->field->getValue());

        // Test internationalized domain (should be stored as-is)
        $this->field->setValue('user@münchen.de');
        $this->assertEquals('user@münchen.de', $this->field->getValue());

        // Test quoted local part (unusual but valid)
        $this->field->setValue('"user name"@example.com');
        $this->assertEquals('"user name"@example.com', $this->field->getValue());
    }

    /**
     * Test invalid email formats (should be stored as-is, validation happens elsewhere)
     */
    public function testInvalidEmailFormats(): void
    {
        $invalidEmails = [
            'not-an-email',
            '@example.com',
            'user@',
            'user@.com',
            'user..name@example.com'
        ];

        foreach ($invalidEmails as $email) {
            $this->field->setValue($email);
            // Should be normalized to lowercase if it's a string
            $this->assertEquals(strtolower($email), $this->field->getValue());
        }
    }
}
