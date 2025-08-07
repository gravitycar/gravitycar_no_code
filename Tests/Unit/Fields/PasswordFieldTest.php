<?php

namespace Gravitycar\Tests\Unit\Fields;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Fields\PasswordField;

/**
 * Test suite for the PasswordField class.
 * Tests secure password field functionality with hashing and security features.
 */
class PasswordFieldTest extends UnitTestCase
{
    private PasswordField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $metadata = [
            'name' => 'password',
            'type' => 'Password',
            'label' => 'Password',
            'required' => true,
            'maxLength' => 100,
            'minLength' => 8,
            'showButton' => true,
            'placeholder' => 'Enter password',
            'hashOnSave' => true
        ];

        $this->field = new PasswordField($metadata, $this->logger);
    }

    /**
     * Test constructor and default properties
     */
    public function testConstructor(): void
    {
        $this->assertEquals('password', $this->field->getName());
        $this->assertEquals('Password', $this->field->getMetadataValue('label'));
        $this->assertTrue($this->field->getMetadataValue('required'));
        $this->assertEquals(100, $this->field->getMetadataValue('maxLength'));
        $this->assertEquals(8, $this->field->getMetadataValue('minLength'));
        $this->assertTrue($this->field->getMetadataValue('showButton'));
        $this->assertEquals('Enter password', $this->field->getMetadataValue('placeholder'));
        $this->assertTrue($this->field->getMetadataValue('hashOnSave'));
        $this->assertEquals('Password', $this->field->getMetadataValue('type'));
    }

    /**
     * Test password field is required by default
     */
    public function testRequiredByDefault(): void
    {
        $this->assertTrue($this->field->isRequired());
        $this->assertTrue($this->field->metadataIsTrue('required'));
    }

    /**
     * Test setting password values
     */
    public function testPasswordValues(): void
    {
        // Test strong password
        $this->field->setValue('SecurePassword123!');
        $this->assertEquals('SecurePassword123!', $this->field->getValue());

        // Test basic password
        $this->field->setValue('password123');
        $this->assertEquals('password123', $this->field->getValue());

        // Test password with special characters
        $this->field->setValue('P@ssw0rd!@#$');
        $this->assertEquals('P@ssw0rd!@#$', $this->field->getValue());
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
     * Test minLength property
     */
    public function testMinLength(): void
    {
        $this->assertEquals(8, $this->field->getMetadataValue('minLength'));

        // Test custom minLength
        $metadata = [
            'name' => 'short_password',
            'type' => 'Password',
            'minLength' => 6
        ];

        $field = new PasswordField($metadata, $this->logger);
        $this->assertEquals(6, $field->getMetadataValue('minLength'));
    }

    /**
     * Test maxLength property
     */
    public function testMaxLength(): void
    {
        $this->assertEquals(100, $this->field->getMetadataValue('maxLength'));

        // Test custom maxLength
        $metadata = [
            'name' => 'long_password',
            'type' => 'Password',
            'maxLength' => 255
        ];

        $field = new PasswordField($metadata, $this->logger);
        $this->assertEquals(255, $field->getMetadataValue('maxLength'));
    }

    /**
     * Test showButton property
     */
    public function testShowButton(): void
    {
        $this->assertTrue($this->field->getMetadataValue('showButton'));

        // Test showButton disabled
        $metadata = [
            'name' => 'no_button_password',
            'type' => 'Password',
            'showButton' => false
        ];

        $field = new PasswordField($metadata, $this->logger);
        $this->assertFalse($field->getMetadataValue('showButton'));
    }

    /**
     * Test hashOnSave property
     */
    public function testHashOnSave(): void
    {
        $this->assertTrue($this->field->getMetadataValue('hashOnSave'));

        // Test hashOnSave disabled (for plain text storage)
        $metadata = [
            'name' => 'plain_password',
            'type' => 'Password',
            'hashOnSave' => false
        ];

        $field = new PasswordField($metadata, $this->logger);
        $this->assertFalse($field->getMetadataValue('hashOnSave'));
    }

    /**
     * Test default properties
     */
    public function testDefaultProperties(): void
    {
        $minimalMetadata = [
            'name' => 'simple_password',
            'type' => 'Password'
        ];

        $field = new PasswordField($minimalMetadata, $this->logger);
        $this->assertTrue($field->getMetadataValue('required'));
        $this->assertEquals(100, $field->getMetadataValue('maxLength'));
        $this->assertEquals(8, $field->getMetadataValue('minLength'));
        $this->assertTrue($field->getMetadataValue('showButton'));
        $this->assertEquals('Enter password', $field->getMetadataValue('placeholder'));
        $this->assertTrue($field->getMetadataValue('hashOnSave'));
    }

    /**
     * Test custom placeholder
     */
    public function testCustomPlaceholder(): void
    {
        $metadata = [
            'name' => 'custom_password',
            'type' => 'Password',
            'placeholder' => 'Create a secure password'
        ];

        $field = new PasswordField($metadata, $this->logger);
        $this->assertEquals('Create a secure password', $field->getMetadataValue('placeholder'));
    }

    /**
     * Test setValueFromTrustedSource
     */
    public function testSetValueFromTrustedSource(): void
    {
        // Trusted source might be hashed password from database
        $hashedPassword = '$2y$10$example.hash.here';
        $this->field->setValueFromTrustedSource($hashedPassword);
        $this->assertEquals($hashedPassword, $this->field->getValue());

        $this->field->setValueFromTrustedSource('plain_password');
        $this->assertEquals('plain_password', $this->field->getValue());

        $this->field->setValueFromTrustedSource(null);
        $this->assertNull($this->field->getValue());
    }

    /**
     * Test various password complexity scenarios
     */
    public function testPasswordComplexity(): void
    {
        // Test simple password
        $this->field->setValue('simple');
        $this->assertEquals('simple', $this->field->getValue());

        // Test password with numbers
        $this->field->setValue('password123');
        $this->assertEquals('password123', $this->field->getValue());

        // Test password with uppercase
        $this->field->setValue('Password');
        $this->assertEquals('Password', $this->field->getValue());

        // Test password with special characters
        $this->field->setValue('Pass!@#$');
        $this->assertEquals('Pass!@#$', $this->field->getValue());

        // Test very long password
        $longPassword = str_repeat('SecurePass123!', 5);
        $this->field->setValue($longPassword);
        $this->assertEquals($longPassword, $this->field->getValue());
    }

    /**
     * Test password confirmation scenarios
     */
    public function testPasswordConfirmation(): void
    {
        $metadata = [
            'name' => 'password_confirmation',
            'type' => 'Password',
            'label' => 'Confirm Password',
            'placeholder' => 'Confirm your password'
        ];

        $confirmField = new PasswordField($metadata, $this->logger);
        $this->assertEquals('Confirm Password', $confirmField->getMetadataValue('label'));
        $this->assertEquals('Confirm your password', $confirmField->getMetadataValue('placeholder'));
    }

    /**
     * Test unicode and special character passwords
     */
    public function testUnicodePasswords(): void
    {
        // Test password with unicode characters
        $unicodePassword = 'pässwörd123';
        $this->field->setValue($unicodePassword);
        $this->assertEquals($unicodePassword, $this->field->getValue());

        // Test password with emojis
        $emojiPassword = 'pass🔒word🗝️';
        $this->field->setValue($emojiPassword);
        $this->assertEquals($emojiPassword, $this->field->getValue());

        // Test password with various special characters
        $specialPassword = 'P@$$w0rd!#$%^&*()';
        $this->field->setValue($specialPassword);
        $this->assertEquals($specialPassword, $this->field->getValue());
    }

    /**
     * Test edge cases and boundary conditions
     */
    public function testEdgeCases(): void
    {
        // Test minimum length password
        $minPassword = str_repeat('a', 8);
        $this->field->setValue($minPassword);
        $this->assertEquals($minPassword, $this->field->getValue());

        // Test maximum length password
        $maxPassword = str_repeat('a', 100);
        $this->field->setValue($maxPassword);
        $this->assertEquals($maxPassword, $this->field->getValue());

        // Test whitespace in password
        $spacePassword = 'pass word 123';
        $this->field->setValue($spacePassword);
        $this->assertEquals($spacePassword, $this->field->getValue());
    }

    /**
     * Test non-string values
     */
    public function testNonStringValues(): void
    {
        // Test numeric password
        $this->field->setValue(12345678);
        $this->assertEquals(12345678, $this->field->getValue());

        // Test boolean
        $this->field->setValue(true);
        $this->assertTrue($this->field->getValue());

        // Test array (should be stored as-is)
        $arrayPassword = ['password' => 'secret'];
        $this->field->setValue($arrayPassword);
        $this->assertEquals($arrayPassword, $this->field->getValue());
    }

    /**
     * Test optional password field
     */
    public function testOptionalPasswordField(): void
    {
        $metadata = [
            'name' => 'optional_password',
            'type' => 'Password',
            'required' => false
        ];

        $field = new PasswordField($metadata, $this->logger);
        $this->assertFalse($field->isRequired());
        $this->assertFalse($field->metadataIsTrue('required'));
    }
}
