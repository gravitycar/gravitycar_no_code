# Complete CRUD Implementation Guide

## Overview
This guide provides comprehensive documentation for implementing the full CRUD (Create, Read, Update, Delete) system in the Gravitycar framework, including soft delete functionality, parameter binding, and metadata-driven operations.

## System Architecture

### Core Components
1. **ModelBase** - Abstract base class for all models with CRUD methods
2. **DatabaseConnector** - Handles all database operations with DBAL
3. **FieldBase** - Provides metadata utilities and field management
4. **ServiceLocator** - Dependency injection and service access
5. **SchemaGenerator** - Database table creation from metadata

### Data Flow
```
Model CRUD Method → ModelBase → ServiceLocator → DatabaseConnector → DBAL → Database
     ↑                ↑              ↑               ↑
Field Validation  Audit Fields  Dependency      Parameter
& Metadata       Management     Injection       Binding
```

## Implementation Steps

### 1. Model Definition
Create a model class extending ModelBase:

```php
<?php
namespace Gravitycar\Models\users;

use Gravitycar\Core\ModelBase;

class Users extends ModelBase {
    // Model inherits all CRUD methods from ModelBase
    // Only implement custom business logic here
    
    public function create(): bool {
        // Custom pre-save logic (e.g., password hashing)
        if ($this->get('password') && !$this->isPasswordHashed($this->get('password'))) {
            $this->set('password', password_hash($this->get('password'), PASSWORD_DEFAULT));
        }
        return parent::create();
    }
}
```

### 2. Metadata Configuration
Define model structure in metadata file:

```php
// src/models/users/users_metadata.php
return [
    'name' => 'Users',
    'table' => 'users',
    'fields' => [
        'id' => [
            'type' => 'ID',
            'required' => true,
            'primary_key' => true
        ],
        'username' => [
            'type' => 'Email',
            'required' => true,
            'unique' => true,
            'validation' => ['Email', 'Required', 'Unique']
        ],
        'password' => [
            'type' => 'Password',
            'required' => true
        ],
        'deleted_at' => [
            'type' => 'DateTime',
            'required' => false,
            'readonly' => true
        ],
        'deleted_by' => [
            'type' => 'Text',
            'required' => false,
            'readonly' => true
        ]
    ]
];
```

### 3. Database Schema Creation
Use SchemaGenerator to create tables:

```php
// Create schema from metadata
$schemaGenerator = ServiceLocator::getSchemaGenerator();
$metadata = ['models' => ['users' => $usersMetadata]];
$schemaGenerator->generateSchema($metadata);
```

## CRUD Operations Implementation

### Create Operation
**ModelBase::create()** → **DatabaseConnector::create()**

```php
// ModelBase implementation
public function create(): bool {
    // 1. Validate all fields
    if (!empty($this->getValidationErrors())) {
        return false;
    }
    
    // 2. Generate UUID if needed
    if (!$this->get('id')) {
        $this->set('id', $this->generateUuid());
    }
    
    // 3. Set audit fields
    $this->setAuditFieldsForCreate();
    
    // 4. Delegate to DatabaseConnector
    $dbConnector = ServiceLocator::getDatabaseConnector();
    return $dbConnector->create($this);
}
```

```php
// DatabaseConnector implementation
public function create($model): bool {
    $data = $this->extractDBFieldData($model); // Excludes isDBField=false
    
    $queryBuilder->insert($tableName);
    foreach ($data as $field => $value) {
        $queryBuilder->setValue($field, ":$field");
        $queryBuilder->setParameter($field, $value); // Proper parameter binding
    }
    return $queryBuilder->executeStatement() > 0;
}
```

### Read Operations
**Static finder methods** → **DatabaseConnector::find()**

```php
// Multiple finders available
$users = Users::find(['user_type' => 'admin'], ['last_name' => 'ASC'], 10, 0);
$user = Users::findById('some-uuid');
$firstAdmin = Users::findFirst(['user_type' => 'admin']);
$allUsers = Users::findAll(['created_at' => 'DESC']);
```

```php
// DatabaseConnector implementation with proper parameter binding
public function find(string $modelClass, array $criteria = [], ...): array {
    foreach ($criteria as $field => $value) {
        if (is_array($value)) {
            $queryBuilder->andWhere("$field IN (:$field)");
        } else {
            $queryBuilder->andWhere("$field = :$field");
        }
        $queryBuilder->setParameter($field, $value); // Safe parameter binding
    }
    // Convert results to model instances
}
```

### Update Operation
**ModelBase::update()** → **DatabaseConnector::update()**

```php
// ModelBase implementation
public function update(): bool {
    if (!empty($this->getValidationErrors())) {
        return false;
    }
    
    $this->setAuditFieldsForUpdate();
    
    $dbConnector = ServiceLocator::getDatabaseConnector();
    return $dbConnector->update($this);
}
```

```php
// DatabaseConnector implementation
public function update($model): bool {
    // CRITICAL: Include null values for restore operations
    $data = $this->extractDBFieldData($model, true);
    unset($data['id']); // Don't update ID
    
    foreach ($data as $field => $value) {
        $queryBuilder->set($field, ":$field");
        $queryBuilder->setParameter($field, $value);
    }
    $queryBuilder->where('id = :id');
    $queryBuilder->setParameter('id', $id);
    
    return $queryBuilder->executeStatement() > 0;
}
```

### Delete Operations (Soft Delete System)

#### Default Delete (Soft Delete)
**ModelBase::delete()** → **ModelBase::softDelete()** → **DatabaseConnector::softDelete()**

```php
// Default delete behavior
public function delete(): bool {
    return $this->softDelete(); // Soft delete is default
}

public function softDelete(): bool {
    $this->setAuditFieldsForSoftDelete(); // Sets deleted_at, deleted_by
    
    $dbConnector = ServiceLocator::getDatabaseConnector();
    return $dbConnector->softDelete($this);
}
```

#### Hard Delete (Permanent)
**ModelBase::hardDelete()** → **DatabaseConnector::hardDelete()**

```php
public function hardDelete(): bool {
    $dbConnector = ServiceLocator::getDatabaseConnector();
    return $dbConnector->hardDelete($this); // Permanent deletion
}
```

#### Restore Operation
**ModelBase::restore()** → **ModelBase::update()**

```php
public function restore(): bool {
    $this->clearSoftDeleteFields(); // Sets deleted_at=null, deleted_by=null
    return $this->update(); // Uses standard update with null inclusion
}
```

## Critical Implementation Details

### Parameter Binding Pattern
**ALL** database operations use this consistent pattern:

```php
// 1. Build SQL with placeholders
$queryBuilder->setValue($field, ":$field");

// 2. Bind parameter values
$queryBuilder->setParameter($field, $value);

// 3. Execute without parameters
$result = $queryBuilder->executeStatement();
```

### Null Value Handling for Restore
The `extractDBFieldData()` method includes critical null handling:

```php
protected function extractDBFieldData($model, bool $includeNulls = false): array {
    foreach ($model->getFields() as $fieldName => $field) {
        if (!$field->isDBField()) continue;
        
        $value = $model->get($fieldName);
        // CRITICAL: Include nulls for restore operations
        if ($value !== null || $includeNulls) {
            $data[$fieldName] = $value;
        }
    }
    return $data;
}
```

### Field Metadata Integration
Fields control database inclusion via metadata:

```php
// Database field (default)
'email' => ['type' => 'Email', 'required' => true]

// Non-database field (excluded from CRUD)
'confirmPassword' => ['type' => 'Password', 'isDBField' => false]
```

## Usage Examples

### Complete User Lifecycle
```php
// 1. Initialize framework
ServiceLocator::initialize();

// 2. Create user
$user = ServiceLocator::create(Users::class);
$user->set('username', 'mike@example.com');
$user->set('password', 'secure123');
$user->set('first_name', 'Mike');
$user->set('last_name', 'Andersen');
$user->set('user_type', 'admin');

if ($user->create()) {
    echo "User created with ID: " . $user->get('id');
}

// 3. Find users
$admins = Users::find(['user_type' => 'admin']);
$user = Users::findById($userId);

// 4. Update user
$user->set('last_name', 'Anderson');
$user->update();

// 5. Soft delete (default)
$user->delete(); // Sets deleted_at timestamp

// 6. Check if deleted
if ($user->isDeleted()) {
    echo "User is soft deleted";
}

// 7. Restore
$user->restore(); // Clears deleted_at

// 8. Hard delete (permanent)
$user->hardDelete(); // Actually removes from database
```

## Error Handling
All operations include comprehensive error handling:
- Validation failures return false with logged errors
- Database exceptions wrapped in GCException with context
- Parameter binding prevents SQL injection
- Detailed logging for debugging

## Testing Strategy
```php
// Test complete CRUD cycle
$model = ServiceLocator::create(TestModel::class);
$model->set('name', 'Test');

// Test create
assert($model->create() === true);
assert($model->get('id') !== null);

// Test read
$found = TestModel::findById($model->get('id'));
assert($found->get('name') === 'Test');

// Test update
$found->set('name', 'Updated');
assert($found->update() === true);

// Test soft delete
assert($found->delete() === true);
assert($found->isDeleted() === true);

// Test restore
assert($found->restore() === true);
assert($found->isDeleted() === false);

// Test hard delete
assert($found->hardDelete() === true);
```

## Dependencies Required
- **Doctrine DBAL** - Database abstraction layer
- **Aura.DI** - Dependency injection container
- **Monolog** - Logging framework
- **Field Classes** - ID, Email, Text, DateTime, Password, etc.
- **Validation Rules** - Email, Required, Unique, etc.

This implementation provides a complete, production-ready CRUD system with enterprise features like soft deletes, audit trails, and comprehensive error handling.
