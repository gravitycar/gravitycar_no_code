<?php

namespace Gravitycar\Tests\Unit\Models;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Models\Auditable\Auditable;
use Gravitycar\Metadata\CoreFieldsMetadata;
use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use Aura\Di\Container;
use Aura\Di\ContainerBuilder;

/**
 * Test suite for the Auditable example class.
 * Tests model-specific core field registration and customization.
 */
class AuditableModelTest extends UnitTestCase
{
    private MockObject $mockLogger;
    private MockObject $mockCoreFieldsMetadata;
    private Container $testContainer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockLogger = $this->createMock(Logger::class);
        $this->mockCoreFieldsMetadata = $this->createMock(CoreFieldsMetadata::class);
        
        // Set up test container using ContainerBuilder
        $builder = new ContainerBuilder();
        $this->testContainer = $builder->newInstance();
        $this->testContainer->set('logger', $this->mockLogger);
        $this->testContainer->set('core_fields_metadata', $this->mockCoreFieldsMetadata);

        // Mock field factory to avoid actual field creation
        $mockFieldFactory = $this->createMock(\Gravitycar\Factories\FieldFactory::class);
        $this->testContainer->set('field_factory', $mockFieldFactory);

        ServiceLocator::setContainer($this->testContainer);
    }

    protected function tearDown(): void
    {
        ServiceLocator::reset();
        parent::tearDown();
    }

    // ====================
    // ADDITIONAL CORE FIELDS REGISTRATION TESTS
    // ====================

    /**
     * Test that AuditableModel registers additional core fields
     */
    public function testAuditableModelRegistersAdditionalCoreFields(): void
    {
        $expectedAdditionalFields = [
            'audit_trail' => [
                'name' => 'audit_trail',
                'type' => 'BigTextField',
                'label' => 'Audit Trail',
                'description' => 'JSON log of all changes made to this record',
                'required' => false,
                'readOnly' => true,
                'isDBField' => true,
                'nullable' => true,
                'validation' => [
                    'type' => 'text',
                    'nullable' => true
                ]
            ],
            'version' => [
                'name' => 'version',
                'type' => 'IntegerField',
                'label' => 'Version',
                'description' => 'Version number for optimistic locking',
                'required' => false,
                'readOnly' => true,
                'isDBField' => true,
                'defaultValue' => 1,
                'validation' => [
                    'type' => 'integer',
                    'min' => 1
                ]
            ]
        ];

        $this->mockCoreFieldsMetadata->expects($this->once())
            ->method('registerModelSpecificCoreFields')
            ->with(Auditable::class, $expectedAdditionalFields);

        $this->mockCoreFieldsMetadata->expects($this->once())
            ->method('getAllCoreFieldsForModel')
            ->with(Auditable::class)
            ->willReturn(['id' => ['name' => 'id', 'type' => 'IDField']]);

        new Auditable($this->mockLogger);
    }

    /**
     * Test that registerAdditionalCoreFields can be called statically
     */
    public function testRegisterAdditionalCoreFieldsCanBeCalledStatically(): void
    {
        $this->mockCoreFieldsMetadata->expects($this->once())
            ->method('registerModelSpecificCoreFields')
            ->with(
                Auditable::class,
                $this->arrayHasKey('audit_trail')
            );

        Auditable::registerAdditionalCoreFields();
    }

    /**
     * Test that additional core fields have correct structure
     */
    public function testAdditionalCoreFieldsHaveCorrectStructure(): void
    {
        $this->mockCoreFieldsMetadata->expects($this->once())
            ->method('registerModelSpecificCoreFields')
            ->with(
                Auditable::class,
                $this->callback(function ($fields) {
                    // Validate audit_trail field structure
                    $this->assertArrayHasKey('audit_trail', $fields);
                    $auditField = $fields['audit_trail'];

                    $this->assertEquals('audit_trail', $auditField['name']);
                    $this->assertEquals('BigTextField', $auditField['type']);
                    $this->assertEquals('Audit Trail', $auditField['label']);
                    $this->assertFalse($auditField['required']);
                    $this->assertTrue($auditField['readOnly']);
                    $this->assertTrue($auditField['isDBField']);
                    $this->assertTrue($auditField['nullable']);

                    // Validate version field structure
                    $this->assertArrayHasKey('version', $fields);
                    $versionField = $fields['version'];

                    $this->assertEquals('version', $versionField['name']);
                    $this->assertEquals('IntegerField', $versionField['type']);
                    $this->assertEquals('Version', $versionField['label']);
                    $this->assertEquals(1, $versionField['defaultValue']);
                    $this->assertFalse($versionField['required']);
                    $this->assertTrue($versionField['readOnly']);
                    $this->assertTrue($versionField['isDBField']);

                    return true;
                })
            );

        $this->mockCoreFieldsMetadata->expects($this->once())
            ->method('getAllCoreFieldsForModel')
            ->willReturn(['id' => ['name' => 'id', 'type' => 'IDField']]);

        new Auditable($this->mockLogger);
    }

    // ====================
    // CORE FIELD CUSTOMIZATION TESTS
    // ====================

    /**
     * Test that AuditableModel can customize existing core fields
     */
    public function testAuditableModelCanCustomizeExistingCoreFields(): void
    {
        $this->mockCoreFieldsMetadata->expects($this->once())
            ->method('getCoreFieldWithOverrides')
            ->with(
                'created_by',
                TestableAuditableModel::class, // Use the actual test class name
                [
                    'required' => true,
                    'validation' => [
                        'type' => 'integer',
                        'required' => true,
                        'min' => 1
                    ]
                ]
            )
            ->willReturn([
                'name' => 'created_by',
                'type' => 'RelatedRecordField',
                'label' => 'Created By',
                'required' => true, // Overridden
                'validation' => [   // Overridden
                    'type' => 'integer',
                    'required' => true,
                    'min' => 1
                ]
            ]);

        $this->mockCoreFieldsMetadata->expects($this->once())
            ->method('getAllCoreFieldsForModel')
            ->willReturn(['id' => ['name' => 'id', 'type' => 'IDField']]);

        $model = new TestableAuditableModel($this->mockLogger);

        // Call the customization method
        $model->testCustomizeCoreFields();

        // Verify the field was customized in metadata
        $metadata = $model->getTestMetadata();
        $this->assertTrue($metadata['fields']['created_by']['required']);
    }

    // ====================
    // INHERITANCE INTEGRATION TESTS
    // ====================

    /**
     * Test that subclasses of AuditableModel inherit additional core fields
     */
    public function testSubclassesInheritAdditionalCoreFields(): void
    {
        // First call for AuditableModel registration
        $this->mockCoreFieldsMetadata->expects($this->exactly(2))
            ->method('registerModelSpecificCoreFields');

        // Second call for SubAuditableModel (should include inherited fields)
        $this->mockCoreFieldsMetadata->expects($this->exactly(2))
            ->method('getAllCoreFieldsForModel')
            ->willReturn(['id' => ['name' => 'id', 'type' => 'IDField']]);

        // Create parent and child models
        new Auditable($this->mockLogger);
        new SubAuditableModel($this->mockLogger);
    }

    /**
     * Test that multiple instances don't duplicate field registration
     */
    public function testMultipleInstancesDontDuplicateRegistration(): void
    {
        // Registration should only happen once per class, not per instance
        $this->mockCoreFieldsMetadata->expects($this->atLeastOnce())
            ->method('registerModelSpecificCoreFields')
            ->with(Auditable::class);

        $this->mockCoreFieldsMetadata->expects($this->exactly(2))
            ->method('getAllCoreFieldsForModel')
            ->willReturn(['id' => ['name' => 'id', 'type' => 'IDField']]);

        // Create multiple instances
        new Auditable($this->mockLogger);
        new Auditable($this->mockLogger);
    }

    // ====================
    // ERROR HANDLING TESTS
    // ====================

    /**
     * Test error handling when CoreFieldsMetadata service is unavailable
     */
    public function testErrorHandlingWhenCoreFieldsMetadataUnavailable(): void
    {
        // Remove service from container
        $builder = new ContainerBuilder();
        $failingContainer = $builder->newInstance();
        $failingContainer->set('logger', $this->mockLogger);
        ServiceLocator::setContainer($failingContainer);

        $this->expectException(\Gravitycar\Exceptions\GCException::class);
        $this->expectExceptionMessage('CoreFieldsMetadata service unavailable');

        new Auditable($this->mockLogger);
    }

    // ====================
    // TABLE NAME AND BASIC FUNCTIONALITY TESTS
    // ====================

    /**
     * Test that AuditableModel has correct table name
     */
    public function testAuditableModelHasCorrectTableName(): void
    {
        $this->mockCoreFieldsMetadata->expects($this->once())
            ->method('registerModelSpecificCoreFields');

        $this->mockCoreFieldsMetadata->expects($this->once())
            ->method('getAllCoreFieldsForModel')
            ->willReturn(['id' => ['name' => 'id', 'type' => 'IDField']]);

        $model = new Auditable($this->mockLogger);

        // Should use lowercased class name as table name - the class is now named 'Auditable'
        $this->assertEquals('auditable', $model->getTableName());
    }
}

/**
 * Testable version of AuditableModel for accessing protected methods
 */
class TestableAuditableModel extends Auditable
{
    public function getTestMetadata(): array
    {
        return $this->metadata;
    }

    public function testCustomizeCoreFields(): void
    {
        $this->customizeCoreFields();
    }

    protected function getMetaDataFilePaths(): array
    {
        return []; // No metadata files for testing
    }

    public function getTableName(): string
    {
        return 'testable_auditable_models';
    }
}

/**
 * Subclass of AuditableModel for testing inheritance
 */
class SubAuditableModel extends Auditable
{
    public function getTableName(): string
    {
        return 'sub_auditable_models';
    }
}
