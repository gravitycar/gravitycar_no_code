<?php

namespace Gravitycar\Tests\Unit\Fields;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Fields\MultiEnumField;

/**
 * Test suite for the MultiEnumField class.
 * Tests multi-select field functionality for multiple values from predefined options.
 */
class MultiEnumFieldTest extends UnitTestCase
{
    private MultiEnumField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $metadata = [
            'name' => 'skills',
            'type' => 'MultiEnum',
            'label' => 'Skills',
            'required' => false,
            'maxLength' => 16000,
            'maxSelections' => 0,
            'minSelections' => 0,
            'options' => [
                'php' => 'PHP',
                'javascript' => 'JavaScript',
                'python' => 'Python',
                'java' => 'Java',
                'csharp' => 'C#'
            ]
        ];

        $this->field = new MultiEnumField($metadata, $this->logger);
    }

    /**
     * Test constructor and default properties
     */
    public function testConstructor(): void
    {
        $this->assertEquals('skills', $this->field->getName());
        $this->assertEquals('Skills', $this->field->getMetadataValue('label'));
        $this->assertEquals(16000, $this->field->getMetadataValue('maxLength'));
        $this->assertEquals(0, $this->field->getMetadataValue('maxSelections'));
        $this->assertEquals(0, $this->field->getMetadataValue('minSelections'));
        $this->assertEquals('MultiEnum', $this->field->getMetadataValue('type'));
    }

    /**
     * Test static options
     */
    public function testStaticOptions(): void
    {
        $expectedOptions = [
            'php' => 'PHP',
            'javascript' => 'JavaScript',
            'python' => 'Python',
            'java' => 'Java',
            'csharp' => 'C#'
        ];

        $this->assertEquals($expectedOptions, $this->field->getOptions());
    }

    /**
     * Test setting array of values
     */
    public function testArrayValues(): void
    {
        // Test setting multiple values
        $values = ['php', 'javascript', 'python'];
        $this->field->setValue($values);
        $this->assertEquals($values, $this->field->getValue());

        // Test single value in array
        $singleValue = ['java'];
        $this->field->setValue($singleValue);
        $this->assertEquals($singleValue, $this->field->getValue());

        // Test empty array
        $this->field->setValue([]);
        $this->assertEquals([], $this->field->getValue());
    }

    /**
     * Test setting string values (comma-separated)
     */
    public function testStringValues(): void
    {
        // Test comma-separated string
        $this->field->setValue('php,javascript,python');
        $this->assertEquals('php,javascript,python', $this->field->getValue());

        // Test single string value
        $this->field->setValue('java');
        $this->assertEquals('java', $this->field->getValue());

        // Test string with spaces
        $this->field->setValue('php, javascript, python');
        $this->assertEquals('php, javascript, python', $this->field->getValue());
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
     * Test min/max selections
     */
    public function testMinMaxSelections(): void
    {
        $metadata = [
            'name' => 'limited_skills',
            'type' => 'MultiEnum',
            'options' => ['skill1' => 'Skill 1', 'skill2' => 'Skill 2', 'skill3' => 'Skill 3'],
            'minSelections' => 1,
            'maxSelections' => 3
        ];

        $field = new MultiEnumField($metadata, $this->logger);
        $this->assertEquals(1, $field->getMetadataValue('minSelections'));
        $this->assertEquals(3, $field->getMetadataValue('maxSelections'));
    }

    /**
     * Test dynamic options using className and methodName
     */
    public function testDynamicOptions(): void
    {
        $metadata = [
            'name' => 'dynamic_multienum',
            'type' => 'MultiEnum',
            'className' => 'TestOptionsProvider',
            'methodName' => 'getSkillOptions'
        ];

        // Since the class doesn't exist, options should be empty
        $field = new MultiEnumField($metadata, $this->logger);
        $this->assertEquals([], $field->getOptions());
    }

    /**
     * Test options from metadata take precedence
     */
    public function testOptionsFromMetadataTakePrecedence(): void
    {
        $metadata = [
            'name' => 'priority_multienum',
            'type' => 'MultiEnum',
            'options' => [
                'option1' => 'Option 1',
                'option2' => 'Option 2'
            ],
            'className' => 'NonExistentClass',
            'methodName' => 'getOptions'
        ];

        $field = new MultiEnumField($metadata, $this->logger);
        $expectedOptions = [
            'option1' => 'Option 1',
            'option2' => 'Option 2'
        ];
        $this->assertEquals($expectedOptions, $field->getOptions());
    }

    /**
     * Test empty options
     */
    public function testEmptyOptions(): void
    {
        $metadata = [
            'name' => 'empty_multienum',
            'type' => 'MultiEnum'
        ];

        $field = new MultiEnumField($metadata, $this->logger);
        $this->assertEquals([], $field->getOptions());
    }

    /**
     * Test numeric option keys
     */
    public function testNumericOptionKeys(): void
    {
        $metadata = [
            'name' => 'numeric_multienum',
            'type' => 'MultiEnum',
            'options' => [
                1 => 'Level 1',
                2 => 'Level 2',
                3 => 'Level 3'
            ]
        ];

        $field = new MultiEnumField($metadata, $this->logger);

        // Test setting numeric values
        $field->setValue([1, 2]);
        $this->assertEquals([1, 2], $field->getValue());

        $field->setValue(['2', '3']);
        $this->assertEquals(['2', '3'], $field->getValue());
    }

    /**
     * Test mixed option key types
     */
    public function testMixedOptionKeyTypes(): void
    {
        $metadata = [
            'name' => 'mixed_multienum',
            'type' => 'MultiEnum',
            'options' => [
                'text' => 'Text Option',
                1 => 'Numeric Option',
                0 => 'Zero Option'
            ]
        ];

        $field = new MultiEnumField($metadata, $this->logger);

        $field->setValue(['text', 1, 0]);
        $this->assertEquals(['text', 1, 0], $field->getValue());
    }

    /**
     * Test default maxLength
     */
    public function testDefaultMaxLength(): void
    {
        $minimalMetadata = [
            'name' => 'simple_multienum',
            'type' => 'MultiEnum'
        ];

        $field = new MultiEnumField($minimalMetadata, $this->logger);
        $this->assertEquals(16000, $field->getMetadataValue('maxLength'));
    }

    /**
     * Test custom maxLength
     */
    public function testCustomMaxLength(): void
    {
        $metadata = [
            'name' => 'custom_multienum',
            'type' => 'MultiEnum',
            'maxLength' => 5000
        ];

        $field = new MultiEnumField($metadata, $this->logger);
        $this->assertEquals(5000, $field->getMetadataValue('maxLength'));
    }

    /**
     * Test required multienum field
     */
    public function testRequiredMultiEnumField(): void
    {
        $metadata = [
            'name' => 'required_multienum',
            'type' => 'MultiEnum',
            'required' => true,
            'options' => ['yes' => 'Yes', 'no' => 'No']
        ];

        $field = new MultiEnumField($metadata, $this->logger);
        $this->assertTrue($field->isRequired());
    }

    /**
     * Test setValueFromTrustedSource
     */
    public function testSetValueFromTrustedSource(): void
    {
        $this->field->setValueFromTrustedSource(['php', 'javascript']);
        $this->assertEquals(['php', 'javascript'], $this->field->getValue());

        $this->field->setValueFromTrustedSource('python,java');
        $this->assertEquals('python,java', $this->field->getValue());

        $this->field->setValueFromTrustedSource(null);
        $this->assertNull($this->field->getValue());
    }

    /**
     * Test boolean option values
     */
    public function testBooleanOptions(): void
    {
        $metadata = [
            'name' => 'boolean_multienum',
            'type' => 'MultiEnum',
            'options' => [
                true => 'True Option',
                false => 'False Option'
            ]
        ];

        $field = new MultiEnumField($metadata, $this->logger);

        $field->setValue([true, false]);
        $this->assertEquals([true, false], $field->getValue());
    }

    /**
     * Test JSON-style data storage
     */
    public function testJsonStyleData(): void
    {
        // Test storing as JSON string
        $jsonData = json_encode(['php', 'javascript', 'python']);
        $this->field->setValue($jsonData);
        $this->assertEquals($jsonData, $this->field->getValue());
    }

    /**
     * Test complex nested values
     */
    public function testComplexNestedValues(): void
    {
        $complexData = [
            ['skill' => 'php', 'level' => 'expert'],
            ['skill' => 'javascript', 'level' => 'intermediate']
        ];

        $this->field->setValue($complexData);
        $this->assertEquals($complexData, $this->field->getValue());
    }

    /**
     * Test with zero min/max selections (unlimited)
     */
    public function testUnlimitedSelections(): void
    {
        $metadata = [
            'name' => 'unlimited_multienum',
            'type' => 'MultiEnum',
            'options' => array_combine(range(1, 20), range(1, 20)),
            'minSelections' => 0,
            'maxSelections' => 0
        ];

        $field = new MultiEnumField($metadata, $this->logger);
        $this->assertEquals(0, $field->getMetadataValue('minSelections'));
        $this->assertEquals(0, $field->getMetadataValue('maxSelections'));
    }
}
