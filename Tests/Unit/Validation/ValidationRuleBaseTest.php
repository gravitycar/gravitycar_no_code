<?php

namespace Gravitycar\Tests\Unit\Validation;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Validation\ValidationRuleBase;
use Gravitycar\Fields\FieldBase;
use Gravitycar\Models\ModelBase;

/**
 * Test suite for the ValidationRuleBase abstract class.
 * Tests base functionality used by all validation rules.
 */
class ValidationRuleBaseTest extends UnitTestCase
{
    private TestableValidationRule $validator;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a testable concrete implementation of ValidationRuleBase
        $this->validator = new TestableValidationRule('TestRule', 'Test error message');
    }

    /**
     * Test constructor and basic property setting
     */
    public function testConstructor(): void
    {
        $validator = new TestableValidationRule('CustomName', 'Custom error');

        $this->assertEquals('Custom error', $validator->getErrorMessage());

        // Test default constructor
        $defaultValidator = new TestableValidationRule();
        $this->assertIsString($defaultValidator->getErrorMessage());
    }

    /**
     * Test setValue and getValue functionality
     */
    public function testSetValue(): void
    {
        $this->validator->setValue('test value');
        $this->assertEquals('test value', $this->validator->getTestValue());

        $this->validator->setValue(123);
        $this->assertEquals(123, $this->validator->getTestValue());

        $this->validator->setValue(null);
        $this->assertNull($this->validator->getTestValue());
    }

    /**
     * Test field association
     */
    public function testSetField(): void
    {
        $field = $this->createMock(FieldBase::class);
        $field->method('getName')->willReturn('test_field');

        $this->validator->setField($field);
        $this->assertSame($field, $this->validator->getTestField());
    }

    /**
     * Test model association
     */
    public function testSetModel(): void
    {
        $model = $this->createMock(ModelBase::class);

        $this->validator->setModel($model);
        $this->assertSame($model, $this->validator->getTestModel());
    }

    /**
     * Test error message formatting with field placeholders
     */
    public function testGetFormatErrorMessage(): void
    {
        // Test with field placeholder
        $validator = new TestableValidationRule('Test', 'Field {fieldName} is invalid');

        $field = $this->createMock(FieldBase::class);
        $field->method('getName')->willReturn('username');
        $validator->setField($field);

        $formatted = $validator->getFormatErrorMessage();
        $this->assertEquals('Field username is invalid', $formatted);
    }

    /**
     * Test error message formatting with value placeholders
     */
    public function testGetFormatErrorMessageWithValue(): void
    {
        $validator = new TestableValidationRule('Test', 'Value {value} is not allowed');
        $validator->setValue('badvalue');

        $formatted = $validator->getFormatErrorMessage();
        $this->assertEquals('Value badvalue is not allowed', $formatted);
    }

    /**
     * Test error message formatting with both field and value placeholders
     */
    public function testGetFormatErrorMessageWithFieldAndValue(): void
    {
        $validator = new TestableValidationRule('Test', 'Field {fieldName} cannot have value {value}');

        $field = $this->createMock(FieldBase::class);
        $field->method('getName')->willReturn('status');
        $validator->setField($field);
        $validator->setValue('invalid');

        $formatted = $validator->getFormatErrorMessage();
        $this->assertEquals('Field status cannot have value invalid', $formatted);
    }

    /**
     * Test shouldValidateValue method with various inputs
     */
    public function testShouldValidateValue(): void
    {
        // Test normal values
        $this->assertTrue($this->validator->testShouldValidateValue('test'));
        $this->assertTrue($this->validator->testShouldValidateValue(123));
        $this->assertTrue($this->validator->testShouldValidateValue(['array']));

        // Test null and empty values
        $this->assertFalse($this->validator->testShouldValidateValue(null));
        $this->assertFalse($this->validator->testShouldValidateValue(''));
    }

    /**
     * Test shouldValidateValue with skipIfEmpty setting
     */
    public function testShouldValidateValueWithSkipIfEmpty(): void
    {
        $validator = new TestableValidationRule();
        $validator->setSkipIfEmpty(true);

        // Should skip validation for empty values
        $this->assertFalse($validator->testShouldValidateValue(''));
        $this->assertFalse($validator->testShouldValidateValue([]));
        $this->assertFalse($validator->testShouldValidateValue(null));

        // Should still validate non-empty values
        $this->assertTrue($validator->testShouldValidateValue('test'));
        $this->assertTrue($validator->testShouldValidateValue(123));
    }

    /**
     * Test isApplicable method functionality
     */
    public function testIsApplicable(): void
    {
        $field = $this->createMock(FieldBase::class);
        $model = $this->createMock(ModelBase::class);

        // Should be applicable by default
        $this->assertTrue($this->validator->isApplicable('test', $field, $model));

        // Should not be applicable when disabled
        $this->validator->setEnabled(false);
        $this->assertFalse($this->validator->isApplicable('test', $field, $model));
    }

    /**
     * Test isApplicable with skipIfEmpty setting
     */
    public function testIsApplicableWithSkipIfEmpty(): void
    {
        $field = $this->createMock(FieldBase::class);
        $validator = new TestableValidationRule();
        $validator->setSkipIfEmpty(true);

        // Should not be applicable for empty values when skipIfEmpty is true
        $this->assertFalse($validator->isApplicable('', $field));
        $this->assertFalse($validator->isApplicable(null, $field));

        // Should be applicable for non-empty values
        $this->assertTrue($validator->isApplicable('test', $field));
    }

    /**
     * Test default JavaScript validation
     */
    public function testGetJavascriptValidation(): void
    {
        $js = $this->validator->getJavascriptValidation();
        $this->assertEquals('', $js);
    }
}

/**
 * Testable concrete implementation of ValidationRuleBase for testing
 */
class TestableValidationRule extends ValidationRuleBase
{
    public function validate($value, $model = null): bool
    {
        return true; // Always valid for testing
    }

    // Expose protected properties and methods for testing
    public function getTestValue()
    {
        return $this->value;
    }

    public function getTestField(): ?FieldBase
    {
        return $this->field;
    }

    public function getTestModel(): ?ModelBase
    {
        return $this->model;
    }

    public function testShouldValidateValue($value): bool
    {
        return $this->shouldValidateValue($value);
    }

    public function setEnabled(bool $enabled): void
    {
        $this->isEnabled = $enabled;
    }

    public function setSkipIfEmpty(bool $skip): void
    {
        $this->skipIfEmpty = $skip;
    }
}
