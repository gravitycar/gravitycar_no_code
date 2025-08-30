# RelatedRecordField::getRelatedModelInstance() Fix

## Problem
The `RelatedRecordField::getRelatedModelInstance()` method was incorrectly using `ServiceLocator::create()` to instantiate related model objects, passing a fully qualified class name. This violated the Gravitycar Framework's best practices.

## Solution
Updated the method to use `ModelFactory::new()` instead, which is the proper way to create ModelBase instances in the Gravitycar framework.

## Changes Made

### 1. RelatedRecordField.php
- **File**: `/src/Fields/RelatedRecordField.php`
- **Import Added**: `use Gravitycar\Factories\ModelFactory;`
- **Method Updated**: `getRelatedModelInstance()`
  - **Before**: Used `ServiceLocator::create($fullClassName)` with fully qualified class name
  - **After**: Uses `ModelFactory::new($modelName)` with just the model name
  - **Comment Updated**: Changed method comment from "using ServiceLocator" to "using ModelFactory"

### 2. Test File Updates
- **File**: `/Tests/Unit/Fields/RelatedRecordFieldTest.php`
- **Metadata Property Fix**: Updated all test cases to use `relatedModel` instead of deprecated `relatedModelName` property
- **Comment Updated**: Changed test comment from "ServiceLocator dependencies" to "ModelFactory dependencies"

### 3. Affected Test Cases
All test cases in `RelatedRecordFieldTest.php` that used the old `relatedModelName` property were updated to use the standard `relatedModel` property:
- `testConstructor()` metadata setup
- `testRequiredMetadata()` - error message updated to expect "relatedModel" 
- `testMissingRelatedFieldName()` metadata
- `testMissingDisplayFieldName()` metadata
- `testDifferentRelatedModels()` metadata 
- `testRequiredRelatedRecordField()` metadata
- `testComplexRelationships()` metadata
- `testMetadataValidationWithEmptyStrings()` metadata

## Validation
- All 17 tests in `RelatedRecordFieldTest.php` are passing
- All 14 tests in `ForeignKeyExistsValidationTest.php` are passing  
- Total of 31 tests with 75 assertions all successful

## Benefits
1. **Consistency**: Now follows Gravitycar framework patterns for model instantiation
2. **Maintainability**: Uses the proper factory pattern instead of direct class instantiation
3. **Flexibility**: ModelFactory can handle any special instantiation logic needed
4. **Correctness**: Passes just the model name instead of assuming namespace structure

## API Compatibility
This change maintains full backward compatibility - the public interface of `getRelatedModelInstance()` remains unchanged, only the internal implementation was updated.
