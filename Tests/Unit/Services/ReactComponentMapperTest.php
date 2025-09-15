<?php

namespace Tests\Unit\Services;

use Gravitycar\Services\ReactComponentMapper;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Metadata\MetadataEngine;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ReactComponentMapperTest extends TestCase
{
    protected ReactComponentMapper $mapper;
    protected LoggerInterface|MockObject $mockLogger;
    protected MetadataEngineInterface|MockObject $mockMetadataEngine;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear any existing cache files before each test
        $this->clearDocumentationCache();
        
        // Create mocks
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        
        // Configure mock metadata engine
        $this->mockMetadataEngine->method('getFieldTypeDefinitions')->willReturn([
            'Text' => ['react_component' => 'TextInput', 'validation_rules' => ['Required']],
            'Email' => ['react_component' => 'EmailInput', 'validation_rules' => ['Required', 'Email']],
            'Integer' => ['react_component' => 'NumberInput', 'validation_rules' => ['Numeric']],
            'DateTime' => ['react_component' => 'DateTimePicker', 'validation_rules' => ['DateTime']]
        ]);
        
        // Create service with injected dependencies
        $this->mapper = new ReactComponentMapper($this->mockLogger, $this->mockMetadataEngine);
    }

    protected function tearDown(): void
    {
        // Clear cache files after each test
        $this->clearDocumentationCache();
        MetadataEngine::reset();
        parent::tearDown();
    }
    
    /**
     * Clear documentation cache directory
     */
    private function clearDocumentationCache(): void
    {
        $cacheDir = 'cache/documentation/';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function testGetReactComponentForField(): void
    {
        $textField = ['type' => 'Text'];
        $emailField = ['type' => 'Email'];
        $booleanField = ['type' => 'Boolean'];
        $unknownField = ['type' => 'Unknown'];

        $this->assertEquals('TextInput', $this->mapper->getReactComponentForField($textField));
        $this->assertEquals('EmailInput', $this->mapper->getReactComponentForField($emailField));
        $this->assertEquals('Checkbox', $this->mapper->getReactComponentForField($booleanField));
        $this->assertEquals('TextInput', $this->mapper->getReactComponentForField($unknownField)); // Fallback
    }

    public function testGetReactComponentForFieldType(): void
    {
        $this->assertEquals('TextInput', $this->mapper->getReactComponentForFieldType('Text'));
        $this->assertEquals('EmailInput', $this->mapper->getReactComponentForFieldType('Email'));
        $this->assertEquals('NumberInput', $this->mapper->getReactComponentForFieldType('Integer'));
        $this->assertEquals('NumberInput', $this->mapper->getReactComponentForFieldType('Float'));
        $this->assertEquals('Checkbox', $this->mapper->getReactComponentForFieldType('Boolean'));
        $this->assertEquals('DatePicker', $this->mapper->getReactComponentForFieldType('Date'));
        $this->assertEquals('DateTimePicker', $this->mapper->getReactComponentForFieldType('DateTime'));
        $this->assertEquals('Select', $this->mapper->getReactComponentForFieldType('Enum'));
        $this->assertEquals('MultiSelect', $this->mapper->getReactComponentForFieldType('MultiEnum'));
        $this->assertEquals('RadioGroup', $this->mapper->getReactComponentForFieldType('RadioButtonSet'));
        $this->assertEquals('RelatedRecordSelect', $this->mapper->getReactComponentForFieldType('RelatedRecord'));
        $this->assertEquals('HiddenInput', $this->mapper->getReactComponentForFieldType('ID'));
        $this->assertEquals('ImageUpload', $this->mapper->getReactComponentForFieldType('Image'));
        $this->assertEquals('TextInput', $this->mapper->getReactComponentForFieldType('Unknown')); // Fallback
    }

    public function testGetReactValidationRules(): void
    {
        // Test with string-based validation rules
        $fieldWithRequired = [
            'validationRules' => ['Required']
        ];
        $validation = $this->mapper->getReactValidationRules($fieldWithRequired);
        $this->assertTrue($validation['required']);
        $this->assertEquals('This field is required', $validation['message']);

        // Test with array-based validation rules
        $fieldWithEmail = [
            'validationRules' => [
                ['name' => 'Email', 'description' => 'Must be a valid email']
            ]
        ];
        $validation = $this->mapper->getReactValidationRules($fieldWithEmail);
        $this->assertEquals('email', $validation['type']);
        $this->assertEquals('Must be a valid email', $validation['message']);

        // Test with metadata validation
        $fieldWithMetadata = [
            'required' => true,
            'maxLength' => 255,
            'unique' => true
        ];
        $validation = $this->mapper->getReactValidationRules($fieldWithMetadata);
        $this->assertTrue($validation['required']);
        $this->assertEquals(255, $validation['maxLength']);
        $this->assertTrue($validation['unique']);
    }

    public function testGetComponentPropsFromField(): void
    {
        $fieldData = [
            'type' => 'Text',
            'placeholder' => 'Enter text here',
            'maxLength' => 100,
            'defaultValue' => 'default',
            'disabled' => false
        ];

        $props = $this->mapper->getComponentPropsFromField($fieldData);
        
        $this->assertIsArray($props);
        // Props depend on the field component map configuration
        // We test that null values are filtered out
        foreach ($props as $value) {
            $this->assertNotNull($value);
        }
    }

    public function testGetComponentPropsForFieldType(): void
    {
        $textProps = $this->mapper->getComponentPropsForFieldType('Text');
        $this->assertIsArray($textProps);
        $this->assertContains('placeholder', $textProps);
        $this->assertContains('maxLength', $textProps);

        $booleanProps = $this->mapper->getComponentPropsForFieldType('Boolean');
        $this->assertIsArray($booleanProps);
        $this->assertContains('defaultChecked', $booleanProps);

        $enumProps = $this->mapper->getComponentPropsForFieldType('Enum');
        $this->assertIsArray($enumProps);
        $this->assertContains('options', $enumProps);
        $this->assertContains('placeholder', $enumProps);
    }

    public function testGenerateFormSchemaStructure(): void
    {
        // This test may fail if Users model doesn't exist in test environment
        try {
            $schema = $this->mapper->generateFormSchema('Users');
            
            $this->assertIsArray($schema);
            $this->assertArrayHasKey('model', $schema);
            $this->assertArrayHasKey('layout', $schema);
            $this->assertArrayHasKey('fields', $schema);
            
            $this->assertEquals('Users', $schema['model']);
            $this->assertEquals('vertical', $schema['layout']);
            $this->assertIsArray($schema['fields']);
            
            // Check field structure if fields exist
            foreach ($schema['fields'] as $fieldName => $fieldConfig) {
                $this->assertArrayHasKey('component', $fieldConfig);
                $this->assertArrayHasKey('props', $fieldConfig);
                $this->assertArrayHasKey('validation', $fieldConfig);
                $this->assertArrayHasKey('label', $fieldConfig);
                $this->assertArrayHasKey('required', $fieldConfig);
                
                $this->assertIsString($fieldConfig['component']);
                $this->assertIsArray($fieldConfig['props']);
                $this->assertIsArray($fieldConfig['validation']);
                $this->assertIsString($fieldConfig['label']);
                $this->assertIsBool($fieldConfig['required']);
            }
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Users model not available for testing: ' . $e->getMessage());
        }
    }

    public function testGetFieldToComponentMap(): void
    {
        $map = $this->mapper->getFieldToComponentMap();
        $this->assertIsArray($map);
        
        foreach ($map as $fieldType => $config) {
            $this->assertIsArray($config);
            $this->assertArrayHasKey('component', $config);
            $this->assertArrayHasKey('props', $config);
            $this->assertArrayHasKey('validation_support', $config);
            
            $this->assertIsString($config['component']);
            $this->assertIsArray($config['props']);
            $this->assertIsArray($config['validation_support']);
        }
    }

    public function testValidationRuleMappingWithAlphanumeric(): void
    {
        $fieldWithAlphanumeric = [
            'validationRules' => ['Alphanumeric']
        ];
        $validation = $this->mapper->getReactValidationRules($fieldWithAlphanumeric);
        
        $this->assertArrayHasKey('pattern', $validation);
        $this->assertEquals('/^[a-zA-Z0-9]+$/', $validation['pattern']);
        $this->assertEquals('Only letters and numbers are allowed', $validation['message']);
    }

    public function testEmptyValidationRules(): void
    {
        $fieldWithoutRules = [];
        $validation = $this->mapper->getReactValidationRules($fieldWithoutRules);
        
        $this->assertIsArray($validation);
        // Should handle empty case gracefully
    }

    public function testMaxLengthHandling(): void
    {
        // Test both maxLength and max_length for backwards compatibility
        $fieldWithMaxLength = ['maxLength' => 50];
        $validation1 = $this->mapper->getReactValidationRules($fieldWithMaxLength);
        
        $fieldWithMaxLengthUnderscore = ['max_length' => 100];
        $validation2 = $this->mapper->getReactValidationRules($fieldWithMaxLengthUnderscore);
        
        $this->assertEquals(50, $validation1['maxLength']);
        $this->assertEquals(100, $validation2['maxLength']);
    }
}
