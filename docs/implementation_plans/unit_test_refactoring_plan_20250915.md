# Unit Test Refactoring Implementation Plan

## Overview
The unit tests are significantly out of date due to recent refactoring that introduced pure dependency injection throughout the framework. This plan outlines the systematic approach to updating all unit tests.

## Major Changes That Broke Tests

### 1. Constructor Signature Changes
- **Router**: Now requires 9 dependencies instead of 1  
- **DatabaseConnector**: Now requires `Config` object instead of array
- **ApiControllerBase**: Now uses pure DI with 6 optional parameters
- **All ModelBase subclasses**: Now require 7-parameter constructor

### 2. Static vs Instance Method Changes
- **ModelFactory**: All methods are now instance methods, not static
- **ServiceLocator**: Removed from most classes in favor of constructor injection

### 3. Missing/Moved Classes
- **TMDBController**: Moved from `Gravitycar\Api\Movies\TMDBController` to `Gravitycar\Api\TMDBController`

### 4. Null Service Dependencies
- Many controllers have null service dependencies causing "Call to member function on null" errors
- Tests need to properly mock and inject all required services

## Implementation Strategy

### Phase 1: Fix Fundamental Test Infrastructure (Priority: High)
1. **ApiControllerBaseTest** ✅ - COMPLETED
2. **RouterTest** - Update to use 9-parameter constructor
3. **DatabaseConnectorTest** - Update to use Config object  
4. **ModelFactoryTest** - Convert from static to instance method calls

## Success Criteria
- All unit tests pass (1108/1108)
- Test coverage maintained or improved
- No use of ServiceLocator in tests
- All tests use proper dependency injection patterns
