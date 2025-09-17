# ValidationRuleBase Subclass Caching Implementation Plan

## Feature Overview

This implementation plan addresses the anti-pattern in `ValidationRuleFactory::discoverValidationRules()` that scans the validation directory on every instantiation. The goal is to move validation rule discovery into the MetadataEngine's caching system, similar to how FieldBase subclasses are currently cached, and update ValidationRuleFactory to use the cached data instead of performing filesystem scans.

## Requirements

### Functional Requirements
1. **Cache ValidationRuleBase Subclasses**: Add validation rule discovery to MetadataEngine's `loadAllMetadata()` process
2. **Eliminate Directory Scanning**: Remove the `discoverValidationRules()` method from ValidationRuleFactory
3. **Maintain API Compatibility**: Keep the same public interface for ValidationRuleFactory methods
4. **Cache Persistence**: Validation rules should appear in `cache/metadata_cache.php` after running `setup.php`
5. **Performance Improvement**: Reduce the current 30+ filesystem scans per request to zero

### Non-functional Requirements
1. **Performance**: Eliminate redundant filesystem operations during application bootstrap
2. **Maintainability**: Centralize validation rule discovery in MetadataEngine
3. **Consistency**: Follow the same caching pattern used for FieldBase subclasses
4. **Backward Compatibility**: No breaking changes to existing validation rule usage

## Current State Analysis

### Existing Anti-Pattern
```php
// ValidationRuleFactory.php - Line 27-38 (PROBLEMATIC)
protected function discoverValidationRules(): void {
    $validationDir = __DIR__ . '/../Validation';
    if (!is_dir($validationDir)) {
        $this->logger->warning("Validation directory not found: $validationDir");
        return;
    }
    $files = scandir($validationDir);
    foreach ($files as $file) {
        if (preg_match('/^(.*)Validation\.php$/', $file, $matches)) {
            $type = $matches[1];
            $this->availableValidationRules[$type] = "Gravitycar\\Validation\\{$type}Validation";
        }
    }
}
```

### Current Duplication Issue
The MetadataEngine already scans validation rules in `getSupportedValidationRulesForFieldType()` (lines 597-633) but only for field type metadata. ValidationRuleFactory performs its own separate scan, creating redundant filesystem operations.

### Performance Impact
- **Current**: 31 ValidationRuleFactory instantiations per request × 1 directory scan each = 31 filesystem operations
- **Target**: 0 directory scans during request handling (cached data only)
- **Memory Savings**: ~60KB per request from eliminating redundant factory instances

## Pure DI Migration Strategy

### Following Pure DI Guidelines
This implementation follows the comprehensive Pure DI Guidelines from `docs/pure_di_guidelines.md`, specifically using the **Big Bang Migration** approach successfully used for ModelBase.

#### Migration Pattern Selection: Big Bang Migration
- **Rationale**: ValidationRuleFactory is a core factory with clear boundaries
- **Benefits**: Clean cut, immediate pure DI compliance, no transition period
- **Approach**: Complete constructor signature change with full dependency injection

#### Pure DI Compliance Checklist
- ✅ **Explicit Dependencies**: All dependencies injected via constructor
- ✅ **No ServiceLocator Fallbacks**: Complete elimination of ServiceLocator usage
- ✅ **Container Management**: All object creation through DI container
- ✅ **Immutable Dependencies**: Dependencies set once at construction
- ✅ **No Hidden Dependencies**: All required services visible in constructor

#### Container-First Architecture
```php
// ✅ AFTER: Pure dependency injection
class ValidationRuleFactory {
    public function __construct(
        private Logger $logger,
        private MetadataEngineInterface $metadataEngine
    ) {
        // All dependencies explicitly injected
        // No ServiceLocator calls
        // No filesystem operations in constructor
        // Ready for immediate use
    }
    
    public function createValidationRule(string $ruleName): ValidationRuleBase {
        // Get class name from cached metadata
        $rules = $this->metadataEngine->getValidationRuleDefinitions();
        $className = $rules[$ruleName]['class'] ?? null;
        
        if (!$className || !class_exists($className)) {
            throw new GCException("Validation rule not found: $ruleName");
        }
        
        // Use container for creation (not ServiceLocator)
        return ContainerConfig::getContainer()->newInstance($className);
    }
}
```

#### ServiceLocator Elimination
**Before (Anti-pattern):**
```php
// ❌ BEFORE: ServiceLocator pattern with filesystem scanning
class ValidationRuleFactory {
    public function __construct() {
        $this->logger = ServiceLocator::getLogger(); // Remove
        $this->discoverValidationRules(); // Remove - filesystem scanning
    }
    
    public function createValidationRule(string $ruleName): ValidationRuleBase {
        // ... 
        return ServiceLocator::create($className); // Remove
    }
}
```

**After (Pure DI):**
```php
// ✅ AFTER: Pure dependency injection
class ValidationRuleFactory {
    public function __construct(
        private Logger $logger,
        private MetadataEngineInterface $metadataEngine
    ) {
        // No ServiceLocator usage
        // No filesystem operations
        // All dependencies available immediately
    }
}
```

## Design

### Architecture Changes

#### 1. MetadataEngine Enhancements
- **New Method**: `scanAndLoadValidationRules()` - Discover all ValidationRuleBase subclasses
- **New Method**: `getValidationRuleDefinitions()` - Public API to access cached validation rules
- **Enhanced**: `loadAllMetadata()` - Include validation rules in metadata structure
- **Enhanced**: Cache structure to include `validation_rules` section

#### 2. ValidationRuleFactory Pure DI Refactoring
Following the Pure DI Guidelines from `docs/pure_di_guidelines.md`:

- **Remove ServiceLocator Dependencies**: Eliminate all `ServiceLocator::getLogger()` and `ServiceLocator::create()` calls
- **Constructor Dependency Injection**: Inject Logger and MetadataEngine explicitly
- **Remove Anti-Pattern Methods**: Eliminate `discoverValidationRules()` and filesystem scanning
- **Container-Based Creation**: Use DI container for validation rule instantiation
- **Immutable Dependencies**: Set all dependencies at construction time

**Pure DI Constructor Pattern:**
```php
class ValidationRuleFactory {
    public function __construct(
        private Logger $logger,
        private MetadataEngineInterface $metadataEngine
    ) {
        // All dependencies explicitly injected
        // No ServiceLocator calls
        // No filesystem operations
        // Ready for immediate use
    }
}
```

**Updated Methods:**
- **Remove**: `discoverValidationRules()` method
- **Remove**: `$availableValidationRules` property initialization in constructor
- **Modify**: `createValidationRule()` to get class names from MetadataEngine cache
- **Modify**: `getAvailableValidationRules()` to delegate to MetadataEngine
- **Add**: Container-based validation rule instantiation

#### 3. Cache Structure Enhancement
```php
// cache/metadata_cache.php structure
return [
    'models' => [...],
    'relationships' => [...],
    'field_types' => [...],
    'validation_rules' => [  // NEW SECTION
        'Required' => [
            'class' => 'Gravitycar\\Validation\\RequiredValidation',
            'description' => 'Ensures a value is present and not empty',
            'javascript_validation' => '...'
        ],
        'Email' => [
            'class' => 'Gravitycar\\Validation\\EmailValidation',
            'description' => 'Validates email format',
            'javascript_validation' => '...'
        ],
        // ... all other validation rules
    ]
];
```

## Implementation Steps

### Step 1: Enhance MetadataEngine with Validation Rule Discovery
**Files to modify:**
- `src/Metadata/MetadataEngine.php`

**Changes:**
1. Add `scanAndLoadValidationRules()` method similar to `scanAndLoadFieldTypes()`
2. Add `getValidationRuleDefinitions()` public method
3. Update `loadAllMetadata()` to include validation rules
4. Remove duplication between `getSupportedValidationRulesForFieldType()` and new validation rule scanning

### Step 2: Pure DI Migration for ValidationRuleFactory
**Files to modify:**
- `src/Factories/ValidationRuleFactory.php`
- `src/Core/ContainerConfig.php`

**Changes (following Pure DI Guidelines):**
1. **Constructor Refactoring**: Update constructor to accept explicit dependencies:
   ```php
   public function __construct(
       private Logger $logger,
       private MetadataEngineInterface $metadataEngine
   ) {
       // No ServiceLocator calls
       // No directory scanning in constructor
       // All dependencies explicitly injected
   }
   ```

2. **ServiceLocator Elimination**: Remove all ServiceLocator usage:
   - Replace `ServiceLocator::getLogger()` with injected `$logger`
   - Replace `ServiceLocator::create()` with container-based creation

3. **Remove Anti-Pattern Methods**:
   - Remove `discoverValidationRules()` method entirely
   - Remove `$availableValidationRules` property initialization

4. **Update Core Methods**:
   - `createValidationRule()`: Use MetadataEngine data instead of internal cache
   - `getAvailableValidationRules()`: Delegate to MetadataEngine

5. **Container Configuration**: Update DI container in `ContainerConfig.php`:
   ```php
   // Add dependency parameters for ValidationRuleFactory
   $di->params[\Gravitycar\Factories\ValidationRuleFactory::class] = [
       'logger' => $di->lazyGet('logger'),
       'metadataEngine' => $di->lazyGet('metadata_engine')
   ];
   ```

### Step 3: Container Dependency Ordering
**Files to modify:**
- `src/Core/ContainerConfig.php`

**Changes:**
1. Ensure MetadataEngine is configured before ValidationRuleFactory
2. Add proper dependency injection parameters
3. Verify no circular dependencies exist
4. Update service registration order if needed

### Step 4: Test Cache Generation
**Files to verify:**
- `cache/metadata_cache.php`
- `setup.php` execution

**Verification:**
1. Run `setup.php` and confirm validation rules appear in cache
2. Verify all 15+ validation rule classes are discovered and cached
3. Confirm cache structure matches design specification
4. Validate pure DI migration doesn't break cache generation

### Step 5: Create Comprehensive Test Suite
**Files to create/modify:**
- `Tests/Unit/Factories/ValidationRuleFactoryTest.php` (create new)
- `Tests/Unit/Metadata/MetadataEngineTest.php` (enhance existing)

**Pure DI Test Strategy (following guidelines):**
1. **ValidationRuleFactory Tests**:
   ```php
   class ValidationRuleFactoryTest extends PHPUnit\Framework\TestCase {
       private ValidationRuleFactory $factory;
       private Logger $mockLogger;
       private MetadataEngineInterface $mockMetadataEngine;
       
       protected function setUp(): void {
           $this->mockLogger = $this->createMock(Logger::class);
           $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
           
           // Direct injection - no complex setup needed
           $this->factory = new ValidationRuleFactory(
               $this->mockLogger,
               $this->mockMetadataEngine
           );
       }
   }
   ```

2. **Test Cases to Implement**:
   - `testCreateValidationRuleFromCache()` - Verify cache-based creation
   - `testGetAvailableValidationRulesFromCache()` - Verify cache-based listing
   - `testCreateValidationRuleThrowsExceptionForUnknownRule()` - Error handling
   - `testNoFilesystemAccessDuringOperation()` - Verify no directory scanning
   - `testPureDIConstructor()` - Validate all dependencies injected

3. **MetadataEngine Tests**:
   - `testScanAndLoadValidationRules()` - Verify discovery logic
   - `testGetValidationRuleDefinitions()` - Verify cache access
   - `testLoadAllMetadataIncludesValidationRules()` - Verify integration

### Step 6: Migration Validation
**Files to create:**
- `tmp/validate_validation_rule_factory_pure_di.php`

**Validation Script (following Pure DI Guidelines):**
```php
#!/usr/bin/env php
<?php
// Validation script to ensure pure DI migration success

function validateValidationRuleFactoryPureDI(): array {
    $errors = [];
    $className = 'Gravitycar\\Factories\\ValidationRuleFactory';
    
    try {
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();
        
        if (!$constructor) {
            $errors[] = "No constructor found";
            return $errors;
        }
        
        // Check constructor parameters
        $params = $constructor->getParameters();
        if (count($params) < 2) {
            $errors[] = "Constructor should have at least 2 parameters (Logger, MetadataEngine)";
        }
        
        // Check for ServiceLocator usage
        $classFile = file_get_contents($reflection->getFileName());
        if (strpos($classFile, 'ServiceLocator::') !== false) {
            $errors[] = "Still contains ServiceLocator usage - pure DI violation";
        }
        
        // Check for directory scanning
        if (strpos($classFile, 'scandir') !== false || strpos($classFile, 'glob') !== false) {
            $errors[] = "Still contains filesystem scanning - anti-pattern not removed";
        }
        
        // Check for discoverValidationRules method
        if ($reflection->hasMethod('discoverValidationRules')) {
            $errors[] = "discoverValidationRules method still exists - should be removed";
        }
        
    } catch (Exception $e) {
        $errors[] = "Reflection error: " . $e->getMessage();
    }
    
    return $errors;
}

// Run validation
$errors = validateValidationRuleFactoryPureDI();
if (empty($errors)) {
    echo "✅ ValidationRuleFactory: Pure DI validation passed\n";
} else {
    echo "❌ ValidationRuleFactory: " . implode(", ", $errors) . "\n";
    exit(1);
}
```

## Testing Strategy

### Unit Tests

#### 1. MetadataEngine Tests
- `testScanAndLoadValidationRules()` - Verify discovery logic
- `testGetValidationRuleDefinitions()` - Verify cache access
- `testLoadAllMetadataIncludesValidationRules()` - Verify integration
- `testValidationRuleCacheStructure()` - Verify cache format
- `testValidationRuleMetadataExtraction()` - Verify description and JavaScript generation

#### 2. ValidationRuleFactory Pure DI Tests
Following Pure DI Guidelines with direct dependency injection:

**Test Setup Pattern:**
```php
class ValidationRuleFactoryTest extends PHPUnit\Framework\TestCase {
    private ValidationRuleFactory $factory;
    private Logger $mockLogger;
    private MetadataEngineInterface $mockMetadataEngine;
    
    protected function setUp(): void {
        $this->mockLogger = $this->createMock(Logger::class);
        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        
        // Direct injection - no complex setup needed
        $this->factory = new ValidationRuleFactory(
            $this->mockLogger,
            $this->mockMetadataEngine
        );
    }
}
```

**Test Cases:**
- `testConstructorWithPureDI()` - Verify all dependencies injected correctly
- `testCreateValidationRuleFromCache()` - Verify cache-based creation
- `testGetAvailableValidationRulesFromCache()` - Verify cache-based listing
- `testCreateValidationRuleThrowsExceptionForUnknownRule()` - Error handling
- `testNoFilesystemAccessDuringOperation()` - Verify no directory scanning
- `testNoServiceLocatorUsage()` - Validate pure DI compliance
- `testContainerBasedValidationRuleCreation()` - Test DI container integration

#### 3. Migration Validation Tests
- `testValidationRuleFactoryPureDIMigration()` - Automated validation script
- `testNoDirectInstantiationRequired()` - Ensure container-based creation works
- `testBackwardCompatibilityMaintained()` - Verify existing code still works

### Integration Tests
1. **Cache Generation Test**: Verify `setup.php` populates validation rules in cache
2. **Performance Test**: Confirm no directory scanning during normal operations
3. **API Compatibility Test**: Ensure existing validation rule usage continues working
4. **Pure DI Integration**: Test container-based factory creation and usage
5. **End-to-End Validation**: Test complete validation flow with cached rules

### Manual Testing
1. Run `setup.php` and inspect `cache/metadata_cache.php` for validation rules
2. Verify all 15+ validation rule classes are discovered and cached correctly
3. Test application functionality to ensure no regressions
4. Validate pure DI migration using validation script
5. Performance comparison: before/after filesystem operation counts

## Documentation

### Code Documentation
1. Update MetadataEngine class documentation to include validation rule caching
2. Add inline documentation for new methods
3. Update ValidationRuleFactory documentation to reflect MetadataEngine dependency

### Implementation Notes
1. Document the refactoring in implementation notes
2. Update architecture documentation to reflect centralized caching approach
3. Document performance improvements achieved

## Risks and Mitigations

### Risk 1: Pure DI Migration Breaking Changes
**Risk**: Constructor signature change breaks existing direct instantiation
**Mitigation**: 
- Use container-based creation: `ContainerConfig::getContainer()->get('validation_rule_factory')`
- Update all direct instantiation points to use ServiceLocator or container
- Maintain backward compatibility through container configuration
**Probability**: Medium
**Impact**: High

### Risk 2: Circular Dependencies
**Risk**: MetadataEngine and ValidationRuleFactory might create circular dependency
**Mitigation**: Ensure ValidationRuleFactory depends on MetadataEngine, not vice versa
**Probability**: Low
**Impact**: High

### Risk 3: Container Configuration Issues
**Risk**: Incomplete or incorrect DI container parameter configuration
**Mitigation**: 
- Follow established ModelBase pure DI pattern
- Use comprehensive validation script to verify configuration
- Test container-based creation in isolation
**Probability**: Low
**Impact**: Medium

### Risk 4: Test Infrastructure Breakage
**Risk**: Existing tests that instantiate ValidationRuleFactory directly may fail
**Mitigation**: 
- Update all tests to use pure DI pattern with mock injection
- Create comprehensive test suite covering new behavior
- Use migration validation script to verify compliance
**Probability**: Medium
**Impact**: Medium

### Risk 5: Cache Invalidation Issues
**Risk**: Validation rule changes might not be reflected without cache rebuild
**Mitigation**: Document cache rebuilding process; ensure `setup.php` clears cache
**Probability**: Medium
**Impact**: Medium

### Risk 6: Performance Regression During Cache Build
**Risk**: Initial cache building might be slower due to more comprehensive scanning
**Mitigation**: This only affects setup time, not runtime performance
**Probability**: Medium
**Impact**: Low

## Expected Outcomes

### Performance Improvements
- **Filesystem Operations**: Reduction from 31 to 0 directory scans per request
- **Memory Usage**: ~60KB memory savings per request
- **Response Time**: Measurable improvement in application bootstrap time
- **Scalability**: Better performance under high load due to reduced I/O
- **Constructor Overhead**: Elimination of expensive filesystem operations during factory creation

### Pure DI Architecture Benefits
- **Explicit Dependencies**: All dependencies visible in constructor signature
- **Testability**: Direct mock injection, simplified test setup
- **Container Management**: Centralized object creation and lifecycle management
- **Immutable Dependencies**: Dependencies set once at construction, no runtime changes
- **Reduced Coupling**: No hidden ServiceLocator dependencies

### Maintainability Improvements
- **Centralized Discovery**: Single location for validation rule discovery logic
- **Consistent Architecture**: Matches existing FieldBase caching pattern and ModelBase pure DI pattern
- **Reduced Duplication**: Eliminates redundant scanning code between MetadataEngine and ValidationRuleFactory
- **Better Testing**: Easier to mock and test validation rule discovery
- **Clear Dependency Graph**: Explicit dependency relationships visible in code

### Code Quality
- **Separation of Concerns**: ValidationRuleFactory focuses on instantiation, MetadataEngine handles discovery
- **Single Responsibility**: MetadataEngine handles all metadata caching consistently
- **DRY Principle**: Eliminates duplicate directory scanning logic
- **Performance Optimization**: Moves expensive operations to setup phase
- **Pure DI Compliance**: Follows framework-wide dependency injection standards

### Architectural Consistency
- **Matches ModelBase Pattern**: Uses same pure DI approach successfully implemented in ModelBase
- **Container-First Design**: Aligns with framework's container-based architecture
- **ServiceLocator Elimination**: Removes anti-pattern usage for cleaner dependency management
- **Framework Standards**: Follows established Gravitycar pure DI guidelines

## Success Criteria

1. ✅ **Cache Generation**: `cache/metadata_cache.php` contains `validation_rules` section after `setup.php`
2. ✅ **No Filesystem Scans**: ValidationRuleFactory no longer performs directory scanning
3. ✅ **Pure DI Compliance**: ValidationRuleFactory uses explicit dependency injection (no ServiceLocator)
4. ✅ **Constructor Signature**: ValidationRuleFactory accepts Logger and MetadataEngine dependencies
5. ✅ **Container Integration**: ValidationRuleFactory properly configured in DI container
6. ✅ **API Compatibility**: Existing validation rule creation continues to work through container
7. ✅ **Performance Improvement**: Measurable reduction in filesystem operations (31 → 0 per request)
8. ✅ **Test Coverage**: All new functionality covered by unit tests with pure DI patterns
9. ✅ **Migration Validation**: Automated validation script confirms pure DI compliance
10. ✅ **Documentation**: Implementation properly documented with Pure DI examples

### Validation Checklist
- [ ] ValidationRuleFactory constructor accepts exactly 2 parameters: Logger, MetadataEngine
- [ ] No ServiceLocator usage in ValidationRuleFactory class
- [ ] No filesystem operations (scandir, glob) in ValidationRuleFactory
- [ ] `discoverValidationRules()` method completely removed
- [ ] Container properly configured with dependency parameters
- [ ] All tests use direct dependency injection (no ServiceLocator mocking)
- [ ] Validation script passes pure DI compliance checks
- [ ] Performance benchmarks show expected filesystem operation reduction
- [ ] Cache contains all validation rules with proper structure
- [ ] No breaking changes to public API when using container-based creation