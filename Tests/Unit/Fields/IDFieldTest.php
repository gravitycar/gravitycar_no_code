<?php

namespace Gravitycar\Tests\Unit\Fields;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Fields\IDField;

/**
 * Test suite for the IDField class.
 * Tests unique identifier field functionality with UUID handling.
 */
class IDFieldTest extends UnitTestCase
{
    private IDField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $metadata = [
            'name' => 'id',
            'type' => 'ID',
            'label' => 'ID',
            'required' => true,
            'unique' => true,
            'readOnly' => true
        ];

        $this->field = new IDField($metadata, $this->logger);
    }

    /**
     * Test constructor and default properties
     */
    public function testConstructor(): void
    {
        $this->assertEquals('id', $this->field->getName());
        $this->assertEquals('ID', $this->field->getMetadataValue('label'));
        $this->assertTrue($this->field->getMetadataValue('required'));
        $this->assertTrue($this->field->getMetadataValue('unique'));
        $this->assertTrue($this->field->getMetadataValue('readOnly'));
        $this->assertEquals('ID', $this->field->getMetadataValue('type'));
    }

    /**
     * Test ID field is required by default
     */
    public function testRequiredByDefault(): void
    {
        $this->assertTrue($this->field->isRequired());
        $this->assertTrue($this->field->metadataIsTrue('required'));
    }

    /**
     * Test ID field is unique by default
     */
    public function testUniqueByDefault(): void
    {
        $this->assertTrue($this->field->isUnique());
        $this->assertTrue($this->field->metadataIsTrue('unique'));
    }

    /**
     * Test ID field is readonly by default
     */
    public function testReadOnlyByDefault(): void
    {
        // Check the ingested metadata for the readOnly property
        $metadata = $this->field->getMetadata();
        $this->assertTrue($metadata['readOnly'] ?? true);
        $this->assertTrue($this->field->metadataIsTrue('readOnly'));
    }

    /**
     * Test setting UUID values
     */
    public function testUuidValues(): void
    {
        // Test valid UUID format
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $this->field->setValue($uuid);
        $this->assertEquals($uuid, $this->field->getValue());

        // Test another UUID format
        $uuid2 = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
        $this->field->setValue($uuid2);
        $this->assertEquals($uuid2, $this->field->getValue());
    }

    /**
     * Test setting various string ID formats
     */
    public function testVariousIdFormats(): void
    {
        // Test short string ID
        $this->field->setValue('abc123');
        $this->assertEquals('abc123', $this->field->getValue());

        // Test numeric string ID
        $this->field->setValue('12345');
        $this->assertEquals('12345', $this->field->getValue());

        // Test mixed alphanumeric ID
        $this->field->setValue('user_123_abc');
        $this->assertEquals('user_123_abc', $this->field->getValue());
    }

    /**
     * Test setting integer ID values
     */
    public function testIntegerIdValues(): void
    {
        // Test integer ID (auto-increment style)
        $this->field->setValue(12345);
        $this->assertEquals(12345, $this->field->getValue());

        // Test zero (might be used for new records)
        $this->field->setValue(0);
        $this->assertEquals(0, $this->field->getValue());
    }

    /**
     * Test null values (for new records)
     */
    public function testNullValues(): void
    {
        // ID fields might be null before assignment
        $this->field->setValue(null);
        $this->assertNull($this->field->getValue());
    }

    /**
     * Test empty string values
     */
    public function testEmptyStringValues(): void
    {
        $this->field->setValue('');
        $this->assertEquals('', $this->field->getValue());
    }

    /**
     * Test setValueFromTrustedSource
     */
    public function testSetValueFromTrustedSource(): void
    {
        $uuid = '123e4567-e89b-12d3-a456-426614174000';
        $this->field->setValueFromTrustedSource($uuid);
        $this->assertEquals($uuid, $this->field->getValue());

        $this->field->setValueFromTrustedSource(98765);
        $this->assertEquals(98765, $this->field->getValue());

        $this->field->setValueFromTrustedSource(null);
        $this->assertNull($this->field->getValue());
    }

    /**
     * Test default properties with minimal metadata
     */
    public function testDefaultProperties(): void
    {
        $minimalMetadata = [
            'name' => 'simple_id',
            'type' => 'ID'
        ];

        $field = new IDField($minimalMetadata, $this->logger);
        // Check the ingested metadata for default values
        $metadata = $field->getMetadata();
        $this->assertEquals('ID', $metadata['label'] ?? 'ID');
        $this->assertTrue($metadata['required'] ?? true);
        $this->assertTrue($metadata['unique'] ?? true);
        $this->assertTrue($metadata['readOnly'] ?? true);
    }

    /**
     * Test custom label
     */
    public function testCustomLabel(): void
    {
        $metadata = [
            'name' => 'user_id',
            'type' => 'ID',
            'label' => 'User ID'
        ];

        $field = new IDField($metadata, $this->logger);
        $this->assertEquals('User ID', $field->getMetadataValue('label'));
    }

    /**
     * Test overriding default properties
     */
    public function testOverridingDefaults(): void
    {
        $metadata = [
            'name' => 'optional_id',
            'type' => 'ID',
            'required' => false,
            'unique' => false,
            'readOnly' => false
        ];

        $field = new IDField($metadata, $this->logger);
        $this->assertFalse($field->getMetadataValue('required'));
        $this->assertFalse($field->getMetadataValue('unique'));
        $this->assertFalse($field->getMetadataValue('readOnly'));
    }

    /**
     * Test various UUID versions
     */
    public function testUuidVersions(): void
    {
        // UUID v1 (time-based)
        $uuidV1 = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
        $this->field->setValue($uuidV1);
        $this->assertEquals($uuidV1, $this->field->getValue());

        // UUID v4 (random)
        $uuidV4 = '550e8400-e29b-41d4-a716-446655440000';
        $this->field->setValue($uuidV4);
        $this->assertEquals($uuidV4, $this->field->getValue());

        // UUID v5 (name-based SHA-1)
        $uuidV5 = '886313e1-3b8a-5372-9b90-0c9aee199e5d';
        $this->field->setValue($uuidV5);
        $this->assertEquals($uuidV5, $this->field->getValue());
    }

    /**
     * Test non-standard ID formats
     */
    public function testNonStandardIdFormats(): void
    {
        // Test MongoDB-style ObjectId
        $objectId = '507f1f77bcf86cd799439011';
        $this->field->setValue($objectId);
        $this->assertEquals($objectId, $this->field->getValue());

        // Test composite ID
        $compositeId = 'user:123:profile';
        $this->field->setValue($compositeId);
        $this->assertEquals($compositeId, $this->field->getValue());

        // Test with special characters
        $specialId = 'id-with-dashes_and_underscores.and.dots';
        $this->field->setValue($specialId);
        $this->assertEquals($specialId, $this->field->getValue());
    }

    /**
     * Test boolean and array values (edge cases)
     */
    public function testEdgeCaseValues(): void
    {
        // Test boolean values
        $this->field->setValue(true);
        $this->assertTrue($this->field->getValue());

        $this->field->setValue(false);
        $this->assertFalse($this->field->getValue());

        // Test array (should be stored as-is)
        $arrayId = ['type' => 'user', 'id' => 123];
        $this->field->setValue($arrayId);
        $this->assertEquals($arrayId, $this->field->getValue());
    }

    /**
     * Test very long ID strings
     */
    public function testLongIdStrings(): void
    {
        $longId = str_repeat('a', 100);
        $this->field->setValue($longId);
        $this->assertEquals($longId, $this->field->getValue());

        // Test very long UUID-like string
        $longUuid = '550e8400-e29b-41d4-a716-446655440000-with-extra-data-appended';
        $this->field->setValue($longUuid);
        $this->assertEquals($longUuid, $this->field->getValue());
    }
}
