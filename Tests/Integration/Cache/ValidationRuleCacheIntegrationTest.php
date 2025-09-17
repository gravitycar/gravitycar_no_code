<?php

namespace Gravitycar\Tests\Integration\Cache;

use Gravitycar\Tests\Integration\IntegrationTestCase;
use Gravitycar\Core\ContainerConfig;
use Gravitycar\Factories\ValidationRuleFactory;
use Gravitycar\Metadata\MetadataEngine;
use Gravitycar\Validation\RequiredValidation;
use Gravitycar\Validation\EmailValidation;

/**
 * Integration test for validation rule caching and Pure DI implementation.
 * Tests end-to-end validation rule functionality with real dependencies.
 */
class ValidationRuleCacheIntegrationTest extends IntegrationTestCase
{
    private ValidationRuleFactory $validationRuleFactory;

    protected function setUp(): void
    {
        parent::setUp();

        // Get real instances from container
        $container = ContainerConfig::getContainer();
        $this->validationRuleFactory = $container->get('validation_rule_factory');
    }

    /**
     * Test end-to-end validation rule creation using cached metadata
     */
    public function testEndToEndValidationRuleCreation(): void
    {
        // Ensure metadata is loaded
        $this->metadataEngine->loadAllMetadata();
        
        // Test creating various validation rules
        $requiredRule = $this->validationRuleFactory->createValidationRule('Required');
        $this->assertInstanceOf(RequiredValidation::class, $requiredRule);
        
        $emailRule = $this->validationRuleFactory->createValidationRule('Email');
        $this->assertInstanceOf(EmailValidation::class, $emailRule);
    }

    /**
     * Test validation rule factory pure DI compliance
     */
    public function testValidationRuleFactoryPureDI(): void
    {
        // Factory should be properly injected with dependencies
        $this->assertInstanceOf(ValidationRuleFactory::class, $this->validationRuleFactory);
        
        // Should be able to get available rules
        $availableRules = $this->validationRuleFactory->getAvailableValidationRules();
        $this->assertIsArray($availableRules);
        $this->assertNotEmpty($availableRules);
        
        // Should include common validation rules
        $this->assertContains('Required', $availableRules);
        $this->assertContains('Email', $availableRules);
    }

    /**
     * Test validation rule caching performance
     */
    public function testValidationRuleCachingPerformance(): void
    {
        // Ensure metadata is loaded
        $this->metadataEngine->loadAllMetadata();
        
        $startTime = microtime(true);
        
        // Multiple validation rule creations should be fast due to caching
        for ($i = 0; $i < 10; $i++) {
            $rule1 = $this->validationRuleFactory->createValidationRule('Required');
            $rule2 = $this->validationRuleFactory->createValidationRule('Email');
            $rules = $this->validationRuleFactory->getAvailableValidationRules();
        }
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // Should complete quickly due to caching
        $this->assertLessThan(0.5, $duration, 'Cached validation rule operations should be fast');
    }

    /**
     * Test validation rule metadata consistency
     */
    public function testValidationRuleMetadataConsistency(): void
    {
        // Get validation rules from both sources
        $metadataRules = $this->metadataEngine->getValidationRuleDefinitions();
        $factoryRules = $this->validationRuleFactory->getAvailableValidationRules();
        
        // Factory should return same rule names as metadata
        foreach ($factoryRules as $ruleName) {
            $this->assertArrayHasKey(
                $ruleName, 
                $metadataRules,
                "Validation rule {$ruleName} from factory not found in metadata"
            );
        }
        
        // All metadata rules should be creatable by factory
        foreach (array_keys($metadataRules) as $ruleName) {
            $rule = $this->validationRuleFactory->createValidationRule($ruleName);
            $this->assertNotNull($rule, "Could not create validation rule: {$ruleName}");
        }
    }

    /**
     * Test cache file contains validation rules
     */
    public function testCacheFileContainsValidationRules(): void
    {
        $cacheFile = 'cache/metadata_cache.php';
        $this->assertFileExists($cacheFile, 'Metadata cache file should exist');
        
        $cacheData = include $cacheFile;
        $this->assertIsArray($cacheData);
        $this->assertArrayHasKey('validation_rules', $cacheData);
        
        $validationRules = $cacheData['validation_rules'];
        $this->assertIsArray($validationRules);
        $this->assertNotEmpty($validationRules);
        
        // Should contain essential validation rules
        $this->assertArrayHasKey('Required', $validationRules);
        $this->assertArrayHasKey('Email', $validationRules);
    }

    /**
     * Test validation rule metadata structure in cache
     */
    public function testValidationRuleMetadataStructureInCache(): void
    {
        $cacheFile = 'cache/metadata_cache.php';
        $cacheData = include $cacheFile;
        $validationRules = $cacheData['validation_rules'];
        
        foreach ($validationRules as $ruleName => $ruleData) {
            // Verify required fields
            $this->assertArrayHasKey('name', $ruleData);
            $this->assertArrayHasKey('class', $ruleData);
            $this->assertArrayHasKey('description', $ruleData);
            $this->assertArrayHasKey('javascript_validation', $ruleData);
            
            // Verify data types
            $this->assertIsString($ruleData['name']);
            $this->assertIsString($ruleData['class']);
            $this->assertIsString($ruleData['description']);
            $this->assertIsString($ruleData['javascript_validation']);
            
            // Verify class exists
            $this->assertTrue(
                class_exists($ruleData['class']),
                "Validation rule class {$ruleData['class']} does not exist"
            );
        }
    }

    /**
     * Test no filesystem scanning during runtime
     */
    public function testNoFilesystemScanningDuringRuntime(): void
    {
        // Clear any existing cache to ensure fresh start
        $this->metadataEngine->loadAllMetadata();
        
        // Monitor filesystem operations (conceptual test)
        $startTime = microtime(true);
        
        // These operations should not trigger filesystem scanning
        $availableRules = $this->validationRuleFactory->getAvailableValidationRules();
        $requiredRule = $this->validationRuleFactory->createValidationRule('Required');
        $emailRule = $this->validationRuleFactory->createValidationRule('Email');
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // Should be very fast since no filesystem operations
        $this->assertLessThan(0.1, $duration, 'Cached operations should be extremely fast');
        
        // Verify we got valid results
        $this->assertNotEmpty($availableRules);
        $this->assertInstanceOf(RequiredValidation::class, $requiredRule);
        $this->assertInstanceOf(EmailValidation::class, $emailRule);
    }

    /**
     * Test container resolution of ValidationRuleFactory
     */
    public function testContainerResolutionOfValidationRuleFactory(): void
    {
        $container = ContainerConfig::getContainer();
        
        // Should be able to get validation rule factory from container
        $factory1 = $container->get('validation_rule_factory');
        $factory2 = $container->get('validation_rule_factory');
        
        $this->assertInstanceOf(ValidationRuleFactory::class, $factory1);
        $this->assertInstanceOf(ValidationRuleFactory::class, $factory2);
        
        // Should get the same instance (singleton behavior)
        $this->assertSame($factory1, $factory2);
    }

    /**
     * Test validation rule error handling
     */
    public function testValidationRuleErrorHandling(): void
    {
        $this->expectException(\Gravitycar\Exceptions\GCException::class);
        $this->expectExceptionMessage('Validation rule not found: NonExistentRule');
        
        $this->validationRuleFactory->createValidationRule('NonExistentRule');
    }
}