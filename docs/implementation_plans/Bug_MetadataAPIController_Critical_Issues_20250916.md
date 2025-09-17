# Bug Report: MetadataAPIController Critical Issues

**Date**: September 16, 2025  
**Severity**: Critical  
**Component**: API Controllers  

## Problem Description

The MetadataAPIController has multiple critical bugs preventing tests from running:

### 1. Infinite Loop in generateModelsListFresh()
**Location**: `src/Api/MetadataAPIController.php:446`  
**Issue**: `generateModelsListFresh()` calls `$this->getModels()` which creates an infinite recursion loop.  

**Current Code**:
```php
private function generateModelsListFresh(): array {
    return $this->getModels();  // Infinite loop!
}
```

### 2. Missing getAllRelationships() Method
**Location**: `src/Api/MetadataAPIController.php:358`  
**Issue**: Code calls `$metadataEngine->getAllRelationships()` but this method doesn't exist in `MetadataEngineInterface`.

**Available Methods**:
- `getRelationshipMetadata(string $relationshipName): array`
- `getCachedMetadata(): array` (contains relationships)

### 3. Test Mock Configuration Issues
**Location**: `Tests/Unit/Api/MetadataAPIControllerTest.php`  
**Issue**: Mock objects not properly configured for void methods and missing method names.

## Proposed Fix

### 1. Fix Infinite Loop
Replace the recursive call with actual fresh generation logic:

```php
private function generateModelsListFresh(): array {
    $metadataEngine = $this->metadataEngine;
    $metadata = $metadataEngine->getCachedMetadata();
    
    $models = [];
    foreach ($metadata['models'] as $modelName => $modelData) {
        if ($this->shouldExposeModel($modelName, $modelData)) {
            $models[] = [
                'name' => $modelName,
                'table' => $modelData['table'] ?? $modelName,
                'displayName' => $modelData['displayName'] ?? $modelName,
                'endpoints' => $this->generateModelEndpoints($modelName)
            ];
        }
    }
    
    return [
        'success' => true,
        'status' => 200,
        'data' => $models,
        'timestamp' => date('c')
    ];
}
```

### 2. Fix Missing getAllRelationships()
Use available methods to get relationship data:

```php
public function getRelationships(): array {
    try {
        $metadataEngine = $this->metadataEngine;
        $metadata = $metadataEngine->getCachedMetadata();
        $relationships = $metadata['relationships'] ?? [];
        
        return [
            'success' => true,
            'status' => 200,
            'data' => $relationships,
            'timestamp' => date('c')
        ];
    } catch (\Exception $e) {
        throw new InternalServerErrorException(
            'Failed to retrieve relationships metadata',
            ['original_error' => $e->getMessage()],
            $e
        );
    }
}
```

### 3. Fix Test Mocks
Update test to properly mock void methods and add missing method stubs.

## Impact Assessment
- **Tests**: 10/10 MetadataAPIController tests failing
- **API Functionality**: Metadata endpoints completely broken  
- **Development**: Unable to verify API controller refactoring
- **Production Risk**: High - core metadata APIs unusable

## Dependencies
Must be fixed before continuing with API controller test refactoring plan.
