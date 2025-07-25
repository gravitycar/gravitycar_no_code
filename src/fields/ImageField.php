<?php

namespace Gravitycar\Fields;

use Gravitycar\Core\FieldsBase;
use Gravitycar\Core\GCException;

/**
 * Image field implementation
 *
 * Handles image file uploads with validation and storage.
 */
class ImageField extends FieldsBase
{
    protected string $type = 'Image';
    protected string $phpDataType = 'string';
    protected string $databaseType = 'VARCHAR(500)';
    protected string $uiDataType = 'file';
    protected array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    protected int $maxFileSize = 5242880; // 5MB in bytes
    protected string $uploadPath = 'uploads/images/';

    public function __construct(array $fieldDefinition)
    {
        // Set custom upload path if provided
        if (isset($fieldDefinition['uploadPath'])) {
            $this->uploadPath = $fieldDefinition['uploadPath'];
        }

        // Set custom allowed extensions if provided
        if (isset($fieldDefinition['allowedExtensions'])) {
            $this->allowedExtensions = $fieldDefinition['allowedExtensions'];
        }

        // Set custom max file size if provided
        if (isset($fieldDefinition['maxFileSize'])) {
            $this->maxFileSize = $fieldDefinition['maxFileSize'];
        }

        parent::__construct($fieldDefinition);
    }

    public function getValueForApi(): mixed
    {
        if ($this->value) {
            return [
                'filename' => basename($this->value),
                'url' => $this->getImageUrl(),
                'path' => $this->value
            ];
        }
        return null;
    }

    public function setValueFromDB(mixed $value): void
    {
        $this->value = (string) $value;
        $this->originalValue = $this->value;
        $this->hasChanged = false;
    }

    public function set(string $fieldName, mixed $value, \Gravitycar\Core\ModelBase $model): void
    {
        // Handle file upload
        if (is_array($value) && isset($value['tmp_name'])) {
            $uploadedFile = $this->handleFileUpload($value);
            parent::set($fieldName, $uploadedFile, $model);
        } else {
            parent::set($fieldName, $value, $model);
        }
    }

    private function handleFileUpload(array $fileData): string
    {
        // Validate file upload
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            throw new GCException("File upload failed with error code: " . $fileData['error']);
        }

        // Validate file size
        if ($fileData['size'] > $this->maxFileSize) {
            throw new GCException("File size exceeds maximum allowed size of " . ($this->maxFileSize / 1024 / 1024) . "MB");
        }

        // Validate file extension
        $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new GCException("File type not allowed. Allowed types: " . implode(', ', $this->allowedExtensions));
        }

        // Validate file is actually an image
        $imageInfo = getimagesize($fileData['tmp_name']);
        if ($imageInfo === false) {
            throw new GCException("Uploaded file is not a valid image");
        }

        // Create upload directory if it doesn't exist
        $uploadDir = rtrim($this->uploadPath, '/') . '/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new GCException("Failed to create upload directory: " . $uploadDir);
            }
        }

        // Generate unique filename
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $uploadPath = $uploadDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($fileData['tmp_name'], $uploadPath)) {
            throw new GCException("Failed to move uploaded file to: " . $uploadPath);
        }

        return $uploadPath;
    }

    public function getImageUrl(): string
    {
        if ($this->value) {
            // Convert file path to URL path
            $webPath = str_replace('\\', '/', $this->value);
            return '/' . ltrim($webPath, '/');
        }
        return '';
    }

    public function deleteImage(): bool
    {
        if ($this->value && file_exists($this->value)) {
            return unlink($this->value);
        }
        return false;
    }

    public function getImageDimensions(): ?array
    {
        if ($this->value && file_exists($this->value)) {
            $imageInfo = getimagesize($this->value);
            if ($imageInfo !== false) {
                return [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1],
                    'type' => $imageInfo[2],
                    'mime' => $imageInfo['mime']
                ];
            }
        }
        return null;
    }

    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    public function getUploadPath(): string
    {
        return $this->uploadPath;
    }
}
