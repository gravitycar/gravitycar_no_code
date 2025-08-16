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
    public function generateSpecification(): array;
    public function generateModelSchema(string $modelClass): array;
    public function generateEndpointDocumentation(string $endpoint): array;
    public function generateResponseExamples(string $modelClass): array;
}

// Metadata API Controller
class MetadataAPIController {
    public function getModels(): array;
    public function getModelMetadata(string $modelName): array;
    public function getRelationships(): array;
    public function getValidationRules(string $modelName): array;
    public function getFieldTypes(): array;
}

// React Component Mapper
class ReactComponentMapper {
    public function getFieldToComponentMap(): array;
    public function getValidationRulesToReact(array $rules): array;
    public function generateFormSchema(string $modelClass): array;
}

// Documentation Cache Manager
class DocumentationCache {
    public function getCachedDocumentation(): ?array;
    public function cacheDorcumentation(array $docs): void;
    public function invalidateCache(): void;
    public function isCacheValid(): bool;
}
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
      "url": "/api",
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

### 5.1 Phase 1: Metadata API Endpoints (Week 1)

#### Step 1: Metadata API Controller
```php
class MetadataAPIController {
    public function getModels(): array {
        return [
            'success' => true,
            'data' => [
                'User' => [
                    'endpoint' => '/Users',
                    'description' => 'User management',
                    'fields_count' => 8,
                    'relationships_count' => 2
                ],
                'Product' => [
                    'endpoint' => '/Products',
                    'description' => 'Product catalog',
                    'fields_count' => 12,
                    'relationships_count' => 3
                ]
            ]
        ];
    }
    
    public function getModelMetadata(string $modelName): array {
        $model = ModelFactory::create($modelName);
        return [
            'success' => true,
            'data' => [
                'name' => $modelName,
                'table' => $model->getTableName(),
                'fields' => $this->getFieldsMetadata($model),
                'relationships' => $this->getRelationshipsMetadata($model),
                'validation_rules' => $this->getValidationMetadata($model),
                'api_endpoints' => $this->getEndpointsMetadata($modelName),
                'permissions' => $this->getPermissionsMetadata($model)
            ]
        ];
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
GET /api/help                    - List all available endpoints
GET /api/openapi.json           - OpenAPI specification
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

#### Step 2: Schema Generation
```php
private function generateModelSchema(string $modelClass): array {
    $model = new $modelClass();
    $fields = $model->getFields();
    
    $properties = [];
    $required = [];
    
    foreach ($fields as $fieldName => $field) {
        $properties[$fieldName] = $this->generateFieldSchema($field);
        if ($field->isRequired()) {
            $required[] = $fieldName;
        }
    }
    
    return [
        'type' => 'object',
        'properties' => $properties,
        'required' => $required
    ];
}
```

### 5.3 Phase 3: React Integration Metadata (Week 2)

#### Step 1: React Component Mapper
```php
class ReactComponentMapper {
    private array $fieldComponentMap = [
        'TextField' => [
            'component' => 'TextInput',
            'props' => ['maxLength', 'placeholder']
        ],
        'EmailField' => [
            'component' => 'EmailInput',
            'props' => ['placeholder', 'validation']
        ],
        'BooleanField' => [
            'component' => 'Checkbox',
            'props' => ['defaultChecked']
        ],
        'DateField' => [
            'component' => 'DatePicker',
            'props' => ['minDate', 'maxDate', 'format']
        ],
        'ImageField' => [
            'component' => 'FileUpload',
            'props' => ['accept', 'maxSize', 'preview']
        ]
    ];
    
    public function generateFormSchema(string $modelClass): array {
        $model = new $modelClass();
        $fields = $model->getFields();
        
        $formSchema = [
            'model' => $modelClass,
            'fields' => []
        ];
        
        foreach ($fields as $fieldName => $field) {
            $formSchema['fields'][$fieldName] = [
                'type' => get_class($field),
                'component' => $this->getReactComponent($field),
                'label' => $field->getLabel(),
                'required' => $field->isRequired(),
                'validation' => $this->getReactValidation($field),
                'props' => $this->getComponentProps($field)
            ];
        }
        
        return $formSchema;
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
            'spec_url' => '/api/openapi.json',
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
    "User": {
      "endpoint": "/Users",
      "description": "User management and authentication",
      "fields_count": 8,
      "relationships_count": 2,
      "operations": ["create", "read", "update", "delete"]
    },
    "Product": {
      "endpoint": "/Products", 
      "description": "Product catalog management",
      "fields_count": 12,
      "relationships_count": 3,
      "operations": ["create", "read", "update", "delete"]
    }
  },
  "timestamp": "2025-08-14T10:30:00+00:00"
}
```

### 6.2 Model Metadata Endpoint
```
GET /metadata/models/User

Response:
{
  "success": true,
  "status": 200,
  "data": {
    "name": "User",
    "table": "users",
    "description": "User management and authentication",
    "fields": {
      "id": {
        "type": "IDField",
        "label": "ID",
        "required": true,
        "primary_key": true,
        "auto_increment": true,
        "react_component": "HiddenInput"
      },
      "email": {
        "type": "EmailField",
        "label": "Email Address",
        "required": true,
        "unique": true,
        "max_length": 255,
        "react_component": "EmailInput",
        "validation": {
          "required": true,
          "email": true,
          "maxLength": 255
        }
      }
    },
    "relationships": {
      "orders": {
        "type": "HasMany",
        "target": "Order",
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
      "model": "User",
      "fields": [...],
      "layout": "vertical"
    }
  },
  "timestamp": "2025-08-14T10:30:00+00:00"
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
    "TextField": {
      "description": "Single-line text input",
      "react_component": "TextInput",
      "props": ["maxLength", "placeholder", "pattern"],
      "validation_rules": ["Required", "MaxLength", "MinLength", "Pattern"]
    },
    "EmailField": {
      "description": "Email address input with validation",
      "react_component": "EmailInput", 
      "props": ["placeholder"],
      "validation_rules": ["Required", "Email", "MaxLength"]
    }
  },
  "timestamp": "2025-08-14T10:30:00+00:00"
}
```

## 7. React Integration Examples

### 7.1 API Discovery Hook
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

### 7.2 Dynamic Form Generation
```typescript
const DynamicForm = ({ modelName }: { modelName: string }) => {
  const { data: metadata } = useQuery(['model-metadata', modelName], () =>
    fetch(`/metadata/models/${modelName}`).then(res => res.json())
  );
  
  if (!metadata) return <div>Loading...</div>;
  
  return (
    <form>
      {Object.entries(metadata.data.fields).map(([fieldName, field]) => {
        const Component = getReactComponent(field.react_component);
        return (
          <Component
            key={fieldName}
            name={fieldName}
            label={field.label}
            required={field.required}
            validation={field.validation}
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
    private const CACHE_TTL = 3600; // 1 hour
    
    public function getCachedDocumentation(): ?array {
        $cacheKey = 'api_documentation_' . $this->getVersionHash();
        return $this->cache->get($cacheKey);
    }
    
    public function cacheDocumentation(array $docs): void {
        $cacheKey = 'api_documentation_' . $this->getVersionHash();
        $this->cache->set($cacheKey, $docs, self::CACHE_TTL);
    }
    
    private function getVersionHash(): string {
        // Generate hash based on model files modification time
        $models = ModelFactory::getAvailableModels();
        $modTimes = [];
        
        foreach ($models as $model) {
            $reflection = new ReflectionClass($model);
            $modTimes[] = filemtime($reflection->getFileName());
        }
        
        return md5(implode('', $modTimes));
    }
}
```

### 8.2 Cache Invalidation
- Automatic invalidation when models change
- Manual cache clearing endpoint
- Development mode with no caching

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
- ModelFactory for model discovery
- MetadataEngine for field information
- Cache system for performance
- RestApiHandler for endpoint integration

## 12. Risks and Mitigations

### 12.1 Performance Risks
- **Risk**: Slow documentation generation with many models
- **Mitigation**: Caching strategy, lazy loading, background generation

### 12.2 Accuracy Risks
- **Risk**: Documentation out of sync with code
- **Mitigation**: Automated generation, cache invalidation, CI/CD integration

### 12.3 Usability Risks
- **Risk**: Complex documentation interface
- **Mitigation**: User testing, progressive disclosure, clear examples

## 13. Estimated Timeline

**Total Time: 2 weeks**

- **Week 1**: Metadata API endpoints, basic OpenAPI generation
- **Week 2**: React integration metadata, documentation UI, testing

This implementation will provide comprehensive API documentation and metadata services, enabling React developers to efficiently discover and consume the Gravitycar Framework APIs.
