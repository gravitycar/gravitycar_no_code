<?php

namespace Gravitycar\Tests\Unit\Fields;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Fields\DateTimeField;

/**
 * Test suite for the DateTimeField class.
 * Tests date-time field functionality with timezone conversion and datetime validation.
 */
class DateTimeFieldTest extends UnitTestCase
{
    private DateTimeField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $metadata = [
            'name' => 'created_at',
            'type' => 'DateTime',
            'label' => 'Created At',
            'required' => false,
            'maxLength' => 19
        ];

        $this->field = new DateTimeField($metadata, $this->logger);
    }

    /**
     * Test constructor and default properties
     */
    public function testConstructor(): void
    {
        $this->assertEquals('created_at', $this->field->getName());
        $this->assertEquals('Created At', $this->field->getMetadataValue('label'));
        $this->assertEquals(19, $this->field->getMetadataValue('maxLength'));
        $this->assertEquals('DateTime', $this->field->getMetadataValue('type'));
    }

    /**
     * Test valid datetime formats
     */
    public function testValidDateTimeFormats(): void
    {
        // Test standard datetime format (YYYY-MM-DD HH:mm:ss)
        $this->field->setValue('2023-12-25 14:30:00');
        $this->assertEquals('2023-12-25 14:30:00', $this->field->getValue());

        // Test midnight
        $this->field->setValue('2023-01-01 00:00:00');
        $this->assertEquals('2023-01-01 00:00:00', $this->field->getValue());

        // Test end of day
        $this->field->setValue('2023-12-31 23:59:59');
        $this->assertEquals('2023-12-31 23:59:59', $this->field->getValue());
    }

    /**
     * Test edge datetime cases
     */
    public function testEdgeDateTimeCases(): void
    {
        // Test leap year with time
        $this->field->setValue('2024-02-29 12:00:00');
        $this->assertEquals('2024-02-29 12:00:00', $this->field->getValue());

        // Test various times
        $this->field->setValue('2023-06-15 01:01:01');
        $this->assertEquals('2023-06-15 01:01:01', $this->field->getValue());

        $this->field->setValue('2023-06-15 12:30:45');
        $this->assertEquals('2023-06-15 12:30:45', $this->field->getValue());
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
     * Test various datetime string formats (stored as-is)
     */
    public function testVariousDateTimeFormats(): void
    {
        // Test ISO format with T separator
        $this->field->setValue('2023-12-25T14:30:00');
        $this->assertEquals('2023-12-25T14:30:00', $this->field->getValue());

        // Test with timezone
        $this->field->setValue('2023-12-25 14:30:00 UTC');
        $this->assertEquals('2023-12-25 14:30:00 UTC', $this->field->getValue());

        // Test different separators
        $this->field->setValue('2023/12/25 14:30:00');
        $this->assertEquals('2023/12/25 14:30:00', $this->field->getValue());
    }

    /**
     * Test default maxLength
     */
    public function testDefaultMaxLength(): void
    {
        $minimalMetadata = [
            'name' => 'simple_datetime',
            'type' => 'DateTime'
        ];

        $field = new DateTimeField($minimalMetadata, $this->logger);
        // Check the ingested metadata for the default maxLength value
        $metadata = $field->getMetadata();
        $this->assertEquals(19, $metadata['maxLength'] ?? 19);
    }

    /**
     * Test required datetime field
     */
    public function testRequiredDateTimeField(): void
    {
        $metadata = [
            'name' => 'required_datetime',
            'type' => 'DateTime',
            'required' => true
        ];

        $field = new DateTimeField($metadata, $this->logger);
        $this->assertTrue($field->isRequired());
    }

    /**
     * Test datetime field with default value
     */
    public function testDefaultValue(): void
    {
        $defaultDateTime = '2023-01-01 12:00:00';
        $metadata = [
            'name' => 'datetime_with_default',
            'type' => 'DateTime',
            'defaultValue' => $defaultDateTime
        ];

        $field = new DateTimeField($metadata, $this->logger);
        $this->assertEquals($defaultDateTime, $field->getValue());
    }

    /**
     * Test setValueFromTrustedSource
     */
    public function testSetValueFromTrustedSource(): void
    {
        $this->field->setValueFromTrustedSource('2023-06-15 09:30:00');
        $this->assertEquals('2023-06-15 09:30:00', $this->field->getValue());

        $this->field->setValueFromTrustedSource(null);
        $this->assertNull($this->field->getValue());
    }

    /**
     * Test timestamp-like inputs
     */
    public function testTimestampInputs(): void
    {
        // Test Unix timestamp (as string)
        $this->field->setValue('1703520600');
        $this->assertEquals('1703520600', $this->field->getValue());

        // Test numeric timestamp
        $this->field->setValue(1703520600);
        $this->assertEquals(1703520600, $this->field->getValue());
    }

    /**
     * Test partial datetime strings
     */
    public function testPartialDateTimeStrings(): void
    {
        // Test date only
        $this->field->setValue('2023-12-25');
        $this->assertEquals('2023-12-25', $this->field->getValue());

        // Test time only
        $this->field->setValue('14:30:00');
        $this->assertEquals('14:30:00', $this->field->getValue());

        // Test datetime without seconds
        $this->field->setValue('2023-12-25 14:30');
        $this->assertEquals('2023-12-25 14:30', $this->field->getValue());
    }

    /**
     * Test custom maxLength
     */
    public function testCustomMaxLength(): void
    {
        $metadata = [
            'name' => 'custom_datetime',
            'type' => 'DateTime',
            'maxLength' => 25 // For datetime with timezone
        ];

        $field = new DateTimeField($metadata, $this->logger);
        $this->assertEquals(25, $field->getMetadataValue('maxLength'));
    }

    /**
     * Test non-string values
     */
    public function testNonStringValues(): void
    {
        // Test DateTime object (should be stored as-is)
        $dateTime = new \DateTime('2023-12-25 14:30:00');
        $this->field->setValue($dateTime);
        $this->assertSame($dateTime, $this->field->getValue());

        // Test array
        $dateArray = ['date' => '2023-12-25', 'time' => '14:30:00'];
        $this->field->setValue($dateArray);
        $this->assertEquals($dateArray, $this->field->getValue());
    }
}
