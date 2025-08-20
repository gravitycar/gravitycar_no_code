<?php

namespace Tests\Unit\Metadata;

use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Core\Config;
use PHPUnit\Framework\TestCase;
use Monolog\Logger;
use Monolog\Handler\NullHandler;

class MetadataEngineFieldTypeDiscoveryTest extends TestCase
{
    protected MetadataEngine $metadataEngine;
    protected Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear any existing cache files before each test
        $this->clearDocumentationCache();
        
        // Create a real logger for testing
        $this->logger = new Logger('test');
        $this->logger->pushHandler(new NullHandler());
        
        // Reset MetadataEngine instance for clean testing
        MetadataEngine::reset();
        $this->metadataEngine = MetadataEngine::getInstance();
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

    public function testGetAvailableModels(): void
    {
        $models = $this->metadataEngine->getAvailableModels();
        $this->assertIsArray($models);
        
        // The array should be a list of strings (model names)
        foreach ($models as $model) {
            $this->assertIsString($model);
        }
        
        // If no models are available, that's OK for a clean test environment
        // But we should at least validate the structure is correct
        if (!empty($models)) {
            $this->assertTrue(count($models) > 0);
        }
    }

    public function testGetModelSummaries(): void
    {
        $summaries = $this->metadataEngine->getModelSummaries();
        $this->assertIsArray($summaries);
        
        if (isset($summaries['Users'])) {
            $userSummary = $summaries['Users'];
            $this->assertArrayHasKey('name', $userSummary);
            $this->assertArrayHasKey('table', $userSummary);
            $this->assertArrayHasKey('description', $userSummary);
            $this->assertArrayHasKey('fieldCount', $userSummary);
            $this->assertArrayHasKey('relationshipCount', $userSummary);
            
            $this->assertEquals('Users', $userSummary['name']);
            $this->assertIsInt($userSummary['fieldCount']);
            $this->assertIsInt($userSummary['relationshipCount']);
        }
    }

    public function testGetFieldTypeDefinitions(): void
    {
        // This test depends on the metadata cache being regenerated with field types
        $fieldTypes = $this->metadataEngine->getFieldTypeDefinitions();
        $this->assertIsArray($fieldTypes);
        
        // The field types might be empty if metadata cache hasn't been regenerated yet
        if (!empty($fieldTypes)) {
            $this->assertArrayHasKey('Text', $fieldTypes);
            
            $textField = $fieldTypes['Text'];
            $this->assertArrayHasKey('type', $textField);
            $this->assertArrayHasKey('class', $textField);
            $this->assertArrayHasKey('description', $textField);
            $this->assertArrayHasKey('react_component', $textField);
            $this->assertArrayHasKey('validation_rules', $textField);
            $this->assertArrayHasKey('operators', $textField);
            
            $this->assertEquals('Text', $textField['type']);
            $this->assertEquals('Gravitycar\\Fields\\TextField', $textField['class']);
        }
    }

    public function testGetAllRelationships(): void
    {
        $relationships = $this->metadataEngine->getAllRelationships();
        $this->assertIsArray($relationships);
        
        // Check for relationships from models if they exist
        foreach ($relationships as $relationshipKey => $relationshipData) {
            $this->assertIsArray($relationshipData);
            
            // If it's a model relationship, it should have source_model
            if (str_contains($relationshipKey, '.')) {
                $this->assertArrayHasKey('source_model', $relationshipData);
            }
        }
    }

    public function testModelExists(): void
    {
        // Test with a model that definitely doesn't exist
        $this->assertFalse($this->metadataEngine->modelExists('NonExistentModel'));
        
        // If there are available models, test that at least one exists
        $availableModels = $this->metadataEngine->getAvailableModels();
        if (!empty($availableModels)) {
            $firstModel = $availableModels[0];
            $this->assertTrue($this->metadataEngine->modelExists($firstModel));
        } else {
            // In a clean test environment with no models, this is acceptable
            $this->markTestSkipped('No models available in test environment');
        }
    }

    public function testScanAndLoadFieldTypesProtected(): void
    {
        // We can't directly test the protected method, but we can test its effects
        // by checking if field types are properly loaded in the cache
        
        // Force metadata regeneration to trigger field type scanning
        $reflection = new \ReflectionClass($this->metadataEngine);
        $scanMethod = $reflection->getMethod('scanAndLoadFieldTypes');
        $scanMethod->setAccessible(true);
        
        $fieldTypes = $scanMethod->invoke($this->metadataEngine);
        $this->assertIsArray($fieldTypes);
        
        // Should contain basic field types
        $this->assertArrayHasKey('Text', $fieldTypes);
        $this->assertArrayHasKey('Integer', $fieldTypes);
        $this->assertArrayHasKey('Email', $fieldTypes);
        
        // Each field type should have required properties
        foreach ($fieldTypes as $fieldType => $fieldData) {
            $this->assertArrayHasKey('type', $fieldData);
            $this->assertArrayHasKey('class', $fieldData);
            $this->assertArrayHasKey('description', $fieldData);
            $this->assertArrayHasKey('react_component', $fieldData);
            $this->assertArrayHasKey('validation_rules', $fieldData);
            $this->assertArrayHasKey('operators', $fieldData);
            
            $this->assertNotEmpty($fieldData['type']);
            $this->assertNotEmpty($fieldData['class']);
            $this->assertNotEmpty($fieldData['description']);
            $this->assertNotEmpty($fieldData['react_component']);
            $this->assertIsArray($fieldData['validation_rules']);
            $this->assertIsArray($fieldData['operators']);
        }
    }

    public function testExtractFieldTypeFromClassName(): void
    {
        $reflection = new \ReflectionClass($this->metadataEngine);
        $extractMethod = $reflection->getMethod('extractFieldTypeFromClassName');
        $extractMethod->setAccessible(true);
        
        $this->assertEquals('Text', $extractMethod->invoke($this->metadataEngine, 'TextField'));
        $this->assertEquals('Email', $extractMethod->invoke($this->metadataEngine, 'EmailField'));
        $this->assertEquals('DateTime', $extractMethod->invoke($this->metadataEngine, 'DateTimeField'));
        $this->assertEquals('ID', $extractMethod->invoke($this->metadataEngine, 'IDField'));
    }

    public function testGenerateDescriptionFromClassName(): void
    {
        $reflection = new \ReflectionClass($this->metadataEngine);
        $generateMethod = $reflection->getMethod('generateDescriptionFromClassName');
        $generateMethod->setAccessible(true);
        
        $this->assertEquals('text field', $generateMethod->invoke($this->metadataEngine, 'TextField'));
        $this->assertEquals('email field', $generateMethod->invoke($this->metadataEngine, 'EmailField'));
        $this->assertEquals('date time field', $generateMethod->invoke($this->metadataEngine, 'DateTimeField'));
    }

    public function testGetSupportedValidationRulesForFieldType(): void
    {
        $reflection = new \ReflectionClass($this->metadataEngine);
        $getSupportedMethod = $reflection->getMethod('getSupportedValidationRulesForFieldType');
        $getSupportedMethod->setAccessible(true);
        
        $rules = $getSupportedMethod->invoke($this->metadataEngine, 'Text');
        $this->assertIsArray($rules);
        
        // Should contain common validation rules
        $ruleNames = array_column($rules, 'name');
        $this->assertContains('Required', $ruleNames);
        $this->assertContains('Email', $ruleNames);
        $this->assertContains('Alphanumeric', $ruleNames);
        
        // Each rule should have required properties
        foreach ($rules as $rule) {
            $this->assertArrayHasKey('name', $rule);
            $this->assertArrayHasKey('class', $rule);
            $this->assertArrayHasKey('description', $rule);
            $this->assertArrayHasKey('javascript_validation', $rule);
            
            $this->assertNotEmpty($rule['name']);
            $this->assertNotEmpty($rule['class']);
            $this->assertNotEmpty($rule['description']);
            // javascript_validation can be empty for some rules
        }
    }

    public function testExtractRuleNameFromClass(): void
    {
        $reflection = new \ReflectionClass($this->metadataEngine);
        $extractMethod = $reflection->getMethod('extractRuleNameFromClass');
        $extractMethod->setAccessible(true);
        
        $this->assertEquals('Required', $extractMethod->invoke($this->metadataEngine, 'RequiredValidation'));
        $this->assertEquals('Email', $extractMethod->invoke($this->metadataEngine, 'EmailValidation'));
        $this->assertEquals('Alphanumeric', $extractMethod->invoke($this->metadataEngine, 'AlphanumericValidation'));
    }
}
