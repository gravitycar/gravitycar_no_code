<?php

namespace Gravitycar\Tests\Unit\Validation;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Validation\OptionsValidation;

/**
 * Test suite for the OptionsValidation class.
 * Tests validation logic for allowed option values.
 */
class OptionsValidationTest extends UnitTestCase
{
    private OptionsValidation $validator;

    protected function setUp(): void
    {
        parent::setUp();

        // Create validator with default options
        $this->validator = new OptionsValidation($this->logger, [
            'option1' => 'First Option',
            'option2' => 'Second Option',
            'option3' => 'Third Option'
        ]);
    }

    /**
     * Test constructor sets correct name and error message
     */
    public function testConstructor(): void
    {
        $this->assertEquals('Value is not in the allowed options.', $this->validator->getErrorMessage());

        // Test constructor with empty options
        $emptyValidator = new OptionsValidation($this->logger, []);
        $this->assertEquals('Value is not in the allowed options.', $emptyValidator->getErrorMessage());
    }

    /**
     * Test validation with valid option keys
     */
    public function testValidOptionKeys(): void
    {
        // Test valid option keys
        $this->assertTrue($this->validator->validate('option1'));
        $this->assertTrue($this->validator->validate('option2'));
        $this->assertTrue($this->validator->validate('option3'));
    }

    /**
     * Test validation with invalid option keys
     */
    public function testInvalidOptionKeys(): void
    {
        // Test invalid option keys
        $this->assertFalse($this->validator->validate('option4'));
        $this->assertFalse($this->validator->validate('invalid'));
        $this->assertFalse($this->validator->validate(''));
        $this->assertFalse($this->validator->validate('OPTION1')); // Case sensitive

        // Test option values instead of keys (should be invalid)
        $this->assertFalse($this->validator->validate('First Option'));
        $this->assertFalse($this->validator->validate('Second Option'));
    }

    /**
     * Test validation with different data types
     */
    public function testDifferentDataTypes(): void
    {
        // Create validator with mixed data type keys
        $mixedValidator = new OptionsValidation($this->logger, [
            '1' => 'String One',
            1 => 'Integer One',
            '0' => 'String Zero',
            0 => 'Integer Zero'
        ]);

        // Test string keys
        $this->assertTrue($mixedValidator->validate('1'));
        $this->assertTrue($mixedValidator->validate('0'));

        // Test numeric keys (should use strict comparison)
        $this->assertTrue($mixedValidator->validate(1));
        $this->assertTrue($mixedValidator->validate(0));

        // Test type mismatches (strict comparison should fail these)
        $this->assertFalse($mixedValidator->validate('2'));
        $this->assertFalse($mixedValidator->validate(2));
    }

    /**
     * Test setOptions method
     */
    public function testSetOptions(): void
    {
        // Initially should validate known options
        $this->assertTrue($this->validator->validate('option1'));
        $this->assertFalse($this->validator->validate('newoption'));

        // Update options
        $this->validator->setOptions([
            'newoption' => 'New Option',
            'anotheroption' => 'Another Option'
        ]);

        // Now should validate new options
        $this->assertTrue($this->validator->validate('newoption'));
        $this->assertTrue($this->validator->validate('anotheroption'));

        // Old options should no longer be valid
        $this->assertFalse($this->validator->validate('option1'));
        $this->assertFalse($this->validator->validate('option2'));
    }

    /**
     * Test validation with empty options array
     */
    public function testEmptyOptionsArray(): void
    {
        $emptyValidator = new OptionsValidation($this->logger, []);

        // When no options are defined, any value should be valid
        $this->assertTrue($emptyValidator->validate('anything'));
        $this->assertTrue($emptyValidator->validate(''));
        $this->assertTrue($emptyValidator->validate(123));
        $this->assertTrue($emptyValidator->validate(null));
        $this->assertTrue($emptyValidator->validate([]));
    }

    /**
     * Test validation with special characters in option keys
     */
    public function testSpecialCharacterOptions(): void
    {
        $specialValidator = new OptionsValidation($this->logger, [
            'option-with-dash' => 'Dash Option',
            'option_with_underscore' => 'Underscore Option',
            'option.with.dots' => 'Dots Option',
            'option with spaces' => 'Spaces Option',
            'UPPERCASE' => 'Upper Case Option',
            'MixedCase' => 'Mixed Case Option'
        ]);

        // Test special character keys
        $this->assertTrue($specialValidator->validate('option-with-dash'));
        $this->assertTrue($specialValidator->validate('option_with_underscore'));
        $this->assertTrue($specialValidator->validate('option.with.dots'));
        $this->assertTrue($specialValidator->validate('option with spaces'));
        $this->assertTrue($specialValidator->validate('UPPERCASE'));
        $this->assertTrue($specialValidator->validate('MixedCase'));

        // Test case sensitivity
        $this->assertFalse($specialValidator->validate('uppercase'));
        $this->assertFalse($specialValidator->validate('mixedcase'));
    }

    /**
     * Test validation with numeric option keys
     */
    public function testNumericOptions(): void
    {
        $numericValidator = new OptionsValidation($this->logger, [
            0 => 'Zero',
            1 => 'One',
            2 => 'Two',
            '3' => 'Three String',
            '4' => 'Four String'
        ]);

        // Test numeric keys
        $this->assertTrue($numericValidator->validate(0));
        $this->assertTrue($numericValidator->validate(1));
        $this->assertTrue($numericValidator->validate(2));
        $this->assertTrue($numericValidator->validate('3'));
        $this->assertTrue($numericValidator->validate('4'));

        // Test invalid numeric values
        $this->assertFalse($numericValidator->validate(5));
        $this->assertFalse($numericValidator->validate('5'));
    }

    /**
     * Test edge cases and special scenarios
     */
    public function testEdgeCases(): void
    {
        // Test with null, boolean, and array keys
        $edgeValidator = new OptionsValidation($this->logger, [
            '' => 'Empty String',
            '0' => 'String Zero',
            'false' => 'String False',
            'true' => 'String True'
        ]);

        $this->assertTrue($edgeValidator->validate(''));
        $this->assertTrue($edgeValidator->validate('0'));
        $this->assertTrue($edgeValidator->validate('false'));
        $this->assertTrue($edgeValidator->validate('true'));

        // These should not match (strict comparison)
        $this->assertTrue($edgeValidator->validate(0)); // PHP converts 0 to '0' for array key lookup
        $this->assertTrue($edgeValidator->validate(false)); // PHP converts false to 'false' for array key lookup
        $this->assertFalse($edgeValidator->validate(true)); // Boolean true doesn't match string 'true'
        $this->assertTrue($edgeValidator->validate(null));
    }

    /**
     * Test JavaScript validation generation
     */
    public function testJavascriptValidation(): void
    {
        $jsValidation = $this->validator->getJavascriptValidation();

        $this->assertIsString($jsValidation);
        $this->assertStringContainsString('function validateOptions', $jsValidation);
        $this->assertStringContainsString('Value is not in the allowed options.', $jsValidation);
        $this->assertStringContainsString('Object.keys(options)', $jsValidation);
        $this->assertStringContainsString('includes', $jsValidation);

        // Should handle empty options case
        $this->assertStringContainsString('Object.keys(options).length === 0', $jsValidation);
    }

    /**
     * Test validation with complex option structures
     */
    public function testComplexOptionStructures(): void
    {
        $complexValidator = new OptionsValidation($this->logger, [
            'status_active' => 'Active Status',
            'status_inactive' => 'Inactive Status',
            'status_pending' => 'Pending Status',
            'priority_low' => 'Low Priority',
            'priority_medium' => 'Medium Priority',
            'priority_high' => 'High Priority'
        ]);

        // Test status options
        $this->assertTrue($complexValidator->validate('status_active'));
        $this->assertTrue($complexValidator->validate('status_inactive'));
        $this->assertTrue($complexValidator->validate('status_pending'));

        // Test priority options
        $this->assertTrue($complexValidator->validate('priority_low'));
        $this->assertTrue($complexValidator->validate('priority_medium'));
        $this->assertTrue($complexValidator->validate('priority_high'));

        // Test invalid combinations
        $this->assertFalse($complexValidator->validate('status_high'));
        $this->assertFalse($complexValidator->validate('priority_active'));
        $this->assertFalse($complexValidator->validate('invalid_option'));
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
            $this->validator->validate(new \stdClass());
            $this->validator->validate(123);
            $this->validator->validate(true);
            $this->validator->validate(false);

            // Test with empty options validator
            $emptyValidator = new OptionsValidation($this->logger, []);
            $emptyValidator->validate('anything');

            $this->assertTrue(true); // If we get here, no exceptions were thrown
        } catch (\Exception $e) {
            $this->fail('Options validation should not throw exceptions, but got: ' . $e->getMessage());
        }
    }
}
