# Migration Plan: Replace Model Instantiation with ModelFactory

## 📋 Executive Summary

Based on analysis of the Gravitycar Framework codebase, I've identified several existing patterns for model instantiation that should be migrated to use the new `ModelFactory` class. This migration will centralize model creation, improve maintainability, and provide consistent error handling across the framework.

**Key Requirements:**
- All files using ModelFactory must include `use Gravitycar\Factories\ModelFactory;` import statement
- Import statements should be added at the top of files after namespace declaration
- Maintain PSR-4 autoloading compliance

## 🔍 Current State Analysis

### Existing Patterns Identified

1. **ServiceLocator::createModel()** - 4 instances found
2. **Static findById() calls** - 12+ instances in documentation and relationships  
3. **Static fromRow() calls** - 4 instances in ModelBase
4. **DatabaseConnector::findById()** - Used in tests and ModelFactory itself

## 📊 Migration Categories

### 🎯 **Category 1: High Priority - Direct Replacements**

#### **Pattern A: ServiceLocator::createModel() → ModelFactory::new()**
**Files to Update:**

1. **`src/Models/installer/Installer.php` (Line 46)**
   ```php
   // ADD IMPORT (after existing use statements):
   use Gravitycar\Factories\ModelFactory;
   
   // CHANGE Line 46:
   // BEFORE:
   $usersModel = \Gravitycar\Core\ServiceLocator::createModel(\Gravitycar\Models\Users\Users::class);
   
   // AFTER:
   $usersModel = ModelFactory::new('Users');
   ```

2. **`test.php` (Line 8)**
   ```php
   // ADD IMPORT (at top of file):
   use Gravitycar\Factories\ModelFactory;
   
   // CHANGE Line 8:
   // BEFORE:  
   $model = ServiceLocator::createModel(\Gravitycar\Models\movie_quotes\Movie_Quotes::class);
   
   // AFTER:
   $model = ModelFactory::new('Movie_Quotes');
   ```

3. **`Tests/Unit/Core/ContainerTestExample.php` (Line 73)**
   ```php
   // ADD IMPORT (after existing use statements):
   use Gravitycar\Factories\ModelFactory;
   
   // CHANGE Line 73:
   // BEFORE:
   $model = ServiceLocator::createModel(\Gravitycar\Models\Installer::class, $metadata);
   
   // AFTER:
   $model = ModelFactory::new('Installer');
   ```

### 🔄 **Category 2: Medium Priority - Static Method Updates**

#### **Pattern B: Model::findById() → ModelFactory::retrieve()**
**Files to Update:**

1. **`src/Relationships/OneToOneRelationship.php` (Line 182)**
   ```php
   // ADD IMPORT (after existing use statements):
   use Gravitycar\Factories\ModelFactory;
   
   // CHANGE Line 182:
   // BEFORE:
   return $relatedModel->findById($relatedId);
   
   // AFTER:
   return ModelFactory::retrieve($this->getRelatedModelName(), $relatedId);
   ```

2. **`src/Relationships/OneToManyRelationship.php` (Line 244)**
   ```php
   // ADD IMPORT (after existing use statements):
   use Gravitycar\Factories\ModelFactory;
   
   // CHANGE Line 244:
   // BEFORE:
   return $manyModel->findById($manyId);
   
   // AFTER:
   return ModelFactory::retrieve($this->getManyModelName(), $manyId);
   ```

### 🚧 **Category 3: Low Priority - Keep As-Is**

#### **Pattern C: ModelBase static methods (DO NOT CHANGE)**
- `static::fromRow()` calls in ModelBase.php should remain unchanged
- These are internal ModelBase methods used for data hydration
- DatabaseConnector::findById() in tests should remain for testing purposes

## 🛠️ Implementation Steps

### **Phase 1: Core Application Updates (Week 1)**

1. **Update Installer.php**
   - Add ModelFactory import statement
   - Replace ServiceLocator::createModel with ModelFactory::new
   - Test installation workflow

2. **Update test.php**
   - Add ModelFactory import statement
   - Replace ServiceLocator::createModel with ModelFactory::new
   - Verify test script functionality

3. **Update Test Files**
   - Add ModelFactory import statements
   - Replace ServiceLocator::createModel in test examples
   - Ensure all tests continue to pass

### **Phase 2: Relationship Updates (Week 2)**

1. **Update OneToOneRelationship.php**
   - Add ModelFactory import statement
   - Replace findById calls with ModelFactory::retrieve
   - Update relationship resolution logic
   - Add model name resolution for relationship types

2. **Update OneToManyRelationship.php**
   - Add ModelFactory import statement
   - Replace findById calls with ModelFactory::retrieve
   - Ensure relationship loading works correctly

### **Phase 3: Documentation Updates (Week 3)**

1. **Update All Documentation**
   - Replace Model::findById examples with ModelFactory::retrieve
   - Update code samples in all .md files to include proper imports
   - Add ModelFactory examples to relevant guides

2. **Create Migration Guide**
   - Document the migration process
   - Provide before/after examples with proper imports
   - Include troubleshooting section

## 📋 Detailed File-by-File Migration Plan

### **File 1: `src/Models/installer/Installer.php`**
```php
<?php
namespace Gravitycar\Models\installer;

use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\ModelFactory;  // ADD THIS IMPORT
use Monolog\Logger;

// Line 46 - CHANGE THIS:
$usersModel = \Gravitycar\Core\ServiceLocator::createModel(\Gravitycar\Models\Users\Users::class);

// TO THIS:
$usersModel = ModelFactory::new('Users');
```

### **File 2: `test.php`**
```php
<?php
// ADD THESE IMPORTS AT TOP:
require_once 'vendor/autoload.php';
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Factories\ModelFactory;  // ADD THIS IMPORT

// Line 8 - CHANGE THIS:
$model = ServiceLocator::createModel(\Gravitycar\Models\movie_quotes\Movie_Quotes::class);

// TO THIS:
$model = ModelFactory::new('Movie_Quotes');
```

### **File 3: `Tests/Unit/Core/ContainerTestExample.php`**
```php
<?php
namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Factories\ModelFactory;  // ADD THIS IMPORT

// Line 73 - CHANGE THIS:
$model = ServiceLocator::createModel(\Gravitycar\Models\Installer::class, $metadata);

// TO THIS:
$model = ModelFactory::new('Installer');
```

### **File 4: `src/Relationships/OneToOneRelationship.php`**
```php
<?php
namespace Gravitycar\Relationships;

use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\ModelFactory;  // ADD THIS IMPORT

// Line 182 - CHANGE THIS:
return $relatedModel->findById($relatedId);

// TO THIS:
return ModelFactory::retrieve($this->getRelatedModelName(), $relatedId);

// Need to add method to get model name from class
```

### **File 5: `src/Relationships/OneToManyRelationship.php`**
```php
<?php
namespace Gravitycar\Relationships;

use Gravitycar\Models\ModelBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\ModelFactory;  // ADD THIS IMPORT

// Line 244 - CHANGE THIS:
return $manyModel->findById($manyId);

// TO THIS:
return ModelFactory::retrieve($this->getManyModelName(), $manyId);
```

## ⚠️ **Migration Challenges & Solutions**

### **Challenge 1: Import Statement Placement**
**Problem:** Need to add imports without breaking existing code structure
**Solution:** Add imports after namespace declaration and before class declaration

### **Challenge 2: Model Name Resolution in Relationships**
**Problem:** Relationship classes work with model class instances, not names
**Solution:** Add helper methods to extract model names from class paths

### **Challenge 3: Backward Compatibility**  
**Problem:** Existing code may depend on current patterns
**Solution:** Implement gradual migration with deprecation warnings

### **Challenge 4: Test Coverage**
**Problem:** Need to ensure all migrations don't break existing functionality
**Solution:** Run full test suite after each phase

## 🧪 **Testing Strategy**

### **Unit Tests**
- Test each migrated file individually
- Verify ModelFactory calls work correctly
- Check error handling scenarios
- Ensure import statements don't conflict

### **Integration Tests**
- Test complete workflows (installation, relationships)
- Verify no breaking changes in existing functionality
- Performance testing to ensure no degradation

### **Regression Tests**
- Run existing test suite after each migration
- Identify and fix any broken dependencies
- Validate all examples in documentation

## 📈 **Success Metrics**

1. **✅ All ServiceLocator::createModel calls replaced**
2. **✅ All static findById calls in relationships replaced**  
3. **✅ All tests passing after migration**
4. **✅ All necessary import statements added**
5. **✅ Documentation updated with new patterns and imports**
6. **✅ No performance regression**
7. **✅ Improved error handling consistency**

## 🗓️ **Timeline Estimate**

- **Phase 1 (Core Updates):** 3-5 days
- **Phase 2 (Relationships):** 5-7 days  
- **Phase 3 (Documentation):** 3-5 days
- **Testing & Validation:** 2-3 days
- **Total Estimated Time:** 2-3 weeks

## 🚀 **Benefits Post-Migration**

1. **Centralized Model Creation** - All model instantiation goes through ModelFactory
2. **Improved Error Handling** - Consistent exception handling and logging
3. **Better Maintainability** - Easier to modify model creation logic
4. **Enhanced Debugging** - Single point for model creation debugging
5. **Future Flexibility** - Easy to add features like caching, validation, etc.
6. **Proper Namespace Management** - Clean import statements following PSR-4

## 📝 **Import Statement Guidelines**

### **Standard Import Order:**
1. PHP native classes (if any)
2. Third-party vendor classes
3. Gravitycar framework classes (alphabetically)
4. ModelFactory import

### **Example Import Section:**
```php
<?php
namespace Gravitycar\SomeNamespace;

use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Models\ModelBase;
use Monolog\Logger;
```

## 📝 **Next Steps**

1. **Review and approve this migration plan**
2. **Create feature branch for migration work**
3. **Start with Phase 1 (highest priority items)**
4. **Test each phase thoroughly before proceeding**
5. **Update documentation as changes are made**
6. **Ensure all import statements are properly added**

This comprehensive migration plan ensures a smooth transition to ModelFactory usage while maintaining system stability, proper namespace management, and improving code quality throughout the Gravitycar Framework.
