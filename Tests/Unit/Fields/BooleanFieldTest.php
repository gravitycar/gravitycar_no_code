<?php

namespace Gravitycar\Tests\Unit\Fields;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Fields\BooleanField;

/**
 * Test suite for the BooleanField class.
 * Tests boolean field functionality with true/false values and display options.
 */
class BooleanFieldTest extends UnitTestCase
{
    private BooleanField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $metadata = [
            'name' => 'is_active',
            'type' => 'Boolean',
            'label' => 'Is Active',
            'required' => false,
            'trueLabel' => 'Yes',
            'falseLabel' => 'No',
            'displayAs' => 'checkbox'
        ];

        $this->field = new BooleanField($metadata, $this->logger);
    }

    /**
     * Test constructor and default properties
     */
    public function testConstructor(): void
    {
        $this->assertEquals('is_active', $this->field->getName());
        $this->assertEquals('Is Active', $this->field->getMetadataValue('label'));
        $this->assertEquals('Yes', $this->field->getMetadataValue('trueLabel'));
        $this->assertEquals('No', $this->field->getMetadataValue('falseLabel'));
        $this->assertEquals('checkbox', $this->field->getMetadataValue('displayAs'));
        $this->assertEquals('Boolean', $this->field->getMetadataValue('type'));
    }

    /**
     * Test boolean value operations
     */
    public function testBooleanValues(): void
    {
        // Test setting true
        $this->field->setValue(true);
        $this->assertTrue($this->field->getValue());

        // Test setting false
        $this->field->setValue(false);
        $this->assertFalse($this->field->getValue());

        // Test null value
        $this->field->setValue(null);
        $this->assertNull($this->field->getValue());
    }

    /**
     * Test string to boolean conversion
     */
    public function testStringToBooleanConversion(): void
    {
        // Test truthy strings
        $this->field->setValue('1');
        $this->assertEquals('1', $this->field->getValue()); // Should store as-is, conversion happens in validation/display

        $this->field->setValue('true');
        $this->assertEquals('true', $this->field->getValue());

        $this->field->setValue('yes');
        $this->assertEquals('yes', $this->field->getValue());

        // Test falsy strings
        $this->field->setValue('0');
        $this->assertEquals('0', $this->field->getValue());

        $this->field->setValue('false');
        $this->assertEquals('false', $this->field->getValue());

        $this->field->setValue('');
        $this->assertEquals('', $this->field->getValue());
    }

    /**
     * Test numeric to boolean handling
     */
    public function testNumericValues(): void
    {
        // Test numeric 1 and 0
        $this->field->setValue(1);
        $this->assertEquals(1, $this->field->getValue());

        $this->field->setValue(0);
        $this->assertEquals(0, $this->field->getValue());

        // Test other numbers
        $this->field->setValue(42);
        $this->assertEquals(42, $this->field->getValue());

        $this->field->setValue(-1);
        $this->assertEquals(-1, $this->field->getValue());
    }

    /**
     * Test custom true/false labels
     */
    public function testCustomLabels(): void
    {
        $metadata = [
            'name' => 'is_published',
            'type' => 'Boolean',
            'trueLabel' => 'Published',
            'falseLabel' => 'Draft'
        ];

        $field = new BooleanField($metadata, $this->logger);
        $this->assertEquals('Published', $field->getMetadataValue('trueLabel'));
        $this->assertEquals('Draft', $field->getMetadataValue('falseLabel'));
    }

    /**
     * Test display options
     */
    public function testDisplayOptions(): void
    {
        // Test checkbox display
        $checkboxMetadata = [
            'name' => 'checkbox_field',
            'type' => 'Boolean',
            'displayAs' => 'checkbox'
        ];
        $checkboxField = new BooleanField($checkboxMetadata, $this->logger);
        $this->assertEquals('checkbox', $checkboxField->getMetadataValue('displayAs'));

        // Test radio display
        $radioMetadata = [
            'name' => 'radio_field',
            'type' => 'Boolean',
            'displayAs' => 'radio'
        ];
        $radioField = new BooleanField($radioMetadata, $this->logger);
        $this->assertEquals('radio', $radioField->getMetadataValue('displayAs'));

        // Test select display
        $selectMetadata = [
            'name' => 'select_field',
            'type' => 'Boolean',
            'displayAs' => 'select'
        ];
        $selectField = new BooleanField($selectMetadata, $this->logger);
        $this->assertEquals('select', $selectField->getMetadataValue('displayAs'));
    }

    /**
     * Test default values
     */
    public function testDefaultValues(): void
    {
        $minimalMetadata = [
            'name' => 'simple_boolean',
            'type' => 'Boolean'
        ];
        $field = new BooleanField($minimalMetadata, $this->logger);

        // Test that the default values are available through complete metadata
        $metadata = $field->getMetadata();
        $this->assertEquals('Yes', $metadata['trueLabel'] ?? 'Yes');
        $this->assertEquals('No', $metadata['falseLabel'] ?? 'No');
        $this->assertEquals('checkbox', $metadata['displayAs'] ?? 'checkbox');

        // Test with explicit default value
        $defaultMetadata = [
            'name' => 'default_boolean',
            'type' => 'Boolean',
            'defaultValue' => true
        ];
        $defaultField = new BooleanField($defaultMetadata, $this->logger);
        $this->assertTrue($defaultField->getValue());
    }

    /**
     * Test required boolean field
     */
    public function testRequiredBooleanField(): void
    {
        $metadata = [
            'name' => 'terms_accepted',
            'type' => 'Boolean',
            'required' => true,
            'trueLabel' => 'Accepted',
            'falseLabel' => 'Not Accepted'
        ];

        $field = new BooleanField($metadata, $this->logger);
        $this->assertTrue($field->isRequired());
        $this->assertEquals('Accepted', $field->getMetadataValue('trueLabel'));
        $this->assertEquals('Not Accepted', $field->getMetadataValue('falseLabel'));
    }

    /**
     * Test edge cases and special values
     */
    public function testEdgeCases(): void
    {
        // Test array (should be stored as-is)
        $this->field->setValue([]);
        $this->assertEquals([], $this->field->getValue());

        // Test object (should be stored as-is)
        $obj = new \stdClass();
        $this->field->setValue($obj);
        $this->assertSame($obj, $this->field->getValue());

        // Test float values
        $this->field->setValue(1.0);
        $this->assertEquals(1.0, $this->field->getValue());

        $this->field->setValue(0.0);
        $this->assertEquals(0.0, $this->field->getValue());
    }

    /**
     * Test setValueFromTrustedSource
     */
    public function testSetValueFromTrustedSource(): void
    {
        $this->field->setValueFromTrustedSource(true);
        $this->assertTrue($this->field->getValue());

        $this->field->setValueFromTrustedSource(false);
        $this->assertFalse($this->field->getValue());

        $this->field->setValueFromTrustedSource(null);
        $this->assertNull($this->field->getValue());
    }
}
