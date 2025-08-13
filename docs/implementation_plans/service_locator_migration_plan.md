# ServiceLocator Migration Implementation Plan

## Overview
This plan outlines the migration from constructor dependen**RelationshipBase** `src/Relationships/RelationshipBase.php`
- **Current Dependencies**: `$metadataOrLogger, Logger $logger = null, ?CoreFieldsMetadata $coreFieldsMetadata = null` (Complex constructor)
- **Migration**: 🚨 **CRITICAL CHANGE REQUIRED**
  - **New Constructor**: `public function __construct(array $metadata)`
  - **Remove**: All Logger and CoreFieldsMetadata parameters
  - **ServiceLocator Usage**: `$this->logger = ServiceLocator::getLogger()` and `$this->coreFieldsMetadata = ServiceLocator::getCoreFieldsMetadata()`
- **Risk**: ⚠️ **HIGH** - Complex constructor logic, multiple usage patterns
- **Critical Issue Found**: ❌ RelationshipFactory is currently passing Logger instead of metadata
- **Breaking Change**: RelationshipFactory.php line 59 needs to be updated to pass metadata
- **Code Impact**: 
  - RelationshipFactory needs to get metadata before creating relationship
  - ContainerConfig.createRelationship() already passes metadata correctly
  - Internal RelationshipBase calls need metadata extractionjection to ServiceLocator-based dependency resolution across the Gravitycar Framework. The goal is to simplify class instantiation by eliminating the need to manually provide dependencies while maintaining testability.

## Migration Strategy

### Current Pattern (Constructor Injection)
```php
public function __construct(Logger $logger, DatabaseConnector $dbConnector) {
    $this->logger = $logger;
    $this->dbConnector = $dbConnector;
}
```

### Target Pattern (ServiceLocator)
```php
public function __construct() {
    $this->logger = ServiceLocator::getLogger();
    $this->dbConnector = ServiceLocator::getDatabaseConnector();
}
```

## Affected Classes Analysis

### 1. Core Framework Classes

#### ✅ Already Compatible
- `src/Core/Gravitycar.php` - Takes config array, doesn't inject dependencies
- `src/Api/Request.php` - No dependency injection
- `src/Exceptions/GCException.php` - No service dependencies

#### 🔄 Requires Migration

**DatabaseConnector** `src/Database/DatabaseConnector.php`
- **Current Dependencies**: `Logger $logger, array $dbParams`
- **Migration**: Logger → ServiceLocator, dbParams needs special handling (config-based)
- **Risk**: Medium - dbParams not available in ServiceLocator
- **Solution**: Get dbParams from ServiceLocator::getConfig()

**Config** `src/Core/Config.php`
- **Current Dependencies**: `Logger $logger`
- **Migration**: ✅ **REMOVE LOGGER ENTIRELY** - Logger is assigned but never used
- **Risk**: None - No actual logger usage found
- **Special Note**: This eliminates a major Config ↔ Logger recursion risk

### 2. API Layer Classes

**APIRouteRegistry** `src/Api/APIRouteRegistry.php`
- **Current Dependencies**: `LoggerInterface $logger`
- **Migration**: Logger → ServiceLocator
- **Risk**: Low

**APIPathScorer** `src/Api/APIPathScorer.php`
- **Current Dependencies**: `LoggerInterface $logger`
- **Migration**: Logger → ServiceLocator
- **Risk**: Low

**Router** `src/Api/Router.php`
- **Current Dependencies**: `$serviceLocator` (already migrated)
- **Migration**: ✅ Already using ServiceLocator pattern
- **Risk**: None

**ModelBaseAPIController** `src/Models/api/Api/ModelBaseAPIController.php`
- **Current Dependencies**: `Logger $logger = null` (optional)
- **Migration**: Already falls back to ServiceLocator::getLogger()
- **Risk**: None

### 3. Field Classes

All field classes follow the same pattern: `(array $metadata, Logger $logger)`

**FieldBase** `src/Fields/FieldBase.php` - Base class
- **Current Dependencies**: `array $metadata, Logger $logger`
- **Migration**: Logger → ServiceLocator, metadata stays as parameter
- **Risk**: Low
- **Impact**: All field subclasses inherit this change

**Field Subclasses** (All inherit from FieldBase):
- BigTextField, BooleanField, DateField, DateTimeField, EmailField
- EnumField, FieldBase, FloatField, IDField, ImageField, IntegerField
- MultiEnumField, PasswordField, RadioButtonSetField, RelatedRecordField, TextField
- **Migration**: Automatic via FieldBase change
- **Risk**: Low

### 4. Validation Classes

**ValidationRuleBase** `src/Validation/ValidationRuleBase.php`
- **Current Dependencies**: `Logger $logger, string $name = '', string $errorMessage = ''`
- **Migration**: Logger → ServiceLocator, other params stay
- **Risk**: Low

**ValidationEngine** `src/Validation/ValidationEngine.php`
- **Current Dependencies**: `Logger $logger`
- **Migration**: Logger → ServiceLocator  
- **Risk**: Low

**Validation Rule Classes**:
- AlphanumericValidation, DateTimeValidation, EmailValidation, ForeignKeyExistsValidation
- OptionsValidation, RequiredValidation, UniqueValidation
- **Migration**: All inherit from ValidationRuleBase
- **Risk**: Low

### 5. Model Classes

**ModelBase** `src/Models/ModelBase.php`
- **Current Dependencies**: `Logger $logger`
- **Migration**: Logger → ServiceLocator
- **Risk**: ⚠️ **MEDIUM** - Already uses ServiceLocator for MetadataEngine
- **Special Note**: Already partially migrated

**Model Subclasses**:
- Auditable, Movies, Movie_Quotes, Installer
- **Migration**: Automatic via ModelBase change
- **Risk**: Low

### 6. Relationship Classes

**RelationshipBase** `src/Relationships/RelationshipBase.php`
- **Current Dependencies**: Complex constructor with backward compatibility
- **Migration**: Logger → ServiceLocator, CoreFieldsMetadata → ServiceLocator
- **Risk**: ⚠️ **HIGH** - Complex constructor logic, already partially uses ServiceLocator
- **Special Note**: Already uses ServiceLocator for MetadataEngine

### 7. Factory Classes

**FieldFactory** `src/Factories/FieldFactory.php`
- **Current Dependencies**: `object $model, Logger $logger`
- **Migration**: Logger → ServiceLocator, model stays as parameter
- **Risk**: Low

**RelationshipFactory** `src/Factories/RelationshipFactory.php`
- **Current Dependencies**: `string $owner, Logger $logger`
- **Migration**: Logger → ServiceLocator, owner stays as parameter
- **Risk**: Low

**ValidationRuleFactory** `src/Factories/ValidationRuleFactory.php`
- **Current Dependencies**: `Logger $logger`
- **Migration**: Logger → ServiceLocator
- **Risk**: Low

**ComponentGeneratorFactory** `src/Factories/ComponentGeneratorFactory.php`
- **Current Dependencies**: `Logger $logger`
- **Migration**: Logger → ServiceLocator
- **Risk**: Low

### 8. Schema Classes

**SchemaGenerator** `src/Schema/SchemaGenerator.php`
- **Current Dependencies**: `Logger $logger, DatabaseConnector $dbConnector, ?CoreFieldsMetadata $coreFieldsMetadata = null`
- **Migration**: All → ServiceLocator
- **Risk**: Low - All dependencies available

### 9. Metadata Classes

**MetadataEngine** `src/Metadata/MetadataEngine.php`
- **Current Dependencies**: `Logger $logger, string $modelsDirPath, string $relationshipsDirPath, string $cacheDirPath`
- **Migration**: Logger → ServiceLocator, paths stay as parameters
- **Risk**: ⚠️ **HIGH** - Core service, singleton pattern, potential bootstrapping issues

**CoreFieldsMetadata** `src/Metadata/CoreFieldsMetadata.php`
- **Current Dependencies**: `Logger $logger, ?string $templatePath = null`
- **Migration**: Logger → ServiceLocator, templatePath stays or moves to config
- **Risk**: Low
- **ServiceLocator**: ✅ Already available via `ServiceLocator::getCoreFieldsMetadata()`

### 10. Component Generator Classes

**ComponentGeneratorBase** `src/ComponentGenerator/ComponentGeneratorBase.php`
- **Current Dependencies**: `array $metadata, Logger $logger`
- **Migration**: Logger → ServiceLocator, metadata stays
- **Risk**: Low

**DefaultComponentGenerator** `src/ComponentGenerator/DefaultComponentGenerator.php`
- **Current Dependencies**: Inherits from ComponentGeneratorBase
- **Migration**: Automatic
- **Risk**: Low

**ApiControllerBase** `src/Api/ApiControllerBase.php`
- **Current Dependencies**: `array $metadata, Logger $logger`
- **Migration**: Logger → ServiceLocator, metadata stays
- **Risk**: Low

## Dependencies Not Available in ServiceLocator

### 🚨 Critical Issues

1. **Database Parameters** (`array $dbParams`)
   - Used by: DatabaseConnector
   - **Solution**: Get from Config service: `ServiceLocator::getConfig()->get('database')`
   - **Impact**: DatabaseConnector constructor needs refactoring

2. **Metadata Arrays** (`array $metadata`)
   - Used by: All Field classes, ComponentGenerator classes, ApiControllerBase
   - **Solution**: Keep as constructor parameters (business data, not dependencies)
   - **Impact**: No change needed - metadata is data, not a service dependency

3. **String Parameters** (paths, names, etc.)
   - Used by: MetadataEngine (paths), RelationshipFactory (owner), ValidationRuleBase (name, errorMessage)
   - **Solution**: Keep as constructor parameters or move to configuration
   - **Impact**: Mixed - some can move to config, others stay as parameters

### 🔧 Configuration Dependencies
These need to be moved to configuration files:

- **MetadataEngine paths**: Move to config
- **CoreFieldsMetadata templatePath**: Move to config  
- **DatabaseConnector dbParams**: Already in config

## Recursion Risk Analysis

### 🚨 High Risk Classes

1. **~~Config → Logger → Config~~** ✅ **ELIMINATED**
   - Risk: ~~Config needs Logger, but Logger might need Config~~
   - **Resolution**: Config doesn't actually use Logger - remove dependency entirely

2. **MetadataEngine** → **Logger** → **MetadataEngine**
   - Risk: MetadataEngine is core service used by many classes
   - **Mitigation**: MetadataEngine is singleton, initialize early in bootstrap

3. **DatabaseConnector** → **Config** → **Logger** → **DatabaseConnector**
   - Risk: ~~Circular dependency through Config~~
   - **Mitigation**: ✅ **REDUCED RISK** - Config no longer depends on Logger

### ✅ Low Risk Classes
- All Field classes (no circular dependencies)
- All Validation classes (no circular dependencies)
- Most Factory classes (no circular dependencies)
- Component Generators (no circular dependencies)

## Test Impact Analysis

### 🔄 Tests Requiring Major Updates

#### Field Tests
**Files**: `Tests/Unit/Fields/*Test.php` (20+ test files)
- **Current Pattern**: `new TextField($metadata, $this->logger)`
- **New Pattern**: Mock ServiceLocator container
- **Impact**: ~50 test methods need updates
- **Example Update**:
```php
// Before
$this->field = new TextField($metadata, $this->logger);

// After  
$mockContainer = $this->createMock(Container::class);
$mockContainer->method('get')->with('logger')->willReturn($this->logger);
ServiceLocator::setContainer($mockContainer);
$this->field = new TextField($metadata);
```

#### Validation Tests
**Files**: `Tests/Unit/Validation/*Test.php` (6+ test files)
- **Pattern**: Similar to field tests
- **Impact**: ~30 test methods need updates

#### Model Tests
**Files**: `Tests/Unit/Models/*Test.php`
- **Current Pattern**: `new ModelClass($this->logger)`
- **Impact**: ~20 test methods need updates

#### Factory Tests
**Files**: `Tests/Unit/Factories/*Test.php`
- **Impact**: ~15 test methods need updates

### 📝 Test Update Checklist

1. **Add ServiceLocator Reset** to tearDown methods:
```php
protected function tearDown(): void {
    ServiceLocator::reset();
    parent::tearDown();
}
```

2. **Mock Container Setup** in setUp methods:
```php
protected function setUp(): void {
    parent::setUp();
    $mockContainer = $this->createMock(Container::class);
    $mockContainer->method('get')->willReturnMap([
        ['logger', $this->createMock(Logger::class)],
        ['config', $this->createMock(Config::class)],
        // Add other services as needed
    ]);
    ServiceLocator::setContainer($mockContainer);
}
```

3. **Update Constructor Calls** throughout test methods

### 📊 Test Files Requiring Updates

#### High Priority (Constructor calls in every test)
- `Tests/Unit/Fields/TextFieldTest.php` - 15+ constructor calls
- `Tests/Unit/Fields/RelatedRecordFieldTest.php` - 10+ constructor calls  
- `Tests/Unit/Fields/PasswordFieldTest.php` - 10+ constructor calls
- `Tests/Unit/Fields/MultiEnumFieldTest.php` - 8+ constructor calls
- `Tests/Unit/Validation/ValidationRuleBaseTest.php` - 8+ constructor calls
- `Tests/Unit/Validation/ForeignKeyExistsValidationTest.php` - 5+ constructor calls

#### Medium Priority
- All other field test files - 3-5 constructor calls each
- All validation test files - 2-3 constructor calls each
- Factory test files - 2-3 constructor calls each

#### Low Priority (Integration tests, fewer direct constructor calls)
- `Tests/Feature/*Test.php`
- `Tests/Integration/*Test.php`

## Implementation Phases

### Phase 1: Foundation (Low Risk)
**Target**: Classes with simple Logger dependencies only
1. ValidationRuleFactory
2. ComponentGeneratorFactory  
3. APIRouteRegistry
4. APIPathScorer
5. ValidationEngine
6. All Validation Rule classes

**Testing**: Update corresponding unit tests

### Phase 2: Field System (Medium Risk)
**Target**: Field classes
1. FieldBase (base class)
2. All Field subclasses inherit automatically
3. FieldFactory

**Testing**: Update all field unit tests (~20 files)

### Phase 3: Component System (Medium Risk)
**Target**: Component generators
1. ComponentGeneratorBase
2. DefaultComponentGenerator  
3. ApiControllerBase

**Testing**: Update component tests

### Phase 4: Core Services (Medium Risk - Reduced from High)
**Target**: Core framework services
1. ✅ Config (remove unused Logger dependency entirely)
2. SchemaGenerator
3. CoreFieldsMetadata

**Testing**: Integration testing focused

### Phase 5: Database Layer (High Risk)
**Target**: Database-related classes
1. DatabaseConnector (with config-based dbParams)

**Testing**: Database integration tests

### Phase 6: Model System (High Risk)
**Target**: Model and relationship classes
1. ModelBase
2. RelationshipBase (complex constructor)
3. All model subclasses

**Testing**: Model and relationship tests

### Phase 7: Metadata System (Critical Risk)
**Target**: Metadata engine (last due to bootstrapping)
1. MetadataEngine (singleton, config-based paths)

**Testing**: Full integration testing

## Risk Mitigation Strategies

### 1. Bootstrapping Order
Ensure ServiceLocator services are available before classes need them:
```php
// In Gravitycar bootstrap
1. Initialize Container
2. Configure Logger (with fallback)
3. Configure Config (with fallback logger)
4. Configure MetadataEngine
5. Configure DatabaseConnector
6. Initialize other services
```

### 2. Fallback Mechanisms
For critical classes that might be instantiated before ServiceLocator is ready:
```php
public function __construct() {
    try {
        $this->logger = ServiceLocator::getLogger();
    } catch (Exception $e) {
        // Fallback to basic logger
        $this->logger = new \Monolog\Logger('fallback');
        $this->logger->pushHandler(new \Monolog\Handler\ErrorLogHandler());
    }
}
```

### 3. Service Availability Checks
```php
public function __construct() {
    if (!ServiceLocator::hasService('logger')) {
        throw new GCException('ServiceLocator not properly initialized');
    }
    $this->logger = ServiceLocator::getLogger();
}
```

### 4. Test Helper Methods
Create helper methods to simplify test setup:
```php
// In UnitTestCase.php
protected function mockServiceLocator(array $services = []): void {
    $mockContainer = $this->createMock(Container::class);
    $defaultServices = [
        'logger' => $this->createMock(Logger::class),
        'config' => $this->createMock(Config::class),
    ];
    $services = array_merge($defaultServices, $services);
    
    $mockContainer->method('get')->willReturnMap(
        array_map(fn($k, $v) => [$k, $v], array_keys($services), $services)
    );
    ServiceLocator::setContainer($mockContainer);
}
```

## Configuration Changes Required

### Update Config Structure
```php
// config.php additions
return [
    'metadata' => [
        'models_path' => 'src/Models',
        'relationships_path' => 'src/Relationships', 
        'cache_path' => 'cache/',
        'core_fields_template' => null, // or path to template
    ],
    // existing database config stays
];
```

## Success Criteria

### ✅ Migration Success Indicators
1. All classes instantiate without manual dependency provision
2. All existing unit tests pass (after updates)
3. Integration tests pass
4. No performance degradation
5. ServiceLocator bootstraps correctly in all environments

### 📋 Testing Checklist
- [ ] All field classes instantiate with `new FieldClass($metadata)`
- [ ] All validation classes instantiate with `new ValidationClass()`
- [ ] All factory classes instantiate with `new FactoryClass($requiredParams)`
- [ ] Model classes instantiate with `new ModelClass()`
- [ ] Core services bootstrap in correct order
- [ ] All unit tests pass
- [ ] Integration tests pass
- [ ] Performance benchmarks pass

## Timeline Estimate

- **Phase 1**: 2-3 days (Foundation classes + tests)
- **Phase 2**: 3-4 days (Field system + extensive test updates)
- **Phase 3**: 1-2 days (Component system + tests)
- **Phase 4**: 2-3 days (Core services + careful testing)
- **Phase 5**: 2-3 days (Database layer + integration tests)
- **Phase 6**: 3-4 days (Model system + relationship complexity)
- **Phase 7**: 2-3 days (MetadataEngine + full integration testing)

**Total Estimated Time**: 15-22 days

## Rollback Plan

If issues arise:
1. **Per-Class Rollback**: Each class can be independently reverted
2. **Phase Rollback**: Complete phases can be reverted
3. **Git Branching**: Use feature branch for entire migration
4. **Backward Compatibility**: Keep old constructor signatures with deprecation warnings

## Remaining Critical Challenges

### ✅ RESOLVED: RelationshipFactory Already Correct
**File**: `src/Factories/RelationshipFactory.php` line 59
**Status**: ✅ **WORKING CORRECTLY** - Already passes Logger object to RelationshipBase constructor
**Current Code**: `$relationship = new $className($this->logger);`
**Analysis**: This pattern will work perfectly with simplified RelationshipBase constructor
**No Changes Required**: RelationshipFactory will continue working after RelationshipBase simplification

### 🚨 PRIORITY 1: RelationshipBase Constructor Simplification  
**Target Pattern**: `public function __construct(Logger $logger)` - SAME AS ModelBase
**Current Pattern**: Complex `($metadataOrLogger, Logger $logger = null, ?CoreFieldsMetadata $coreFieldsMetadata = null)`
**Key Discovery**: RelationshipBase extends ModelBase and already overrides loadMetadata() to use MetadataEngine
**Simplification**: Remove complex constructor entirely - RelationshipBase can use identical pattern to ModelBase
**ServiceLocator Migration**: Change `new CoreFieldsMetadata($this->logger)` to `ServiceLocator::getCoreFieldsMetadata()`
**Migration Impact**: Affects all relationship classes inheriting from RelationshipBase

### 🔧 READY FOR IMPLEMENTATION
**All Prerequisites Met**: ✅ ServiceLocator infrastructure complete, ✅ MetadataEngine supports relationships, ✅ RelationshipFactory already compatible

## Conclusion

This migration will significantly simplify class instantiation throughout the framework while maintaining testability through ServiceLocator mocking. The main risks are around bootstrapping order and circular dependencies, which can be mitigated through careful phasing and fallback mechanisms.

**IMPLEMENTATION READY**: All critical analysis complete, RelationshipFactory already compatible, ServiceLocator infrastructure complete. The framework is ready for phased migration starting with Phase 1 (Foundation classes).

The extensive test updates are a significant effort but will result in more consistent test patterns across the framework. The ServiceLocator pattern will make the framework more user-friendly while preserving the benefits of dependency injection.
