<?php

namespace Gravitycar\Tests\Unit\Factories;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Factories\ValidationRuleFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Validation\ValidationRuleBase;
use Gravitycar\Validation\RequiredValidation;
use Gravitycar\Validation\EmailValidation;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test suite for ValidationRuleFactory with Pure DI.
 * Tests cache-based validation rule creation and Pure DI compliance.
 */
class ValidationRuleFactoryTest extends UnitTestCase
{
    private ValidationRuleFactory $factory;
    private Logger|MockObject $mockLogger;
    private MetadataEngineInterface|MockObject $mockMetadataEngine;

    protected function setUp(): void
    {
        parent::setUp();

        // Pure DI setup - direct dependency injection
        $this->mockLogger = $this->createMock(Logger::class);
        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        
        // Direct injection - no complex setup needed
        $this->factory = new ValidationRuleFactory(
            $this->mockLogger,
            $this->mockMetadataEngine
        );
    }

    /**
     * Test constructor with Pure DI
     */
    public function testConstructorWithPureDI(): void
    {
        // Verify that factory can be instantiated with all dependencies
        $this->assertInstanceOf(ValidationRuleFactory::class, $this->factory);
        
        // Factory should be ready for immediate use
        $this->mockMetadataEngine
            ->expects($this->once())
            ->method('getValidationRuleDefinitions')
            ->willReturn([]);
            
        $availableRules = $this->factory->getAvailableValidationRules();
        $this->assertIsArray($availableRules);
    }

    /**
     * Test createValidationRule from cache
     */
    public function testCreateValidationRuleFromCache(): void
    {
        // Mock cached validation rules
        $cachedRules = [
            'Required' => [
                'name' => 'Required',
                'class' => RequiredValidation::class,
                'description' => 'Ensures a value is present and not empty',
                'javascript_validation' => 'function() { return true; }'
            ],
            'Email' => [
                'name' => 'Email',
                'class' => EmailValidation::class,
                'description' => 'Validates email format',
                'javascript_validation' => 'function() { return true; }'
            ]
        ];

        $this->mockMetadataEngine
            ->expects($this->once())
            ->method('getValidationRuleDefinitions')
            ->willReturn($cachedRules);

        $rule = $this->factory->createValidationRule('Required');
        
        $this->assertInstanceOf(ValidationRuleBase::class, $rule);
        $this->assertInstanceOf(RequiredValidation::class, $rule);
    }

    /**
     * Test getAvailableValidationRules from cache
     */
    public function testGetAvailableValidationRulesFromCache(): void
    {
        $cachedRules = [
            'Required' => ['class' => RequiredValidation::class],
            'Email' => ['class' => EmailValidation::class],
            'Alphanumeric' => ['class' => 'Gravitycar\\Validation\\AlphanumericValidation']
        ];

        $this->mockMetadataEngine
            ->expects($this->once())
            ->method('getValidationRuleDefinitions')
            ->willReturn($cachedRules);

        $availableRules = $this->factory->getAvailableValidationRules();
        
        $this->assertIsArray($availableRules);
        $this->assertCount(3, $availableRules);
        $this->assertContains('Required', $availableRules);
        $this->assertContains('Email', $availableRules);
        $this->assertContains('Alphanumeric', $availableRules);
    }

    /**
     * Test createValidationRule throws exception for unknown rule
     */
    public function testCreateValidationRuleThrowsExceptionForUnknownRule(): void
    {
        $cachedRules = [
            'Required' => ['class' => RequiredValidation::class]
        ];

        $this->mockMetadataEngine
            ->expects($this->once())
            ->method('getValidationRuleDefinitions')
            ->willReturn($cachedRules);

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Validation rule not found: UnknownRule');

        $this->factory->createValidationRule('UnknownRule');
    }

    /**
     * Test createValidationRule throws exception for missing class data
     */
    public function testCreateValidationRuleThrowsExceptionForMissingClassData(): void
    {
        $cachedRules = [
            'BadRule' => [
                'name' => 'BadRule',
                // Missing 'class' key
                'description' => 'Bad rule'
            ]
        ];

        $this->mockMetadataEngine
            ->expects($this->once())
            ->method('getValidationRuleDefinitions')
            ->willReturn($cachedRules);

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Validation rule not found: BadRule');

        $this->factory->createValidationRule('BadRule');
    }

    /**
     * Test createValidationRule throws exception for non-existent class
     */
    public function testCreateValidationRuleThrowsExceptionForNonExistentClass(): void
    {
        $cachedRules = [
            'NonExistent' => [
                'class' => 'Gravitycar\\Validation\\NonExistentValidation'
            ]
        ];

        $this->mockMetadataEngine
            ->expects($this->once())
            ->method('getValidationRuleDefinitions')
            ->willReturn($cachedRules);

        $this->expectException(GCException::class);
        $this->expectExceptionMessage('Validation rule class does not exist: Gravitycar\\Validation\\NonExistentValidation');

        $this->factory->createValidationRule('NonExistent');
    }

    /**
     * Test no filesystem access during operation
     */
    public function testNoFilesystemAccessDuringOperation(): void
    {
        // This test verifies that ValidationRuleFactory doesn't scan directories
        // by ensuring it only uses cached metadata
        
        $cachedRules = [
            'Required' => ['class' => RequiredValidation::class]
        ];

        $this->mockMetadataEngine
            ->expects($this->exactly(2))
            ->method('getValidationRuleDefinitions')
            ->willReturn($cachedRules);

        // Multiple operations should not trigger filesystem scanning
        $availableRules = $this->factory->getAvailableValidationRules();
        $rule = $this->factory->createValidationRule('Required');
        
        $this->assertCount(1, $availableRules);
        $this->assertInstanceOf(RequiredValidation::class, $rule);
    }

    /**
     * Test Pure DI compliance - no ServiceLocator usage
     */
    public function testNoServiceLocatorUsage(): void
    {
        // This test validates that the factory doesn't use ServiceLocator
        // by ensuring it works with only injected dependencies
        
        $cachedRules = ['Required' => ['class' => RequiredValidation::class]];
        
        $this->mockMetadataEngine
            ->method('getValidationRuleDefinitions')
            ->willReturn($cachedRules);

        // Factory should work without any ServiceLocator calls
        $availableRules = $this->factory->getAvailableValidationRules();
        $rule = $this->factory->createValidationRule('Required');
        
        $this->assertIsArray($availableRules);
        $this->assertInstanceOf(ValidationRuleBase::class, $rule);
    }

    /**
     * Test empty cache handling
     */
    public function testEmptyCacheHandling(): void
    {
        $this->mockMetadataEngine
            ->expects($this->once())
            ->method('getValidationRuleDefinitions')
            ->willReturn([]);

        $availableRules = $this->factory->getAvailableValidationRules();
        
        $this->assertIsArray($availableRules);
        $this->assertEmpty($availableRules);
    }

    /**
     * Test error context in exceptions
     */
    public function testErrorContextInExceptions(): void
    {
        $cachedRules = [
            'Existing' => ['class' => RequiredValidation::class]
        ];

        $this->mockMetadataEngine
            ->expects($this->once())
            ->method('getValidationRuleDefinitions')
            ->willReturn($cachedRules);

        try {
            $this->factory->createValidationRule('Missing');
            $this->fail('Expected GCException was not thrown');
        } catch (GCException $e) {
            $context = $e->getContext();
            $this->assertArrayHasKey('rule_name', $context);
            $this->assertArrayHasKey('available_rules', $context);
            $this->assertEquals('Missing', $context['rule_name']);
            $this->assertEquals(['Existing'], $context['available_rules']);
        }
    }
}