# Pure Dependency Injection Implementation Plan for ModelBase

## Feature Overview

This implementation plan outlines the transformation of the Gravitycar Framework's ModelBase class and its ecosystem from a hybrid Dependency Injection/ServiceLocator pattern to a pure Dependency Injection approach. This change will eliminate ServiceLocator fallbacks throughout the codebase, making all dependencies explicit constructor parameters and significantly improving testability.

## Requirements

### Functional Requirements
1. **Complete ServiceLocator Elimination**: Remove all ServiceLocator fallback calls from ModelBase and related classes
2. **Constructor-Based DI**: All dependencies must be provided via constructor parameters
3. **Container Configuration**: Aura DI container must be fully configured to handle all ModelBase dependencies
4. **Backward Compatibility**: Maintain all existing functionality while changing internal architecture
5. **Factory Pattern Preservation**: ModelFactory remains the primary instantiation mechanism

### Non-Functional Requirements
1. **Test Simplification**: Reduce test setup complexity by 30-40%
2. **Performance Maintenance**: No performance degradation in production
3. **Developer Experience**: Clear error messages when dependencies are missing
4. **Zero Downtime**: Changes must be deployable without service interruption

## Design

### Current Architecture Analysis

The current codebase scan reveals these key architectural patterns:

#### ModelBase Dependency Fallbacks (7 dependencies)
```php
// Current pattern in ModelBase.php
protected function getLogger(): LoggerInterface {
    return $this->logger ?? \Gravitycar\Core\ServiceLocator::getLogger();
}
```

#### ServiceLocator Usage Patterns
1. **ModelBase Internal**: 7 getter methods with ServiceLocator fallbacks
2. **ModelBaseAPIController**: 5 direct ServiceLocator calls for edge cases
3. **RelatedRecordField**: 1 direct call for model instantiation
4. **Various Services**: 8 classes using ServiceLocator for ModelFactory
5. **Installer**: 1 direct call for setup operations

#### Container Configuration Issues
- `ContainerConfig::configureModelClasses()` only configures 2 of 7 ModelBase dependencies
- Abstract class mapping `ModelBase::class` is invalid for Aura DI
- Missing dependency configurations will cause injection failures

### Target Architecture

#### Pure DI ModelBase Pattern
```php
// Target pattern - no fallbacks
public function __construct(
    LoggerInterface $logger,
    MetadataEngine $metadataEngine,
    FieldFactory $fieldFactory,
    DatabaseConnector $databaseConnector,
    RelationshipFactory $relationshipFactory,
    ModelFactory $modelFactory,
    CurrentUserService $currentUserService
) {
    $this->logger = $logger;
    $this->metadataEngine = $metadataEngine;
    $this->fieldFactory = $fieldFactory;
    $this->databaseConnector = $databaseConnector;
    $this->relationshipFactory = $relationshipFactory;
    $this->modelFactory = $modelFactory;
    $this->currentUserService = $currentUserService;
}
```

#### Dependency Flow
1. **Container** → **ModelFactory** → **ModelBase Subclasses**
2. **Container** → **Services** (with ModelFactory injection)
3. **Container** → **APIControllers** (with ModelFactory injection)

## Implementation Steps

### Phase 1: Container Configuration Completion
**Estimated Time**: 2-3 hours

#### Step 1.1: Fix ContainerConfig::configureModelClasses()
- Remove invalid abstract class mapping
- Add all 7 ModelBase dependencies to container parameter configuration
- Configure concrete model classes with full dependency injection

```php
// Complete container configuration needed
$di->params[\Gravitycar\Models\Users\Users::class] = [
    'logger' => $di->lazyGet('logger'),
    'metadataEngine' => $di->lazyGet('metadata_engine'),
    'fieldFactory' => $di->lazyGet('field_factory'),
    'databaseConnector' => $di->lazyGet('database_connector'),
    'relationshipFactory' => $di->lazyGet('relationship_factory'),
    'modelFactory' => $di->lazyGet('model_factory'),
    'currentUserService' => $di->lazyGet('current_user_service')
];
```

#### Step 1.2: Verify Service Dependencies
- Ensure all referenced services are properly configured in container
- Add missing service configurations if needed
- Test container resolution for all ModelBase subclasses

### Phase 2: ModelBase Constructor Modification
**Estimated Time**: 4-5 hours

#### Step 2.1: Remove Optional Parameters
- Make all 7 dependencies required in ModelBase constructor
- Remove null coalescing operators (`??`) from parameter assignments
- Remove ServiceLocator fallback methods entirely

#### Step 2.2: Update Getter Methods
- Convert all `getX()` methods to simple property accessors
- Remove ServiceLocator calls from getter implementations
- Maintain method signatures for backward compatibility

```php
// Before: Complex getter with fallback
protected function getLogger(): LoggerInterface {
    return $this->logger ?? \Gravitycar\Core\ServiceLocator::getLogger();
}

// After: Simple property accessor
protected function getLogger(): LoggerInterface {
    return $this->logger;
}
```

### Phase 3: ModelFactory Enhancement
**Estimated Time**: 2-3 hours

#### Step 3.1: Ensure Complete Dependency Injection
- Verify ModelFactory receives all dependencies via constructor
- Update instantiation logic to pass all 7 dependencies to models
- Add validation to ensure no dependencies are null

#### Step 3.2: Error Handling Enhancement
- Add clear error messages when dependencies are missing
- Implement dependency validation in ModelFactory::new() method
- Create helpful debugging information for DI failures

### Phase 4: Service Layer Updates
**Estimated Time**: 6-8 hours

#### Step 4.1: Update Services with ServiceLocator Calls
Classes requiring updates:
- `AuthenticationService` (3 ServiceLocator calls)
- `AuthorizationService` (1 ServiceLocator call)
- `UserService` (1 ServiceLocator call)
- `GuestUserManager` (1 ServiceLocator call)

#### Step 4.2: Container Configuration for Services
- Add full DI configuration for all service classes
- Ensure ModelFactory is properly injected into services
- Remove ServiceLocator fallbacks from service constructors

### Phase 5: API Controller Updates
**Estimated Time**: 4-5 hours

#### Step 5.1: ModelBaseAPIController Cleanup
- Remove 5 direct ServiceLocator calls in edge cases
- Ensure ModelFactory dependency is always available
- Update error handling for missing dependencies

#### Step 5.2: RelatedRecordField Updates
- Remove ServiceLocator call in RelatedRecordField::getModelInstance()
- Inject ModelFactory into RelatedRecordField via container
- Update field factory to provide ModelFactory to fields

### Phase 6: Testing Infrastructure Simplification
**Estimated Time**: 8-10 hours

#### Step 6.1: Eliminate TestableModelBase
- Remove the 300+ line TestableModelBase helper class
- Update all ModelBase tests to use direct dependency injection
- Simplify mock setup by eliminating ServiceLocator mocking

#### Step 6.2: Test Refactoring
- Update all test setUp() methods to use pure DI
- Remove ServiceLocator-related test utilities
- Create new test helpers for dependency injection setup

#### Step 6.3: Integration Test Updates
- Update tests that rely on ServiceLocator for model creation
- Ensure all integration tests use proper DI container setup
- Verify test coverage remains comprehensive

### Phase 7: Edge Case Handling
**Estimated Time**: 3-4 hours

#### Step 7.1: Installer Updates
- Update `Installer::createAdminUser()` to use injected ModelFactory
- Ensure installer has access to properly configured container
- Test installation process with pure DI setup

#### Step 7.2: Migration and Setup Scripts
- Update any migration scripts that directly instantiate models
- Ensure setup.php works with new DI requirements
- Test bootstrap process with pure DI configuration

### Phase 8: Performance Optimization
**Estimated Time**: 2-3 hours

#### Step 8.1: Container Performance
- Profile container resolution performance
- Optimize lazy loading configuration
- Ensure no performance regression from DI changes

#### Step 8.2: Memory Usage Analysis
- Monitor memory usage with explicit dependency injection
- Optimize service lifetimes and scope management
- Validate garbage collection efficiency

## Testing Strategy

### Unit Testing Approach
1. **Simplified Test Setup**: Direct dependency injection eliminates complex mocking
2. **Isolated Testing**: Each dependency can be mocked independently
3. **Clear Test Structure**: Explicit dependencies make test intentions obvious

### Integration Testing
1. **Container Testing**: Verify all services resolve correctly
2. **End-to-End Workflows**: Test complete request cycles with pure DI
3. **Performance Testing**: Ensure no degradation in response times

### Regression Testing
1. **Existing Functionality**: All current features must continue working
2. **API Compatibility**: REST endpoints must maintain same behavior
3. **Database Operations**: CRUD operations must remain unchanged

### Test Coverage Requirements
- Maintain 90%+ code coverage for ModelBase and related classes
- Add specific tests for dependency injection failures
- Test error scenarios with missing dependencies

## Documentation

### Code Documentation
1. **Constructor Documentation**: Clear PHPDoc for all required dependencies
2. **Migration Guide**: Instructions for developers updating custom model classes
3. **Container Configuration Guide**: How to add new models to DI container

### API Documentation
1. **No API Changes**: REST endpoints remain unchanged
2. **Error Response Updates**: New error messages for DI failures
3. **Developer Guide**: Best practices for pure DI in Gravitycar

### Architecture Documentation
1. **Dependency Flow Diagrams**: Visual representation of injection patterns
2. **Performance Impact**: Documented performance characteristics
3. **Troubleshooting Guide**: Common DI issues and solutions

## Risks and Mitigations

### Technical Risks

#### Risk 1: Container Configuration Complexity
- **Impact**: High - Incomplete configuration breaks model instantiation
- **Probability**: Medium - Container setup is complex
- **Mitigation**: 
  - Comprehensive container validation script
  - Automated tests for all model classes
  - Detailed configuration documentation

#### Risk 2: Circular Dependency Issues
- **Impact**: High - Could prevent container from resolving dependencies
- **Probability**: Low - Current architecture review shows no circularity
- **Mitigation**:
  - Dependency graph analysis before implementation
  - Lazy loading for complex dependencies
  - Refactor circular patterns if discovered

#### Risk 3: Performance Degradation
- **Impact**: Medium - Could slow down model operations
- **Probability**: Low - DI typically improves performance
- **Mitigation**:
  - Performance benchmarking before and after changes
  - Container caching optimization
  - Lazy service instantiation

### Development Risks

#### Risk 4: Test Complexity During Transition
- **Impact**: Medium - Tests might become unstable during refactoring
- **Probability**: High - Large-scale changes often break tests
- **Mitigation**:
  - Incremental testing approach
  - Maintain both old and new test patterns temporarily
  - Comprehensive test review process

#### Risk 5: Developer Adoption Challenges
- **Impact**: Medium - Team might struggle with pure DI patterns
- **Probability**: Medium - Significant paradigm shift
- **Mitigation**:
  - Training sessions on pure DI principles
  - Clear documentation and examples
  - Gradual rollout with mentoring

### Deployment Risks

#### Risk 6: Production Deployment Issues
- **Impact**: High - Could break production model operations
- **Probability**: Low - Thorough testing should prevent this
- **Mitigation**:
  - Staging environment testing
  - Rollback plan with ServiceLocator restoration
  - Canary deployment approach

## Success Criteria

### Functional Success Metrics
1. **Zero ServiceLocator Calls**: Complete elimination from ModelBase ecosystem
2. **All Tests Pass**: No regression in existing functionality
3. **Container Resolution**: All model classes successfully instantiate via DI

### Performance Success Metrics
1. **Response Time Maintenance**: No increase in API response times
2. **Memory Usage Stability**: No significant memory usage increase
3. **Test Execution Speed**: 30-40% faster test suite execution

### Code Quality Metrics
1. **Reduced Test Complexity**: Elimination of TestableModelBase class
2. **Clear Dependency Graph**: Explicit dependencies throughout codebase
3. **Maintainability Improvement**: Simplified debugging and development

### Developer Experience Metrics
1. **Easier Test Writing**: Simplified mock setup for new tests
2. **Clear Error Messages**: Helpful DI failure diagnostics
3. **Reduced Debugging Time**: Explicit dependencies simplify troubleshooting

## Timeline

**Total Estimated Time**: 31-40 hours (~5-6 weeks part-time)

### Week 1: Foundation (8-10 hours)
- Phase 1: Container Configuration Completion
- Phase 2: ModelBase Constructor Modification

### Week 2: Core Updates (8-10 hours)
- Phase 3: ModelFactory Enhancement
- Phase 4: Service Layer Updates (partial)

### Week 3: Service & API Updates (8-10 hours)
- Phase 4: Service Layer Updates (completion)
- Phase 5: API Controller Updates

### Week 4: Testing & Edge Cases (7-10 hours)
- Phase 6: Testing Infrastructure Simplification
- Phase 7: Edge Case Handling

### Week 5: Optimization & Validation (4-6 hours)
- Phase 8: Performance Optimization
- Final testing and validation

### Week 6: Documentation & Deployment (2-4 hours)
- Documentation completion
- Production deployment preparation

## Implementation Notes

### Critical Dependencies
- Aura DI Container (already configured)
- ModelFactory (already implemented)
- All service classes (already exist)

### Breaking Changes
- None for public APIs
- Internal architecture changes only
- Potential test breakage (expected and planned)

### Rollback Strategy
If implementation issues arise:
1. Restore ServiceLocator fallbacks in ModelBase
2. Revert container configuration changes
3. Restore original test patterns
4. Deploy rollback version

This comprehensive plan ensures a smooth transition to pure dependency injection while maintaining all existing functionality and significantly improving the testability and maintainability of the Gravitycar Framework.
