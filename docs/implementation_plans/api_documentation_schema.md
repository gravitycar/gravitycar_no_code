# API Documentation & Schema System Implementation Plan

## 1. Feature Overview

This plan focuses on implementing a comprehensive API documentation and schema system for the Gravitycar Framework. The system will provide auto-generated OpenAPI/Swagger documentation, interactive API exploration, and metadata endpoints to help React developers understand and consume the APIs effectively.

## 2. Current State Assessment

**Current State**: No API documentation system exists
**Impact**: Essential for React developers to understand available endpoints
**Priority**: HIGH - Week 3-4 implementation

### 2.1 Missing Components
- OpenAPI/Swagger documentation
- Interactive API explorer
- Metadata endpoints for model discovery
- Request/response examples
- Field type to React component mapping

### 2.2 Framework Strengths to Leverage
- Metadata-driven architecture
- Consistent REST API structure
- Field type system
- Validation rules
- Relationship definitions

## 3. Requirements

### 3.1 Functional Requirements
- Auto-generated OpenAPI 3.0 specification
- Interactive Swagger UI for API testing
- Metadata endpoints for runtime discovery
- React component mapping information
- Validation rules for client-side validation
- Request/response examples
- Error response documentation

### 3.2 Non-Functional Requirements
- Real-time documentation updates
- Performance-optimized metadata endpoints
- Searchable documentation
- Mobile-friendly documentation interface
- Version control for API changes

## 4. Design

### 4.1 Architecture Components

```php
// OpenAPI Documentation Generator
class OpenAPIGenerator {
    private MetadataEngine $metadataEngine;
    private APIRouteRegistry $routeRegistry;
    private ReactComponentMapper $componentMapper;
    
    public function generateSpecification(): array;
    public function generateModelSchema(string $modelName): array;
    public function generateEndpointDocumentation(string $endpoint): array;
    public function generateResponseExamples(string $modelName): array;
}

// Metadata API Controller - Uses MetadataEngine and APIRouteRegistry
class MetadataAPIController {
    private MetadataEngine $metadataEngine;
    private APIRouteRegistry $routeRegistry;
    private ReactComponentMapper $componentMapper;
    
    public function getModels(): array;
    public function getModelMetadata(string $modelName): array;
    public function getRelationships(): array;
    public function getValidationRules(string $modelName): array;
    public function getFieldTypes(): array;
    public function getApiEndpoints(): array;
    public function getModelRoutes(string $modelName): array;
}

// React Component Mapper
class ReactComponentMapper {
    private MetadataEngine $metadataEngine;
    
    public function getFieldToComponentMap(): array;
    public function getValidationRulesToReact(array $rules): array;
    public function generateFormSchema(string $modelName): array;
    public function getComponentPropsFromField(array $field): array;
}

// Documentation Cache Manager
class DocumentationCache {
    public function getCachedOpenAPISpec(): ?array;
    public function cacheOpenAPISpec(array $spec): void;
    public function getCachedModelMetadata(string $modelName): ?array;
    public function cacheModelMetadata(string $modelName, array $metadata): void;
    public function getCachedFieldTypes(): ?array;
    public function cacheFieldTypes(array $fieldTypes): void;
    public function clearCache(): void;
    public function clearModelCache(string $modelName): void;
}
```

### 4.2 Required Enhancements to Existing Classes

#### MetadataEngine Enhancements
```php
// Additional methods needed in MetadataEngine
class MetadataEngine {
    // ... existing methods ...
    
    /**
     * Get all available model names from cache
     */
    public function getAvailableModels(): array;
    
    /**
     * Get model summary information for API discovery
     */
    public function getModelSummaries(): array;
    
    /**
     * Get all relationships across all models
     */
    public function getAllRelationships(): array;
    
    /**
     * Get field type definitions for React mapping
     */
    public function getFieldTypeDefinitions(): array;
    
    /**
     * Check if model exists in cache
     */
    public function modelExists(string $modelName): bool;
}
```

#### APIRouteRegistry Enhancements
```php
// Additional methods needed in APIRouteRegistry
class APIRouteRegistry {
    // ... existing methods ...
    
    /**
     * Get all routes for a specific model
     * Uses ModelBaseAPIController wildcard routes as templates for all models
     */
    public function getModelRoutes(string $modelName): array;
    
    /**
     * Generate model-specific routes from ModelBaseAPIController wildcard templates
     */
    private function generateModelRoutesFromDefaults(string $modelName): array;
    
    /**
     * Replace wildcard placeholders with actual model name in route path
     */
    private function replaceModelNameInPath(string $path, array $parameterNames, string $modelName): string;
    
    /**
     * Get routes summary for API documentation
     */
    public function getRoutesSummary(): array;
    
    /**
     * Get endpoint documentation for OpenAPI
     */
    public function getEndpointDocumentation(string $path, string $method): array;
    
    /**
     * Get all unique endpoint paths
     */
    public function getAllEndpointPaths(): array;
    
    /**
     * Get routes grouped by model
     * Includes both model-specific routes and generated routes from ModelBaseAPIController defaults
     */
    public function getRoutesByModel(): array;
}
```

**Key Enhancement**: The `getModelRoutes()` method now properly handles the Gravitycar Framework's wildcard routing system. Most models don't register their own routes - they rely on `ModelBaseAPIController`'s wildcard routes (like `/?` and `/?/?`). The enhanced method:

1. **First checks for model-specific routes** (if a model has overridden the defaults)
2. **Falls back to generating routes from ModelBaseAPIController templates** by:
   - Getting the wildcard route definitions from `ModelBaseAPIController::registerRoutes()`
   - Finding `'modelName'` positions in each route's `parameterNames` array
   - Replacing the corresponding `/?` in the path with the actual model name
   - Converting `/?` → `/Movies`, `/?/?` → `/Movies/{id}`, etc.

This ensures that **every model gets a complete set of API routes** for documentation, whether they're custom or generated from the framework defaults.

**Example**: For the "Movies" model:
```php
// ModelBaseAPIController wildcard route:
[
    'method' => 'GET', 
    'path' => '/?/?',
    'parameterNames' => ['modelName', 'id']
]

// Becomes model-specific route:
[
    'method' => 'GET',
    'path' => '/Movies/{id}', 
    'parameterNames' => ['modelName', 'id'],
    'generated_from' => 'ModelBaseAPIController',
    'target_model' => 'Movies'
]
```

### 4.2 API Documentation Structure

```json
{
  "openapi": "3.0.3",
  "info": {
    "title": "Gravitycar Framework API",
    "version": "1.0.0",
    "description": "Auto-generated API documentation for the Gravitycar Framework"
  },
  "servers": [
    {
      "url": "/",
      "description": "Main API Server"
    }
  ],
  "paths": {
    "/Users": {
      "get": {
        "summary": "List users with pagination and filtering",
        "parameters": [...],
        "responses": {...}
      },
      "post": {
        "summary": "Create a new user",
        "requestBody": {...},
        "responses": {...}
      }
    }
  },
  "components": {
    "schemas": {
      "User": {...},
      "ApiResponse": {...},
      "ValidationError": {...}
    }
  }
}
```

## 5. Implementation Steps

### 5.0 Phase 0: Enhance Existing Framework Classes (Week 1)

Before implementing the MetadataAPIController, we need to enhance the existing MetadataEngine and APIRouteRegistry classes with additional methods required for the documentation system, and add field type discovery to the MetadataEngine.

#### Step 0: Add Field Type Discovery to MetadataEngine

First, we need to enhance the MetadataEngine to discover field types dynamically, similar to how it discovers models. This involves:

1. **Add Description and React Metadata to FieldBase Subclasses**
   Each field class should have static properties for description, React component, and props:

2. **Add Description Metadata to ValidationRuleBase Subclasses**
   Each validation rule class should have a static description property:

```php
// In each ValidationRuleBase subclass (e.g., RequiredValidation.php, EmailValidation.php, etc.)
class RequiredValidation extends ValidationRuleBase {
    protected static string $description = 'Field must have a value';
    
    public function __construct() {
        parent::__construct('Required', 'This field is required.');
    }
    // ... existing code ...
}

class EmailValidation extends ValidationRuleBase {
    protected static string $description = 'Must be a valid email address';
    
    public function __construct() {
        parent::__construct('Email', 'Please enter a valid email address.');
    }
    // ... existing code ...
}

class MaxLengthValidation extends ValidationRuleBase {
    protected static string $description = 'Must not exceed maximum length';
    
    public function __construct(int $maxLength = 255) {
        parent::__construct('MaxLength', 'Field {fieldName} must be at most {maxLength} characters.');
        $this->maxLength = $maxLength;
    }
    // ... existing code ...
}

class AlphanumericValidation extends ValidationRuleBase {
    protected static string $description = 'Must contain only letters and numbers';
    
    public function __construct() {
        parent::__construct('Alphanumeric', 'Field {fieldName} must contain only letters and numbers.');
    }
    // ... existing code ...
}

// Similar pattern for all other validation rule classes...
```

3. **Add Description and React Metadata to FieldBase Subclasses**
   Each field class should have static properties for description, React component, and props:

```php
// In each FieldBase subclass (e.g., TextField.php, EmailField.php, etc.)
class TextField extends FieldBase {
    protected static string $description = 'Single-line text input';
    protected static string $reactComponent = 'TextInput';
    protected static array $reactProps = ['maxLength', 'placeholder'];
    protected static array $openApiSchema = ['type' => 'string'];
    // ... existing code ...
}

class BigTextField extends FieldBase {
    protected static string $description = 'Multi-line text area for large text content';
    protected static string $reactComponent = 'TextArea';
    protected static array $reactProps = ['maxLength', 'placeholder', 'rows'];
    protected static array $openApiSchema = ['type' => 'string'];
    // ... existing code ...
}

class EmailField extends FieldBase {
    protected static string $description = 'Email address input with validation';
    protected static string $reactComponent = 'EmailInput';
    protected static array $reactProps = ['placeholder', 'validation'];
    protected static array $openApiSchema = ['type' => 'string', 'format' => 'email'];
    // ... existing code ...
}

class PasswordField extends FieldBase {
    protected static string $description = 'Secure password input field';
    protected static string $reactComponent = 'PasswordInput';
    protected static array $reactProps = ['placeholder', 'showToggle', 'minLength'];
    protected static array $openApiSchema = ['type' => 'string', 'format' => 'password'];
    // ... existing code ...
}

class BooleanField extends FieldBase {
    protected static string $description = 'True/false checkbox input';
    protected static string $reactComponent = 'Checkbox';
    protected static array $reactProps = ['defaultChecked'];
    protected static array $openApiSchema = ['type' => 'boolean'];
    // ... existing code ...
}

class IntegerField extends FieldBase {
    protected static string $description = 'Numeric input for whole numbers';
    protected static string $reactComponent = 'NumberInput';
    protected static array $reactProps = ['min', 'max', 'step', 'placeholder'];
    protected static array $openApiSchema = ['type' => 'integer'];
    // ... existing code ...
}

class FloatField extends FieldBase {
    protected static string $description = 'Numeric input for decimal numbers';
    protected static string $reactComponent = 'NumberInput';
    protected static array $reactProps = ['min', 'max', 'step', 'placeholder'];
    protected static array $openApiSchema = ['type' => 'number', 'format' => 'float'];
    // ... existing code ...
}

class DateTimeField extends FieldBase {
    protected static string $description = 'Date and time picker input';
    protected static string $reactComponent = 'DatePicker';
    protected static array $reactProps = ['minDate', 'maxDate', 'format'];
    protected static array $openApiSchema = ['type' => 'string', 'format' => 'date-time'];
    // ... existing code ...
}

class DateField extends FieldBase {
    protected static string $description = 'Date picker input (date only, no time)';
    protected static string $reactComponent = 'DatePicker';
    protected static array $reactProps = ['minDate', 'maxDate', 'format'];
    protected static array $openApiSchema = ['type' => 'string', 'format' => 'date'];
    // ... existing code ...
}

class EnumField extends FieldBase {
    protected static string $description = 'Dropdown select from predefined options';
    protected static string $reactComponent = 'Select';
    protected static array $reactProps = ['options', 'placeholder'];
    protected static array $openApiSchema = ['type' => 'string']; // enum values added dynamically from field instance
    // ... existing code ...
}

class MultiEnumField extends FieldBase {
    protected static string $description = 'Multi-select field for choosing multiple values from predefined options';
    protected static string $reactComponent = 'MultiSelect';
    protected static array $reactProps = ['options', 'placeholder'];
    protected static array $openApiSchema = ['type' => 'array', 'items' => ['type' => 'string']]; // enum values added dynamically
    // ... existing code ...
}

class RadioButtonSetField extends FieldBase {
    protected static string $description = 'Radio button group for single selection from multiple options';
    protected static string $reactComponent = 'RadioGroup';
    protected static array $reactProps = ['options', 'direction'];
    protected static array $openApiSchema = ['type' => 'string']; // enum values added dynamically from field instance
    // ... existing code ...
}

class RelatedRecordField extends FieldBase {
    protected static string $description = 'Field for selecting related records from another model';
    protected static string $reactComponent = 'RelatedRecordSelect';
    protected static array $reactProps = ['sourceModel', 'displayField', 'valueField'];
    protected static array $openApiSchema = ['type' => 'integer', 'description' => 'ID of related record'];
    // ... existing code ...
}

class IDField extends FieldBase {
    protected static string $description = 'Auto-incrementing primary key';
    protected static string $reactComponent = 'HiddenInput';
    protected static array $reactProps = [];
    protected static array $openApiSchema = ['type' => 'integer', 'format' => 'int64'];
    // ... existing code ...
}

class ImageField extends FieldBase {
    protected static string $description = 'Display field for image paths or URLs';
    protected static string $reactComponent = 'ImageDisplay';
    protected static array $reactProps = ['alt', 'width', 'height', 'fallback'];
    protected static array $openApiSchema = ['type' => 'string', 'description' => 'Image URL or file path'];
    // ... existing code ...
}
```

4. **Add Abstract OpenAPI Schema Method to FieldBase**

The FieldBase class needs an abstract method that each subclass must implement to generate its OpenAPI schema:

```php
// Add this abstract method to the FieldBase class
abstract class FieldBase {
    // ... existing properties and methods ...
    
    /**
     * Generate OpenAPI schema for this field instance
     * Each field type implements its own logic based on instance metadata
     * 
     * @return array OpenAPI schema array for this field
     */
    abstract public function generateOpenAPISchema(): array;
    
    /**
     * Helper method to get base schema from static property with instance overrides
     */
    protected function getBaseOpenAPISchema(): array {
        $reflection = new \ReflectionClass($this);
        
        // Get base schema from static property
        if ($reflection->hasProperty('openApiSchema') && $reflection->getProperty('openApiSchema')->isStatic()) {
            $baseSchema = $reflection->getStaticPropertyValue('openApiSchema');
        } else {
            // Fallback if no static schema defined
            $baseSchema = ['type' => 'string'];
        }
        
        return $baseSchema;
    }
    
    /**
     * Helper method to add common field properties to schema
     */
    protected function addCommonSchemaProperties(array $schema): array {
        // Add description from label if available
        if (!empty($this->label)) {
            $schema['description'] = $this->label;
        }
        
        // Add default value if specified
        if ($this->default !== null) {
            $schema['default'] = $this->default;
        }
        
        return $schema;
    }
}
```

5. **Implement generateOpenAPISchema() in Each FieldBase Subclass**

Each field type must implement the abstract method to generate its specific OpenAPI schema:

```php
// TextField.php
class TextField extends FieldBase {
    protected static string $description = 'Single-line text input';
    protected static string $reactComponent = 'TextInput';
    protected static array $reactProps = ['maxLength', 'placeholder'];
    protected static array $openApiSchema = ['type' => 'string'];
    
    public function generateOpenAPISchema(): array {
        $schema = $this->getBaseOpenAPISchema();
        
        // Add string-specific constraints
        if ($this->maxLength !== null) {
            $schema['maxLength'] = $this->maxLength;
        }
        
        if ($this->minLength !== null) {
            $schema['minLength'] = $this->minLength;
        }
        
        return $this->addCommonSchemaProperties($schema);
    }
    
    // ... existing code ...
}

// EmailField.php
class EmailField extends FieldBase {
    protected static string $description = 'Email address input with validation';
    protected static string $reactComponent = 'EmailInput';
    protected static array $reactProps = ['placeholder', 'validation'];
    protected static array $openApiSchema = ['type' => 'string', 'format' => 'email'];
    
    public function generateOpenAPISchema(): array {
        $schema = $this->getBaseOpenAPISchema();
        
        // Email fields can have maxLength constraints
        if ($this->maxLength !== null) {
            $schema['maxLength'] = $this->maxLength;
        }
        
        return $this->addCommonSchemaProperties($schema);
    }
    
    // ... existing code ...
}

// IntegerField.php
class IntegerField extends FieldBase {
    protected static string $description = 'Numeric input for whole numbers';
    protected static string $reactComponent = 'NumberInput';
    protected static array $reactProps = ['min', 'max', 'step', 'placeholder'];
    protected static array $openApiSchema = ['type' => 'integer'];
    
    public function generateOpenAPISchema(): array {
        $schema = $this->getBaseOpenAPISchema();
        
        // Add numeric constraints
        if ($this->minValue !== null) {
            $schema['minimum'] = $this->minValue;
        }
        
        if ($this->maxValue !== null) {
            $schema['maximum'] = $this->maxValue;
        }
        
        return $this->addCommonSchemaProperties($schema);
    }
    
    // ... existing code ...
}

// FloatField.php  
class FloatField extends FieldBase {
    protected static string $description = 'Numeric input for decimal numbers';
    protected static string $reactComponent = 'NumberInput';
    protected static array $reactProps = ['min', 'max', 'step', 'placeholder'];
    protected static array $openApiSchema = ['type' => 'number', 'format' => 'float'];
    
    public function generateOpenAPISchema(): array {
        $schema = $this->getBaseOpenAPISchema();
        
        // Add numeric constraints
        if ($this->minValue !== null) {
            $schema['minimum'] = $this->minValue;
        }
        
        if ($this->maxValue !== null) {
            $schema['maximum'] = $this->maxValue;
        }
        
        return $this->addCommonSchemaProperties($schema);
    }
    
    // ... existing code ...
}

// BooleanField.php
class BooleanField extends FieldBase {
    protected static string $description = 'True/false checkbox input';
    protected static string $reactComponent = 'Checkbox';
    protected static array $reactProps = ['defaultChecked'];
    protected static array $openApiSchema = ['type' => 'boolean'];
    
    public function generateOpenAPISchema(): array {
        $schema = $this->getBaseOpenAPISchema();
        return $this->addCommonSchemaProperties($schema);
    }
    
    // ... existing code ...
}

// EnumField.php
class EnumField extends FieldBase {
    protected static string $description = 'Dropdown select from predefined options';
    protected static string $reactComponent = 'Select';
    protected static array $reactProps = ['options', 'placeholder'];
    protected static array $openApiSchema = ['type' => 'string'];
    
    public function generateOpenAPISchema(): array {
        $schema = $this->getBaseOpenAPISchema();
        
        // Add enum values from field instance options
        if (!empty($this->options)) {
            $schema['enum'] = $this->options;
        }
        
        return $this->addCommonSchemaProperties($schema);
    }
    
    // ... existing code ...
}

// MultiEnumField.php
class MultiEnumField extends FieldBase {
    protected static string $description = 'Multi-select field for choosing multiple values from predefined options';
    protected static string $reactComponent = 'MultiSelect';
    protected static array $reactProps = ['options', 'placeholder'];
    protected static array $openApiSchema = ['type' => 'array', 'items' => ['type' => 'string']];
    
    public function generateOpenAPISchema(): array {
        $schema = $this->getBaseOpenAPISchema();
        
        // Add enum values to items schema from field instance options
        if (!empty($this->options)) {
            $schema['items']['enum'] = $this->options;
        }
        
        return $this->addCommonSchemaProperties($schema);
    }
    
    // ... existing code ...
}

// RadioButtonSetField.php
class RadioButtonSetField extends FieldBase {
    protected static string $description = 'Radio button group for single selection from multiple options';
    protected static string $reactComponent = 'RadioGroup';
    protected static array $reactProps = ['options', 'direction'];
    protected static array $openApiSchema = ['type' => 'string'];
    
    public function generateOpenAPISchema(): array {
        $schema = $this->getBaseOpenAPISchema();
        
        // Add enum values from field instance options
        if (!empty($this->options)) {
            $schema['enum'] = $this->options;
        }
        
        return $this->addCommonSchemaProperties($schema);
    }
    
    // ... existing code ...
}

// DateTimeField.php
class DateTimeField extends FieldBase {
    protected static string $description = 'Date and time picker input';
    protected static string $reactComponent = 'DatePicker';
    protected static array $reactProps = ['minDate', 'maxDate', 'format'];
    protected static array $openApiSchema = ['type' => 'string', 'format' => 'date-time'];
    
    public function generateOpenAPISchema(): array {
        $schema = $this->getBaseOpenAPISchema();
        
        // Add date constraints if specified
        if ($this->minDate !== null) {
            $schema['minimum'] = $this->minDate;
        }
        
        if ($this->maxDate !== null) {
            $schema['maximum'] = $this->maxDate;
        }
        
        return $this->addCommonSchemaProperties($schema);
    }
    
    // ... existing code ...
}

// DateField.php
class DateField extends FieldBase {
    protected static string $description = 'Date picker input (date only, no time)';
    protected static string $reactComponent = 'DatePicker';
    protected static array $reactProps = ['minDate', 'maxDate', 'format'];
    protected static array $openApiSchema = ['type' => 'string', 'format' => 'date'];
    
    public function generateOpenAPISchema(): array {
        $schema = $this->getBaseOpenAPISchema();
        
        // Add date constraints if specified
        if ($this->minDate !== null) {
            $schema['minimum'] = $this->minDate;
        }
        
        if ($this->maxDate !== null) {
            $schema['maximum'] = $this->maxDate;
        }
        
        return $this->addCommonSchemaProperties($schema);
    }
    
    // ... existing code ...
}

// RelatedRecordField.php
class RelatedRecordField extends FieldBase {
    protected static string $description = 'Field for selecting related records from another model';
    protected static string $reactComponent = 'RelatedRecordSelect';
    protected static array $reactProps = ['sourceModel', 'displayField', 'valueField'];
    protected static array $openApiSchema = ['type' => 'integer', 'description' => 'ID of related record'];
    
    public function generateOpenAPISchema(): array {
        $schema = $this->getBaseOpenAPISchema();
        
        // Add related model information to description
        if (!empty($this->sourceModel)) {
            $schema['description'] = "ID of related {$this->sourceModel} record";
        }
        
        return $this->addCommonSchemaProperties($schema);
    }
    
    // ... existing code ...
}

// IDField.php
class IDField extends FieldBase {
    protected static string $description = 'Auto-incrementing primary key';
    protected static string $reactComponent = 'HiddenInput';
    protected static array $reactProps = [];
    protected static array $openApiSchema = ['type' => 'integer', 'format' => 'int64'];
    
    public function generateOpenAPISchema(): array {
        $schema = $this->getBaseOpenAPISchema();
        
        // ID fields are usually read-only
        $schema['readOnly'] = true;
        
        return $this->addCommonSchemaProperties($schema);
    }
    
    // ... existing code ...
}

// ImageField.php
class ImageField extends FieldBase {
    protected static string $description = 'Display field for image paths or URLs';
    protected static string $reactComponent = 'ImageDisplay';
    protected static array $reactProps = ['alt', 'width', 'height', 'fallback'];
    protected static array $openApiSchema = ['type' => 'string', 'description' => 'Image URL or file path'];
    
    public function generateOpenAPISchema(): array {
        $schema = $this->getBaseOpenAPISchema();
        
        // Add URL format validation
        $schema['format'] = 'uri';
        
        return $this->addCommonSchemaProperties($schema);
    }
    
    // ... existing code ...
}

// PasswordField.php
class PasswordField extends FieldBase {
    protected static string $description = 'Secure password input field';
    protected static string $reactComponent = 'PasswordInput';
    protected static array $reactProps = ['placeholder', 'showToggle', 'minLength'];
    protected static array $openApiSchema = ['type' => 'string', 'format' => 'password'];
    
    public function generateOpenAPISchema(): array {
        $schema = $this->getBaseOpenAPISchema();
        
        // Add password constraints
        if ($this->minLength !== null) {
            $schema['minLength'] = $this->minLength;
        }
        
        if ($this->maxLength !== null) {
            $schema['maxLength'] = $this->maxLength;
        }
        
        // Password fields are write-only
        $schema['writeOnly'] = true;
        
        return $this->addCommonSchemaProperties($schema);
    }
    
    // ... existing code ...
}

// BigTextField.php
class BigTextField extends FieldBase {
    protected static string $description = 'Multi-line text area for large text content';
    protected static string $reactComponent = 'TextArea';
    protected static array $reactProps = ['maxLength', 'placeholder', 'rows'];
    protected static array $openApiSchema = ['type' => 'string'];
    
    public function generateOpenAPISchema(): array {
        $schema = $this->getBaseOpenAPISchema();
        
        // Add text constraints
        if ($this->maxLength !== null) {
            $schema['maxLength'] = $this->maxLength;
        }
        
        if ($this->minLength !== null) {
            $schema['minLength'] = $this->minLength;
        }
        
        return $this->addCommonSchemaProperties($schema);
    }
    
    // ... existing code ...
}
```

#### **Benefits of Field-Instance-Based OpenAPI Schema Generation:**

- ✅ **No Hard-Coded Field Types**: OpenAPIGenerator no longer needs special handling for each field type
- ✅ **Instance-Specific Schemas**: Each field instance generates schema based on its actual configuration
- ✅ **Extensible Design**: New field types automatically work without changes to OpenAPIGenerator
- ✅ **Proper Encapsulation**: Field classes handle their own schema generation logic
- ✅ **Metadata-Driven**: Uses FieldFactory with actual field metadata from models
- ✅ **Error Recovery**: Fallback schema generation if field creation fails
- ✅ **Framework Consistency**: Uses FieldFactory pattern consistent with rest of framework

3. **Enhanced Field Discovery Method in MetadataEngine**

```php
// Add this method to MetadataEngine class
protected string $fieldsDirPath = 'src/Fields';

/**
 * Scan and discover all FieldBase subclasses dynamically with React metadata
 */
protected function scanAndLoadFieldTypes(): array {
    $fieldTypes = [];
    if (!is_dir($this->fieldsDirPath)) {
        $this->logger->warning("Fields directory not found: {$this->fieldsDirPath}");
        return $fieldTypes;
    }

    $files = scandir($this->fieldsDirPath);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || !str_ends_with($file, '.php')) {
            continue;
        }

        // Skip FieldBase.php itself
        if ($file === 'FieldBase.php') {
            continue;
        }

        $className = str_replace('.php', '', $file);
        $fullClassName = "Gravitycar\\Fields\\{$className}";
        
        // Check if class exists and extends FieldBase
        if (class_exists($fullClassName)) {
            $reflection = new \ReflectionClass($fullClassName);
            
            if ($reflection->isSubclassOf('Gravitycar\\Fields\\FieldBase') && !$reflection->isAbstract()) {
                // Get metadata from static properties using reflection
                $description = $this->getStaticProperty($reflection, 'description', 
                    $this->generateDescriptionFromClassName($className));
                $reactComponent = $this->getStaticProperty($reflection, 'reactComponent', 'TextInput');
                $reactProps = $this->getStaticProperty($reflection, 'reactProps', ['placeholder']);
                $openApiSchema = $this->getStaticProperty($reflection, 'openApiSchema', ['type' => 'string']);
                
                // Use FieldFactory to create instance and get field type
                try {
                    $fieldType = $this->extractFieldTypeFromClassName($className);
                    $instance = FieldFactory::createField($fieldType, []);
                    
                    // For field type definitions, we want to show what validation rules 
                    // this field type is capable of supporting, not specific instance rules
                    $supportedValidationRules = $this->getSupportedValidationRulesForFieldType($fieldType);
                    
                    $fieldTypes[$fieldType] = [
                        'type' => $fieldType,
                        'class' => $fullClassName,
                        'description' => $description,
                        'react_component' => $reactComponent,
                        'react_props' => $reactProps,
                        'openapi_schema' => $openApiSchema,
                        'supported_validation_rules' => $supportedValidationRules,
                        'operators' => $instance->getOperators()
                    ];
                } catch (\Exception $e) {
                    $this->logger->warning("Failed to create field instance for {$className}: " . $e->getMessage());
                    continue;
                }
            }
        }
    }
    
    return $fieldTypes;
}

/**
 * Extract rule name from validation rule class name
 * Converts "Gravitycar\Validation\EmailValidation" to "Email"
 */
private function extractRuleNameFromClass(string $className): string {
    // Extract the class name without namespace
    $shortClassName = substr($className, strrpos($className, '\\') + 1);
    
    // Remove "Validation" suffix if present
    if (str_ends_with($shortClassName, 'Validation')) {
        return str_replace('Validation', '', $shortClassName);
    }
    
    return $shortClassName;
}

/**
 * Extract field type from class name for FieldFactory
 */
private function extractFieldTypeFromClassName(string $className): string {
    // Convert "TextField" to "Text", "EmailField" to "Email", etc.
    if (str_ends_with($className, 'Field')) {
        return str_replace('Field', '', $className);
    }
    return $className;
}

/**
 * Safely get static property value with fallback
 */
private function getStaticProperty(\ReflectionClass $reflection, string $propertyName, $fallback) {
    if ($reflection->hasProperty($propertyName) && $reflection->getProperty($propertyName)->isStatic()) {
        return $reflection->getStaticPropertyValue($propertyName);
    }
    return $fallback;
}

/**
 * Generate fallback description from class name
 */
private function generateDescriptionFromClassName(string $className): string {
    // Convert "TextField" to "Text field"
    $words = preg_split('/(?=[A-Z])/', $className, -1, PREG_SPLIT_NO_EMPTY);
    return implode(' ', array_map('strtolower', $words));
}

/**
 * Get supported validation rules for a field type (what it CAN support, not what's configured)
 */
private function getSupportedValidationRulesForFieldType(string $fieldType): array {
    // Get all available validation rules from ValidationRuleFactory
    $validationRuleFactory = \Gravitycar\Core\ServiceLocator::getValidationRuleFactory();
    $availableRules = $validationRuleFactory->getAvailableValidationRules();
    
    $supportedRules = [];
    foreach ($availableRules as $ruleName) {
        try {
            // Create an instance of the validation rule to get its metadata
            $ruleInstance = $validationRuleFactory->createValidationRule($ruleName);
            
            // Get description from the rule instance using reflection for static property
            $description = $this->getValidationRuleDescription($ruleInstance);
            
            $supportedRules[] = [
                'name' => $ruleName,
                'description' => $description,
                'error_message' => $ruleInstance->getErrorMessage(),
                'javascript_validation' => $ruleInstance->getJavascriptValidation(),
                'class' => get_class($ruleInstance)
            ];
        } catch (\Exception $e) {
            $this->logger->warning("Failed to create validation rule {$ruleName}: " . $e->getMessage());
            continue;
        }
    }
    
    return $supportedRules;
}

/**
 * Get human-readable description from validation rule instance
 */
private function getValidationRuleDescription(\Gravitycar\Validation\ValidationRuleBase $ruleInstance): string {
    $reflection = new \ReflectionClass($ruleInstance);
    $description = $this->getStaticProperty($reflection, 'description', null);
    
    if ($description !== null) {
        return $description;
    }
    
    // Throw exception if no static description property exists
    $className = $reflection->getName();
    throw new \Exception(
        "Validation rule class '{$className}' must define a static \$description property. " .
        "Add: protected static string \$description = 'Your description here';"
    );
}

/**
 * Extract validation rules that a specific field instance actually has configured
 * This is used for model field instances, not field type definitions
 */
private function getFieldValidationRules($fieldInstance): array {
    $rules = [];
    
    // Introspect the field instance's actual validation rules
    // Access the validationRules property through reflection since it's protected
    $reflection = new \ReflectionClass($fieldInstance);
    $validationRulesProperty = $reflection->getProperty('validationRules');
    $validationRulesProperty->setAccessible(true);
    $validationRules = $validationRulesProperty->getValue($fieldInstance);
    
    foreach ($validationRules as $ruleInstance) {
        if ($ruleInstance instanceof \Gravitycar\Validation\ValidationRuleBase) {
            // Extract rule information from the actual instantiated validation rule
            $ruleName = $this->extractRuleNameFromClass(get_class($ruleInstance));
            $rules[] = [
                'name' => $ruleName,
                'error_message' => $ruleInstance->getErrorMessage(),
                'javascript_validation' => $ruleInstance->getJavascriptValidation(),
                'class' => get_class($ruleInstance)
            ];
        }
    }
    
    return $rules;
}
```

4. **Update generateMetadataCache Method**

```php
// Update the generateMetadataCache method to include field types
public function generateMetadataCache(): array {
    $this->initializeServices();
    $this->logger->info('Generating fresh metadata cache...');
    
    $models = $this->scanAndLoadMetadata($this->modelsDirPath);
    $relationships = $this->scanAndLoadMetadata($this->relationshipsDirPath);
    $fieldTypes = $this->scanAndLoadFieldTypes(); // Add this line
    
    return [
        'models' => $models,
        'relationships' => $relationships,
        'field_types' => $fieldTypes, // Add this line
        'generated_at' => date('Y-m-d H:i:s'),
        'cache_version' => '1.0'
    ];
}
```

#### **Benefits of Enhanced ValidationRuleBase Subclasses:**

- ✅ **Self-Describing**: Each validation rule provides its own human-readable description
- ✅ **No Hard-Coding**: Eliminates the match() statement that hard-coded rule descriptions
- ✅ **Consistent Pattern**: Follows the same pattern as FieldBase subclasses with static metadata
- ✅ **Dynamic Discovery**: New validation rules automatically provide their descriptions
- ✅ **Maintainable**: Rule descriptions are maintained alongside the rule logic
- ✅ **Fallback Support**: System gracefully handles rules without static descriptions

#### Step 1: Enhance MetadataEngine
```php
// Add these methods to existing MetadataEngine class
class MetadataEngine {
    // ... existing methods ...
    
    /**
     * Get all available model names from cache
     */
    public function getAvailableModels(): array {
        $cachedMetadata = $this->getCachedMetadata();
        return array_keys($cachedMetadata['models'] ?? []);
    }
    
    /**
     * Get model summary information for API discovery
     */
    public function getModelSummaries(): array {
        $cachedMetadata = $this->getCachedMetadata();
        $summaries = [];
        
        if (isset($cachedMetadata['models'])) {
            foreach ($cachedMetadata['models'] as $modelName => $modelData) {
                $summaries[$modelName] = [
                    'name' => $modelName,
                    'table' => $modelData['table'] ?? strtolower($modelName),
                    'description' => $modelData['description'] ?? ucfirst($modelName) . ' management',
                    'fields_count' => count($modelData['fields'] ?? []),
                    'relationships_count' => count($modelData['relationships'] ?? [])
                ];
            }
        }
        
        return $summaries;
    }
    
    /**
     * Get all relationships across all models
     */
    public function getAllRelationships(): array {
        $cachedMetadata = $this->getCachedMetadata();
        $allRelationships = [];
        
        if (isset($cachedMetadata['models'])) {
            foreach ($cachedMetadata['models'] as $modelName => $modelData) {
                $relationships = $modelData['relationships'] ?? [];
                foreach ($relationships as $relationshipName => $relationshipData) {
                    $allRelationships["{$modelName}.{$relationshipName}"] = array_merge(
                        $relationshipData,
                        ['source_model' => $modelName]
                    );
                }
            }
        }
        
        return $allRelationships;
    }
    
    /**
     * Get field type definitions from cache (dynamically discovered)
     */
    public function getFieldTypeDefinitions(): array {
        $cachedMetadata = $this->getCachedMetadata();
        return $cachedMetadata['field_types'] ?? [];
    }
    
    /**
     * Check if model exists in cache
     */
    public function modelExists(string $modelName): bool {
        $cachedMetadata = $this->getCachedMetadata();
        return isset($cachedMetadata['models'][$modelName]);
    }
}
```

#### Step 2: Enhance APIRouteRegistry
```php
// Add these methods to existing APIRouteRegistry class
class APIRouteRegistry {
    // ... existing methods ...
    
    /**
     * Get all routes for a specific model
     * Uses ModelBaseAPIController wildcard routes as templates for all models
     */
    public function getModelRoutes(string $modelName): array {
        $modelRoutes = [];
        
        // First, look for model-specific routes
        foreach ($this->routes as $route) {
            if ($this->isRouteForModel($route, $modelName)) {
                $modelRoutes[] = $route;
            }
        }
        
        // If no model-specific routes found, generate routes from ModelBaseAPIController defaults
        if (empty($modelRoutes)) {
            $modelRoutes = $this->generateModelRoutesFromDefaults($modelName);
        }
        
        return $modelRoutes;
    }
    
    /**
     * Generate model-specific routes from ModelBaseAPIController wildcard templates
     */
    private function generateModelRoutesFromDefaults(string $modelName): array {
        // Get the default wildcard routes from ModelBaseAPIController
        $modelBaseController = new \Gravitycar\Models\Api\Api\ModelBaseAPIController();
        $defaultRoutes = $modelBaseController->registerRoutes();
        
        $modelRoutes = [];
        
        foreach ($defaultRoutes as $route) {
            // Create a copy of the route for this specific model
            $modelRoute = $route;
            
            // Replace wildcards in the path with actual model name
            $modelRoute['path'] = $this->replaceModelNameInPath($route['path'], $route['parameterNames'], $modelName);
            
            // Add model-specific metadata
            $modelRoute['generated_from'] = 'ModelBaseAPIController';
            $modelRoute['target_model'] = $modelName;
            
            $modelRoutes[] = $modelRoute;
        }
        
        return $modelRoutes;
    }
    
    /**
     * Replace wildcard placeholders with actual model name in route path
     */
    private function replaceModelNameInPath(string $path, array $parameterNames, string $modelName): string {
        // Split path into components
        $pathComponents = explode('/', trim($path, '/'));
        
        // Find the position of 'modelName' in parameterNames and replace corresponding path component
        foreach ($parameterNames as $index => $paramName) {
            if ($paramName === 'modelName' && isset($pathComponents[$index])) {
                $pathComponents[$index] = $modelName;
            }
        }
        
        // Reconstruct the path
        return '/' . implode('/', $pathComponents);
    }
    
    /**
     * Get routes summary for API documentation
     */
    public function getRoutesSummary(): array {
        $summary = [
            'total_routes' => count($this->routes),
            'routes_by_method' => [],
            'routes_by_model' => []
        ];
        
        foreach ($this->routes as $route) {
            // Count by method
            $method = $route['method'];
            $summary['routes_by_method'][$method] = ($summary['routes_by_method'][$method] ?? 0) + 1;
            
            // Count by model
            $modelName = $this->extractModelFromRoute($route);
            if ($modelName) {
                $summary['routes_by_model'][$modelName] = ($summary['routes_by_model'][$modelName] ?? 0) + 1;
            }
        }
        
        return $summary;
    }
    
    /**
     * Get endpoint documentation for OpenAPI
     */
    public function getEndpointDocumentation(string $path, string $method): array {
        foreach ($this->routes as $route) {
            if ($route['path'] === $path && strtoupper($route['method']) === strtoupper($method)) {
                return [
                    'path' => $route['path'],
                    'method' => $route['method'],
                    'apiClass' => $route['apiClass'],
                    'apiMethod' => $route['apiMethod'],
                    'description' => $route['description'] ?? '',
                    'parameters' => $route['parameterNames'] ?? [],
                    'pathComponents' => $route['pathComponents'] ?? []
                ];
            }
        }
        
        return [];
    }
    
    /**
     * Get all unique endpoint paths
     */
    public function getAllEndpointPaths(): array {
        $paths = [];
        foreach ($this->routes as $route) {
            $paths[] = $route['path'];
        }
        return array_unique($paths);
    }
    
    /**
     * Get routes grouped by model
     * Includes both model-specific routes and generated routes from ModelBaseAPIController defaults
     */
    public function getRoutesByModel(): array {
        $routesByModel = [];
        
        // First, collect explicitly registered routes
        foreach ($this->routes as $route) {
            $modelName = $this->extractModelFromRoute($route);
            if ($modelName) {
                if (!isset($routesByModel[$modelName])) {
                    $routesByModel[$modelName] = [];
                }
                $routesByModel[$modelName][] = $route;
            }
        }
        
        // Then, for all available models that don't have explicit routes, 
        // generate routes from ModelBaseAPIController defaults
        $availableModels = \Gravitycar\Factories\ModelFactory::getAvailableModels();
        
        foreach ($availableModels as $modelName) {
            if (!isset($routesByModel[$modelName])) {
                // No explicit routes found, generate from defaults
                $routesByModel[$modelName] = $this->generateModelRoutesFromDefaults($modelName);
            }
        }
        
        return $routesByModel;
    }
    
    /**
     * Check if a route belongs to a specific model
     * Uses parameterNames array to find modelName position for accurate matching
     */
    private function isRouteForModel(array $route, string $modelName): bool {
        // Method 1: Use parameterNames to find modelName position and check path
        if (isset($route['parameterNames']) && isset($route['path'])) {
            $modelNameFromPath = $this->extractModelFromRoutePath($route['path'], $route['parameterNames']);
            if ($modelNameFromPath === $modelName) {
                return true;
            }
        }
        
        // Method 2: Check if the apiClass contains the model name
        if (isset($route['apiClass']) && str_contains($route['apiClass'], $modelName)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Extract model name from route using parameterNames array for accurate positioning
     */
    private function extractModelFromRoute(array $route): ?string {
        // Method 1: Extract from path using parameterNames to find correct position
        if (isset($route['parameterNames']) && isset($route['path'])) {
            $modelName = $this->extractModelFromRoutePath($route['path'], $route['parameterNames']);
            if ($modelName !== null) {
                return $modelName;
            }
        }
        
        // Method 2: Try to extract from apiClass as fallback
        if (isset($route['apiClass']) && preg_match('/(\w+)(?:APIController|Controller)?$/', $route['apiClass'], $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Extract model name from route path using parameterNames array to find modelName position
     */
    private function extractModelFromRoutePath(string $path, array $parameterNames): ?string {
        // Find the index of 'modelName' in parameterNames
        $modelNameIndex = array_search('modelName', $parameterNames);
        
        if ($modelNameIndex === false) {
            return null; // No modelName parameter in this route
        }
        
        // Split path into components and get the component at the modelName index
        $pathComponents = explode('/', trim($path, '/'));
        
        if (isset($pathComponents[$modelNameIndex]) && $pathComponents[$modelNameIndex] !== '?') {
            // If it's not a wildcard, return the actual model name
            return $pathComponents[$modelNameIndex];
        }
        
        return null; // This is a wildcard route template, no specific model name
    }
}
```

### 5.1 Phase 1: Metadata API Endpoints (Week 1)

#### Step 1: Metadata API Controller
```php
class MetadataAPIController {
    private MetadataEngine $metadataEngine;
    private APIRouteRegistry $routeRegistry;
    private ReactComponentMapper $componentMapper;
    private DocumentationCache $cache;
    
    public function __construct() {
        $this->metadataEngine = MetadataEngine::getInstance();
        $this->routeRegistry = new APIRouteRegistry();
        $this->componentMapper = new ReactComponentMapper();
        $this->cache = new DocumentationCache();
    }
    
    public function getModels(): array {
        // Check cache first
        $cached = $this->cache->getCachedModelsList();
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            $cachedMetadata = $this->metadataEngine->getCachedMetadata();
            $models = [];
            
            if (isset($cachedMetadata['models'])) {
                foreach ($cachedMetadata['models'] as $modelName => $modelData) {
                    $routes = $this->routeRegistry->getModelRoutes($modelName);
                    $endpoint = $this->extractPrimaryEndpoint($routes);
                    
                    $models[$modelName] = [
                        'endpoint' => $endpoint,
                        'description' => $modelData['description'] ?? ucfirst($modelName) . ' management',
                        'table' => $modelData['table'] ?? strtolower($modelName),
                        'fields_count' => count($modelData['fields'] ?? []),
                        'relationships_count' => count($modelData['relationships'] ?? []),
                        'operations' => $this->getAvailableOperations($routes)
                    ];
                }
            }
            
            $result = [
                'success' => true,
                'data' => $models,
                'timestamp' => date('c')
            ];
            
            // Cache the result
            $this->cache->cacheModelsList($result);
            
            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to retrieve models: ' . $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }
    
    public function getModelMetadata(string $modelName): array {
        // Check cache first
        $cached = $this->cache->getCachedModelMetadata($modelName);
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            if (!$this->metadataEngine->modelExists($modelName)) {
                return [
                    'success' => false,
                    'error' => "Model '{$modelName}' not found",
                    'timestamp' => date('c')
                ];
            }
            
            $modelData = $this->metadataEngine->getModelMetadata($modelName);
            $routes = $this->routeRegistry->getModelRoutes($modelName);
            
            $responseData = [
                'name' => $modelData['name'],
                'table' => $modelData['table'],
                'description' => $modelData['description'] ?? ucfirst($modelName) . ' management',
                'fields' => $this->enhanceFieldsWithReactInfo($modelData['fields'] ?? []),
                'relationships' => $modelData['relationships'] ?? [],
                'api_endpoints' => $this->formatApiEndpoints($routes),
                'react_form_schema' => $this->componentMapper->generateFormSchema($modelName)
            ];
            
            $result = [
                'success' => true,
                'data' => $responseData,
                'timestamp' => date('c')
            ];
            
            // Cache the result
            $this->cache->cacheModelMetadata($modelName, $result);
            
            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to retrieve model metadata: ' . $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }
    
    public function getFieldTypes(): array {
        // Check cache first
        $cached = $this->cache->getCachedFieldTypes();
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            $fieldTypeDefinitions = $this->metadataEngine->getFieldTypeDefinitions();
            $componentMap = $this->componentMapper->getFieldToComponentMap();
            
            $fieldTypes = [];
            foreach ($fieldTypeDefinitions as $fieldType => $definition) {
                $fieldTypes[$fieldType] = [
                    'description' => $definition['description'] ?? '',
                    'react_component' => $componentMap[$fieldType]['component'] ?? 'TextInput',
                    'props' => $componentMap[$fieldType]['props'] ?? [],
                    'validation_rules' => $definition['validation_rules'] ?? []
                ];
            }
            
            $result = [
                'success' => true,
                'data' => $fieldTypes,
                'timestamp' => date('c')
            ];
            
            // Cache the result
            $this->cache->cacheFieldTypes($result);
            
            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to retrieve field types: ' . $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }
    
    private function extractPrimaryEndpoint(array $routes): string {
        foreach ($routes as $route) {
            if ($route['method'] === 'GET' && !str_contains($route['path'], '{')) {
                return $route['path'];
            }
        }
        return '/Unknown';
    }
    
    private function getAvailableOperations(array $routes): array {
        $operations = [];
        foreach ($routes as $route) {
            switch ($route['method']) {
                case 'GET':
                    $operations[] = str_contains($route['path'], '{') ? 'read' : 'list';
                    break;
                case 'POST':
                    $operations[] = 'create';
                    break;
                case 'PUT':
                case 'PATCH':
                    $operations[] = 'update';
                    break;
                case 'DELETE':
                    $operations[] = 'delete';
                    break;
            }
        }
        return array_unique($operations);
    }
    
    private function enhanceFieldsWithReactInfo(array $fields): array {
        $enhancedFields = [];
        foreach ($fields as $fieldName => $fieldData) {
            $enhancedFields[$fieldName] = $fieldData;
            $enhancedFields[$fieldName]['react_component'] = 
                $this->componentMapper->getReactComponentForField($fieldData);
            $enhancedFields[$fieldName]['validation'] = 
                $this->componentMapper->getReactValidationRules($fieldData);
        }
        return $enhancedFields;
    }
    
    private function formatApiEndpoints(array $routes): array {
        $endpoints = [];
        foreach ($routes as $route) {
            $key = strtolower($route['method']);
            if (str_contains($route['path'], '{')) {
                $key .= '_by_id';
            }
            $endpoints[$key] = $route['method'] . ' ' . $route['path'];
        }
        return $endpoints;
    }
    
    /**
     * Clear documentation cache (for development use)
     */
    public function clearDocumentationCache(): array {
        try {
            $this->cache->clearCache();
            return [
                'success' => true,
                'message' => 'Documentation cache cleared successfully',
                'timestamp' => date('c')
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to clear cache: ' . $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }
}
```

#### API Endpoints to Implement:
```
GET /metadata/models              - List all available models
GET /metadata/models/{model}      - Specific model metadata
GET /metadata/relationships       - All relationship definitions
GET /metadata/field-types         - Available field types and React mappings
GET /metadata/validation-rules    - Validation rules catalog
GET /help                        - List all available endpoints
GET /openapi.json               - OpenAPI specification
POST /metadata/cache/clear       - Clear documentation cache (development)
```

### 5.2 Phase 2: OpenAPI Generation (Week 1-2)

#### Step 1: OpenAPI Generator
```php
class OpenAPIGenerator {
    public function generateSpecification(): array {
        $spec = [
            'openapi' => '3.0.3',
            'info' => $this->generateInfo(),
            'servers' => $this->generateServers(),
            'paths' => $this->generatePaths(),
            'components' => $this->generateComponents()
        ];
        
        return $spec;
    }
    
    private function generatePaths(): array {
        $paths = [];
        $models = ModelFactory::getAvailableModels();
        
        foreach ($models as $modelName) {
            $endpoint = "/{$modelName}";
            $paths[$endpoint] = $this->generateModelEndpoints($modelName);
        }
        
        return $paths;
    }
    
    private function generateModelEndpoints(string $modelName): array {
        return [
            'get' => $this->generateListEndpoint($modelName),
            'post' => $this->generateCreateEndpoint($modelName)
        ];
    }
}
```

#### Step 2: OpenAPI Generator
```php
class OpenAPIGenerator {
    private MetadataEngine $metadataEngine;
    private APIRouteRegistry $routeRegistry;
    private ReactComponentMapper $componentMapper;
    private DocumentationCache $cache;
    
    public function __construct() {
        $this->metadataEngine = MetadataEngine::getInstance();
        $this->routeRegistry = new APIRouteRegistry();
        $this->componentMapper = new ReactComponentMapper();
        $this->cache = new DocumentationCache();
    }
    
    public function generateSpecification(): array {
        // Check cache first
        $cached = $this->cache->getCachedOpenAPISpec();
        if ($cached !== null) {
            return $cached;
        }
        
        $spec = [
            'openapi' => '3.0.3',
            'info' => $this->generateInfo(),
            'servers' => $this->generateServers(),
            'paths' => $this->generatePaths(),
            'components' => $this->generateComponents()
        ];
        
        // Cache the generated specification
        $this->cache->cacheOpenAPISpec($spec);
        
        return $spec;
    }
    
    private function generatePaths(): array {
        $paths = [];
        $routes = $this->routeRegistry->getRoutes();
        
        foreach ($routes as $route) {
            $path = $route['path'];
            $method = strtolower($route['method']);
            
            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }
            
            $paths[$path][$method] = $this->generateOperationFromRoute($route);
        }
        
        return $paths;
    }
    
    private function generateOperationFromRoute(array $route): array {
        $modelName = $this->extractModelNameFromRoute($route);
        
        $operation = [
            'summary' => $this->generateOperationSummary($route, $modelName),
            'operationId' => $this->generateOperationId($route, $modelName),
            'tags' => [$modelName],
            'responses' => $this->generateResponsesForRoute($route, $modelName)
        ];
        
        // Add parameters for GET requests with path parameters
        if ($route['method'] === 'GET' && str_contains($route['path'], '{')) {
            $operation['parameters'] = $this->generatePathParameters($route);
        }
        
        // Add request body for POST/PUT requests
        if (in_array($route['method'], ['POST', 'PUT', 'PATCH'])) {
            $operation['requestBody'] = $this->generateRequestBody($modelName);
        }
        
        return $operation;
    }
    
    private function extractModelNameFromRoute(array $route): string {
        // Extract model name from route path or apiClass
        $path = trim($route['path'], '/');
        $pathParts = explode('/', $path);
        return $pathParts[0] ?? 'Unknown';
    }
    
    private function generateComponents(): array {
        $schemas = [];
        $cachedMetadata = $this->metadataEngine->getCachedMetadata();
        
        if (isset($cachedMetadata['models'])) {
            foreach ($cachedMetadata['models'] as $modelName => $modelData) {
                $schemas[$modelName] = $this->generateModelSchema($modelName, $modelData);
            }
        }
        
        // Add common response schemas
        $schemas['ApiResponse'] = [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean'],
                'status' => ['type' => 'integer'],
                'data' => ['type' => 'object'],
                'timestamp' => ['type' => 'string', 'format' => 'date-time']
            ]
        ];
        
        $schemas['ValidationError'] = [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean', 'example' => false],
                'status' => ['type' => 'integer', 'example' => 400],
                'error' => ['type' => 'string'],
                'validation_errors' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'array',
                        'items' => ['type' => 'string']
                    ]
                ],
                'timestamp' => ['type' => 'string', 'format' => 'date-time']
            ]
        ];
        
        return ['schemas' => $schemas];
    }
    
    private function generateModelSchema(string $modelName, array $modelData): array {
        $properties = [];
        $required = [];
        
        $fields = $modelData['fields'] ?? [];
        foreach ($fields as $fieldName => $fieldData) {
            $properties[$fieldName] = $this->generateFieldSchema($fieldData);
            if ($fieldData['required'] ?? false) {
                $required[] = $fieldName;
            }
        }
        
        $schema = [
            'type' => 'object',
            'properties' => $properties
        ];
        
        if (!empty($required)) {
            $schema['required'] = $required;
        }
        
        return $schema;
    }
    
    private function generateFieldSchema(array $fieldData): array {
        try {
            $fieldType = $fieldData['type'] ?? 'Text';
            
            // Use FieldFactory to create a field instance with the specific metadata
            $fieldFactory = \Gravitycar\Core\ServiceLocator::getFieldFactory();
            $fieldInstance = $fieldFactory->createField($fieldType, $fieldData);
            
            // Let the field instance generate its own OpenAPI schema
            return $fieldInstance->generateOpenAPISchema();
            
        } catch (\Exception $e) {
            $this->logger->warning("Failed to generate schema for field type {$fieldType}: " . $e->getMessage());
            
            // Fallback to basic string schema if field creation fails
            return [
                'type' => 'string',
                'description' => $fieldData['label'] ?? 'Field'
            ];
        }
    }
}
```

### 5.3 Phase 3: React Integration Metadata (Week 2)

#### Step 1: React Component Mapper
```php
class ReactComponentMapper {
    private MetadataEngine $metadataEngine;
    private array $fieldComponentMap;
    
    public function __construct() {
        $this->metadataEngine = MetadataEngine::getInstance();
        $this->initializeFieldComponentMap();
    }
    
    private function initializeFieldComponentMap(): void {
        // Get dynamically discovered field types from cache
        $fieldTypes = $this->metadataEngine->getFieldTypeDefinitions();
        
        // Build component map from cached metadata
        $this->fieldComponentMap = [];
        foreach ($fieldTypes as $fieldType => $fieldData) {
            $this->fieldComponentMap[$fieldType] = [
                'component' => $fieldData['react_component'] ?? 'TextInput',
                'props' => $fieldData['react_props'] ?? ['placeholder']
            ];
        }
    }
    
    public function generateFormSchema(string $modelName): array {
        try {
            $modelData = $this->metadataEngine->getModelMetadata($modelName);
            $fields = $modelData['fields'] ?? [];
            
            $formSchema = [
                'model' => $modelName,
                'layout' => 'vertical',
                'fields' => []
            ];
            
            foreach ($fields as $fieldName => $fieldData) {
                $formSchema['fields'][$fieldName] = [
                    'type' => $fieldData['type'],
                    'component' => $this->getReactComponentForField($fieldData),
                    'label' => $fieldData['label'] ?? ucfirst(str_replace('_', ' ', $fieldName)),
                    'required' => $fieldData['required'] ?? false,
                    'readOnly' => $fieldData['readOnly'] ?? false,
                    'validation' => $this->getReactValidationRules($fieldData),
                    'props' => $this->getComponentPropsFromField($fieldData)
                ];
            }
            
            return $formSchema;
        } catch (\Exception $e) {
            throw new \Exception("Failed to generate form schema for {$modelName}: " . $e->getMessage());
        }
    }
    
    public function getReactComponentForField(array $fieldData): string {
        $fieldType = $fieldData['type'] ?? 'Text';
        return $this->fieldComponentMap[$fieldType]['component'] ?? 'TextInput';
    }
    
    public function getReactValidationRules(array $fieldData): array {
        $validationRules = $fieldData['validationRules'] ?? [];
        $reactValidation = [];
        
        // Map from metadata validation rules to React validation
        foreach ($validationRules as $rule) {
            switch ($rule) {
                case 'Required':
                    $reactValidation['required'] = true;
                    break;
                case 'Email':
                    $reactValidation['email'] = true;
                    break;
                case 'DateTime':
                    $reactValidation['datetime'] = true;
                    break;
            }
        }
        
        // Add field-specific validation from metadata
        if (isset($fieldData['required']) && $fieldData['required']) {
            $reactValidation['required'] = true;
        }
        
        if (isset($fieldData['unique']) && $fieldData['unique']) {
            $reactValidation['unique'] = true;
        }
        
        if (isset($fieldData['max_length'])) {
            $reactValidation['maxLength'] = $fieldData['max_length'];
        }
        
        return $reactValidation;
    }
    
    public function getComponentPropsFromField(array $fieldData): array {
        $fieldType = $fieldData['type'] ?? 'Text';
        $baseProps = $this->fieldComponentMap[$fieldType]['props'] ?? [];
        $props = [];
        
        // Map metadata properties to component props
        foreach ($baseProps as $prop) {
            switch ($prop) {
                case 'maxLength':
                    if (isset($fieldData['max_length'])) {
                        $props['maxLength'] = $fieldData['max_length'];
                    }
                    break;
                case 'placeholder':
                    $props['placeholder'] = $fieldData['placeholder'] ?? 
                                          'Enter ' . strtolower($fieldData['label'] ?? $fieldData['name']);
                    break;
                case 'defaultChecked':
                    $props['defaultChecked'] = $fieldData['default'] ?? false;
                    break;
            }
        }
        
        return $props;
    }
    
    public function getFieldToComponentMap(): array {
        return $this->fieldComponentMap;
    }
}
```

#### Step 2: Validation Rule Mapping
```php
private function getReactValidation(FieldBase $field): array {
    $validationRules = $field->getValidationRules();
    $reactValidation = [];
    
    foreach ($validationRules as $rule) {
        switch (get_class($rule)) {
            case 'RequiredValidationRule':
                $reactValidation['required'] = true;
                break;
            case 'MaxLengthValidationRule':
                $reactValidation['maxLength'] = $rule->getMaxLength();
                break;
            case 'EmailValidationRule':
                $reactValidation['email'] = true;
                break;
            case 'RangeValidationRule':
                $reactValidation['min'] = $rule->getMin();
                $reactValidation['max'] = $rule->getMax();
                break;
        }
    }
    
    return $reactValidation;
}
```

### 5.4 Phase 4: Documentation UI (Week 2)

#### Step 1: Swagger UI Integration
```php
class DocumentationController {
    public function showSwaggerUI(): string {
        return $this->renderTemplate('swagger-ui.html', [
            'spec_url' => '/openapi.json',
            'title' => 'Gravitycar Framework API Documentation'
        ]);
    }
    
    public function showReactDocs(): string {
        return $this->renderTemplate('react-docs.html', [
            'models_url' => '/metadata/models',
            'components_url' => '/metadata/field-types'
        ]);
    }
}
```

#### Step 2: Custom Documentation Interface
- Create documentation dashboard
- Model explorer interface
- API endpoint testing
- React component examples

## 6. Metadata API Specification

### 6.1 Models Endpoint
```
GET /metadata/models

Response:
{
  "success": true,
  "status": 200,
  "data": {
    "Users": {
      "endpoint": "/Users",
      "description": "User management and authentication",
      "table": "users",
      "fields_count": 8,
      "relationships_count": 2,
      "operations": ["create", "read", "update", "delete"]
    },
    "Movies": {
      "endpoint": "/Movies", 
      "description": "Movie catalog management",
      "table": "movies",
      "fields_count": 12,
      "relationships_count": 3,
      "operations": ["create", "read", "update", "delete"]
    },
    "MovieQuotes": {
      "endpoint": "/MovieQuotes",
      "description": "MovieQuotes management",
      "table": "movie_quotes", 
      "fields_count": 5,
      "relationships_count": 1,
      "operations": ["create", "read", "update", "delete"]
    }
  },
  "timestamp": "2025-08-19T10:30:00+00:00"
}
```

### 6.2 Model Metadata Endpoint
```
GET /metadata/models/Users

Response:
{
  "success": true,
  "status": 200,
  "data": {
    "name": "Users",
    "table": "users",
    "description": "User management and authentication",
    "fields": {
      "id": {
        "name": "id",
        "type": "ID",
        "label": "User ID",
        "required": true,
        "readOnly": true,
        "unique": true,
        "react_component": "HiddenInput",
        "validation": {}
      },
      "email": {
        "name": "email",
        "type": "Email",
        "label": "Email Address",
        "required": true,
        "unique": true,
        "react_component": "EmailInput",
        "validation": {
          "required": true,
          "email": true,
          "unique": true
        }
      }
    },
    "relationships": {
      "roles": {
        "type": "HasMany",
        "target": "Roles",
        "foreign_key": "user_id"
      }
    },
    "api_endpoints": {
      "list": "GET /Users",
      "create": "POST /Users",
      "read": "GET /Users/{id}",
      "update": "PUT /Users/{id}",
      "delete": "DELETE /Users/{id}"
    },
    "react_form_schema": {
      "model": "Users",
      "layout": "vertical",
      "fields": {
        "email": {
          "type": "Email",
          "component": "EmailInput",
          "label": "Email Address",
          "required": true,
          "readOnly": false,
          "validation": {
            "required": true,
            "email": true,
            "unique": true
          },
          "props": {
            "placeholder": "Enter email address"
          }
        }
      }
    }
  },
  "timestamp": "2025-08-19T10:30:00+00:00"
}
```

### 6.3 Field Types Endpoint
```
GET /metadata/field-types

Response:
{
  "success": true,
  "status": 200,
  "data": {
    "Text": {
      "description": "Single-line text input",
      "react_component": "TextInput",
      "props": ["maxLength", "placeholder"],
      "supported_validation_rules": [
        {
          "name": "Required",
          "description": "Field must have a value",
          "error_message": "This field is required.",
          "javascript_validation": "function validateRequired(value, fieldName) { ... }",
          "class": "Gravitycar\\Validation\\RequiredValidation"
        },
        {
          "name": "MaxLength",
          "description": "Must not exceed maximum length",
          "error_message": "Field {fieldName} must be at most {maxLength} characters.",
          "javascript_validation": "function validateMaxLength(value, fieldName, maxLength) { ... }",
          "class": "Gravitycar\\Validation\\MaxLengthValidation"
        },
        {
          "name": "Alphanumeric",
          "description": "Must contain only letters and numbers",
          "error_message": "Field {fieldName} must contain only letters and numbers.",
          "javascript_validation": "function validateAlphanumeric(value, fieldName) { ... }",
          "class": "Gravitycar\\Validation\\AlphanumericValidation"
        }
        // ... all available validation rules that could be applied to Text fields
      ]
    },
    "Email": {
      "description": "Email address input with validation",
      "react_component": "EmailInput", 
      "props": ["placeholder"],
      "supported_validation_rules": [
        {
          "name": "Required",
          "description": "Field must have a value",
          "error_message": "This field is required.",
          "javascript_validation": "function validateRequired(value, fieldName) { ... }",
          "class": "Gravitycar\\Validation\\RequiredValidation"
        },
        {
          "name": "Email",
          "description": "Must be a valid email address",
          "error_message": "Please enter a valid email address.",
          "javascript_validation": "function validateEmail(value, fieldName) { ... }",
          "class": "Gravitycar\\Validation\\EmailValidation"
        }
        // ... all available validation rules
      ]
    },
    "Boolean": {
      "description": "True/false checkbox input",
      "react_component": "Checkbox",
      "props": ["defaultChecked"],
      "validation_rules": [
        {
          "name": "Required",
          "error_message": "This field is required.",
          "javascript_validation": "function validateRequired(value, fieldName) { ... }",
          "class": "Gravitycar\\Validation\\RequiredValidation"
        }
      ]
    },
    "ID": {
      "description": "Auto-incrementing primary key",
      "react_component": "HiddenInput",
      "props": [],
      "validation_rules": []
    }
    // ... other field types follow the same pattern with actual validation rule objects
  },
  "timestamp": "2025-08-19T10:30:00+00:00"
}
```

*Note: This endpoint returns **field type capabilities** - all validation rules that could potentially be applied to each field type. This differs from model field instances which show only the rules actually configured for specific fields.*

*For example:*
- *Field Types API: `TextField` supports `['Required', 'MaxLength', 'Alphanumeric', ...]` (all possible rules)*
- *Model Metadata API: `Users.first_name` has `['Alphanumeric']` (only configured rules)*
- *Model Metadata API: `Users.last_name` has `['Required', 'Alphanumeric']` (only configured rules)*

*React applications use model field instances for actual validation, and field type capabilities for understanding what validation options are available.*

## 7. React Integration Examples

### 7.1 Enhanced Validation Rule Introspection

**Key Enhancement**: The system now distinguishes between **field type capabilities** and **field instance configurations**:

#### **Two-Level Validation Rule Discovery:**

1. **Field Type Capabilities** (`/metadata/field-types`):
   - Shows **all possible validation rules** that could be applied to each field type
   - Uses `ValidationRuleFactory::getAvailableValidationRules()` to discover all rules
   - Provides React with complete validation rule catalog for UI building

2. **Model Field Instance Rules** (`/metadata/models/{model}`):
   - Shows **only the validation rules actually configured** on specific model fields
   - Uses reflection to access each field's `validationRules` property
   - Provides React with exact validation rules needed for each field

#### **Example Data Flow:**
```
TextField class (no default validation rules)
  ↓
Users.first_name field → configured with ['Alphanumeric'] in metadata
Users.last_name field → configured with ['Required', 'Alphanumeric'] in metadata
  ↓
React receives field-specific rules, not class defaults
```

**React Compatibility**: 
- ✅ **Field Type Discovery**: React can discover all available validation rules for building UIs
- ✅ **Instance-Specific Rules**: React gets exact validation rules configured for each model field
- ✅ **JavaScript Validation**: Each rule provides `getJavascriptValidation()` for client-side validation
- ✅ **No Conflicts**: TextField having no defaults doesn't affect configured field instances

### 7.2 API Discovery Hook
```typescript
const useApiMetadata = () => {
  const { data: models } = useQuery(['api-models'], () =>
    fetch('/metadata/models').then(res => res.json())
  );
  
  const getModelMetadata = (modelName: string) => {
    return useQuery(['model-metadata', modelName], () =>
      fetch(`/metadata/models/${modelName}`).then(res => res.json())
    );
  };
  
  return { models, getModelMetadata };
};
```

### 7.3 Dynamic Form Generation with Enhanced Validation
```typescript
const DynamicForm = ({ modelName }: { modelName: string }) => {
  const { data: metadata } = useQuery(['model-metadata', modelName], () =>
    fetch(`/metadata/models/${modelName}`).then(res => res.json())
  );
  
  const { data: fieldTypes } = useQuery(['field-types'], () =>
    fetch('/metadata/field-types').then(res => res.json())
  );
  
  if (!metadata || !fieldTypes) return <div>Loading...</div>;
  
  // Create client-side validation functions from field type definitions
  const createValidationFunctions = (fieldType: string) => {
    const fieldTypeDef = fieldTypes.data[fieldType];
    if (!fieldTypeDef?.validation_rules) return {};
    
    const validationFunctions = {};
    fieldTypeDef.validation_rules.forEach(rule => {
      if (rule.javascript_validation) {
        // Execute the JavaScript validation function to create a usable validator
        const validatorFunc = new Function('return ' + rule.javascript_validation)();
        validationFunctions[rule.name.toLowerCase()] = validatorFunc;
      }
    });
    
    return validationFunctions;
  };
  
  return (
    <form>
      {Object.entries(metadata.data.fields).map(([fieldName, field]) => {
        const Component = getReactComponent(field.react_component);
        const validators = createValidationFunctions(field.type);
        
        return (
          <Component
            key={fieldName}
            name={fieldName}
            label={field.label}
            required={field.required}
            validation={field.validation}
            validators={validators} // Pass client-side validation functions
            {...field.props}
          />
        );
      })}
    </form>
  );
};
```

## 8. Caching Strategy

### 8.1 Documentation Cache
```php
class DocumentationCache {
    private const CACHE_DIR = 'cache/documentation/';
    
    public function __construct() {
        if (!is_dir(self::CACHE_DIR)) {
            mkdir(self::CACHE_DIR, 0755, true);
        }
    }
    
    public function getCachedOpenAPISpec(): ?array {
        $cacheFile = self::CACHE_DIR . 'openapi_spec.php';
        if (file_exists($cacheFile)) {
            return include $cacheFile;
        }
        return null;
    }
    
    public function cacheOpenAPISpec(array $spec): void {
        $cacheFile = self::CACHE_DIR . 'openapi_spec.php';
        $content = '<?php return ' . var_export($spec, true) . ';';
        file_put_contents($cacheFile, $content);
    }
    
    public function getCachedModelMetadata(string $modelName): ?array {
        $cacheFile = self::CACHE_DIR . "model_{$modelName}.php";
        if (file_exists($cacheFile)) {
            return include $cacheFile;
        }
        return null;
    }
    
    public function cacheModelMetadata(string $modelName, array $metadata): void {
        $cacheFile = self::CACHE_DIR . "model_{$modelName}.php";
        $content = '<?php return ' . var_export($metadata, true) . ';';
        file_put_contents($cacheFile, $content);
    }
    
    public function getCachedModelsList(): ?array {
        $cacheFile = self::CACHE_DIR . 'models_list.php';
        if (file_exists($cacheFile)) {
            return include $cacheFile;
        }
        return null;
    }
    
    public function cacheModelsList(array $modelsList): void {
        $cacheFile = self::CACHE_DIR . 'models_list.php';
        $content = '<?php return ' . var_export($modelsList, true) . ';';
        file_put_contents($cacheFile, $content);
    }
    
    public function getCachedFieldTypes(): ?array {
        $cacheFile = self::CACHE_DIR . 'field_types.php';
        if (file_exists($cacheFile)) {
            return include $cacheFile;
        }
        return null;
    }
    
    public function cacheFieldTypes(array $fieldTypes): void {
        $cacheFile = self::CACHE_DIR . 'field_types.php';
        $content = '<?php return ' . var_export($fieldTypes, true) . ';';
        file_put_contents($cacheFile, $content);
    }
    
    public function clearCache(): void {
        $files = glob(self::CACHE_DIR . '*.php');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    public function clearModelCache(string $modelName): void {
        $cacheFile = self::CACHE_DIR . "model_{$modelName}.php";
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }
}
```

### 8.2 Cache Management
- **Manual Cache Clearing**: Provides methods to clear all cache or specific model cache
- **Simple File-Based Storage**: Uses PHP files for easy debugging and inspection
- **Separate Cache Files**: Each type of documentation (OpenAPI spec, model metadata, field types) has its own cache file
- **Development-Friendly**: Cache can be easily cleared during development when models change

## 9. Testing Strategy

### 9.1 Unit Tests
- OpenAPIGenerator methods
- MetadataAPIController responses
- ReactComponentMapper functionality
- Cache management

### 9.2 Integration Tests
- End-to-end documentation generation
- API endpoint responses
- React component mapping accuracy
- Performance testing with many models

### 9.3 Documentation Tests
- Swagger UI functionality
- API example accuracy
- React integration examples
- Documentation completeness

## 10. Success Criteria

- [ ] Complete OpenAPI specification is generated automatically
- [ ] Interactive Swagger UI is functional
- [ ] Metadata endpoints provide complete model information
- [ ] React developers can discover APIs without external documentation
- [ ] Form generation from metadata works correctly
- [ ] Documentation stays in sync with code changes
- [ ] Performance is acceptable for large model sets
- [ ] Examples are accurate and helpful

## 11. Dependencies

### 11.1 External Libraries
- Swagger UI for documentation interface
- OpenAPI specification validator
- Template engine for custom documentation

### 11.2 Framework Components
- **MetadataEngine** - Enhanced with new methods for model discovery and field type definitions
- **APIRouteRegistry** - Enhanced with route grouping and model extraction capabilities  
- **DocumentationCache** - Simple file-based caching for generated documentation
- **RestApiHandler** - For endpoint integration and request handling

## 12. Risks and Mitigations

### 12.1 Performance Risks
- **Risk**: Slow documentation generation with many models
- **Mitigation**: Simple file-based caching, lazy loading, cached responses for repeated requests

### 12.2 Accuracy Risks
- **Risk**: Documentation out of sync with code
- **Mitigation**: 
  - Automated generation from cached metadata
  - Manual cache clearing during development
  - Simple cache invalidation when models are known to have changed

### 12.3 Usability Risks
- **Risk**: Complex documentation interface
- **Mitigation**: User testing, progressive disclosure, clear examples

## 14. Framework Integration Strategy

This implementation leverages the existing Gravitycar Framework architecture in the following ways:

### 14.1 Metadata-Driven Approach
- **No Hard-Coded Data**: All model information comes from `cache/metadata_cache.php` via MetadataEngine
- **Dynamic Field Type Discovery**: Field types are discovered by scanning `src/Fields/` directory, similar to model discovery
- **Real-Time Accuracy**: Documentation reflects the actual metadata structure and available field types in the framework
- **Automatic Updates**: When metadata cache is regenerated, documentation automatically reflects changes including new field types

### 14.2 Field Type Discovery System
- **Filesystem-Based Discovery**: Scans `src/Fields/` directory to find all FieldBase subclasses
- **Self-Describing Fields**: Each field class contains its own description and validation rules
- **Future-Proof**: New field types added to the framework are automatically included without code changes
- **Consistent with Model Discovery**: Uses the same pattern as the existing model discovery mechanism

### 14.3 Cache Structure Enhancement
```
cache/metadata_cache.php now contains:
{
  'models' => [...],           // Existing model metadata
  'relationships' => [...],    // Existing relationship metadata  
  'field_types' => [           // NEW: Dynamically discovered field types
    'Text' => [
      'type' => 'Text',
      'class' => 'Gravitycar\\Fields\\TextField',
      'description' => 'Single-line text input',
      'validation_rules' => ['Required', 'MaxLength', ...],
      'operators' => ['equals', 'contains', ...]
    ],
    ...
  ]
}
```

### 14.2 Route Discovery Integration  
- **Dynamic Route Detection**: Uses APIRouteRegistry to discover all available endpoints
- **Model-Route Association**: Automatically links model endpoints based on route patterns and naming conventions
- **Complete API Coverage**: Documents both ModelBase auto-generated routes and custom API controllers

### 14.3 Validation Rule Mapping
- **Direct Metadata Translation**: Converts framework validation rules to React-compatible validation objects
- **Type-Safe Validation**: Maps field types (Text, Email, Boolean, etc.) to appropriate React components
- **Consistent Validation**: Ensures frontend validation matches backend validation rules

### 14.4 Cache Strategy
```
Model Definition Changes
    ↓
metadata_cache.php regenerated (MetadataEngine)
    ↓  
api_routes.php regenerated (APIRouteRegistry)
    ↓
Manual cache clearing (DocumentationCache)
    ↓
Fresh documentation generated on next request
```

### 14.5 Performance Optimization
- **Simple File-Based Caching**: Generated documentation stored in separate cache files
- **Manual Cache Management**: Developers clear cache when they know models have changed
- **Lazy Loading**: Documentation generated only when requested, not on every request

This approach ensures the API documentation system is fully integrated with the framework's metadata-driven architecture and remains accurate without manual maintenance.

## 15. Estimated Timeline

**Total Time: 2 weeks**

- **Week 1, Days 1-2**: Enhance MetadataEngine and APIRouteRegistry with new methods
- **Week 1, Days 3-5**: Implement MetadataAPIController and basic OpenAPI generation  
- **Week 2, Days 1-3**: Implement ReactComponentMapper and enhanced documentation features
- **Week 2, Days 4-5**: Documentation UI, testing, and cache optimization

This implementation will provide comprehensive API documentation and metadata services, enabling React developers to efficiently discover and consume the Gravitycar Framework APIs.

## 16. Review Findings & Missing Specifications

### 16.1 Remaining Hard-Coded Elements Found

After thorough review, the following hard-coded elements were identified and should be addressed:

#### 16.1.1 Validation Rule Names in Examples
The plan still contains examples with hard-coded validation rule references:
- `'Required'`, `'Email'`, `'MaxLength'` should be dynamically discovered
- Constructor calls like `parent::__construct('Required', 'This field is required.')` are acceptable as they define the rule type

#### 16.1.2 Field Type Names in OpenAPI Generator 
In `generateFieldSchema()` method:
```php
// Hard-coded field type checks - should use dynamic discovery
if (in_array($fieldType, ['Enum', 'RadioButtonSet']) && isset($fieldData['options'])) {
if ($fieldType === 'MultiEnum' && isset($fieldData['options'])) {
if (in_array($fieldType, ['Integer', 'Float'])) {
```
**Solution**: Use field type capabilities from cached metadata instead of hard-coded arrays.

#### 16.1.3 React Component Mappings
Default fallback components are hard-coded:
```php
return $this->fieldComponentMap[$fieldType]['component'] ?? 'TextInput';
```
**Solution**: Define a system-wide default component or throw descriptive errors.

### 16.2 Missing Specifications for Small-Scale Framework

Given the framework's scale (max 3 concurrent users, max 1000 records per table), the following specifications are needed:

#### 16.2.1 Error Handling & Recovery Specifications
- **Cache Failure Recovery**: What happens when documentation cache files are corrupted or missing?
- **Missing Field Type Handling**: Behavior when new field types exist but lack React metadata
- **Validation Rule Discovery Failures**: Fallback when validation rule classes can't be instantiated
- **Model Metadata Corruption**: Recovery strategy when cached model metadata is invalid

#### 16.2.2 Development Workflow Specifications
- **Cache Invalidation Strategy**: When should developers manually clear documentation cache?
- **Field Type Addition Workflow**: Steps to add new field types with React integration
- **Validation Rule Addition Workflow**: Process for adding new validation rules with descriptions
- **Documentation Testing**: How to verify documentation accuracy during development

#### 16.2.3 API Response Format Specifications
- **Error Response Format**: Standardized error responses for metadata endpoints
- **Pagination Specifications**: Not needed given scale, but should be explicitly stated
- **Rate Limiting**: Not needed given scale, but should be documented
- **Authentication**: Does metadata API require authentication?

#### 16.2.4 React Integration Specifications
- **Component Dependency Management**: How to handle missing React components gracefully
- **Form Generation Behavior**: Default behavior when field types lack React mappings
- **Validation Rule Translation**: Complete mapping from PHP validation to JavaScript
- **Field Type Extensibility**: How React developers can extend field type mappings

#### 16.2.5 Performance & Scalability Specifications
- **Cache Size Limits**: Maximum cache file sizes (likely not an issue with small scale)
- **Memory Usage**: Expected memory footprint for documentation generation
- **Response Time Targets**: Acceptable response times for metadata endpoints
- **Concurrent User Handling**: Behavior with multiple users accessing documentation simultaneously

#### 16.2.6 Data Integrity Specifications
- **Metadata Consistency**: Ensuring field types in documentation match actual field classes
- **Route Accuracy**: Verification that documented routes match actual API endpoints
- **Schema Validation**: OpenAPI schema validation against actual API responses
- **Breaking Change Detection**: Identifying when model changes break existing documentation

### 16.3 Security Considerations for Small-Scale Framework

#### 16.3.1 Metadata Exposure
- **Sensitive Field Information**: Should field metadata include validation rules that reveal business logic?
- **Internal Implementation Details**: How much framework internals should be exposed via metadata?
- **Development vs Production**: Different documentation exposure levels for different environments?

#### 16.3.2 Cache Security
- **File Permissions**: Documentation cache file permissions and ownership
- **Cache Directory Security**: Preventing unauthorized access to cached metadata
- **Sensitive Data in Cache**: Ensuring no sensitive data is cached in documentation files

### 16.4 Missing Implementation Details

#### 16.4.1 Validation Rule Factory Integration
The plan mentions `ValidationRuleFactory::getAvailableValidationRules()` but doesn't specify:
- How this method discovers validation rules
- Whether it uses filesystem scanning like field discovery
- Error handling when validation rule classes are invalid

#### 16.4.2 OpenAPI Schema Enhancement
Missing specifications for:
- How to handle custom field properties in OpenAPI schemas
- Relationship field documentation in OpenAPI
- Nested object schemas for complex field types
- Example generation for each field type

#### 16.4.3 Documentation UI Implementation
The plan mentions Swagger UI integration but lacks:
- Custom styling requirements
- Integration with existing framework UI
- Mobile responsiveness specifications
- Accessibility requirements

#### 16.4.4 Testing Infrastructure
Missing specifications for:
- Automated testing of generated documentation
- Validation of OpenAPI schema correctness
- Testing React component mappings
- Integration testing with actual API endpoints

### 16.5 Recommended Enhancements for Implementation

#### 16.5.1 Configuration System
Add a configuration system for:
```php
'documentation' => [
    'cache_enabled' => true,
    'default_react_component' => 'TextInput',
    'include_internal_fields' => false,
    'expose_validation_details' => true,
    'authentication_required' => false,
    'response_time_target_ms' => 100
]
```

#### 16.5.2 Validation and Error Recovery
```php
class DocumentationValidator {
    public function validateFieldTypeMetadata(array $fieldTypes): array;
    public function validateOpenAPISchema(array $schema): bool;
    public function validateReactComponentMappings(array $mappings): array;
    public function repairCorruptedCache(): bool;
}
```

#### 16.5.3 Development Tools
```php
class DocumentationDeveloperTools {
    public function validateDocumentationAccuracy(): array;
    public function generateMissingFieldMetadata(): array;
    public function detectBreakingChanges(): array;
    public function benchmarkDocumentationGeneration(): array;
}
```

### 16.6 Implementation Priority

Given the small scale, prioritize:
1. **✅ COMPLETED**: Remove remaining hard-coded elements (Field-instance-based OpenAPI schema generation implemented)
2. **✅ COMPLETED**: Define error handling and recovery strategies (Using existing APIException framework)
3. **✅ COMPLETED**: Implement configuration system (Comprehensive configuration system designed for small-scale framework)
4. **✅ COMPLETED**: Add validation and testing infrastructure (Comprehensive testing framework with CLI commands)
5. **⏸️ DEFERRED**: Advanced development tools and monitoring

## 18. Configuration System Implementation

### 18.1 Purpose & Benefits

The configuration system extends the existing Gravitycar Framework configuration architecture (`src/Core/Config.php`) to provide customizable behavior for the API documentation and schema generation system:

- **📝 Customizable Documentation Behavior**: Control how documentation is generated, cached, and presented
- **🔧 Environment-Specific Settings**: Different configuration for development vs production environments  
- **⚡ Performance Tuning**: Configure caching strategies, response times, and resource limits
- **🔒 Security Controls**: Control what information is exposed in documentation
- **🔗 Integration Flexibility**: Configure React component mappings and validation rule exposure

### 18.2 Configuration Structure

The system would add a new `documentation` section to the existing `config.php`, leveraging the framework's existing `Config::get()` and `Config::set()` methods with dot notation support:

```php
// Enhanced config.php structure
return [
    'database' => [...], // Existing database config
    'app' => [...],      // Existing app config  
    'logging' => [...],  // Existing logging config
    
    // NEW: Documentation system configuration
    'documentation' => [
        // Caching Configuration
        'cache_enabled' => true,
        'cache_ttl_seconds' => 3600, // 1 hour cache expiration
        'cache_directory' => 'cache/documentation/',
        'auto_clear_cache_on_metadata_change' => false, // Manual cache clearing for small scale
        
        // React Integration Configuration  
        'default_react_component' => 'TextInput',
        'include_react_metadata' => true,
        'react_validation_mapping' => true,
        'fallback_component_on_missing' => true,
        
        // API Exposure Configuration
        'expose_internal_fields' => false, // Hide framework internal fields
        'expose_validation_rules' => true,
        'expose_field_capabilities' => true,
        'include_example_data' => true,
        
        // OpenAPI Configuration
        'openapi_version' => '3.0.3',
        'api_title' => 'Gravitycar Framework API',
        'api_version' => '1.0.0',
        'api_description' => 'Auto-generated API documentation for Gravitycar Framework',
        'include_deprecated_endpoints' => false,
        
        // Performance Configuration  
        'response_time_target_ms' => 200,
        'enable_response_compression' => true,
        'max_field_types_per_request' => 100, // Good practice even for small scale
        
        // Security Configuration (designed for small scale)
        'authentication_required' => false, // Not needed for 3 concurrent users
        'allowed_origins' => ['*'], // CORS configuration
        'rate_limiting_enabled' => false, // Not needed for small scale
        
        // Development Configuration
        'enable_debug_info' => true,
        'include_generation_timestamps' => true,
        'log_cache_operations' => false,
        'validate_generated_schemas' => true,
        
        // Error Handling Configuration
        'graceful_degradation' => true,
        'fallback_on_cache_corruption' => true,
        'detailed_error_responses' => true, // Include context in error responses
        'log_documentation_errors' => true
    ]
];
```

### 18.3 Configuration Usage in Documentation Components

#### 18.3.1 Enhanced MetadataAPIController with Configuration

```php
class MetadataAPIController {
    private MetadataEngine $metadataEngine;
    private APIRouteRegistry $routeRegistry;
    private ReactComponentMapper $componentMapper;
    private DocumentationCache $cache;
    private Config $config;
    
    public function __construct() {
        $this->metadataEngine = MetadataEngine::getInstance();
        $this->cache = new DocumentationCache();
        $this->config = new Config();
    }
    
    public function getModels(): array {
        // Use configuration for caching behavior
        if (!$this->config->get('documentation.cache_enabled', true)) {
            return $this->generateModelsListFresh();
        }
        
        // Check cache first (respecting cache TTL)
        $cached = $this->cache->getCachedModelsList();
        if ($cached !== null && $this->isCacheValid($cached)) {
            return $cached;
        }
        
        try {
            $cachedMetadata = $this->metadataEngine->getCachedMetadata();
            if (empty($cachedMetadata['models'])) {
                throw new NotFoundException('No models found in metadata cache');
            }
            
            $models = [];
            foreach ($cachedMetadata['models'] as $modelName => $modelData) {
                // Respect internal field exposure configuration
                if (!$this->shouldExposeModel($modelName, $modelData)) {
                    continue;
                }
                
                $routes = $this->routeRegistry->getModelRoutes($modelName);
                $models[$modelName] = [
                    'endpoint' => $this->extractPrimaryEndpoint($routes),
                    'description' => $modelData['description'] ?? $this->generateModelDescription($modelName),
                    'table' => $modelData['table'] ?? strtolower($modelName),
                    'fields_count' => count($modelData['fields'] ?? []),
                    'relationships_count' => count($modelData['relationships'] ?? []),
                    'operations' => $this->getAvailableOperations($routes)
                ];
            }
            
            $result = [
                'success' => true,
                'data' => $models,
                'timestamp' => date('c')
            ];
            
            // Include debug info if configured
            if ($this->config->get('documentation.enable_debug_info', false)) {
                $result['debug'] = [
                    'cache_hit' => false,
                    'generation_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'models_processed' => count($models)
                ];
            }
            
            // Cache if enabled
            if ($this->config->get('documentation.cache_enabled', true)) {
                $this->cache->cacheModelsList($result);
            }
            
            return $result;
            
        } catch (NotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Use configured error handling
            if ($this->config->get('documentation.graceful_degradation', true)) {
                return $this->getGracefulErrorResponse('Failed to retrieve models metadata', $e);
            }
            throw new InternalServerErrorException(
                'Failed to retrieve models metadata', 
                ['original_error' => $e->getMessage()],
                $e
            );
        }
    }
    
    public function getFieldTypes(): array {
        try {
            // Check cache if enabled
            if ($this->config->get('documentation.cache_enabled', true)) {
                $cached = $this->cache->getCachedFieldTypes();
                if ($cached !== null && $this->isCacheValid($cached)) {
                    return $cached;
                }
            }
            
            $fieldTypeDefinitions = $this->metadataEngine->getFieldTypeDefinitions();
            if (empty($fieldTypeDefinitions)) {
                throw new ServiceUnavailableException(
                    'Field type discovery failed - metadata cache may be corrupted',
                    ['cache_path' => 'cache/metadata_cache.php']
                );
            }
            
            $fieldTypes = [];
            foreach ($fieldTypeDefinitions as $fieldType => $fieldData) {
                $fieldTypeInfo = [
                    'description' => $fieldData['description'] ?? $this->generateDescriptionFromFieldType($fieldType),
                    'class' => $fieldData['class'] ?? "Gravitycar\\Fields\\{$fieldType}Field"
                ];
                
                // Include React metadata if configured
                if ($this->config->get('documentation.include_react_metadata', true)) {
                    $fieldTypeInfo['react_component'] = $fieldData['react_component'] ?? $this->config->get('documentation.default_react_component', 'TextInput');
                    $fieldTypeInfo['props'] = $fieldData['react_props'] ?? [];
                }
                
                // Include validation rule capabilities if configured
                if ($this->config->get('documentation.expose_field_capabilities', true)) {
                    $fieldTypeInfo['supported_validation_rules'] = $this->getSupportedValidationRulesForFieldType($fieldType);
                }
                
                // Include example data if configured
                if ($this->config->get('documentation.include_example_data', true)) {
                    $fieldTypeInfo['example_value'] = $this->generateExampleValue($fieldType);
                }
                
                $fieldTypes[$fieldType] = $fieldTypeInfo;
            }
            
            $result = [
                'success' => true,
                'data' => $fieldTypes,
                'timestamp' => date('c')
            ];
            
            // Cache if enabled
            if ($this->config->get('documentation.cache_enabled', true)) {
                $this->cache->cacheFieldTypes($result);
            }
            
            return $result;
            
        } catch (ServiceUnavailableException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InternalServerErrorException(
                'Failed to retrieve field type definitions',
                ['original_error' => $e->getMessage()],
                $e
            );
        }
    }
    
    /**
     * Check if cached data is still valid based on configured TTL
     */
    private function isCacheValid(array $cachedData): bool {
        if (!isset($cachedData['timestamp'])) {
            return false;
        }
        
        $cacheTime = strtotime($cachedData['timestamp']);
        $ttl = $this->config->get('documentation.cache_ttl_seconds', 3600);
        
        return (time() - $cacheTime) < $ttl;
    }
    
    /**
     * Check if model should be exposed based on configuration
     */
    private function shouldExposeModel(string $modelName, array $modelData): bool {
        // Hide internal framework models if configured
        if (!$this->config->get('documentation.expose_internal_fields', false)) {
            $internalModels = ['MetadataCache', 'SystemLog', 'FrameworkConfig'];
            if (in_array($modelName, $internalModels)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Generate graceful error response when degradation is enabled
     */
    private function getGracefulErrorResponse(string $message, \Exception $e): array {
        return [
            'success' => false,
            'error' => $message,
            'fallback_data' => [],
            'timestamp' => date('c'),
            'context' => $this->config->get('documentation.detailed_error_responses', true) 
                ? ['error_type' => get_class($e), 'error_message' => $e->getMessage()]
                : []
        ];
    }
}
```

#### 18.3.2 Enhanced OpenAPIGenerator with Configuration

```php
class OpenAPIGenerator {
    private MetadataEngine $metadataEngine;
    private APIRouteRegistry $routeRegistry;
    private ReactComponentMapper $componentMapper;
    private DocumentationCache $cache;
    private Config $config;
    
    public function __construct() {
        $this->metadataEngine = MetadataEngine::getInstance();
        $this->routeRegistry = new APIRouteRegistry();
        $this->componentMapper = new ReactComponentMapper();
        $this->cache = new DocumentationCache();
        $this->config = new Config();
    }
    
    public function generateSpecification(): array {
        // Check cache if enabled
        if ($this->config->get('documentation.cache_enabled', true)) {
            $cached = $this->cache->getCachedOpenAPISpec();
            if ($cached !== null && $this->isCacheValid($cached)) {
                return $cached;
            }
        }
        
        $spec = [
            'openapi' => $this->config->get('documentation.openapi_version', '3.0.3'),
            'info' => [
                'title' => $this->config->get('documentation.api_title', 'Gravitycar Framework API'),
                'version' => $this->config->get('documentation.api_version', '1.0.0'),
                'description' => $this->config->get('documentation.api_description', 'Auto-generated API documentation')
            ],
            'servers' => $this->generateServers(),
            'paths' => $this->generatePaths(),
            'components' => $this->generateComponents()
        ];
        
        // Validate generated specification if configured
        if ($this->config->get('documentation.validate_generated_schemas', true)) {
            $this->validateOpenAPISpec($spec);
        }
        
        // Cache the generated specification if enabled
        if ($this->config->get('documentation.cache_enabled', true)) {
            $this->cache->cacheOpenAPISpec($spec);
        }
        
        return $spec;
    }
    
    private function generateFieldSchema(array $fieldData): array {
        try {
            $fieldType = $fieldData['type'] ?? 'Text';
            
            // Use FieldFactory to create field instance
            $fieldFactory = \Gravitycar\Core\ServiceLocator::getFieldFactory();
            $fieldInstance = $fieldFactory->createField($fieldType, $fieldData);
            
            // Generate schema from field instance
            return $fieldInstance->generateOpenAPISchema();
            
        } catch (\Exception $e) {
            // Use configured error handling
            if ($this->config->get('documentation.graceful_degradation', true)) {
                if ($this->config->get('documentation.log_documentation_errors', true)) {
                    $this->logger->warning("Failed to generate schema for field type {$fieldType}: " . $e->getMessage());
                }
                
                // Return fallback schema with configuration-based default component
                return [
                    'type' => 'string',
                    'description' => $fieldData['label'] ?? 'Field',
                    'x-generation-error' => 'Schema generated from fallback due to field creation failure',
                    'x-react-component' => $this->config->get('documentation.default_react_component', 'TextInput')
                ];
            }
            
            throw $e;
        }
    }
}
```

#### 18.3.3 Enhanced DocumentationCache with Configuration

```php
class DocumentationCache {
    private Config $config;
    private string $cacheDir;
    
    public function __construct() {
        $this->config = new Config();
        $this->cacheDir = $this->config->get('documentation.cache_directory', 'cache/documentation/');
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    public function getCachedOpenAPISpec(): ?array {
        if (!$this->config->get('documentation.cache_enabled', true)) {
            return null;
        }
        
        try {
            $cacheFile = $this->cacheDir . 'openapi_spec.php';
            if (!file_exists($cacheFile)) {
                return null;
            }
            
            $cached = include $cacheFile;
            
            // Check TTL if configured
            if (isset($cached['cached_at'])) {
                $ttl = $this->config->get('documentation.cache_ttl_seconds', 3600);
                if ((time() - $cached['cached_at']) > $ttl) {
                    return null; // Cache expired
                }
            }
            
            // Log cache operations if configured
            if ($this->config->get('documentation.log_cache_operations', false)) {
                error_log("Documentation cache HIT: OpenAPI spec loaded from cache");
            }
            
            return $cached['data'] ?? $cached;
            
        } catch (\Exception $e) {
            // Log cache errors if configured
            if ($this->config->get('documentation.log_documentation_errors', true)) {
                error_log("Documentation cache ERROR: Failed to load OpenAPI spec - " . $e->getMessage());
            }
            
            // Use configured fallback behavior
            if ($this->config->get('documentation.fallback_on_cache_corruption', true)) {
                return null; // Treat as cache miss
            }
            
            throw new ServiceUnavailableException(
                'Documentation cache corrupted',
                ['cache_file' => $cacheFile, 'error' => $e->getMessage()],
                $e
            );
        }
    }
    
    public function cacheOpenAPISpec(array $spec): void {
        if (!$this->config->get('documentation.cache_enabled', true)) {
            return;
        }
        
        try {
            $cacheFile = $this->cacheDir . 'openapi_spec.php';
            $cacheData = [
                'data' => $spec,
                'cached_at' => time(),
                'cache_version' => '1.0'
            ];
            
            $content = '<?php return ' . var_export($cacheData, true) . ';';
            file_put_contents($cacheFile, $content);
            
            // Log cache operations if configured
            if ($this->config->get('documentation.log_cache_operations', false)) {
                error_log("Documentation cache WRITE: OpenAPI spec cached successfully");
            }
            
        } catch (\Exception $e) {
            if ($this->config->get('documentation.log_documentation_errors', true)) {
                error_log("Documentation cache ERROR: Failed to cache OpenAPI spec - " . $e->getMessage());
            }
            
            throw new InternalServerErrorException(
                'Failed to cache OpenAPI specification',
                ['cache_file' => $cacheFile, 'error' => $e->getMessage()],
                $e
            );
        }
    }
}
```

### 18.4 Configuration Management Methods

#### 18.4.1 Enhanced Config Class Methods

```php
// Additional methods to add to existing Config class
class Config {
    // ... existing methods ...
    
    /**
     * Get documentation configuration section
     */
    public function getDocumentationConfig(): array {
        return $this->get('documentation', []);
    }
    
    /**
     * Update documentation configuration and write to file
     */
    public function updateDocumentationConfig(array $newConfig): void {
        $current = $this->get('documentation', []);
        $merged = array_merge($current, $newConfig);
        $this->set('documentation', $merged);
        $this->write();
    }
    
    /**
     * Reset documentation configuration to defaults
     */
    public function resetDocumentationConfig(): void {
        $defaults = [
            'cache_enabled' => true,
            'cache_ttl_seconds' => 3600,
            'cache_directory' => 'cache/documentation/',
            'default_react_component' => 'TextInput',
            'include_react_metadata' => true,
            'expose_validation_rules' => true,
            'graceful_degradation' => true,
            'detailed_error_responses' => true
        ];
        
        $this->set('documentation', $defaults);
        $this->write();
    }
    
    /**
     * Validate documentation configuration
     */
    public function validateDocumentationConfig(): array {
        $config = $this->get('documentation', []);
        $errors = [];
        
        // Validate cache directory
        $cacheDir = $config['cache_directory'] ?? 'cache/documentation/';
        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true)) {
            $errors[] = "Cache directory '{$cacheDir}' cannot be created";
        }
        
        // Validate TTL is reasonable
        $ttl = $config['cache_ttl_seconds'] ?? 3600;
        if (!is_int($ttl) || $ttl < 60 || $ttl > 86400) {
            $errors[] = "Cache TTL should be between 60 and 86400 seconds";
        }
        
        // Validate OpenAPI version
        $version = $config['openapi_version'] ?? '3.0.3';
        if (!in_array($version, ['3.0.0', '3.0.1', '3.0.2', '3.0.3'])) {
            $errors[] = "Unsupported OpenAPI version: {$version}";
        }
        
        return $errors;
    }
}
```

### 18.5 Configuration CLI Commands

```php
// Add to existing CLI command structure
class DocumentationConfigCommand {
    private Config $config;
    
    public function __construct() {
        $this->config = new Config();
    }
    
    /**
     * Show current documentation configuration
     */
    public function showConfig(): void {
        $config = $this->config->getDocumentationConfig();
        echo "Current Documentation Configuration:\n";
        echo json_encode($config, JSON_PRETTY_PRINT) . "\n";
    }
    
    /**
     * Update specific configuration value
     */
    public function setConfig(string $key, $value): void {
        $current = $this->config->get('documentation', []);
        $current[$key] = $value;
        $this->config->set('documentation', $current);
        $this->config->write();
        echo "Configuration updated: documentation.{$key} = {$value}\n";
    }
    
    /**
     * Validate configuration and show any issues
     */
    public function validateConfig(): void {
        $errors = $this->config->validateDocumentationConfig();
        if (empty($errors)) {
            echo "Documentation configuration is valid.\n";
        } else {
            echo "Configuration errors found:\n";
            foreach ($errors as $error) {
                echo "- {$error}\n";
            }
        }
    }
    
    /**
     * Reset to default configuration
     */
    public function resetConfig(): void {
        $this->config->resetDocumentationConfig();
        echo "Documentation configuration reset to defaults.\n";
    }
}
```

### 18.6 Benefits for Small-Scale Framework

#### 18.6.1 Specific Advantages

- **🔧 No Performance Overhead**: Configuration checks happen once per request, minimal impact for 3 concurrent users
- **📁 Simple File-Based Storage**: Uses existing config.php pattern, no additional infrastructure needed
- **🛠️ Development-Friendly**: Easy to modify configuration during development and testing
- **🔒 Security by Default**: Internal fields hidden by default, authentication optional for small scale
- **⚡ Graceful Degradation**: System continues working even if some components fail
- **📝 Flexible Documentation**: Can be customized for different presentation needs without code changes

#### 18.6.2 Small-Scale Optimizations

The configuration system is specifically designed for the framework's small scale:

- **No Database Dependencies**: All configuration stored in simple PHP files
- **Manual Cache Management**: Auto-clear disabled by default, developers control when to clear cache
- **Minimal Resource Usage**: Conservative defaults for cache TTL and response sizes
- **Simple Error Handling**: Graceful degradation enabled by default
- **Development Focus**: Debug information and detailed errors enabled by default

This configuration system provides the flexibility needed for the API documentation system while maintaining the simplicity appropriate for a small-scale framework.

## 19. Validation and Testing Infrastructure

### 19.1 Purpose & Benefits

The validation and testing infrastructure ensures the API documentation system generates accurate, consistent, and reliable documentation that stays synchronized with the actual framework implementation:

- **🔍 Documentation Accuracy**: Validate that generated documentation matches actual API behavior
- **🧪 Automated Testing**: Test all documentation components without manual verification
- **📋 Schema Validation**: Ensure OpenAPI schemas are valid and complete
- **🔄 Regression Prevention**: Detect when changes break existing documentation
- **⚡ Performance Validation**: Verify documentation generation meets performance targets
- **🛠️ Development Support**: Provide tools for developers to validate their changes

### 19.2 Testing Strategy for Small-Scale Framework

Given the framework's constraints (max 3 concurrent users, max 1000 records per table), the testing approach focuses on:

- **Simple Test Infrastructure**: File-based tests, no complex testing databases needed
- **Fast Execution**: Quick validation suitable for small development cycles
- **Comprehensive Coverage**: Test all documentation aspects despite small scale
- **Developer-Friendly**: Easy to run and understand test results
- **Integration Focus**: Ensure documentation matches actual framework behavior

### 19.3 Documentation Validation Components

#### 19.3.1 OpenAPI Schema Validator

```php
class OpenAPISchemaValidator {
    private Config $config;
    private array $validationErrors = [];
    
    public function __construct() {
        $this->config = new Config();
    }
    
    /**
     * Validate complete OpenAPI specification
     */
    public function validateOpenAPISpecification(array $spec): array {
        $errors = [];
        
        // Validate basic structure
        $errors = array_merge($errors, $this->validateSpecStructure($spec));
        
        // Validate info section
        $errors = array_merge($errors, $this->validateInfoSection($spec['info'] ?? []));
        
        // Validate paths section
        $errors = array_merge($errors, $this->validatePathsSection($spec['paths'] ?? []));
        
        // Validate components section
        $errors = array_merge($errors, $this->validateComponentsSection($spec['components'] ?? []));
        
        // Validate against OpenAPI 3.0.3 specification
        $errors = array_merge($errors, $this->validateOpenAPICompliance($spec));
        
        return $errors;
    }
    
    /**
     * Validate OpenAPI specification structure
     */
    private function validateSpecStructure(array $spec): array {
        $errors = [];
        $requiredFields = ['openapi', 'info', 'paths'];
        
        foreach ($requiredFields as $field) {
            if (!isset($spec[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }
        
        // Validate OpenAPI version
        if (isset($spec['openapi'])) {
            $supportedVersions = ['3.0.0', '3.0.1', '3.0.2', '3.0.3'];
            if (!in_array($spec['openapi'], $supportedVersions)) {
                $errors[] = "Unsupported OpenAPI version: {$spec['openapi']}";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate info section
     */
    private function validateInfoSection(array $info): array {
        $errors = [];
        $requiredFields = ['title', 'version'];
        
        foreach ($requiredFields as $field) {
            if (!isset($info[$field]) || empty($info[$field])) {
                $errors[] = "Info section missing required field: {$field}";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate paths section
     */
    private function validatePathsSection(array $paths): array {
        $errors = [];
        
        if (empty($paths)) {
            $errors[] = "Paths section is empty - no API endpoints documented";
            return $errors;
        }
        
        foreach ($paths as $path => $pathItem) {
            $errors = array_merge($errors, $this->validatePathItem($path, $pathItem));
        }
        
        return $errors;
    }
    
    /**
     * Validate individual path item
     */
    private function validatePathItem(string $path, array $pathItem): array {
        $errors = [];
        $validMethods = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'];
        
        if (empty($pathItem)) {
            $errors[] = "Path '{$path}' has no operations defined";
            return $errors;
        }
        
        foreach ($pathItem as $method => $operation) {
            if (!in_array(strtolower($method), $validMethods)) {
                $errors[] = "Path '{$path}' has invalid HTTP method: {$method}";
                continue;
            }
            
            $errors = array_merge($errors, $this->validateOperation($path, $method, $operation));
        }
        
        return $errors;
    }
    
    /**
     * Validate operation object
     */
    private function validateOperation(string $path, string $method, array $operation): array {
        $errors = [];
        
        // Validate required operation fields
        if (!isset($operation['responses']) || empty($operation['responses'])) {
            $errors[] = "Operation {$method} {$path} missing responses";
        }
        
        // Validate responses
        if (isset($operation['responses'])) {
            foreach ($operation['responses'] as $statusCode => $response) {
                if (!is_numeric($statusCode) && $statusCode !== 'default') {
                    $errors[] = "Operation {$method} {$path} has invalid response status code: {$statusCode}";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate components section
     */
    private function validateComponentsSection(array $components): array {
        $errors = [];
        
        if (isset($components['schemas'])) {
            foreach ($components['schemas'] as $schemaName => $schema) {
                $errors = array_merge($errors, $this->validateSchema($schemaName, $schema));
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate individual schema
     */
    private function validateSchema(string $schemaName, array $schema): array {
        $errors = [];
        
        // Validate schema has type
        if (!isset($schema['type'])) {
            $errors[] = "Schema '{$schemaName}' missing type field";
        }
        
        // Validate object schemas have properties
        if (isset($schema['type']) && $schema['type'] === 'object') {
            if (!isset($schema['properties']) || empty($schema['properties'])) {
                $errors[] = "Object schema '{$schemaName}' has no properties defined";
            }
        }
        
        // Validate array schemas have items
        if (isset($schema['type']) && $schema['type'] === 'array') {
            if (!isset($schema['items'])) {
                $errors[] = "Array schema '{$schemaName}' missing items definition";
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate against OpenAPI 3.0.3 specification compliance
     */
    private function validateOpenAPICompliance(array $spec): array {
        $errors = [];
        
        // This could integrate with external OpenAPI validation libraries
        // For small scale, we focus on basic validation above
        
        return $errors;
    }
}
```

#### 19.3.2 Metadata Accuracy Validator

```php
class MetadataAccuracyValidator {
    private MetadataEngine $metadataEngine;
    private Config $config;
    
    public function __construct() {
        $this->metadataEngine = MetadataEngine::getInstance();
        $this->config = new Config();
    }
    
    /**
     * Validate that field types in documentation match actual field classes
     */
    public function validateFieldTypeConsistency(): array {
        $errors = [];
        
        // Get field types from documentation cache
        $cachedFieldTypes = $this->metadataEngine->getFieldTypeDefinitions();
        
        // Get actual field classes from filesystem
        $actualFieldClasses = $this->scanActualFieldClasses();
        
        // Check for missing field types in documentation
        foreach ($actualFieldClasses as $className => $fieldType) {
            if (!isset($cachedFieldTypes[$fieldType])) {
                $errors[] = "Field type '{$fieldType}' exists in code but missing from documentation cache";
            }
        }
        
        // Check for documented field types that don't exist in code
        foreach ($cachedFieldTypes as $fieldType => $fieldData) {
            $expectedClass = $fieldData['class'] ?? "Gravitycar\\Fields\\{$fieldType}Field";
            if (!class_exists($expectedClass)) {
                $errors[] = "Field type '{$fieldType}' documented but class '{$expectedClass}' doesn't exist";
            }
        }
        
        // Validate field type metadata accuracy
        foreach ($cachedFieldTypes as $fieldType => $fieldData) {
            $errors = array_merge($errors, $this->validateFieldTypeMetadata($fieldType, $fieldData));
        }
        
        return $errors;
    }
    
    /**
     * Validate model metadata accuracy
     */
    public function validateModelMetadataAccuracy(): array {
        $errors = [];
        
        $cachedMetadata = $this->metadataEngine->getCachedMetadata();
        $models = $cachedMetadata['models'] ?? [];
        
        foreach ($models as $modelName => $modelData) {
            $errors = array_merge($errors, $this->validateModelExists($modelName));
            $errors = array_merge($errors, $this->validateModelFields($modelName, $modelData));
            $errors = array_merge($errors, $this->validateModelRelationships($modelName, $modelData));
        }
        
        return $errors;
    }
    
    /**
     * Validate API route documentation accuracy
     */
    public function validateRouteDocumentationAccuracy(): array {
        $errors = [];
        
        // This would validate that documented routes match actual registered routes
        // For small scale, we can implement basic route existence checking
        
        return $errors;
    }
    
    /**
     * Scan actual field classes in filesystem
     */
    private function scanActualFieldClasses(): array {
        $fieldClasses = [];
        $fieldsDir = 'src/Fields/';
        
        if (!is_dir($fieldsDir)) {
            return $fieldClasses;
        }
        
        $files = glob($fieldsDir . '*Field.php');
        foreach ($files as $file) {
            $className = basename($file, '.php');
            $fieldType = str_replace('Field', '', $className);
            $fieldClasses[$className] = $fieldType;
        }
        
        return $fieldClasses;
    }
    
    /**
     * Validate field type metadata against actual class
     */
    private function validateFieldTypeMetadata(string $fieldType, array $fieldData): array {
        $errors = [];
        
        $className = $fieldData['class'] ?? "Gravitycar\\Fields\\{$fieldType}Field";
        
        if (!class_exists($className)) {
            $errors[] = "Field type '{$fieldType}' references non-existent class '{$className}'";
            return $errors;
        }
        
        // Validate class extends FieldBase
        $reflection = new \ReflectionClass($className);
        if (!$reflection->isSubclassOf('Gravitycar\\Fields\\FieldBase')) {
            $errors[] = "Field class '{$className}' does not extend FieldBase";
        }
        
        // Validate static description property exists
        if (!$reflection->hasProperty('description') || !$reflection->getProperty('description')->isStatic()) {
            $errors[] = "Field class '{$className}' missing static description property";
        }
        
        return $errors;
    }
    
    /**
     * Validate model exists and can be instantiated
     */
    private function validateModelExists(string $modelName): array {
        $errors = [];
        
        try {
            $modelFactory = \Gravitycar\Core\ServiceLocator::getModelFactory();
            $model = $modelFactory->createModel($modelName);
            
            if (!$model) {
                $errors[] = "Model '{$modelName}' cannot be instantiated";
            }
        } catch (\Exception $e) {
            $errors[] = "Model '{$modelName}' instantiation failed: " . $e->getMessage();
        }
        
        return $errors;
    }
    
    /**
     * Validate model fields match actual model definition
     */
    private function validateModelFields(string $modelName, array $modelData): array {
        $errors = [];
        
        // This would validate that documented fields match actual model fields
        // Implementation depends on how models define their fields
        
        return $errors;
    }
    
    /**
     * Validate model relationships
     */
    private function validateModelRelationships(string $modelName, array $modelData): array {
        $errors = [];
        
        // This would validate that documented relationships exist and are correctly configured
        // Implementation depends on relationship system
        
        return $errors;
    }
}
```

#### 19.3.3 Performance Validator

```php
class DocumentationPerformanceValidator {
    private Config $config;
    private array $performanceResults = [];
    
    public function __construct() {
        $this->config = new Config();
    }
    
    /**
     * Validate documentation generation performance
     */
    public function validatePerformanceTargets(): array {
        $errors = [];
        $targetResponseTime = $this->config->get('documentation.response_time_target_ms', 200);
        
        // Test OpenAPI generation performance
        $openApiTime = $this->measureOpenAPIGenerationTime();
        if ($openApiTime > $targetResponseTime) {
            $errors[] = "OpenAPI generation time ({$openApiTime}ms) exceeds target ({$targetResponseTime}ms)";
        }
        
        // Test metadata endpoints performance
        $metadataTime = $this->measureMetadataEndpointsTime();
        if ($metadataTime > $targetResponseTime) {
            $errors[] = "Metadata endpoints time ({$metadataTime}ms) exceeds target ({$targetResponseTime}ms)";
        }
        
        // Test cache operations performance
        $cacheTime = $this->measureCacheOperationsTime();
        if ($cacheTime > 50) { // Cache operations should be very fast
            $errors[] = "Cache operations time ({$cacheTime}ms) exceeds 50ms threshold";
        }
        
        $this->performanceResults = [
            'openapi_generation_ms' => $openApiTime,
            'metadata_endpoints_ms' => $metadataTime,
            'cache_operations_ms' => $cacheTime,
            'target_ms' => $targetResponseTime
        ];
        
        return $errors;
    }
    
    /**
     * Measure OpenAPI specification generation time
     */
    private function measureOpenAPIGenerationTime(): float {
        $startTime = microtime(true);
        
        try {
            $generator = new OpenAPIGenerator();
            $spec = $generator->generateSpecification();
        } catch (\Exception $e) {
            return 999999; // Return very high time on error
        }
        
        return round((microtime(true) - $startTime) * 1000, 2);
    }
    
    /**
     * Measure metadata endpoints response time
     */
    private function measureMetadataEndpointsTime(): float {
        $startTime = microtime(true);
        
        try {
            $controller = new MetadataAPIController();
            $models = $controller->getModels();
            $fieldTypes = $controller->getFieldTypes();
        } catch (\Exception $e) {
            return 999999; // Return very high time on error
        }
        
        return round((microtime(true) - $startTime) * 1000, 2);
    }
    
    /**
     * Measure cache operations time
     */
    private function measureCacheOperationsTime(): float {
        $startTime = microtime(true);
        
        try {
            $cache = new DocumentationCache();
            
            // Test cache write
            $testData = ['test' => 'data', 'timestamp' => date('c')];
            $cache->cacheModelsList($testData);
            
            // Test cache read
            $cached = $cache->getCachedModelsList();
            
            // Clean up test cache
            $cache->clearCache();
        } catch (\Exception $e) {
            return 999999; // Return very high time on error
        }
        
        return round((microtime(true) - $startTime) * 1000, 2);
    }
    
    /**
     * Get performance test results
     */
    public function getPerformanceResults(): array {
        return $this->performanceResults;
    }
}
```

### 19.4 Integration Testing Framework

#### 19.4.1 Documentation Integration Tests

```php
class DocumentationIntegrationTester {
    private Config $config;
    private array $testResults = [];
    
    public function __construct() {
        $this->config = new Config();
    }
    
    /**
     * Run complete integration test suite
     */
    public function runIntegrationTests(): array {
        $results = [
            'openapi_generation' => $this->testOpenAPIGeneration(),
            'metadata_endpoints' => $this->testMetadataEndpoints(),
            'cache_functionality' => $this->testCacheFunctionality(),
            'error_handling' => $this->testErrorHandling(),
            'configuration_system' => $this->testConfigurationSystem(),
            'field_schema_generation' => $this->testFieldSchemaGeneration()
        ];
        
        $this->testResults = $results;
        return $results;
    }
    
    /**
     * Test OpenAPI specification generation end-to-end
     */
    private function testOpenAPIGeneration(): array {
        $test = ['name' => 'OpenAPI Generation', 'passed' => false, 'errors' => []];
        
        try {
            $generator = new OpenAPIGenerator();
            $spec = $generator->generateSpecification();
            
            // Validate basic structure
            if (!isset($spec['openapi']) || !isset($spec['info']) || !isset($spec['paths'])) {
                $test['errors'][] = 'Generated OpenAPI spec missing required sections';
                return $test;
            }
            
            // Validate content
            if (empty($spec['paths'])) {
                $test['errors'][] = 'Generated OpenAPI spec has no paths';
                return $test;
            }
            
            // Validate schema validation
            $validator = new OpenAPISchemaValidator();
            $validationErrors = $validator->validateOpenAPISpecification($spec);
            
            if (!empty($validationErrors)) {
                $test['errors'] = array_merge($test['errors'], $validationErrors);
                return $test;
            }
            
            $test['passed'] = true;
            
        } catch (\Exception $e) {
            $test['errors'][] = 'OpenAPI generation failed: ' . $e->getMessage();
        }
        
        return $test;
    }
    
    /**
     * Test metadata API endpoints
     */
    private function testMetadataEndpoints(): array {
        $test = ['name' => 'Metadata Endpoints', 'passed' => false, 'errors' => []];
        
        try {
            $controller = new MetadataAPIController();
            
            // Test getModels endpoint
            $models = $controller->getModels();
            if (!isset($models['success']) || !$models['success']) {
                $test['errors'][] = 'getModels endpoint failed';
                return $test;
            }
            
            // Test getFieldTypes endpoint
            $fieldTypes = $controller->getFieldTypes();
            if (!isset($fieldTypes['success']) || !$fieldTypes['success']) {
                $test['errors'][] = 'getFieldTypes endpoint failed';
                return $test;
            }
            
            // Test getModelMetadata for each available model
            if (isset($models['data']) && is_array($models['data'])) {
                foreach (array_keys($models['data']) as $modelName) {
                    $metadata = $controller->getModelMetadata($modelName);
                    if (!isset($metadata['success']) || !$metadata['success']) {
                        $test['errors'][] = "getModelMetadata failed for model: {$modelName}";
                        return $test;
                    }
                }
            }
            
            $test['passed'] = true;
            
        } catch (\Exception $e) {
            $test['errors'][] = 'Metadata endpoints test failed: ' . $e->getMessage();
        }
        
        return $test;
    }
    
    /**
     * Test cache functionality
     */
    private function testCacheFunctionality(): array {
        $test = ['name' => 'Cache Functionality', 'passed' => false, 'errors' => []];
        
        try {
            $cache = new DocumentationCache();
            
            // Test cache write and read
            $testData = ['test' => 'data', 'timestamp' => date('c')];
            $cache->cacheModelsList($testData);
            
            $cached = $cache->getCachedModelsList();
            if ($cached !== $testData) {
                $test['errors'][] = 'Cache write/read failed - data mismatch';
                return $test;
            }
            
            // Test cache clearing
            $cache->clearCache();
            $clearedCache = $cache->getCachedModelsList();
            if ($clearedCache !== null) {
                $test['errors'][] = 'Cache clearing failed - data still exists';
                return $test;
            }
            
            $test['passed'] = true;
            
        } catch (\Exception $e) {
            $test['errors'][] = 'Cache functionality test failed: ' . $e->getMessage();
        }
        
        return $test;
    }
    
    /**
     * Test error handling behavior
     */
    private function testErrorHandling(): array {
        $test = ['name' => 'Error Handling', 'passed' => false, 'errors' => []];
        
        try {
            $controller = new MetadataAPIController();
            
            // Test invalid model name handling
            try {
                $result = $controller->getModelMetadata('NonExistentModel');
                if (isset($result['success']) && $result['success']) {
                    $test['errors'][] = 'Error handling failed - should reject invalid model name';
                    return $test;
                }
            } catch (\Gravitycar\Exceptions\NotFoundException $e) {
                // Expected behavior - this is good
            }
            
            // Test graceful degradation with configured settings
            $originalSetting = $this->config->get('documentation.graceful_degradation', true);
            if ($originalSetting) {
                // Test that graceful degradation works
                // This would involve simulating various error conditions
            }
            
            $test['passed'] = true;
            
        } catch (\Exception $e) {
            $test['errors'][] = 'Error handling test failed: ' . $e->getMessage();
        }
        
        return $test;
    }
    
    /**
     * Test configuration system
     */
    private function testConfigurationSystem(): array {
        $test = ['name' => 'Configuration System', 'passed' => false, 'errors' => []];
        
        try {
            // Test configuration reading
            $cacheEnabled = $this->config->get('documentation.cache_enabled', true);
            $defaultComponent = $this->config->get('documentation.default_react_component', 'TextInput');
            
            // Test configuration validation
            $validationErrors = $this->config->validateDocumentationConfig();
            if (!empty($validationErrors)) {
                $test['errors'] = array_merge($test['errors'], $validationErrors);
                return $test;
            }
            
            $test['passed'] = true;
            
        } catch (\Exception $e) {
            $test['errors'][] = 'Configuration system test failed: ' . $e->getMessage();
        }
        
        return $test;
    }
    
    /**
     * Test field schema generation with actual field instances
     */
    private function testFieldSchemaGeneration(): array {
        $test = ['name' => 'Field Schema Generation', 'passed' => false, 'errors' => []];
        
        try {
            $generator = new OpenAPIGenerator();
            $fieldTypes = $this->config->get('metadata.field_types', []);
            
            foreach ($fieldTypes as $fieldType => $fieldData) {
                try {
                    // Test field instance creation and schema generation
                    $fieldFactory = \Gravitycar\Core\ServiceLocator::getFieldFactory();
                    $fieldInstance = $fieldFactory->createField($fieldType, $fieldData);
                    $schema = $fieldInstance->generateOpenAPISchema();
                    
                    if (empty($schema) || !isset($schema['type'])) {
                        $test['errors'][] = "Field type '{$fieldType}' generated invalid schema";
                        return $test;
                    }
                    
                } catch (\Exception $e) {
                    $test['errors'][] = "Field type '{$fieldType}' schema generation failed: " . $e->getMessage();
                    return $test;
                }
            }
            
            $test['passed'] = true;
            
        } catch (\Exception $e) {
            $test['errors'][] = 'Field schema generation test failed: ' . $e->getMessage();
        }
        
        return $test;
    }
    
    /**
     * Get test results summary
     */
    public function getTestResults(): array {
        return $this->testResults;
    }
    
    /**
     * Check if all tests passed
     */
    public function allTestsPassed(): bool {
        foreach ($this->testResults as $testResult) {
            if (!$testResult['passed']) {
                return false;
            }
        }
        return true;
    }
}
```

### 19.5 Test Runner and CLI Commands

#### 19.5.1 Documentation Test Runner

```php
class DocumentationTestRunner {
    private Config $config;
    
    public function __construct() {
        $this->config = new Config();
    }
    
    /**
     * Run all documentation tests
     */
    public function runAllTests(): array {
        $results = [
            'timestamp' => date('c'),
            'tests' => []
        ];
        
        echo "Running Documentation System Tests...\n\n";
        
        // Run schema validation tests
        echo "1. Running OpenAPI Schema Validation...\n";
        $schemaValidator = new OpenAPISchemaValidator();
        $generator = new OpenAPIGenerator();
        $spec = $generator->generateSpecification();
        $schemaErrors = $schemaValidator->validateOpenAPISpecification($spec);
        
        $results['tests']['schema_validation'] = [
            'passed' => empty($schemaErrors),
            'errors' => $schemaErrors
        ];
        echo empty($schemaErrors) ? "   ✅ PASSED\n" : "   ❌ FAILED\n";
        
        // Run metadata accuracy tests
        echo "2. Running Metadata Accuracy Validation...\n";
        $metadataValidator = new MetadataAccuracyValidator();
        $metadataErrors = array_merge(
            $metadataValidator->validateFieldTypeConsistency(),
            $metadataValidator->validateModelMetadataAccuracy()
        );
        
        $results['tests']['metadata_accuracy'] = [
            'passed' => empty($metadataErrors),
            'errors' => $metadataErrors
        ];
        echo empty($metadataErrors) ? "   ✅ PASSED\n" : "   ❌ FAILED\n";
        
        // Run performance tests
        echo "3. Running Performance Validation...\n";
        $performanceValidator = new DocumentationPerformanceValidator();
        $performanceErrors = $performanceValidator->validatePerformanceTargets();
        
        $results['tests']['performance'] = [
            'passed' => empty($performanceErrors),
            'errors' => $performanceErrors,
            'metrics' => $performanceValidator->getPerformanceResults()
        ];
        echo empty($performanceErrors) ? "   ✅ PASSED\n" : "   ❌ FAILED\n";
        
        // Run integration tests
        echo "4. Running Integration Tests...\n";
        $integrationTester = new DocumentationIntegrationTester();
        $integrationResults = $integrationTester->runIntegrationTests();
        
        $results['tests']['integration'] = $integrationResults;
        echo $integrationTester->allTestsPassed() ? "   ✅ PASSED\n" : "   ❌ FAILED\n";
        
        echo "\n";
        
        // Print summary
        $totalTests = count($results['tests']);
        $passedTests = 0;
        foreach ($results['tests'] as $test) {
            if (isset($test['passed']) && $test['passed']) {
                $passedTests++;
            } elseif (is_array($test)) {
                // Handle integration tests structure
                $allPassed = true;
                foreach ($test as $subTest) {
                    if (!$subTest['passed']) {
                        $allPassed = false;
                        break;
                    }
                }
                if ($allPassed) $passedTests++;
            }
        }
        
        echo "Test Summary: {$passedTests}/{$totalTests} test suites passed\n";
        
        if ($passedTests < $totalTests) {
            echo "\nFailed Tests:\n";
            foreach ($results['tests'] as $testName => $testResult) {
                if (isset($testResult['passed']) && !$testResult['passed']) {
                    echo "- {$testName}: " . implode(', ', $testResult['errors']) . "\n";
                } elseif (is_array($testResult)) {
                    foreach ($testResult as $subTestName => $subTest) {
                        if (!$subTest['passed']) {
                            echo "- {$testName}.{$subTestName}: " . implode(', ', $subTest['errors']) . "\n";
                        }
                    }
                }
            }
        }
        
        $results['summary'] = [
            'total_tests' => $totalTests,
            'passed_tests' => $passedTests,
            'all_passed' => $passedTests === $totalTests
        ];
        
        return $results;
    }
    
    /**
     * Run specific test suite
     */
    public function runSpecificTest(string $testType): array {
        switch ($testType) {
            case 'schema':
                return $this->runSchemaValidationTest();
            case 'metadata':
                return $this->runMetadataAccuracyTest();
            case 'performance':
                return $this->runPerformanceTest();
            case 'integration':
                return $this->runIntegrationTest();
            default:
                throw new \InvalidArgumentException("Unknown test type: {$testType}");
        }
    }
    
    private function runSchemaValidationTest(): array {
        $validator = new OpenAPISchemaValidator();
        $generator = new OpenAPIGenerator();
        $spec = $generator->generateSpecification();
        $errors = $validator->validateOpenAPISpecification($spec);
        
        return [
            'test_type' => 'schema_validation',
            'passed' => empty($errors),
            'errors' => $errors,
            'timestamp' => date('c')
        ];
    }
    
    private function runMetadataAccuracyTest(): array {
        $validator = new MetadataAccuracyValidator();
        $errors = array_merge(
            $validator->validateFieldTypeConsistency(),
            $validator->validateModelMetadataAccuracy()
        );
        
        return [
            'test_type' => 'metadata_accuracy',
            'passed' => empty($errors),
            'errors' => $errors,
            'timestamp' => date('c')
        ];
    }
    
    private function runPerformanceTest(): array {
        $validator = new DocumentationPerformanceValidator();
        $errors = $validator->validatePerformanceTargets();
        
        return [
            'test_type' => 'performance',
            'passed' => empty($errors),
            'errors' => $errors,
            'metrics' => $validator->getPerformanceResults(),
            'timestamp' => date('c')
        ];
    }
    
    private function runIntegrationTest(): array {
        $tester = new DocumentationIntegrationTester();
        $results = $tester->runIntegrationTests();
        
        return [
            'test_type' => 'integration',
            'passed' => $tester->allTestsPassed(),
            'results' => $results,
            'timestamp' => date('c')
        ];
    }
}
```

### 19.6 Benefits for Small-Scale Framework

#### 19.6.1 Specific Advantages

- **🚀 Fast Execution**: Tests designed for small scale run quickly (< 1 second total)
- **📁 Simple Infrastructure**: File-based testing, no complex test databases needed
- **🛠️ Developer-Friendly**: Clear test output, easy to understand failures
- **🔍 Comprehensive Coverage**: Tests all aspects despite small scale
- **⚡ Integration Focus**: Ensures documentation matches actual framework
- **📋 Automated Validation**: Reduces manual testing burden

#### 19.6.2 Small-Scale Optimizations

- **No Test Database**: Uses existing metadata cache for testing
- **In-Memory Testing**: Tests run without external dependencies
- **Simple Assertions**: Clear pass/fail criteria
- **Performance Targets**: Conservative targets appropriate for small scale
- **Error Simulation**: Tests error conditions without complex setup

### 19.7 CLI Commands for Testing

```bash
# Run all documentation tests
php cli.php documentation:test

# Run specific test suite
php cli.php documentation:test --type=schema
php cli.php documentation:test --type=metadata
php cli.php documentation:test --type=performance
php cli.php documentation:test --type=integration

# Run tests with verbose output
php cli.php documentation:test --verbose

# Generate test report
php cli.php documentation:test --report=json > test_results.json
```

This validation and testing infrastructure ensures the API documentation system generates accurate, reliable documentation while maintaining the simplicity appropriate for a small-scale framework.

## 17. Error Handling & Recovery Strategies

### 17.1 Leveraging Existing APIException Framework

The Gravitycar Framework already includes a comprehensive suite of APIException classes in `src/Exceptions/` that perfectly address the error handling needs for the API documentation system. These exceptions provide:

- ✅ **HTTP Status Code Mapping**: Each exception maps to appropriate HTTP status codes
- ✅ **Structured Error Responses**: Consistent error format with context and logging
- ✅ **Error Type Classification**: Client errors (4xx) vs Server errors (5xx)
- ✅ **Default Messages**: Human-readable error messages for each exception type
- ✅ **Context Support**: Additional context for debugging and logging
- ✅ **Exception Chaining**: Support for previous exceptions in error chains

### 17.2 API Documentation Endpoint Error Mapping

#### 17.2.1 MetadataAPIController Error Handling

```php
class MetadataAPIController {
    // ... existing methods ...
    
    public function getModels(): array {
        try {
            // Check cache first
            $cached = $this->cache->getCachedModelsList();
            if ($cached !== null) {
                return $cached;
            }
            
            $cachedMetadata = $this->metadataEngine->getCachedMetadata();
            if (empty($cachedMetadata['models'])) {
                throw new NotFoundException('No models found in metadata cache');
            }
            
            // Process models...
            $result = [
                'success' => true,
                'data' => $models,
                'timestamp' => date('c')
            ];
            
            $this->cache->cacheModelsList($result);
            return $result;
            
        } catch (NotFoundException $e) {
            // 404 - No models found
            throw $e;
        } catch (\Exception $e) {
            // 500 - Unexpected server error
            throw new InternalServerErrorException(
                'Failed to retrieve models metadata', 
                ['original_error' => $e->getMessage()],
                $e
            );
        }
    }
    
    public function getModelMetadata(string $modelName): array {
        try {
            // Validate model name format
            if (empty($modelName) || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $modelName)) {
                throw new BadRequestException(
                    'Invalid model name format',
                    ['model_name' => $modelName]
                );
            }
            
            // Check if model exists
            if (!$this->metadataEngine->modelExists($modelName)) {
                throw new NotFoundException(
                    "Model '{$modelName}' not found",
                    ['requested_model' => $modelName]
                );
            }
            
            // Check cache first
            $cached = $this->cache->getCachedModelMetadata($modelName);
            if ($cached !== null) {
                return $cached;
            }
            
            // Generate metadata
            $modelData = $this->metadataEngine->getModelMetadata($modelName);
            $routes = $this->routeRegistry->getModelRoutes($modelName);
            
            // Process and return result...
            
        } catch (BadRequestException | NotFoundException $e) {
            // Re-throw client errors as-is
            throw $e;
        } catch (\Exception $e) {
            // 500 - Unexpected server error
            throw new InternalServerErrorException(
                "Failed to retrieve metadata for model '{$modelName}'",
                [
                    'model_name' => $modelName,
                    'original_error' => $e->getMessage()
                ],
                $e
            );
        }
    }
    
    public function getFieldTypes(): array {
        try {
            // Check cache first
            $cached = $this->cache->getCachedFieldTypes();
            if ($cached !== null) {
                return $cached;
            }
            
            // Get field type definitions from MetadataEngine
            $fieldTypeDefinitions = $this->metadataEngine->getFieldTypeDefinitions();
            if (empty($fieldTypeDefinitions)) {
                throw new ServiceUnavailableException(
                    'Field type discovery failed - metadata cache may be corrupted',
                    ['cache_path' => 'cache/metadata_cache.php']
                );
            }
            
            // Get component mappings
            $componentMap = $this->componentMapper->getFieldToComponentMap();
            
            // Process field types...
            
        } catch (ServiceUnavailableException $e) {
            // Re-throw service unavailable errors
            throw $e;
        } catch (\Exception $e) {
            // 500 - Unexpected server error  
            throw new InternalServerErrorException(
                'Failed to retrieve field type definitions',
                ['original_error' => $e->getMessage()],
                $e
            );
        }
    }
    
    public function clearDocumentationCache(): array {
        try {
            $this->cache->clearCache();
            return [
                'success' => true,
                'message' => 'Documentation cache cleared successfully',
                'timestamp' => date('c')
            ];
        } catch (\Exception $e) {
            // 500 - Cache clearing failed
            throw new InternalServerErrorException(
                'Failed to clear documentation cache',
                [
                    'cache_directory' => DocumentationCache::CACHE_DIR,
                    'original_error' => $e->getMessage()
                ],
                $e
            );
        }
    }
}
```

#### 17.2.2 OpenAPIGenerator Error Handling

```php
class OpenAPIGenerator {
    // ... existing methods ...
    
    public function generateSpecification(): array {
        try {
            // Check cache first
            $cached = $this->cache->getCachedOpenAPISpec();
            if ($cached !== null) {
                return $cached;
            }
            
            // Generate specification components
            $spec = [
                'openapi' => '3.0.3',
                'info' => $this->generateInfo(),
                'servers' => $this->generateServers(),
                'paths' => $this->generatePaths(),
                'components' => $this->generateComponents()
            ];
            
            // Validate generated specification
            if (empty($spec['paths'])) {
                throw new ServiceUnavailableException(
                    'No API paths found - route registry may be empty',
                    ['spec_sections' => array_keys($spec)]
                );
            }
            
            // Cache and return
            $this->cache->cacheOpenAPISpec($spec);
            return $spec;
            
        } catch (ServiceUnavailableException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InternalServerErrorException(
                'Failed to generate OpenAPI specification',
                ['original_error' => $e->getMessage()],
                $e
            );
        }
    }
    
    private function generateFieldSchema(array $fieldData): array {
        try {
            $fieldType = $fieldData['type'] ?? 'Text';
            
            // Use FieldFactory to create field instance
            $fieldFactory = \Gravitycar\Core\ServiceLocator::getFieldFactory();
            $fieldInstance = $fieldFactory->createField($fieldType, $fieldData);
            
            // Generate schema from field instance
            return $fieldInstance->generateOpenAPISchema();
            
        } catch (\InvalidArgumentException $e) {
            // 422 - Invalid field configuration
            throw new UnprocessableEntityException(
                "Invalid field configuration for type '{$fieldType}'",
                [
                    'field_type' => $fieldType,
                    'field_data' => $fieldData,
                    'original_error' => $e->getMessage()
                ],
                $e
            );
        } catch (\Exception $e) {
            // Log error but provide fallback schema
            $this->logger->warning("Failed to generate schema for field type {$fieldType}: " . $e->getMessage());
            
            // Return fallback schema instead of throwing
            return [
                'type' => 'string',
                'description' => $fieldData['label'] ?? "Field of type {$fieldType}",
                'x-generation-error' => 'Schema generated from fallback due to field creation failure'
            ];
        }
    }
}
```

#### 17.2.3 ReactComponentMapper Error Handling

```php
class ReactComponentMapper {
    // ... existing methods ...
    
    private function initializeFieldComponentMap(): void {
        try {
            // Get field types from MetadataEngine
            $fieldTypes = $this->metadataEngine->getFieldTypeDefinitions();
            
            if (empty($fieldTypes)) {
                throw new ServiceUnavailableException(
                    'No field types found in metadata cache',
                    ['cache_file' => 'cache/metadata_cache.php']
                );
            }
            
            // Build component map
            $this->fieldComponentMap = [];
            foreach ($fieldTypes as $fieldType => $fieldData) {
                $this->fieldComponentMap[$fieldType] = [
                    'component' => $fieldData['react_component'] ?? $this->getDefaultComponent(),
                    'props' => $fieldData['react_props'] ?? ['placeholder']
                ];
            }
            
        } catch (ServiceUnavailableException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InternalServerErrorException(
                'Failed to initialize field component mappings',
                ['original_error' => $e->getMessage()],
                $e
            );
        }
    }
    
    public function generateFormSchema(string $modelName): array {
        try {
            $modelData = $this->metadataEngine->getModelMetadata($modelName);
            if (empty($modelData)) {
                throw new NotFoundException(
                    "Model '{$modelName}' not found or has no metadata",
                    ['model_name' => $modelName]
                );
            }
            
            // Generate form schema...
            
        } catch (NotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InternalServerErrorException(
                "Failed to generate form schema for model '{$modelName}'",
                [
                    'model_name' => $modelName,
                    'original_error' => $e->getMessage()
                ],
                $e
            );
        }
    }
    
    private function getDefaultComponent(): string {
        // Get from configuration or use framework default
        return \Gravitycar\Core\Config::get('documentation.default_react_component', 'TextInput');
    }
}
```

### 17.3 Cache Error Recovery Strategies

#### 17.3.1 DocumentationCache Error Handling

```php
class DocumentationCache {
    // ... existing methods ...
    
    public function getCachedOpenAPISpec(): ?array {
        try {
            $cacheFile = self::CACHE_DIR . 'openapi_spec.php';
            
            if (!file_exists($cacheFile)) {
                return null; // Cache miss is normal
            }
            
            // Validate cache file is readable
            if (!is_readable($cacheFile)) {
                throw new ServiceUnavailableException(
                    'Documentation cache file is not readable',
                    [
                        'cache_file' => $cacheFile,
                        'permissions' => substr(sprintf('%o', fileperms($cacheFile)), -4)
                    ]
                );
            }
            
            $spec = include $cacheFile;
            
            // Validate cache contents
            if (!is_array($spec) || empty($spec['openapi'])) {
                // Corrupted cache - remove it
                unlink($cacheFile);
                return null;
            }
            
            return $spec;
            
        } catch (ServiceUnavailableException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Log error and treat as cache miss
            error_log("Cache read error: " . $e->getMessage());
            return null;
        }
    }
    
    public function cacheOpenAPISpec(array $spec): void {
        try {
            // Ensure cache directory exists
            if (!is_dir(self::CACHE_DIR)) {
                if (!mkdir(self::CACHE_DIR, 0755, true)) {
                    throw new ServiceUnavailableException(
                        'Failed to create documentation cache directory',
                        ['cache_dir' => self::CACHE_DIR]
                    );
                }
            }
            
            // Validate cache directory is writable
            if (!is_writable(self::CACHE_DIR)) {
                throw new ServiceUnavailableException(
                    'Documentation cache directory is not writable',
                    [
                        'cache_dir' => self::CACHE_DIR,
                        'permissions' => substr(sprintf('%o', fileperms(self::CACHE_DIR)), -4)
                    ]
                );
            }
            
            $cacheFile = self::CACHE_DIR . 'openapi_spec.php';
            $content = '<?php return ' . var_export($spec, true) . ';';
            
            // Write to temporary file first, then rename (atomic operation)
            $tempFile = $cacheFile . '.tmp';
            if (file_put_contents($tempFile, $content) === false) {
                throw new ServiceUnavailableException(
                    'Failed to write documentation cache file',
                    ['cache_file' => $cacheFile]
                );
            }
            
            if (!rename($tempFile, $cacheFile)) {
                unlink($tempFile); // Clean up temp file
                throw new ServiceUnavailableException(
                    'Failed to finalize documentation cache file',
                    ['cache_file' => $cacheFile]
                );
            }
            
        } catch (ServiceUnavailableException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InternalServerErrorException(
                'Unexpected error while caching OpenAPI specification',
                ['original_error' => $e->getMessage()],
                $e
            );
        }
    }
    
    public function clearCache(): void {
        try {
            if (!is_dir(self::CACHE_DIR)) {
                return; // Nothing to clear
            }
            
            $files = glob(self::CACHE_DIR . '*.php');
            if ($files === false) {
                throw new ServiceUnavailableException(
                    'Failed to list documentation cache files',
                    ['cache_dir' => self::CACHE_DIR]
                );
            }
            
            foreach ($files as $file) {
                if (!unlink($file)) {
                    throw new ServiceUnavailableException(
                        'Failed to delete cache file',
                        ['cache_file' => $file]
                    );
                }
            }
            
        } catch (ServiceUnavailableException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InternalServerErrorException(
                'Unexpected error while clearing documentation cache',
                ['original_error' => $e->getMessage()],
                $e
            );
        }
    }
}
```

### 17.4 Validation Rule Discovery Error Handling

#### 17.4.1 MetadataEngine Validation Rule Error Recovery

```php
// Enhanced MetadataEngine methods with error handling
class MetadataEngine {
    // ... existing methods ...
    
    private function getSupportedValidationRulesForFieldType(string $fieldType): array {
        try {
            $validationRuleFactory = \Gravitycar\Core\ServiceLocator::getValidationRuleFactory();
            $availableRules = $validationRuleFactory->getAvailableValidationRules();
            
            $supportedRules = [];
            foreach ($availableRules as $ruleName) {
                try {
                    // Attempt to create validation rule instance
                    $ruleInstance = $validationRuleFactory->createValidationRule($ruleName);
                    $description = $this->getValidationRuleDescription($ruleInstance);
                    
                    $supportedRules[] = [
                        'name' => $ruleName,
                        'description' => $description,
                        'class' => get_class($ruleInstance)
                    ];
                    
                } catch (\Exception $e) {
                    // Log individual rule failure but continue processing others
                    $this->logger->warning(
                        "Failed to create validation rule '{$ruleName}': " . $e->getMessage(),
                        ['field_type' => $fieldType, 'rule_name' => $ruleName]
                    );
                    
                    // Add fallback rule info
                    $supportedRules[] = [
                        'name' => $ruleName,
                        'description' => "Validation rule (description unavailable)",
                        'class' => "Unknown",
                        'error' => 'Failed to instantiate rule class'
                    ];
                    continue;
                }
            }
            
            return $supportedRules;
            
        } catch (\Exception $e) {
            // If validation rule factory fails completely, log and return empty array
            $this->logger->error(
                "Validation rule discovery failed for field type '{$fieldType}': " . $e->getMessage(),
                ['field_type' => $fieldType]
            );
            
            return [];
        }
    }
    
    private function getValidationRuleDescription(\Gravitycar\Validation\ValidationRuleBase $ruleInstance): string {
        try {
            $reflection = new \ReflectionClass($ruleInstance);
            $description = $this->getStaticProperty($reflection, 'description', null);
            
            if ($description !== null) {
                return $description;
            }
            
            // Generate fallback description from class name
            $className = $reflection->getShortName();
            return $this->generateDescriptionFromClassName($className);
            
        } catch (\Exception $e) {
            // If reflection fails, return generic description
            $className = get_class($ruleInstance);
            $this->logger->warning(
                "Failed to get description for validation rule '{$className}': " . $e->getMessage()
            );
            
            return "Validation rule";
        }
    }
    
    protected function scanAndLoadFieldTypes(): array {
        $fieldTypes = [];
        
        try {
            if (!is_dir($this->fieldsDirPath)) {
                $this->logger->warning("Fields directory not found: {$this->fieldsDirPath}");
                return $fieldTypes;
            }
            
            $files = scandir($this->fieldsDirPath);
            if ($files === false) {
                throw new \Exception("Failed to scan fields directory: {$this->fieldsDirPath}");
            }
            
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || !str_ends_with($file, '.php') || $file === 'FieldBase.php') {
                    continue;
                }
                
                try {
                    $className = str_replace('.php', '', $file);
                    $fullClassName = "Gravitycar\\Fields\\{$className}";
                    
                    // Check if class exists
                    if (!class_exists($fullClassName)) {
                        $this->logger->warning("Field class not found: {$fullClassName}");
                        continue;
                    }
                    
                    $reflection = new \ReflectionClass($fullClassName);
                    
                    // Check if it extends FieldBase
                    if (!$reflection->isSubclassOf('Gravitycar\\Fields\\FieldBase')) {
                        $this->logger->warning("Class {$fullClassName} does not extend FieldBase");
                        continue;
                    }
                    
                    // Extract field metadata with error handling
                    $fieldType = $this->extractFieldTypeFromClassName($className);
                    $fieldTypes[$fieldType] = $this->extractFieldMetadata($reflection, $fieldType);
                    
                } catch (\Exception $e) {
                    // Log individual field processing error but continue
                    $this->logger->warning(
                        "Failed to process field file '{$file}': " . $e->getMessage(),
                        ['file' => $file]
                    );
                    continue;
                }
            }
            
            return $fieldTypes;
            
        } catch (\Exception $e) {
            $this->logger->error(
                "Field type discovery failed: " . $e->getMessage(),
                ['fields_dir' => $this->fieldsDirPath]
            );
            
            // Return empty array - system can still function without field type discovery
            return [];
        }
    }
}
```

### 17.5 Error Response Format Standardization

#### 17.5.1 Centralized Error Handler

```php
class DocumentationErrorHandler {
    /**
     * Convert APIException to standardized error response
     */
    public static function handleAPIException(APIException $exception): array {
        return [
            'success' => false,
            'status' => $exception->getHttpStatusCode(),
            'error' => $exception->getMessage(),
            'error_type' => $exception->getErrorType(),
            'error_category' => method_exists($exception, 'getErrorCategory') 
                ? $exception->getErrorCategory() 
                : 'Unknown',
            'context' => $exception->getContext(),
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Handle validation errors specifically
     */
    public static function handleValidationError(UnprocessableEntityException $exception): array {
        $response = self::handleAPIException($exception);
        
        // Add validation errors if available
        $context = $exception->getContext();
        if (isset($context['validation_errors'])) {
            $response['validation_errors'] = $context['validation_errors'];
        }
        
        return $response;
    }
}
```

### 17.6 Recovery Strategy Summary

#### 17.6.1 Error Classification and Responses

| **Error Scenario** | **Exception Type** | **HTTP Status** | **Recovery Action** |
|---|---|---|---|
| Invalid model name | `BadRequestException` | 400 | Return error, validate input format |
| Model not found | `NotFoundException` | 404 | Return error, suggest available models |
| Field validation failure | `UnprocessableEntityException` | 422 | Return fallback schema with error notes |
| Cache file corruption | `ServiceUnavailableException` | 503 | Delete corrupted cache, regenerate |
| Cache directory not writable | `ServiceUnavailableException` | 503 | Log error, suggest file permissions fix |
| MetadataEngine failure | `InternalServerErrorException` | 500 | Log error, return generic error response |
| Field type discovery failure | *(Logged, not thrown)* | - | Continue with empty field types array |
| Validation rule creation failure | *(Logged, not thrown)* | - | Use fallback rule description |

#### 17.6.2 Graceful Degradation Principles

1. **Cache Failures**: System continues to function, regenerates cache when possible
2. **Field Type Failures**: Individual field failures don't stop overall processing
3. **Validation Rule Failures**: Fallback descriptions provided for missing rules
4. **Metadata Corruption**: Corrupted cache files are automatically removed and regenerated
5. **Permission Issues**: Clear error messages with actionable suggestions

The existing APIException framework provides comprehensive, production-ready error handling that perfectly addresses the documentation system's needs without requiring new exception classes.
4. **Medium Priority**: Add validation and testing infrastructure
5. **Low Priority**: Advanced development tools and monitoring

### 16.7 Hard-Coded Elements Resolution Status

#### ✅ **Field Type Arrays in OpenAPI Generator - RESOLVED**
**Problem**: Hard-coded arrays like `['Enum', 'RadioButtonSet']` and `['Integer', 'Float']` in `generateFieldSchema()` method.

**Solution Implemented**: 
- Added abstract `generateOpenAPISchema()` method to FieldBase class
- Implemented method in all FieldBase subclasses with instance-specific logic
- Updated `OpenAPIGenerator::generateFieldSchema()` to use FieldFactory and delegate to field instances
- Eliminated all hard-coded field type handling in OpenAPIGenerator

**Benefits**:
- ✅ **Completely Metadata-Driven**: Field instances generate their own schemas based on actual configuration
- ✅ **No Hard-Coded Types**: OpenAPIGenerator has no field-type-specific logic
- ✅ **Instance-Specific**: Schemas reflect actual field constraints (maxLength, options, etc.)
- ✅ **Extensible**: New field types work automatically without code changes
- ✅ **Error Recovery**: Fallback schema generation if field creation fails
- ✅ **Framework Consistency**: Uses FieldFactory pattern throughout

#### 🔄 **Remaining Issues to Address**
1. **Default React Component Fallback**: Hard-coded `'TextInput'` fallback should be configurable
2. **Validation Rule Discovery Failures**: Need fallback when validation rule classes can't be instantiated  
3. **Error Response Format**: Standardized error responses for metadata endpoints needed
4. **Medium Priority**: Add validation and testing infrastructure
5. **Low Priority**: Advanced development tools and monitoring
