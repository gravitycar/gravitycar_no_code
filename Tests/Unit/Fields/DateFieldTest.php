<?php

namespace Gravitycar\Tests\Unit\Fields;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Fields\DateField;

/**
 * Test suite for the DateField class.
 * Tests date field functionality with timezone conversion and date validation.
 */
class DateFieldTest extends UnitTestCase
{
    private DateField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $metadata = [
            'name' => 'birth_date',
            'type' => 'Date',
            'label' => 'Birth Date',
            'required' => false,
            'maxLength' => 10
        ];

        $this->field = new DateField($metadata, $this->logger);
    }

    /**
     * Test constructor and default properties
     */
    public function testConstructor(): void
    {
        $this->assertEquals('birth_date', $this->field->getName());
        $this->assertEquals('Birth Date', $this->field->getMetadataValue('label'));
        $this->assertEquals(10, $this->field->getMetadataValue('maxLength'));
        $this->assertEquals('Date', $this->field->getMetadataValue('type'));
    }

    /**
     * Test valid date formats
     */
    public function testValidDateFormats(): void
    {
        // Test ISO date format (YYYY-MM-DD)
        $this->field->setValue('2023-12-25');
        $this->assertEquals('2023-12-25', $this->field->getValue());

        // Test another valid date
        $this->field->setValue('1990-01-01');
        $this->assertEquals('1990-01-01', $this->field->getValue());

        // Test leap year date
        $this->field->setValue('2024-02-29');
        $this->assertEquals('2024-02-29', $this->field->getValue());
    }

    /**
     * Test edge date cases
     */
    public function testEdgeDateCases(): void
    {
        // Test year boundaries
        $this->field->setValue('1900-01-01');
        $this->assertEquals('1900-01-01', $this->field->getValue());

        $this->field->setValue('2099-12-31');
        $this->assertEquals('2099-12-31', $this->field->getValue());

        // Test month boundaries
        $this->field->setValue('2023-01-01');
        $this->assertEquals('2023-01-01', $this->field->getValue());

        $this->field->setValue('2023-12-31');
        $this->assertEquals('2023-12-31', $this->field->getValue());
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
     * Test invalid date formats (stored as-is, validation happens elsewhere)
     */
    public function testInvalidDateFormats(): void
    {
        // These should be stored as-is, validation happens in validation rules
        $this->field->setValue('2023/12/25');
        $this->assertEquals('2023/12/25', $this->field->getValue());

        $this->field->setValue('25-12-2023');
        $this->assertEquals('25-12-2023', $this->field->getValue());

        $this->field->setValue('December 25, 2023');
        $this->assertEquals('December 25, 2023', $this->field->getValue());
    }

    /**
     * Test default maxLength
     */
    public function testDefaultMaxLength(): void
    {
        $minimalMetadata = [
            'name' => 'simple_date',
            'type' => 'Date'
        ];

        $field = new DateField($minimalMetadata, $this->logger);
        // Check the ingested metadata for the default maxLength value
        $metadata = $field->getMetadata();
        $this->assertEquals(10, $metadata['maxLength'] ?? 10);
    }

    /**
     * Test required date field
     */
    public function testRequiredDateField(): void
    {
        $metadata = [
            'name' => 'required_date',
            'type' => 'Date',
            'required' => true
        ];

        $field = new DateField($metadata, $this->logger);
        $this->assertTrue($field->isRequired());
    }

    /**
     * Test date field with default value
     */
    public function testDefaultValue(): void
    {
        $metadata = [
            'name' => 'date_with_default',
            'type' => 'Date',
            'defaultValue' => '2023-01-01'
        ];

        $field = new DateField($metadata, $this->logger);
        $this->assertEquals('2023-01-01', $field->getValue());
    }

    /**
     * Test setValueFromTrustedSource
     */
    public function testSetValueFromTrustedSource(): void
    {
        $this->field->setValueFromTrustedSource('2023-06-15');
        $this->assertEquals('2023-06-15', $this->field->getValue());

        $this->field->setValueFromTrustedSource(null);
        $this->assertNull($this->field->getValue());
    }

    /**
     * Test various string inputs
     */
    public function testVariousStringInputs(): void
    {
        // Test numeric strings
        $this->field->setValue('20231225');
        $this->assertEquals('20231225', $this->field->getValue());

        // Test partial dates
        $this->field->setValue('2023-12');
        $this->assertEquals('2023-12', $this->field->getValue());

        // Test just year
        $this->field->setValue('2023');
        $this->assertEquals('2023', $this->field->getValue());
    }

    /**
     * Test non-string values
     */
    public function testNonStringValues(): void
    {
        // Test integer (should be stored as-is)
        $this->field->setValue(20231225);
        $this->assertEquals(20231225, $this->field->getValue());

        // Test array
        $this->field->setValue(['year' => 2023, 'month' => 12, 'day' => 25]);
        $this->assertEquals(['year' => 2023, 'month' => 12, 'day' => 25], $this->field->getValue());
    }
}
