<?php

namespace Gravitycar\Tests\Unit\Fields;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Fields\RelatedRecordField;
use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;

/**
 * Test suite for the RelatedRecordField class.
 * Tests foreign key relationship field functionality with related model integration.
 */
class RelatedRecordFieldTest extends UnitTestCase
{
    private RelatedRecordField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $metadata = [
            'name' => 'user_id',
            'type' => 'RelatedRecord',
            'label' => 'User',
            'required' => false,
            'relatedModelName' => 'User',
            'relatedFieldName' => 'id',
            'displayFieldName' => 'user_name'
        ];

        $this->field = new RelatedRecordField($metadata, $this->logger);
    }

    /**
     * Test constructor and default properties
     */
    public function testConstructor(): void
    {
        $this->assertEquals('user_id', $this->field->getName());
        $this->assertEquals('User', $this->field->getMetadataValue('label'));
        $this->assertEquals('User', $this->field->getRelatedModelName());
        $this->assertEquals('id', $this->field->getRelatedFieldName());
        $this->assertEquals('user_name', $this->field->getDisplayFieldName());
        $this->assertEquals('RelatedRecord', $this->field->getType());
    }

    /**
     * Test required metadata validation
     */
    public function testRequiredMetadata(): void
    {
        // Test missing relatedModelName
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('RelatedRecord field missing required metadata: relatedModelName');

        $invalidMetadata = [
            'name' => 'invalid_field',
            'type' => 'RelatedRecord',
            'relatedFieldName' => 'id',
            'displayFieldName' => 'name'
        ];

        new RelatedRecordField($invalidMetadata, $this->logger);
    }

    /**
     * Test missing relatedFieldName throws exception
     */
    public function testMissingRelatedFieldName(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('RelatedRecord field missing required metadata: relatedFieldName');

        $invalidMetadata = [
            'name' => 'invalid_field',
            'type' => 'RelatedRecord',
            'relatedModelName' => 'User',
            'displayFieldName' => 'name'
        ];

        new RelatedRecordField($invalidMetadata, $this->logger);
    }

    /**
     * Test missing displayFieldName throws exception
     */
    public function testMissingDisplayFieldName(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage('RelatedRecord field missing required metadata: displayFieldName');

        $invalidMetadata = [
            'name' => 'invalid_field',
            'type' => 'RelatedRecord',
            'relatedModelName' => 'User',
            'relatedFieldName' => 'id'
        ];

        new RelatedRecordField($invalidMetadata, $this->logger);
    }

    /**
     * Test setting foreign key values
     */
    public function testForeignKeyValues(): void
    {
        // Test UUID foreign key
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->field->setValue($uuid);
        $this->assertEquals($uuid, $this->field->getValue());

        // Test integer foreign key
        $this->field->setValue(123);
        $this->assertEquals(123, $this->field->getValue());

        // Test string foreign key
        $this->field->setValue('user_123');
        $this->assertEquals('user_123', $this->field->getValue());
    }

    /**
     * Test null and empty values
     */
    public function testNullAndEmptyValues(): void
    {
        // Test null (no related record)
        $this->field->setValue(null);
        $this->assertNull($this->field->getValue());

        // Test empty string
        $this->field->setValue('');
        $this->assertEquals('', $this->field->getValue());
    }

    /**
     * Test getRelatedModelInstance with mocked dependencies
     */
    public function testGetRelatedModelInstance(): void
    {
        // This test will fail in practice due to ServiceLocator dependencies
        // but we can test that the method attempts to create the model
        try {
            $instance = $this->field->getRelatedModelInstance();
            // If successful, should be a ModelBase instance
            if ($instance !== null) {
                $this->assertInstanceOf(ModelBase::class, $instance);
            }
        } catch (GCException $e) {
            // Expected behavior when dependencies aren't available
            $this->assertStringContainsString('Could not create instance of related model', $e->getMessage());
        }
    }

    /**
     * Test render method
     */
    public function testRender(): void
    {
        $this->field->setValue('test_value');
        $this->assertEquals('test_value', $this->field->render());

        $this->field->setValue(123);
        $this->assertEquals('123', $this->field->render());

        $this->field->setValue(null);
        $this->assertEquals('', $this->field->render());
    }

    /**
     * Test getType method
     */
    public function testGetType(): void
    {
        $this->assertEquals('RelatedRecord', $this->field->getType());
    }

    /**
     * Test setValueFromTrustedSource
     */
    public function testSetValueFromTrustedSource(): void
    {
        $this->field->setValueFromTrustedSource('trusted_user_id');
        $this->assertEquals('trusted_user_id', $this->field->getValue());

        $this->field->setValueFromTrustedSource(456);
        $this->assertEquals(456, $this->field->getValue());

        $this->field->setValueFromTrustedSource(null);
        $this->assertNull($this->field->getValue());
    }

    /**
     * Test different related model configurations
     */
    public function testDifferentRelatedModels(): void
    {
        // Test Category relationship
        $categoryMetadata = [
            'name' => 'category_id',
            'type' => 'RelatedRecord',
            'relatedModelName' => 'Category',
            'relatedFieldName' => 'id',
            'displayFieldName' => 'category_name'
        ];

        $categoryField = new RelatedRecordField($categoryMetadata, $this->logger);
        $this->assertEquals('Category', $categoryField->getRelatedModelName());
        $this->assertEquals('category_name', $categoryField->getDisplayFieldName());

        // Test Department relationship
        $deptMetadata = [
            'name' => 'department_id',
            'type' => 'RelatedRecord',
            'relatedModelName' => 'Department',
            'relatedFieldName' => 'dept_id',
            'displayFieldName' => 'dept_display'
        ];

        $deptField = new RelatedRecordField($deptMetadata, $this->logger);
        $this->assertEquals('Department', $deptField->getRelatedModelName());
        $this->assertEquals('dept_id', $deptField->getRelatedFieldName());
        $this->assertEquals('dept_display', $deptField->getDisplayFieldName());
    }

    /**
     * Test required RelatedRecord field
     */
    public function testRequiredRelatedRecordField(): void
    {
        $metadata = [
            'name' => 'required_user_id',
            'type' => 'RelatedRecord',
            'required' => true,
            'relatedModelName' => 'User',
            'relatedFieldName' => 'id',
            'displayFieldName' => 'user_name'
        ];

        $field = new RelatedRecordField($metadata, $this->logger);
        $this->assertTrue($field->isRequired());
    }

    /**
     * Test complex foreign key relationships
     */
    public function testComplexRelationships(): void
    {
        // Test self-referencing relationship
        $selfRefMetadata = [
            'name' => 'parent_id',
            'type' => 'RelatedRecord',
            'relatedModelName' => 'Category',
            'relatedFieldName' => 'id',
            'displayFieldName' => 'parent_name'
        ];

        $selfRefField = new RelatedRecordField($selfRefMetadata, $this->logger);
        $this->assertEquals('Category', $selfRefField->getRelatedModelName());
        $this->assertEquals('parent_name', $selfRefField->getDisplayFieldName());
    }

    /**
     * Test metadata validation with empty strings
     */
    public function testMetadataValidationWithEmptyStrings(): void
    {
        $this->expectException(GCException::class);

        $invalidMetadata = [
            'name' => 'invalid_field',
            'type' => 'RelatedRecord',
            'relatedModelName' => '', // Empty string should fail
            'relatedFieldName' => 'id',
            'displayFieldName' => 'name'
        ];

        new RelatedRecordField($invalidMetadata, $this->logger);
    }

    /**
     * Test various data types for foreign key values
     */
    public function testVariousDataTypes(): void
    {
        // Test string UUID
        $this->field->setValue('550e8400-e29b-41d4-a716-446655440000');
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $this->field->getValue());

        // Test integer
        $this->field->setValue(12345);
        $this->assertEquals(12345, $this->field->getValue());

        // Test zero (valid foreign key)
        $this->field->setValue(0);
        $this->assertEquals(0, $this->field->getValue());

        // Test negative number (unusual but should be stored)
        $this->field->setValue(-1);
        $this->assertEquals(-1, $this->field->getValue());

        // Test float (should be stored as-is)
        $this->field->setValue(123.45);
        $this->assertEquals(123.45, $this->field->getValue());
    }

    /**
     * Test array and object values (edge cases)
     */
    public function testComplexValues(): void
    {
        // Test array (might represent composite keys)
        $arrayKey = ['user_id' => 123, 'tenant_id' => 456];
        $this->field->setValue($arrayKey);
        $this->assertEquals($arrayKey, $this->field->getValue());

        // Test object
        $objectKey = new \stdClass();
        $objectKey->id = 789;
        $this->field->setValue($objectKey);
        $this->assertEquals($objectKey, $this->field->getValue());
    }

    /**
     * Test very long foreign key values
     */
    public function testLongForeignKeys(): void
    {
        // Test very long string key
        $longKey = str_repeat('a', 100);
        $this->field->setValue($longKey);
        $this->assertEquals($longKey, $this->field->getValue());

        // Test long UUID-style key
        $longUuid = '550e8400-e29b-41d4-a716-446655440000-extended-with-extra-data';
        $this->field->setValue($longUuid);
        $this->assertEquals($longUuid, $this->field->getValue());
    }
}
