<?php
namespace Tests\Unit\Fields;

use PHPUnit\Framework\TestCase;
use Gravitycar\Fields\FieldBase;
use Gravitycar\Validation\ValidationRuleBase;
use Monolog\Logger;

/**
 * Mock field class for testing setValue validation fix
 */
class TestField extends FieldBase {
    
    public function validate($model = null): bool {
        // Clear previous validation errors
        $this->validationErrors = [];
        
        // Simple validation: value cannot be "invalid"
        if ($this->value === 'invalid') {
            $this->registerValidationError('Value cannot be "invalid"');
            return false;
        }
        
        return true;
    }
}

/**
 * Unit tests for FieldBase setValue validation fix
 */
class FieldBaseSetValueTest extends TestCase {

    public function testSetValueWithValidValue(): void {
        $metadata = [
            'name' => 'test_field',
            'type' => 'string'
        ];
        
        $field = new TestField($metadata);
        
        // Test that valid values are set
        $field->setValue('valid_value');
        $this->assertEquals('valid_value', $field->getValue());
        $this->assertEmpty($field->getValidationErrors());
    }

    public function testSetValueWithInvalidValueDoesNotSetValue(): void {
        $metadata = [
            'name' => 'test_field',
            'type' => 'string'
        ];
        
        $field = new TestField($metadata);
        
        // Set an initial valid value
        $field->setValue('initial_value');
        $this->assertEquals('initial_value', $field->getValue());
        
        // Try to set an invalid value
        $field->setValue('invalid');
        
        // Value should remain the original value, not the invalid one
        $this->assertEquals('initial_value', $field->getValue());
        $this->assertNotEquals('invalid', $field->getValue());
        
        // Validation errors should be present
        $validationErrors = $field->getValidationErrors();
        $this->assertNotEmpty($validationErrors);
        $this->assertContains('Value cannot be "invalid"', $validationErrors);
    }

    public function testSetValueFromTrustedSourceBypassesValidation(): void {
        $metadata = [
            'name' => 'test_field',
            'type' => 'string'
        ];
        
        $field = new TestField($metadata);
        
        // setValueFromTrustedSource should bypass validation
        $field->setValueFromTrustedSource('invalid');
        
        // Value should be set even though it would fail validation
        $this->assertEquals('invalid', $field->getValue());
        
        // No validation errors should be present since validation was bypassed
        $this->assertEmpty($field->getValidationErrors());
    }

    public function testSetValueValidationIsCalledAndErrorsAreRegistered(): void {
        $metadata = [
            'name' => 'test_field',
            'type' => 'string'
        ];
        
        $field = new TestField($metadata);
        
        // Set an initial valid value
        $field->setValue('valid');
        $this->assertEmpty($field->getValidationErrors());
        
        // Try to set an invalid value
        $field->setValue('invalid');
        
        // Validation errors should be present
        $validationErrors = $field->getValidationErrors();
        $this->assertNotEmpty($validationErrors);
        $this->assertEquals(['Value cannot be "invalid"'], $validationErrors);
    }

    public function testMultipleInvalidSetValueAttempts(): void {
        $metadata = [
            'name' => 'test_field',
            'type' => 'string'
        ];
        
        $field = new TestField($metadata);
        
        // Set an initial valid value
        $field->setValue('initial');
        $this->assertEquals('initial', $field->getValue());
        
        // Try multiple invalid values
        $field->setValue('invalid');
        $this->assertEquals('initial', $field->getValue());
        
        $field->setValue('invalid');
        $this->assertEquals('initial', $field->getValue());
        
        // Finally set a valid value
        $field->setValue('final_valid');
        $this->assertEquals('final_valid', $field->getValue());
        $this->assertEmpty($field->getValidationErrors());
    }
}
