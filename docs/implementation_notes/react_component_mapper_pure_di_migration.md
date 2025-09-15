# ReactComponentMapper Pure Dependency Injection Migration

## Overview
Successfully migrated `ReactComponentMapper` from ServiceLocator-based pattern to pure dependency injection, following the ModelBase pattern and pure DI guidelines established in the framework.

## Migration Summary

### Before (ServiceLocator Pattern)
```php
class ReactComponentMapper {
    private MetadataEngine $metadataEngine;
    private LoggerInterface $logger;
    
    public function __construct(?MetadataEngine $metadataEngine = null, ?Logger $logger = null) {
        $this->metadataEngine = $metadataEngine ?? $this->getMetadataEngine();
        $this->logger = $logger ?? ServiceLocator::getLogger();
        // ... lazy loading methods
    }
    
    protected function getMetadataEngine(): MetadataEngine {
        return MetadataEngine::getInstance();
    }
    
    protected function getLogger(): LoggerInterface {
        return $this->logger;
    }
}
```

### After (Pure Dependency Injection)
```php
class ReactComponentMapper {
    private MetadataEngineInterface $metadataEngine;
    private LoggerInterface $logger;
    
    public function __construct(
        LoggerInterface $logger,
        MetadataEngineInterface $metadataEngine
    ) {
        $this->logger = $logger;
        $this->metadataEngine = $metadataEngine;
        // Direct property access throughout
    }
}
```

## Dependencies Analysis

ReactComponentMapper requires 2 dependencies:

1. **LoggerInterface** - Operation logging for mapping operations
2. **MetadataEngineInterface** - Field type definitions and model metadata access

## Changes Made

### 1. Constructor Refactoring
- **Removed**: Optional parameters with `null` defaults
- **Added**: 2 explicit required dependencies with proper type hints
- **Updated**: Interface-based dependency injection (MetadataEngineInterface instead of concrete MetadataEngine)

### 2. ServiceLocator Elimination
- **Removed**: All `ServiceLocator` imports and usage
- **Removed**: `getMetadataEngine()` and `getLogger()` lazy getter methods
- **Updated**: All method calls to use direct property access

### 3. Interface Updates
Extended MetadataEngineInterface to include missing method:
```php
/**
 * Get field type definitions from cached metadata
 */
public function getFieldTypeDefinitions(): array;
```

### 4. Type System Improvements
- **Before**: Used concrete `MetadataEngine` class
- **After**: Uses `MetadataEngineInterface` for better abstraction
- **Benefits**: Interface-based design, easier testing, loose coupling

### 5. Container Configuration
Added parameter mapping in `ContainerConfig::configureCoreServices()`:
```php
$di->set('react_component_mapper', $di->lazyNew(\Gravitycar\Services\ReactComponentMapper::class));
$di->params[\Gravitycar\Services\ReactComponentMapper::class] = [
    'logger' => $di->lazyGet('logger'),
    'metadataEngine' => $di->lazyGet('metadata_engine')
];
```

### 6. Factory Method
Factory method already existed: `ContainerConfig::createReactComponentMapper()`

## Core Functionality

ReactComponentMapper provides essential React UI mapping services:

### Field-to-Component Mapping
- Maps Gravitycar field types to React components
- Generates form schemas for models
- Provides component props and validation rules

### React Component Types Supported
- `TextInput` - Text fields
- `NumberInput` - Integer/Float fields  
- `BooleanCheckbox` - Boolean fields
- `DatePicker` - Date/DateTime fields
- `SelectDropdown` - Enum fields
- `RelatedRecordSelect` - Foreign key relationships
- And more...

### Key Methods
- `generateFormSchema(string $modelName)` - Complete form generation
- `getReactComponentForField(array $fieldData)` - Individual field mapping
- `getReactComponentForFieldType(string $fieldType)` - Type-based mapping

## Usage Patterns

### Container-Based Creation
```php
$container = \Gravitycar\Core\ContainerConfig::getContainer();
$mapper = $container->get('react_component_mapper');
```

### Factory Method Creation
```php
$mapper = \Gravitycar\Core\ContainerConfig::createReactComponentMapper();
```

### Direct Instantiation (Testing)
```php
$mockLogger = $this->createMock(LoggerInterface::class);
$mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);

$mapper = new ReactComponentMapper($mockLogger, $mockMetadataEngine);
```

### Form Schema Generation
```php
$mapper = ContainerConfig::createReactComponentMapper();
$formSchema = $mapper->generateFormSchema('Users');

// Result structure:
[
    'model' => 'Users',
    'layout' => 'vertical',
    'fields' => [
        'username' => [
            'component' => 'TextInput',
            'props' => ['placeholder' => 'Enter username'],
            'validation' => ['required' => true],
            'label' => 'Username',
            'required' => true
        ]
        // ... more fields
    ]
]
```

## Benefits Achieved

### 1. **Explicit Dependencies**
- Constructor signature clearly shows all dependencies
- No hidden ServiceLocator or singleton dependencies
- Easy to understand and maintain

### 2. **Interface-Based Design**
- Uses MetadataEngineInterface instead of concrete class
- Better abstraction and loose coupling
- Easier to mock for testing

### 3. **Immutable Dependencies**
- All dependencies set at construction time
- No runtime dependency changes
- Predictable behavior

### 4. **Improved Testability**
- Direct mock injection via constructor
- No complex ServiceLocator mocking needed
- Clean test setup with interface mocks

### 5. **Type Safety**
- Strong typing on all dependencies
- IDE support and autocompletion
- Compile-time dependency checking

### 6. **Container Management**
- Proper dependency lifecycle management
- Singleton services shared across application
- Lazy loading with proper initialization

## Interface Extension

Added `getFieldTypeDefinitions()` method to MetadataEngineInterface:
- **Purpose**: Access field type definitions for component mapping
- **Return Type**: `array` - Field type configuration data
- **Usage**: Component initialization and mapping operations

## Validation Results

All migration validation checks passed:
- ✅ No ServiceLocator usage found
- ✅ 2 explicit constructor dependencies
- ✅ Container-based creation working
- ✅ Factory method creation working
- ✅ Basic component mapping functionality working
- ✅ No lazy getter methods found
- ✅ Interface compatibility verified

## Files Modified

1. **src/Services/ReactComponentMapper.php**
   - Constructor refactored to pure DI
   - ServiceLocator usage eliminated
   - Lazy getter methods removed
   - Interface-based MetadataEngine dependency

2. **src/Contracts/MetadataEngineInterface.php**
   - Added `getFieldTypeDefinitions()` method
   - Enhanced interface contract

3. **src/Core/ContainerConfig.php**
   - Added parameter configuration for ReactComponentMapper
   - Dependency injection mapping

4. **tmp/validate_react_component_mapper_pure_di.php**
   - Comprehensive validation script
   - Tests all aspects of pure DI implementation

## Component Mapping Examples

The service maps field types to React components:

```php
// Field type mappings
'Text' => 'TextInput'
'Integer' => 'NumberInput' 
'Boolean' => 'BooleanCheckbox'
'Date' => 'DatePicker'
'Enum' => 'SelectDropdown'
'RelatedRecord' => 'RelatedRecordSelect'
```

## Integration Points

ReactComponentMapper is used by:
- **OpenAPIGenerator**: Component information in API documentation
- **Frontend Forms**: React form generation
- **Metadata System**: Field type validation and mapping
- **Model CRUD**: Dynamic UI generation

## Testing Strategy

### Unit Tests (Pure DI Pattern)
```php
class ReactComponentMapperTest extends TestCase {
    private ReactComponentMapper $mapper;
    private LoggerInterface $mockLogger;
    private MetadataEngineInterface $mockMetadataEngine;
    
    protected function setUp(): void {
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        
        $this->mapper = new ReactComponentMapper(
            $this->mockLogger,
            $this->mockMetadataEngine
        );
    }
    
    public function testComponentMapping(): void {
        // Test with direct dependency injection
        // No ServiceLocator mocking needed
    }
}
```

## Next Steps

This migration completes the pure DI conversion for core services:
- ✅ **OpenAPIGenerator**: 7 dependencies - Complete
- ✅ **DocumentationCache**: 2 dependencies - Complete  
- ✅ **ReactComponentMapper**: 2 dependencies - Complete

### Future Work
- **Update Service Dependencies**: Review services that depend on these migrated services
- **Enhanced Testing**: Create comprehensive unit tests using pure DI pattern
- **Documentation Updates**: Update API documentation for pure DI usage patterns
- **Performance Analysis**: Measure impact of pure DI on application performance

The ReactComponentMapper pure DI migration is complete and serves as a template for interface-based service conversions with minimal dependencies.
