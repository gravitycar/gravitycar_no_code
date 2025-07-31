<?php

namespace Gravitycar\Tests\Unit\Fields;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Fields\RadioButtonSetField;

/**
 * Test suite for the RadioButtonSetField class.
 * Tests radio button set field functionality for selecting single values displayed as radio buttons.
 */
class RadioButtonSetFieldTest extends UnitTestCase
{
    private RadioButtonSetField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $metadata = [
            'name' => 'priority',
            'type' => 'RadioButtonSet',
            'label' => 'Priority',
            'required' => false,
            'defaultValue' => null,
            'layout' => 'vertical',
            'allowClear' => false,
            'clearLabel' => 'None',
            'options' => [
                'low' => 'Low Priority',
                'medium' => 'Medium Priority',
                'high' => 'High Priority',
                'urgent' => 'Urgent'
            ]
        ];

        $this->field = new RadioButtonSetField($metadata, $this->logger);
    }

    /**
     * Test constructor and default properties
     */
    public function testConstructor(): void
    {
        $this->assertEquals('priority', $this->field->getName());
        $this->assertEquals('Priority', $this->field->getMetadataValue('label'));
        $this->assertEquals('vertical', $this->field->getMetadataValue('layout'));
        $this->assertFalse($this->field->getMetadataValue('allowClear'));
        $this->assertEquals('None', $this->field->getMetadataValue('clearLabel'));
        $this->assertEquals('RadioButtonSet', $this->field->getMetadataValue('type'));
    }

    /**
     * Test static options
     */
    public function testStaticOptions(): void
    {
        $expectedOptions = [
            'low' => 'Low Priority',
            'medium' => 'Medium Priority',
            'high' => 'High Priority',
            'urgent' => 'Urgent'
        ];

        $this->assertEquals($expectedOptions, $this->field->getOptions());
    }

    /**
     * Test setting valid option values
     */
    public function testValidOptionValues(): void
    {
        // Test setting valid option keys
        $this->field->setValue('low');
        $this->assertEquals('low', $this->field->getValue());

        $this->field->setValue('medium');
        $this->assertEquals('medium', $this->field->getValue());

        $this->field->setValue('high');
        $this->assertEquals('high', $this->field->getValue());

        $this->field->setValue('urgent');
        $this->assertEquals('urgent', $this->field->getValue());
    }

    /**
     * Test setting invalid option values (should be stored as-is)
     */
    public function testInvalidOptionValues(): void
    {
        // Invalid options should be stored as-is, validation happens elsewhere
        $this->field->setValue('invalid_priority');
        $this->assertEquals('invalid_priority', $this->field->getValue());

        $this->field->setValue('nonexistent');
        $this->assertEquals('nonexistent', $this->field->getValue());
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
     * Test layout options
     */
    public function testLayoutOptions(): void
    {
        // Test horizontal layout
        $horizontalMetadata = [
            'name' => 'horizontal_radio',
            'type' => 'RadioButtonSet',
            'layout' => 'horizontal',
            'options' => ['yes' => 'Yes', 'no' => 'No']
        ];
        $horizontalField = new RadioButtonSetField($horizontalMetadata, $this->logger);
        $this->assertEquals('horizontal', $horizontalField->getMetadataValue('layout'));

        // Test inline layout
        $inlineMetadata = [
            'name' => 'inline_radio',
            'type' => 'RadioButtonSet',
            'layout' => 'inline',
            'options' => ['option1' => 'Option 1', 'option2' => 'Option 2']
        ];
        $inlineField = new RadioButtonSetField($inlineMetadata, $this->logger);
        $this->assertEquals('inline', $inlineField->getMetadataValue('layout'));
    }

    /**
     * Test allowClear functionality
     */
    public function testAllowClear(): void
    {
        // Test with allowClear enabled
        $clearableMetadata = [
            'name' => 'clearable_radio',
            'type' => 'RadioButtonSet',
            'allowClear' => true,
            'clearLabel' => 'No Selection',
            'options' => ['option1' => 'Option 1', 'option2' => 'Option 2']
        ];

        $clearableField = new RadioButtonSetField($clearableMetadata, $this->logger);
        $this->assertTrue($clearableField->getMetadataValue('allowClear'));
        $this->assertEquals('No Selection', $clearableField->getMetadataValue('clearLabel'));
    }

    /**
     * Test dynamic options using className and methodName
     */
    public function testDynamicOptions(): void
    {
        $metadata = [
            'name' => 'dynamic_radio',
            'type' => 'RadioButtonSet',
            'className' => 'TestOptionsProvider',
            'methodName' => 'getPriorityOptions'
        ];

        // Since the class doesn't exist, options should be empty
        $field = new RadioButtonSetField($metadata, $this->logger);
        $this->assertEquals([], $field->getOptions());
    }

    /**
     * Test options from metadata take precedence
     */
    public function testOptionsFromMetadataTakePrecedence(): void
    {
        $metadata = [
            'name' => 'priority_radio',
            'type' => 'RadioButtonSet',
            'options' => [
                'low' => 'Low',
                'high' => 'High'
            ],
            'className' => 'NonExistentClass',
            'methodName' => 'getOptions'
        ];

        $field = new RadioButtonSetField($metadata, $this->logger);
        $expectedOptions = [
            'low' => 'Low',
            'high' => 'High'
        ];
        $this->assertEquals($expectedOptions, $field->getOptions());
    }

    /**
     * Test empty options
     */
    public function testEmptyOptions(): void
    {
        $metadata = [
            'name' => 'empty_radio',
            'type' => 'RadioButtonSet'
        ];

        $field = new RadioButtonSetField($metadata, $this->logger);
        $this->assertEquals([], $field->getOptions());
    }

    /**
     * Test numeric option keys
     */
    public function testNumericOptionKeys(): void
    {
        $metadata = [
            'name' => 'numeric_radio',
            'type' => 'RadioButtonSet',
            'options' => [
                1 => 'Option One',
                2 => 'Option Two',
                3 => 'Option Three'
            ]
        ];

        $field = new RadioButtonSetField($metadata, $this->logger);

        // Test setting numeric values
        $field->setValue(1);
        $this->assertEquals(1, $field->getValue());

        $field->setValue('2');
        $this->assertEquals('2', $field->getValue());

        // Test getting options
        $expectedOptions = [
            1 => 'Option One',
            2 => 'Option Two',
            3 => 'Option Three'
        ];
        $this->assertEquals($expectedOptions, $field->getOptions());
    }

    /**
     * Test boolean option keys
     */
    public function testBooleanOptionKeys(): void
    {
        $metadata = [
            'name' => 'boolean_radio',
            'type' => 'RadioButtonSet',
            'layout' => 'horizontal',
            'options' => [
                true => 'Yes',
                false => 'No'
            ]
        ];

        $field = new RadioButtonSetField($metadata, $this->logger);

        $field->setValue(true);
        $this->assertTrue($field->getValue());

        $field->setValue(false);
        $this->assertFalse($field->getValue());
    }

    /**
     * Test default properties
     */
    public function testDefaultProperties(): void
    {
        $minimalMetadata = [
            'name' => 'simple_radio',
            'type' => 'RadioButtonSet'
        ];

        $field = new RadioButtonSetField($minimalMetadata, $this->logger);
        $this->assertNull($field->getMetadataValue('defaultValue'));
        $this->assertEquals('vertical', $field->getMetadataValue('layout'));
        $this->assertFalse($field->getMetadataValue('allowClear'));
        $this->assertEquals('None', $field->getMetadataValue('clearLabel'));
        $this->assertEquals([], $field->getOptions());
    }

    /**
     * Test required radio button field
     */
    public function testRequiredRadioButtonField(): void
    {
        $metadata = [
            'name' => 'required_radio',
            'type' => 'RadioButtonSet',
            'required' => true,
            'options' => ['yes' => 'Yes', 'no' => 'No']
        ];

        $field = new RadioButtonSetField($metadata, $this->logger);
        $this->assertTrue($field->isRequired());
    }

    /**
     * Test with default value
     */
    public function testDefaultValue(): void
    {
        $metadata = [
            'name' => 'radio_with_default',
            'type' => 'RadioButtonSet',
            'defaultValue' => 'medium',
            'options' => [
                'low' => 'Low',
                'medium' => 'Medium',
                'high' => 'High'
            ]
        ];

        $field = new RadioButtonSetField($metadata, $this->logger);
        $this->assertEquals('medium', $field->getValue());
    }

    /**
     * Test setValueFromTrustedSource
     */
    public function testSetValueFromTrustedSource(): void
    {
        $this->field->setValueFromTrustedSource('high');
        $this->assertEquals('high', $this->field->getValue());

        $this->field->setValueFromTrustedSource('invalid_option');
        $this->assertEquals('invalid_option', $this->field->getValue());

        $this->field->setValueFromTrustedSource(null);
        $this->assertNull($this->field->getValue());
    }

    /**
     * Test complex option structures
     */
    public function testComplexOptions(): void
    {
        $metadata = [
            'name' => 'complex_radio',
            'type' => 'RadioButtonSet',
            'layout' => 'horizontal',
            'options' => [
                'status_draft' => 'Draft',
                'status_review' => 'Under Review',
                'status_approved' => 'Approved',
                'status_published' => 'Published'
            ]
        ];

        $field = new RadioButtonSetField($metadata, $this->logger);

        $field->setValue('status_review');
        $this->assertEquals('status_review', $field->getValue());

        $options = $field->getOptions();
        $this->assertCount(4, $options);
        $this->assertEquals('Under Review', $options['status_review']);
    }

    /**
     * Test yes/no radio buttons
     */
    public function testYesNoRadioButtons(): void
    {
        $metadata = [
            'name' => 'yes_no_radio',
            'type' => 'RadioButtonSet',
            'layout' => 'horizontal',
            'options' => [
                'yes' => 'Yes',
                'no' => 'No'
            ]
        ];

        $field = new RadioButtonSetField($metadata, $this->logger);

        $field->setValue('yes');
        $this->assertEquals('yes', $field->getValue());

        $field->setValue('no');
        $this->assertEquals('no', $field->getValue());
    }
}
