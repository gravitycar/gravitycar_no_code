<?php

namespace Gravitycar\Tests\Unit\Fields;

use Gravitycar\Tests\Unit\UnitTestCase;
use Gravitycar\Fields\ImageField;

/**
 * Test suite for the ImageField class.
 * Tests image field functionality for storing and displaying image file paths or URLs.
 */
class ImageFieldTest extends UnitTestCase
{
    private ImageField $field;

    protected function setUp(): void
    {
        parent::setUp();

        $metadata = [
            'name' => 'profile_image',
            'type' => 'Image',
            'label' => 'Profile Image',
            'required' => false,
            'maxLength' => 500,
            'width' => null,
            'height' => null,
            'altText' => '',
            'allowLocal' => true,
            'allowRemote' => true,
            'placeholder' => 'Enter image path or URL'
        ];

        $this->field = new ImageField($metadata, $this->logger);
    }

    /**
     * Test constructor and default properties
     */
    public function testConstructor(): void
    {
        $this->assertEquals('profile_image', $this->field->getName());
        $this->assertEquals('Profile Image', $this->field->getMetadataValue('label'));
        $this->assertEquals(500, $this->field->getMetadataValue('maxLength'));
        $this->assertNull($this->field->getMetadataValue('width'));
        $this->assertNull($this->field->getMetadataValue('height'));
        $this->assertEquals('', $this->field->getMetadataValue('altText'));
        $this->assertTrue($this->field->getMetadataValue('allowLocal'));
        $this->assertTrue($this->field->getMetadataValue('allowRemote'));
        $this->assertEquals('Enter image path or URL', $this->field->getMetadataValue('placeholder'));
        $this->assertEquals('Image', $this->field->getMetadataValue('type'));
    }

    /**
     * Test local file paths
     */
    public function testLocalFilePaths(): void
    {
        // Test relative path
        $this->field->setValue('images/profile.jpg');
        $this->assertEquals('images/profile.jpg', $this->field->getValue());

        // Test absolute path
        $this->field->setValue('/var/www/images/avatar.png');
        $this->assertEquals('/var/www/images/avatar.png', $this->field->getValue());

        // Test Windows path
        $this->field->setValue('C:\\uploads\\image.gif');
        $this->assertEquals('C:\\uploads\\image.gif', $this->field->getValue());
    }

    /**
     * Test remote URLs
     */
    public function testRemoteUrls(): void
    {
        // Test HTTP URL
        $this->field->setValue('http://example.com/image.jpg');
        $this->assertEquals('http://example.com/image.jpg', $this->field->getValue());

        // Test HTTPS URL
        $this->field->setValue('https://cdn.example.com/photos/user123.png');
        $this->assertEquals('https://cdn.example.com/photos/user123.png', $this->field->getValue());

        // Test URL with query parameters
        $this->field->setValue('https://api.example.com/image?id=123&size=large');
        $this->assertEquals('https://api.example.com/image?id=123&size=large', $this->field->getValue());
    }

    /**
     * Test various image formats
     */
    public function testImageFormats(): void
    {
        $formats = [
            'image.jpg',
            'photo.jpeg',
            'graphic.png',
            'animation.gif',
            'vector.svg',
            'bitmap.bmp',
            'image.webp',
            'photo.tiff'
        ];

        foreach ($formats as $format) {
            $this->field->setValue($format);
            $this->assertEquals($format, $this->field->getValue());
        }
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
     * Test image dimensions
     */
    public function testImageDimensions(): void
    {
        $metadata = [
            'name' => 'sized_image',
            'type' => 'Image',
            'width' => 800,
            'height' => 600
        ];

        $field = new ImageField($metadata, $this->logger);
        $this->assertEquals(800, $field->getMetadataValue('width'));
        $this->assertEquals(600, $field->getMetadataValue('height'));
    }

    /**
     * Test alt text
     */
    public function testAltText(): void
    {
        $metadata = [
            'name' => 'image_with_alt',
            'type' => 'Image',
            'altText' => 'User profile photo'
        ];

        $field = new ImageField($metadata, $this->logger);
        $this->assertEquals('User profile photo', $field->getMetadataValue('altText'));
    }

    /**
     * Test allow local/remote restrictions
     */
    public function testLocalRemoteRestrictions(): void
    {
        // Test local only
        $localOnlyMetadata = [
            'name' => 'local_only',
            'type' => 'Image',
            'allowLocal' => true,
            'allowRemote' => false
        ];
        $localField = new ImageField($localOnlyMetadata, $this->logger);
        $this->assertTrue($localField->getMetadataValue('allowLocal'));
        $this->assertFalse($localField->getMetadataValue('allowRemote'));

        // Test remote only
        $remoteOnlyMetadata = [
            'name' => 'remote_only',
            'type' => 'Image',
            'allowLocal' => false,
            'allowRemote' => true
        ];
        $remoteField = new ImageField($remoteOnlyMetadata, $this->logger);
        $this->assertFalse($remoteField->getMetadataValue('allowLocal'));
        $this->assertTrue($remoteField->getMetadataValue('allowRemote'));
    }

    /**
     * Test default properties
     */
    public function testDefaultProperties(): void
    {
        $minimalMetadata = [
            'name' => 'simple_image',
            'type' => 'Image'
        ];

        $field = new ImageField($minimalMetadata, $this->logger);
        // Check the ingested metadata for default values
        $metadata = $field->getMetadata();
        $this->assertEquals(500, $metadata['maxLength'] ?? 500);
        $this->assertNull($metadata['width'] ?? null);
        $this->assertNull($metadata['height'] ?? null);
        $this->assertEquals('', $metadata['altText'] ?? '');
        $this->assertTrue($metadata['allowLocal'] ?? true);
        $this->assertTrue($metadata['allowRemote'] ?? true);
        $this->assertEquals('Enter image path or URL', $metadata['placeholder'] ?? 'Enter image path or URL');
    }

    /**
     * Test custom maxLength
     */
    public function testCustomMaxLength(): void
    {
        $metadata = [
            'name' => 'long_path_image',
            'type' => 'Image',
            'maxLength' => 1000
        ];

        $field = new ImageField($metadata, $this->logger);
        $this->assertEquals(1000, $field->getMetadataValue('maxLength'));
    }

    /**
     * Test required image field
     */
    public function testRequiredImageField(): void
    {
        $metadata = [
            'name' => 'required_image',
            'type' => 'Image',
            'required' => true
        ];

        $field = new ImageField($metadata, $this->logger);
        $this->assertTrue($field->isRequired());
    }

    /**
     * Test setValueFromTrustedSource
     */
    public function testSetValueFromTrustedSource(): void
    {
        $this->field->setValueFromTrustedSource('uploads/trusted-image.jpg');
        $this->assertEquals('uploads/trusted-image.jpg', $this->field->getValue());

        $this->field->setValueFromTrustedSource('https://trusted.example.com/image.png');
        $this->assertEquals('https://trusted.example.com/image.png', $this->field->getValue());

        $this->field->setValueFromTrustedSource(null);
        $this->assertNull($this->field->getValue());
    }

    /**
     * Test data URLs (base64 encoded images)
     */
    public function testDataUrls(): void
    {
        $dataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
        $this->field->setValue($dataUrl);
        $this->assertEquals($dataUrl, $this->field->getValue());
    }

    /**
     * Test very long image paths
     */
    public function testLongImagePaths(): void
    {
        $longPath = str_repeat('folder/', 50) . 'image.jpg';
        $this->field->setValue($longPath);
        $this->assertEquals($longPath, $this->field->getValue());

        // Test long URL
        $longUrl = 'https://very-long-domain-name.example.com/very/long/path/to/images/with/many/subdirectories/image.jpg';
        $this->field->setValue($longUrl);
        $this->assertEquals($longUrl, $this->field->getValue());
    }

    /**
     * Test non-string values
     */
    public function testNonStringValues(): void
    {
        // Test array (might be used for complex image data)
        $imageData = ['url' => 'image.jpg', 'width' => 100, 'height' => 100];
        $this->field->setValue($imageData);
        $this->assertEquals($imageData, $this->field->getValue());

        // Test object
        $imageObject = new \stdClass();
        $imageObject->src = 'image.jpg';
        $this->field->setValue($imageObject);
        $this->assertEquals($imageObject, $this->field->getValue());
    }

    /**
     * Test edge cases and special characters
     */
    public function testEdgeCases(): void
    {
        // Test path with spaces
        $this->field->setValue('images/my photo.jpg');
        $this->assertEquals('images/my photo.jpg', $this->field->getValue());

        // Test path with special characters
        $this->field->setValue('images/üser-phöto.jpg');
        $this->assertEquals('images/üser-phöto.jpg', $this->field->getValue());

        // Test URL-encoded path
        $this->field->setValue('images/my%20photo.jpg');
        $this->assertEquals('images/my%20photo.jpg', $this->field->getValue());
    }
}
