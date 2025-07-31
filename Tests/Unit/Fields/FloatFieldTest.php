<?php

namespace Gravitycar\Tests\Unit\Fields;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Fields\FloatField;

/**
 * Test suite for the FloatField class.
 * Tests decimal number field functionality with precision control and range validation.
 */
class FloatFieldTest extends UnitTestCase
{
    private FloatField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $metadata = [
            'name' => 'price',
            'type' => 'Float',
            'label' => 'Price',
            'required' => false,
            'precision' => 2,
            'step' => 0.01,
            'allowNegative' => true,
            'minValue' => null,
            'maxValue' => null,
            'placeholder' => 'Enter a decimal number',
            'showSpinners' => true,
            'formatDisplay' => false
        ];

        $this->field = new FloatField($metadata, $this->logger);
    }

    /**
     * Test constructor and default properties
     */
    public function testConstructor(): void
    {
        $this->assertEquals('price', $this->field->getName());
        $this->assertEquals('Price', $this->field->getMetadataValue('label'));
        $this->assertEquals(2, $this->field->getMetadataValue('precision'));
        $this->assertEquals(0.01, $this->field->getMetadataValue('step'));
        $this->assertTrue($this->field->getMetadataValue('allowNegative'));
        $this->assertEquals('Enter a decimal number', $this->field->getMetadataValue('placeholder'));
        $this->assertTrue($this->field->getMetadataValue('showSpinners'));
        $this->assertFalse($this->field->getMetadataValue('formatDisplay'));
        $this->assertEquals('Float', $this->field->getMetadataValue('type'));
    }

    /**
     * Test setting float values
     */
    public function testFloatValues(): void
    {
        // Test positive float
        $this->field->setValue(123.45);
        $this->assertEquals(123.45, $this->field->getValue());

        // Test negative float
        $this->field->setValue(-67.89);
        $this->assertEquals(-67.89, $this->field->getValue());

        // Test zero
        $this->field->setValue(0.0);
        $this->assertEquals(0.0, $this->field->getValue());

        // Test small decimal
        $this->field->setValue(0.01);
        $this->assertEquals(0.01, $this->field->getValue());
    }

    /**
     * Test setting integer values
     */
    public function testIntegerValues(): void
    {
        // Integers should be stored as-is
        $this->field->setValue(100);
        $this->assertEquals(100, $this->field->getValue());

        $this->field->setValue(-50);
        $this->assertEquals(-50, $this->field->getValue());

        $this->field->setValue(0);
        $this->assertEquals(0, $this->field->getValue());
    }

    /**
     * Test string numeric values
     */
    public function testStringNumericValues(): void
    {
        // Numeric strings should be stored as-is (conversion happens in validation/display)
        $this->field->setValue('123.45');
        $this->assertEquals('123.45', $this->field->getValue());

        $this->field->setValue('-67.89');
        $this->assertEquals('-67.89', $this->field->getValue());

        $this->field->setValue('0.00');
        $this->assertEquals('0.00', $this->field->getValue());
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
     * Test precision settings
     */
    public function testPrecisionSettings(): void
    {
        // Test different precision values
        $metadata = [
            'name' => 'high_precision',
            'type' => 'Float',
            'precision' => 4
        ];

        $field = new FloatField($metadata, $this->logger);
        $this->assertEquals(4, $field->getMetadataValue('precision'));

        // Test zero precision
        $metadata['precision'] = 0;
        $field = new FloatField($metadata, $this->logger);
        $this->assertEquals(0, $field->getMetadataValue('precision'));
    }

    /**
     * Test step values
     */
    public function testStepValues(): void
    {
        $metadata = [
            'name' => 'custom_step',
            'type' => 'Float',
            'step' => 0.1
        ];

        $field = new FloatField($metadata, $this->logger);
        $this->assertEquals(0.1, $field->getMetadataValue('step'));

        // Test larger step
        $metadata['step'] = 1.0;
        $field = new FloatField($metadata, $this->logger);
        $this->assertEquals(1.0, $field->getMetadataValue('step'));
    }

    /**
     * Test negative values handling
     */
    public function testNegativeValuesHandling(): void
    {
        // Test allowing negative values
        $this->assertTrue($this->field->getMetadataValue('allowNegative'));

        // Test disallowing negative values
        $metadata = [
            'name' => 'positive_only',
            'type' => 'Float',
            'allowNegative' => false
        ];

        $field = new FloatField($metadata, $this->logger);
        $this->assertFalse($field->getMetadataValue('allowNegative'));
    }

    /**
     * Test min/max value constraints
     */
    public function testMinMaxValues(): void
    {
        $metadata = [
            'name' => 'constrained_float',
            'type' => 'Float',
            'minValue' => 0.0,
            'maxValue' => 100.0
        ];

        $field = new FloatField($metadata, $this->logger);
        $this->assertEquals(0.0, $field->getMetadataValue('minValue'));
        $this->assertEquals(100.0, $field->getMetadataValue('maxValue'));
    }

    /**
     * Test default properties
     */
    public function testDefaultProperties(): void
    {
        $minimalMetadata = [
            'name' => 'simple_float',
            'type' => 'Float'
        ];

        $field = new FloatField($minimalMetadata, $this->logger);
        // Check the ingested metadata for default values
        $metadata = $field->getMetadata();
        $this->assertEquals(2, $metadata['precision'] ?? 2);
        $this->assertEquals(0.01, $metadata['step'] ?? 0.01);
        $this->assertTrue($metadata['allowNegative'] ?? true);
        $this->assertNull($metadata['minValue'] ?? null);
        $this->assertNull($metadata['maxValue'] ?? null);
        $this->assertEquals('Enter a decimal number', $metadata['placeholder'] ?? 'Enter a decimal number');
        $this->assertTrue($metadata['showSpinners'] ?? true);
        $this->assertFalse($metadata['formatDisplay'] ?? false);
    }

    /**
     * Test required float field
     */
    public function testRequiredFloatField(): void
    {
        $metadata = [
            'name' => 'required_float',
            'type' => 'Float',
            'required' => true
        ];

        $field = new FloatField($metadata, $this->logger);
        $this->assertTrue($field->isRequired());
    }

    /**
     * Test default value
     */
    public function testDefaultValue(): void
    {
        $metadata = [
            'name' => 'float_with_default',
            'type' => 'Float',
            'defaultValue' => 99.99
        ];

        $field = new FloatField($metadata, $this->logger);
        $this->assertEquals(99.99, $field->getValue());
    }

    /**
     * Test setValueFromTrustedSource
     */
    public function testSetValueFromTrustedSource(): void
    {
        $this->field->setValueFromTrustedSource(456.78);
        $this->assertEquals(456.78, $this->field->getValue());

        $this->field->setValueFromTrustedSource('123.45');
        $this->assertEquals('123.45', $this->field->getValue());

        $this->field->setValueFromTrustedSource(null);
        $this->assertNull($this->field->getValue());
    }

    /**
     * Test very small and large numbers
     */
    public function testExtremeValues(): void
    {
        // Test very small positive number
        $this->field->setValue(0.00001);
        $this->assertEquals(0.00001, $this->field->getValue());

        // Test very small negative number
        $this->field->setValue(-0.00001);
        $this->assertEquals(-0.00001, $this->field->getValue());

        // Test large number
        $this->field->setValue(999999.99);
        $this->assertEquals(999999.99, $this->field->getValue());

        // Test large negative number
        $this->field->setValue(-999999.99);
        $this->assertEquals(-999999.99, $this->field->getValue());
    }

    /**
     * Test scientific notation
     */
    public function testScientificNotation(): void
    {
        // Scientific notation should be stored as-is
        $this->field->setValue('1.23e-4');
        $this->assertEquals('1.23e-4', $this->field->getValue());

        $this->field->setValue('5.67E+3');
        $this->assertEquals('5.67E+3', $this->field->getValue());
    }

    /**
     * Test non-numeric values
     */
    public function testNonNumericValues(): void
    {
        // Non-numeric values should be stored as-is (validation happens elsewhere)
        $this->field->setValue('not-a-number');
        $this->assertEquals('not-a-number', $this->field->getValue());

        $this->field->setValue([1.23, 4.56]);
        $this->assertEquals([1.23, 4.56], $this->field->getValue());

        $this->field->setValue(true);
        $this->assertTrue($this->field->getValue());
    }

    /**
     * Test display formatting options
     */
    public function testDisplayFormatting(): void
    {
        $metadata = [
            'name' => 'formatted_float',
            'type' => 'Float',
            'formatDisplay' => true
        ];

        $field = new FloatField($metadata, $this->logger);
        $this->assertTrue($field->getMetadataValue('formatDisplay'));
    }
}
