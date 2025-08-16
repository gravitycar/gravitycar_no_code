# File Upload System Implementation Plan

## 1. Feature Overview

This plan focuses on implementing a comprehensive file upload and management system for the Gravitycar Framework. The system will provide secure file upload/download capabilities, integration with existing ImageField types, and React-friendly APIs for modern web applications.

## 2. Current State Assessment

**Current State**: ImageField exists but no file upload handling infrastructure
**Impact**: Most React apps need file upload capabilities for user content
**Priority**: MEDIUM - Week 5-6 implementation

### 2.1 Existing Components
- ImageField class for image metadata storage
- Basic field validation system
- REST API infrastructure

### 2.2 Missing Components
- File upload endpoint handling
- Secure file storage management
- File validation and processing
- File metadata storage
- Temporary file cleanup
- Image resizing/processing
- Download/access endpoints

## 3. Requirements

### 3.1 Functional Requirements
- Single and multiple file upload support
- File type validation and restrictions
- File size limits and validation
- Secure file storage with access control
- Image processing (resize, thumbnails)
- File metadata storage and retrieval
- Temporary file cleanup
- Integration with existing ImageField
- Progress tracking for large uploads
- File versioning support

### 3.2 Non-Functional Requirements
- Secure file access with proper authentication
- Performance optimization for large files
- Memory-efficient file processing
- Scalable storage solutions
- Error handling and recovery
- Audit logging for file operations

## 4. Design

### 4.1 Architecture Components

```php
// File Management Service
class FileManager {
    public function uploadFile(array $fileData, array $options = []): File;
    public function uploadMultipleFiles(array $filesData, array $options = []): array;
    public function downloadFile(int $fileId): ?File;
    public function deleteFile(int $fileId): bool;
    public function getFileMetadata(int $fileId): ?array;
}

// File Storage Handler
class FileStorageHandler {
    public function store(string $filePath, string $content): string;
    public function retrieve(string $storagePath): ?string;
    public function delete(string $storagePath): bool;
    public function exists(string $storagePath): bool;
    public function getPublicUrl(string $storagePath): string;
}

// File Validator
class FileValidator {
    public function validateFile(array $fileData, array $rules): ValidationResult;
    public function validateFileType(string $mimeType, array $allowedTypes): bool;
    public function validateFileSize(int $fileSize, int $maxSize): bool;
    public function scanForMalware(string $filePath): bool;
}

// Image Processor
class ImageProcessor {
    public function resize(string $imagePath, int $width, int $height): string;
    public function createThumbnail(string $imagePath, int $size): string;
    public function optimizeImage(string $imagePath): string;
    public function getImageDimensions(string $imagePath): array;
}

// File API Controller
class FileAPIController {
    public function uploadSingle(Request $request): array;
    public function uploadMultiple(Request $request): array;
    public function download(Request $request, int $fileId): Response;
    public function getFileInfo(Request $request, int $fileId): array;
    public function deleteFile(Request $request, int $fileId): array;
}
```

### 4.2 Database Schema

#### Files Table
```sql
CREATE TABLE files (
    id INT PRIMARY KEY AUTO_INCREMENT,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    file_hash VARCHAR(64),
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT,
    is_public BOOLEAN DEFAULT FALSE,
    is_temporary BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_file_hash (file_hash),
    INDEX idx_storage_path (storage_path),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_expires_at (expires_at)
);
```

#### File Associations Table
```sql
CREATE TABLE file_associations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    file_id INT NOT NULL,
    model_type VARCHAR(100) NOT NULL,
    model_id INT NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    INDEX idx_model (model_type, model_id, field_name),
    INDEX idx_file_id (file_id),
    UNIQUE KEY unique_association (file_id, model_type, model_id, field_name)
);
```

#### Image Variants Table
```sql
CREATE TABLE image_variants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    original_file_id INT NOT NULL,
    variant_type VARCHAR(50) NOT NULL, -- 'thumbnail', 'medium', 'large'
    width INT,
    height INT,
    storage_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (original_file_id) REFERENCES files(id) ON DELETE CASCADE,
    INDEX idx_original_file (original_file_id),
    INDEX idx_variant_type (variant_type)
);
```

## 5. Implementation Steps

### 5.1 Phase 1: Core File Management (Week 1)

#### Step 1: File Model and Manager
```php
class File extends ModelBase {
    protected array $fields = [
        'id' => ['type' => 'IDField'],
        'original_name' => ['type' => 'TextField', 'maxLength' => 255],
        'stored_name' => ['type' => 'TextField', 'maxLength' => 255],
        'storage_path' => ['type' => 'TextField', 'maxLength' => 500],
        'mime_type' => ['type' => 'TextField', 'maxLength' => 100],
        'file_size' => ['type' => 'IntegerField'],
        'file_hash' => ['type' => 'TextField', 'maxLength' => 64],
        'upload_date' => ['type' => 'DateTimeField'],
        'uploaded_by' => ['type' => 'IntegerField'],
        'is_public' => ['type' => 'BooleanField'],
        'is_temporary' => ['type' => 'BooleanField'],
        'expires_at' => ['type' => 'DateTimeField', 'nullable' => true],
        'metadata' => ['type' => 'JSONField']
    ];
    
    public function getPublicUrl(): string {
        return $this->storageHandler->getPublicUrl($this->storage_path);
    }
    
    public function getDownloadUrl(): string {
        return "/files/{$this->id}/download";
    }
}
```

#### Step 2: File Storage Handler
```php
class FileStorageHandler {
    private string $uploadPath;
    private string $publicPath;
    
    public function __construct() {
        $this->uploadPath = Config::get('file_upload.storage_path', '/var/www/uploads');
        $this->publicPath = Config::get('file_upload.public_path', '/uploads');
    }
    
    public function store(string $tempFilePath, string $fileName): string {
        $storagePath = $this->generateStoragePath($fileName);
        $fullPath = $this->uploadPath . '/' . $storagePath;
        
        // Create directory if it doesn't exist
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        if (move_uploaded_file($tempFilePath, $fullPath)) {
            return $storagePath;
        }
        
        throw new FileUploadException("Failed to store file: {$fileName}");
    }
    
    private function generateStoragePath(string $fileName): string {
        $hash = md5(uniqid() . $fileName);
        $year = date('Y');
        $month = date('m');
        
        return "{$year}/{$month}/" . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash . '_' . $fileName;
    }
}
```

#### Step 3: File Validator
```php
class FileValidator {
    private array $defaultRules = [
        'max_size' => 10485760, // 10MB
        'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf']
    ];
    
    public function validateFile(array $fileData, array $rules = []): ValidationResult {
        $rules = array_merge($this->defaultRules, $rules);
        $errors = [];
        
        // Check file size
        if ($fileData['size'] > $rules['max_size']) {
            $errors[] = "File size exceeds maximum allowed size of " . $this->formatBytes($rules['max_size']);
        }
        
        // Check MIME type
        if (!in_array($fileData['type'], $rules['allowed_types'])) {
            $errors[] = "File type not allowed. Allowed types: " . implode(', ', $rules['allowed_types']);
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $rules['allowed_extensions'])) {
            $errors[] = "File extension not allowed. Allowed extensions: " . implode(', ', $rules['allowed_extensions']);
        }
        
        return new ValidationResult(empty($errors), $errors);
    }
}
```

### 5.2 Phase 2: API Endpoints (Week 1-2)

#### Step 1: File API Controller
```php
class FileAPIController extends ModelBaseAPIController {
    
    public function uploadSingle(Request $request): array {
        try {
            if (!isset($_FILES['file'])) {
                throw new BadRequestException("No file provided");
            }
            
            $fileData = $_FILES['file'];
            $options = $this->parseUploadOptions($request);
            
            // Validate file
            $validationResult = $this->fileValidator->validateFile($fileData, $options['validation_rules'] ?? []);
            if (!$validationResult->isValid()) {
                throw new UnprocessableEntityException("File validation failed")
                    ->withValidationErrors($validationResult->getErrors());
            }
            
            // Upload file
            $file = $this->fileManager->uploadFile($fileData, $options);
            
            return [
                'success' => true,
                'status' => 201,
                'data' => $this->formatFileResponse($file),
                'timestamp' => date('c')
            ];
            
        } catch (APIException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new InternalServerErrorException("File upload failed: " . $e->getMessage());
        }
    }
    
    public function uploadMultiple(Request $request): array {
        // Similar implementation for multiple files
    }
    
    public function download(Request $request, int $fileId): Response {
        $file = $this->fileManager->getFile($fileId);
        
        if (!$file) {
            throw new NotFoundException("File not found");
        }
        
        // Check permissions
        if (!$this->canDownloadFile($file)) {
            throw new ForbiddenException("Access denied to file");
        }
        
        return $this->createFileDownloadResponse($file);
    }
}
```

#### API Endpoints:
```
POST /files/upload              - Single file upload
POST /files/upload/multiple     - Multiple file upload
GET  /files/{id}               - File metadata
GET  /files/{id}/download      - File download
DELETE /files/{id}             - Delete file
POST /files/{id}/variants      - Create image variants
GET  /files/{id}/variants      - List image variants
```

### 5.3 Phase 3: Image Processing (Week 2)

#### Step 1: Image Processor
```php
class ImageProcessor {
    public function createThumbnail(string $imagePath, int $size = 150): string {
        $image = $this->loadImage($imagePath);
        
        list($width, $height) = getimagesize($imagePath);
        $ratio = min($size / $width, $size / $height);
        
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);
        
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        $thumbnailPath = $this->generateVariantPath($imagePath, 'thumbnail');
        $this->saveImage($thumbnail, $thumbnailPath);
        
        return $thumbnailPath;
    }
    
    public function resize(string $imagePath, int $width, int $height): string {
        // Implementation for image resizing
    }
}
```

#### Step 2: ImageField Integration
```php
class ImageField extends FieldBase {
    protected array $variants = ['thumbnail', 'medium', 'large'];
    
    public function setValue($value): void {
        if ($value instanceof File) {
            // Create image variants automatically
            if ($this->isImage($value->mime_type)) {
                $this->createImageVariants($value);
            }
        }
        
        parent::setValue($value);
    }
    
    private function createImageVariants(File $file): void {
        $processor = new ImageProcessor();
        
        foreach ($this->variants as $variant) {
            $variantPath = $processor->createVariant($file->storage_path, $variant);
            $this->saveImageVariant($file->id, $variant, $variantPath);
        }
    }
}
```

## 6. File Upload API Specification

### 6.1 Single File Upload
```
POST /files/upload
Content-Type: multipart/form-data

Form Data:
- file: [FILE] (required)
- model_type: string (optional) - Associate with model
- model_id: integer (optional) - Associate with model instance
- field_name: string (optional) - Associate with model field
- is_public: boolean (optional, default: false)
- is_temporary: boolean (optional, default: false)
- expires_in: integer (optional) - Expiration in seconds

Response (201):
{
  "success": true,
  "status": 201,
  "data": {
    "id": 123,
    "original_name": "photo.jpg",
    "mime_type": "image/jpeg",
    "file_size": 1024000,
    "upload_date": "2025-08-14T10:30:00+00:00",
    "is_public": false,
    "download_url": "/files/123/download",
    "public_url": null,
    "variants": {
      "thumbnail": "/files/123/variants/thumbnail",
      "medium": "/files/123/variants/medium"
    },
    "metadata": {
      "width": 1920,
      "height": 1080,
      "exif": {...}
    }
  },
  "timestamp": "2025-08-14T10:30:00+00:00"
}
```

### 6.2 File Download
```
GET /files/{id}/download
Authorization: Bearer {token} (if file is not public)

Response:
Content-Type: {file_mime_type}
Content-Disposition: attachment; filename="{original_filename}"
Content-Length: {file_size}

[File content]
```

## 7. React Integration Examples

### 7.1 File Upload Hook
```typescript
const useFileUpload = () => {
  const [uploading, setUploading] = useState(false);
  const [progress, setProgress] = useState(0);
  
  const uploadFile = async (file: File, options?: UploadOptions) => {
    setUploading(true);
    setProgress(0);
    
    const formData = new FormData();
    formData.append('file', file);
    
    if (options) {
      Object.entries(options).forEach(([key, value]) => {
        formData.append(key, value.toString());
      });
    }
    
    try {
      const response = await fetch('/files/upload', {
        method: 'POST',
        body: formData,
        onUploadProgress: (progressEvent) => {
          const progress = (progressEvent.loaded / progressEvent.total) * 100;
          setProgress(progress);
        }
      });
      
      const result = await response.json();
      return result.data;
    } finally {
      setUploading(false);
      setProgress(0);
    }
  };
  
  return { uploadFile, uploading, progress };
};
```

### 7.2 Image Upload Component
```typescript
const ImageUploadField = ({ value, onChange, ...props }) => {
  const { uploadFile, uploading, progress } = useFileUpload();
  
  const handleFileSelect = async (file: File) => {
    try {
      const uploadedFile = await uploadFile(file, {
        model_type: props.modelType,
        model_id: props.modelId,
        field_name: props.fieldName
      });
      
      onChange(uploadedFile);
    } catch (error) {
      console.error('Upload failed:', error);
    }
  };
  
  return (
    <div className="image-upload-field">
      {value && (
        <img src={value.variants?.thumbnail || value.public_url} alt="Preview" />
      )}
      
      <FileDropzone onFileSelect={handleFileSelect} disabled={uploading}>
        {uploading ? (
          <ProgressBar progress={progress} />
        ) : (
          <span>Drop image here or click to select</span>
        )}
      </FileDropzone>
    </div>
  );
};
```

## 8. Security Considerations

### 8.1 File Validation
- Validate file types using both MIME type and file extension
- Scan uploaded files for malware (integrate ClamAV)
- Limit file sizes to prevent DoS attacks
- Validate image files using image processing libraries

### 8.2 Access Control
- Implement proper authentication for file access
- Support public and private files
- File ownership and permission checking
- Secure file URLs with time-limited tokens

### 8.3 Storage Security
- Store files outside web root when possible
- Use random file names to prevent enumeration
- Implement file integrity checking with hashes
- Regular cleanup of temporary and expired files

## 9. Performance Optimization

### 9.1 Upload Optimization
- Chunked upload support for large files
- Resume interrupted uploads
- Background processing for image variants
- Asynchronous file processing

### 9.2 Download Optimization
- HTTP caching headers for static files
- CDN integration support
- Image optimization and compression
- Lazy loading for image variants

## 10. Testing Strategy

### 10.1 Unit Tests
- File validation logic
- Image processing functions
- Storage handler methods
- File model operations

### 10.2 Integration Tests
- End-to-end upload/download flows
- File association with models
- Image variant generation
- Security and access control

### 10.3 Performance Tests
- Large file upload handling
- Concurrent upload performance
- Memory usage during processing
- Storage space management

## 11. Success Criteria

- [ ] Single and multiple file uploads work reliably
- [ ] File validation prevents malicious uploads
- [ ] Image processing creates variants automatically
- [ ] File associations with models function correctly
- [ ] Security controls protect private files
- [ ] Performance is acceptable for typical use cases
- [ ] React integration is smooth and user-friendly
- [ ] Cleanup processes prevent storage bloat

## 12. Dependencies

### 12.1 External Libraries
- GD or ImageMagick for image processing
- ClamAV for malware scanning (optional)
- Cloud storage SDK (optional)

### 12.2 Framework Components
- ModelBase for file models
- Validation system for file rules
- Authentication system for access control
- Exception handling for error management

## 13. Risks and Mitigations

### 13.1 Security Risks
- **Risk**: Malicious file uploads
- **Mitigation**: Comprehensive validation, malware scanning, sandboxed processing

### 13.2 Performance Risks
- **Risk**: Large file processing blocking requests
- **Mitigation**: Background processing, chunked uploads, resource limits

### 13.3 Storage Risks
- **Risk**: Unlimited storage consumption
- **Mitigation**: File quotas, cleanup processes, monitoring

## 14. Estimated Timeline

**Total Time: 2 weeks**

- **Week 1**: Core file management, basic upload/download, validation
- **Week 2**: Image processing, React integration, security hardening

This implementation will provide a robust, secure file upload system that integrates seamlessly with the Gravitycar Framework and supports modern React applications.
