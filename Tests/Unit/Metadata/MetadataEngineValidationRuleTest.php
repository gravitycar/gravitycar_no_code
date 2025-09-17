<?php

namespace Gravitycar\Tests\Unit\Metadata;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Metadata\CoreFieldsMetadata;
use Gravitycar\Core\Config;
use Gravitycar\Validation\RequiredValidation;
use Gravitycar\Validation\EmailValidation;
use Gravitycar\Validation\AlphanumericValidation;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test suite for MetadataEngine validation rule discovery and caching functionality.
 * Tests the new validation rule scanning and caching features.
 */
class MetadataEngineValidationRuleTest extends UnitTestCase
{
    private MetadataEngine $metadataEngine;
    private Logger|MockObject $mockLogger;
    private Config|MockObject $mockConfig;
    private CoreFieldsMetadata|MockObject $mockCoreFieldsMetadata;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock dependencies
        $this->mockLogger = $this->createMock(Logger::class);
        $this->mockConfig = $this->createMock(Config::class);
        $this->mockCoreFieldsMetadata = $this->createMock(CoreFieldsMetadata::class);

        // Configure mock Config to return proper paths
        $this->mockConfig->method('get')->willReturnCallback(function($key, $default = null) {
            $configValues = [
                'metadata.models_dir_path' => 'src/Models',
                'metadata.relationships_dir_path' => 'src/Relationships',
                'metadata.fields_dir_path' => 'src/Fields',
                'metadata.cache_dir_path' => 'cache/',
            ];
            return $configValues[$key] ?? $default;
        });

        // Create MetadataEngine with dependency injection
        $this->metadataEngine = new MetadataEngine(
            $this->mockLogger,
            $this->mockConfig,
            $this->mockCoreFieldsMetadata
        );
    }

    /**
     * Test scanAndLoadValidationRules discovers validation rules
     */
    public function testScanAndLoadValidationRulesDiscoversRules(): void
    {
        // Call the private method through reflection since we need to test it
        $reflection = new \ReflectionClass($this->metadataEngine);
        $method = $reflection->getMethod('scanAndLoadValidationRules');
        $method->setAccessible(true);
        
        $validationRules = $method->invoke($this->metadataEngine);
        
        $this->assertIsArray($validationRules);
        $this->assertNotEmpty($validationRules);
        
        // Check that essential validation rules are discovered
        $this->assertArrayHasKey('Required', $validationRules);
        $this->assertArrayHasKey('Email', $validationRules);
        $this->assertArrayHasKey('Alphanumeric', $validationRules);
        
        // Verify structure of a validation rule entry
        $requiredRule = $validationRules['Required'];
        $this->assertArrayHasKey('name', $requiredRule);
        $this->assertArrayHasKey('class', $requiredRule);
        $this->assertArrayHasKey('description', $requiredRule);
        $this->assertArrayHasKey('javascript_validation', $requiredRule);
        
        $this->assertEquals('Required', $requiredRule['name']);
        $this->assertEquals(RequiredValidation::class, $requiredRule['class']);
    }

    /**
     * Test getValidationRuleDefinitions returns cached validation rules
     */
    public function testGetValidationRuleDefinitionsReturnsCachedRules(): void
    {
        // First, load the validation rules by triggering loadAllMetadata
        $this->metadataEngine->loadAllMetadata();
        
        // Now get the validation rule definitions
        $validationRules = $this->metadataEngine->getValidationRuleDefinitions();
        
        $this->assertIsArray($validationRules);
        $this->assertNotEmpty($validationRules);
        
        // Check that we get the expected validation rules
        $this->assertArrayHasKey('Required', $validationRules);
        $this->assertArrayHasKey('Email', $validationRules);
        
        // Verify each rule has the required structure
        foreach ($validationRules as $ruleName => $ruleData) {
            $this->assertIsString($ruleName);
            $this->assertIsArray($ruleData);
            $this->assertArrayHasKey('name', $ruleData);
            $this->assertArrayHasKey('class', $ruleData);
            $this->assertArrayHasKey('description', $ruleData);
            $this->assertArrayHasKey('javascript_validation', $ruleData);
            
            // Verify the class exists
            $this->assertTrue(class_exists($ruleData['class']), 
                "Validation rule class {$ruleData['class']} does not exist");
        }
    }

    /**
     * Test validation rules are included in loadAllMetadata
     */
    public function testValidationRulesIncludedInLoadAllMetadata(): void
    {
        // Load all metadata which should include validation rules
        $allMetadata = $this->metadataEngine->loadAllMetadata();
        
        $this->assertIsArray($allMetadata);
        $this->assertArrayHasKey('validation_rules', $allMetadata);
        
        $validationRules = $allMetadata['validation_rules'];
        $this->assertIsArray($validationRules);
        $this->assertNotEmpty($validationRules);
        
        // Verify some essential validation rules are present
        $this->assertArrayHasKey('Required', $validationRules);
        $this->assertArrayHasKey('Email', $validationRules);
    }

    /**
     * Test validation rule discovery respects ValidationRuleBase inheritance
     */
    public function testValidationRuleDiscoveryRespectsInheritance(): void
    {
        // Access the private method to test inheritance filtering
        $reflection = new \ReflectionClass($this->metadataEngine);
        $method = $reflection->getMethod('scanAndLoadValidationRules');
        $method->setAccessible(true);
        
        $validationRules = $method->invoke($this->metadataEngine);
        
        // Verify all discovered rules extend ValidationRuleBase
        foreach ($validationRules as $ruleName => $ruleData) {
            $className = $ruleData['class'];
            $reflection = new \ReflectionClass($className);
            
            $this->assertTrue(
                $reflection->isSubclassOf('Gravitycar\\Validation\\ValidationRuleBase'),
                "Class {$className} does not extend ValidationRuleBase"
            );
        }
    }

    /**
     * Test validation rule metadata includes JavaScript validation
     */
    public function testValidationRuleMetadataIncludesJavaScript(): void
    {
        $this->metadataEngine->loadAllMetadata();
        $validationRules = $this->metadataEngine->getValidationRuleDefinitions();
        
        // Check that validation rules have JavaScript validation field
        foreach ($validationRules as $ruleName => $ruleData) {
            $this->assertArrayHasKey('javascript_validation', $ruleData);
            $this->assertIsString($ruleData['javascript_validation']);
            // Note: Some validation rules may have empty JavaScript validation
            // which is acceptable for server-side only validation
        }
    }

    /**
     * Test validation rule caching is consistent
     */
    public function testValidationRuleCachingIsConsistent(): void
    {
        // Load metadata twice to ensure consistent caching
        $firstLoad = $this->metadataEngine->loadAllMetadata();
        $secondLoad = $this->metadataEngine->loadAllMetadata();
        
        $this->assertEquals(
            $firstLoad['validation_rules'], 
            $secondLoad['validation_rules'],
            'Validation rules should be consistent across multiple loads'
        );
        
        // Get validation rules directly twice
        $firstRules = $this->metadataEngine->getValidationRuleDefinitions();
        $secondRules = $this->metadataEngine->getValidationRuleDefinitions();
        
        $this->assertEquals(
            $firstRules, 
            $secondRules,
            'Direct validation rule access should be consistent'
        );
    }

    /**
     * Test validation rule discovery performance
     */
    public function testValidationRuleDiscoveryPerformance(): void
    {
        $startTime = microtime(true);
        
        // Load validation rules
        $this->metadataEngine->loadAllMetadata();
        $validationRules = $this->metadataEngine->getValidationRuleDefinitions();
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // Should complete quickly (less than 1 second for validation rule discovery)
        $this->assertLessThan(1.0, $duration, 'Validation rule discovery should be fast');
        
        // Should discover a reasonable number of rules
        $this->assertGreaterThan(5, count($validationRules), 'Should discover several validation rules');
        $this->assertLessThan(50, count($validationRules), 'Should not discover excessive validation rules');
    }

    /**
     * Test empty validation rules handling
     */
    public function testEmptyValidationRulesHandling(): void
    {
        // Create a fresh MetadataEngine to test edge cases
        $tempMockConfig = $this->createMock(Config::class);
        $tempMockConfig->method('get')->willReturnCallback(function($key, $default = null) {
            $configValues = [
                'metadata.models_dir_path' => 'src/Models',
                'metadata.relationships_dir_path' => 'src/Relationships',
                'metadata.fields_dir_path' => 'src/Fields',
                'metadata.cache_dir_path' => 'cache/',
            ];
            return $configValues[$key] ?? $default;
        });
        
        $tempEngine = new MetadataEngine(
            $this->mockLogger,
            $tempMockConfig,
            $this->mockCoreFieldsMetadata
        );
        
        // Should handle empty case gracefully
        $rules = $tempEngine->getValidationRuleDefinitions();
        $this->assertIsArray($rules);
        
        // After loading metadata, should have rules
        $tempEngine->loadAllMetadata();
        $rulesAfterLoad = $tempEngine->getValidationRuleDefinitions();
        $this->assertIsArray($rulesAfterLoad);
        $this->assertNotEmpty($rulesAfterLoad);
    }

    /**
     * Test validation rule class existence
     */
    public function testValidationRuleClassExistence(): void
    {
        $this->metadataEngine->loadAllMetadata();
        $validationRules = $this->metadataEngine->getValidationRuleDefinitions();
        
        foreach ($validationRules as $ruleName => $ruleData) {
            $className = $ruleData['class'];
            
            $this->assertTrue(
                class_exists($className),
                "Validation rule class {$className} for rule {$ruleName} does not exist"
            );
            
            $this->assertTrue(
                is_subclass_of($className, 'Gravitycar\\Validation\\ValidationRuleBase'),
                "Validation rule class {$className} does not extend ValidationRuleBase"
            );
        }
    }

    /**
     * Test validation rule metadata structure
     */
    public function testValidationRuleMetadataStructure(): void
    {
        $this->metadataEngine->loadAllMetadata();
        $validationRules = $this->metadataEngine->getValidationRuleDefinitions();
        
        $requiredFields = ['name', 'class', 'description', 'javascript_validation'];
        
        foreach ($validationRules as $ruleName => $ruleData) {
            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey(
                    $field, 
                    $ruleData,
                    "Validation rule {$ruleName} missing required field: {$field}"
                );
                
                if ($field !== 'javascript_validation') {
                    // All fields except javascript_validation must be non-empty
                    $this->assertNotEmpty(
                        $ruleData[$field],
                        "Validation rule {$ruleName} has empty {$field}"
                    );
                } else {
                    // javascript_validation field must exist but can be empty
                    $this->assertIsString(
                        $ruleData[$field],
                        "Validation rule {$ruleName} javascript_validation must be a string"
                    );
                }
            }
            
            // Verify name matches key
            $this->assertEquals(
                $ruleName,
                $ruleData['name'],
                "Validation rule key {$ruleName} does not match name {$ruleData['name']}"
            );
        }
    }
}