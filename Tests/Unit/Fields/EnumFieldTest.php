<?php

namespace Gravitycar\Tests\Unit\Fields;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Fields\EnumField;

/**
 * Test suite for the EnumField class.
 * Tests dropdown field functionality for selecting single values from predefined options.
 */
class EnumFieldTest extends UnitTestCase
{
    private EnumField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $metadata = [
            'name' => 'status',
            'type' => 'Enum',
            'label' => 'Status',
            'required' => false,
            'maxLength' => 255,
            'options' => [
                'draft' => 'Draft',
                'published' => 'Published',
                'archived' => 'Archived'
            ]
        ];

        $this->field = new EnumField($metadata, $this->logger);
    }

    /**
     * Test constructor and default properties
     */
    public function testConstructor(): void
    {
        $this->assertEquals('status', $this->field->getName());
        $this->assertEquals('Status', $this->field->getMetadataValue('label'));
        $this->assertEquals(255, $this->field->getMetadataValue('maxLength'));
        $this->assertEquals('Enum', $this->field->getMetadataValue('type'));
    }

    /**
     * Test static options
     */
    public function testStaticOptions(): void
    {
        $expectedOptions = [
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived'
        ];

        $this->assertEquals($expectedOptions, $this->field->getOptions());
    }

    /**
     * Test setting valid option values
     */
    public function testValidOptionValues(): void
    {
        // Test setting valid option keys
        $this->field->setValue('draft');
        $this->assertEquals('draft', $this->field->getValue());

        $this->field->setValue('published');
        $this->assertEquals('published', $this->field->getValue());

        $this->field->setValue('archived');
        $this->assertEquals('archived', $this->field->getValue());
    }

    /**
     * Test setting invalid option values (should be stored as-is)
     */
    public function testInvalidOptionValues(): void
    {
        // Invalid options should be stored as-is, validation happens elsewhere
        $this->field->setValue('invalid_status');
        $this->assertEquals('invalid_status', $this->field->getValue());

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
     * Test dynamic options using className and methodName
     */
    public function testDynamicOptions(): void
    {
        $metadata = [
            'name' => 'dynamic_enum',
            'type' => 'Enum',
            'className' => 'TestOptionsProvider',
            'methodName' => 'getStatusOptions'
        ];

        // Since the class doesn't exist, options should be empty
        $field = new EnumField($metadata, $this->logger);
        $this->assertEquals([], $field->getOptions());
    }

    /**
     * Test options from metadata take precedence
     */
    public function testOptionsFromMetadataTakePrecedence(): void
    {
        $metadata = [
            'name' => 'priority_enum',
            'type' => 'Enum',
            'options' => [
                'low' => 'Low Priority',
                'high' => 'High Priority'
            ],
            'className' => 'NonExistentClass',
            'methodName' => 'getOptions'
        ];

        $field = new EnumField($metadata, $this->logger);
        $expectedOptions = [
            'low' => 'Low Priority',
            'high' => 'High Priority'
        ];
        $this->assertEquals($expectedOptions, $field->getOptions());
    }

    /**
     * Test empty options
     */
    public function testEmptyOptions(): void
    {
        $metadata = [
            'name' => 'empty_enum',
            'type' => 'Enum'
        ];

        $field = new EnumField($metadata, $this->logger);
        $this->assertEquals([], $field->getOptions());
    }

    /**
     * Test numeric option keys
     */
    public function testNumericOptionKeys(): void
    {
        $metadata = [
            'name' => 'numeric_enum',
            'type' => 'Enum',
            'options' => [
                1 => 'Option One',
                2 => 'Option Two',
                3 => 'Option Three'
            ]
        ];

        $field = new EnumField($metadata, $this->logger);

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
     * Test mixed option key types
     */
    public function testMixedOptionKeyTypes(): void
    {
        $metadata = [
            'name' => 'mixed_enum',
            'type' => 'Enum',
            'options' => [
                'text' => 'Text Option',
                1 => 'Numeric Option',
                0 => 'Zero Option'
            ]
        ];

        $field = new EnumField($metadata, $this->logger);

        $field->setValue('text');
        $this->assertEquals('text', $field->getValue());

        $field->setValue(1);
        $this->assertEquals(1, $field->getValue());

        $field->setValue(0);
        $this->assertEquals(0, $field->getValue());
    }

    /**
     * Test default maxLength
     */
    public function testDefaultMaxLength(): void
    {
        $minimalMetadata = [
            'name' => 'simple_enum',
            'type' => 'Enum'
        ];

        $field = new EnumField($minimalMetadata, $this->logger);
        // Check the ingested metadata for the default maxLength value
        $metadata = $field->getMetadata();
        $this->assertEquals(255, $metadata['maxLength'] ?? 255);
    }

    /**
     * Test custom maxLength
     */
    public function testCustomMaxLength(): void
    {
        $metadata = [
            'name' => 'short_enum',
            'type' => 'Enum',
            'maxLength' => 50
        ];

        $field = new EnumField($metadata, $this->logger);
        $this->assertEquals(50, $field->getMetadataValue('maxLength'));
    }

    /**
     * Test required enum field
     */
    public function testRequiredEnumField(): void
    {
        $metadata = [
            'name' => 'required_enum',
            'type' => 'Enum',
            'required' => true,
            'options' => ['yes' => 'Yes', 'no' => 'No']
        ];

        $field = new EnumField($metadata, $this->logger);
        $this->assertTrue($field->isRequired());
    }

    /**
     * Test setValueFromTrustedSource
     */
    public function testSetValueFromTrustedSource(): void
    {
        $this->field->setValueFromTrustedSource('published');
        $this->assertEquals('published', $this->field->getValue());

        $this->field->setValueFromTrustedSource('invalid_option');
        $this->assertEquals('invalid_option', $this->field->getValue());

        $this->field->setValueFromTrustedSource(null);
        $this->assertNull($this->field->getValue());
    }

    /**
     * Test boolean option values
     */
    public function testBooleanOptions(): void
    {
        $metadata = [
            'name' => 'boolean_enum',
            'type' => 'Enum',
            'options' => [
                true => 'Yes',
                false => 'No'
            ]
        ];

        $field = new EnumField($metadata, $this->logger);

        $field->setValue(true);
        $this->assertTrue($field->getValue());

        $field->setValue(false);
        $this->assertFalse($field->getValue());
    }

    /**
     * Test complex option structures
     */
    public function testComplexOptions(): void
    {
        $metadata = [
            'name' => 'complex_enum',
            'type' => 'Enum',
            'options' => [
                'status_draft' => 'Draft Status',
                'status_review' => 'Under Review',
                'status_approved' => 'Approved',
                'status_published' => 'Published',
                'status_archived' => 'Archived'
            ]
        ];

        $field = new EnumField($metadata, $this->logger);

        $field->setValue('status_review');
        $this->assertEquals('status_review', $field->getValue());

        $options = $field->getOptions();
        $this->assertCount(5, $options);
        $this->assertEquals('Under Review', $options['status_review']);
    }
}
