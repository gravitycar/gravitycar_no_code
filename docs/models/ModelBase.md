# ModelBase Class Documentation

## Overview
The ModelBase class is the abstract foundation for all models in the Gravitycar framework. It provides a metadata-driven approach to model definition, dynamic field management, validation, relationships, and comprehensive CRUD operations with soft delete functionality.

## Core Features
- **Metadata-Driven Configuration**: Models are defined through metadata files rather than hardcoded properties
- **Dynamic Field System**: Fields are created dynamically based on metadata specifications
- **Full CRUD Operations**: Create, Read, Update, Delete with soft delete as default
- **Audit Trail Management**: Automatic tracking of created_at, updated_at, created_by, updated_by
- **Soft Delete System**: Default delete behavior preserves data with restore capability
- **Validation Framework**: Comprehensive field and model-level validation
- **Relationship Management**: Support for model relationships
- **Dependency Injection**: Full integration with Aura.DI container

## Constructor
```php
public function __construct()
```
The constructor automatically:
- Gets Logger and metadata via dependency injection
- Calls `ingestMetadata()` to load model configuration
- Initializes fields, relationships, and validation rules

## Metadata Management

### ingestMetadata()
```php
protected function ingestMetadata(): void
```
Loads metadata from files based on the model class name. Looks for files in:
- `src/models/{modelname}/{modelname}_metadata.php`

### getMetaDataFilePaths()
```php
protected function getMetaDataFilePaths(): array
```
Returns array of metadata file paths for this model.

## Field Management

### initializeFields()
```php
protected function initializeFields(): void
```
Creates field instances based on metadata using the FieldFactory and ServiceLocator for dependency injection.

### getField(string $fieldName)
```php
public function getField(string $fieldName): ?FieldBase
```
Returns a specific field instance or null if not found.

### get(string $fieldName)
```php
public function get(string $fieldName)
```
Gets the value of a specific field.

### set(string $fieldName, $value)
```php
public function set(string $fieldName, $value): void
```
Sets the value of a specific field with validation.

### hasField(string $fieldName)
```php
public function hasField(string $fieldName): bool
```
Checks if a field exists on this model.

## CRUD Operations

### create()
```php
public function create(): bool
```
Creates a new record in the database:
- Validates all fields before saving
- Generates UUID for ID field if not set
- Sets audit fields (created_at, updated_at, created_by, updated_by)
- Delegates to DatabaseConnector for actual database operations
- Returns true on success, false on failure

### update()
```php
public function update(): bool
```
Updates an existing record:
- Validates all fields before saving
- Requires ID field to be set
- Updates audit fields (updated_at, updated_by)
- Delegates to DatabaseConnector
- Returns true on success, false on failure

### delete()
```php
public function delete(): bool
```
**Default delete behavior - performs soft delete:**
- Calls `softDelete()` internally
- Preserves data while marking as deleted
- Returns true on success, false on failure

### softDelete()
```php
public function softDelete(): bool
```
Soft deletes the record:
- Sets deleted_at timestamp and deleted_by user ID
- Updates internal deleted flags
- Delegates to DatabaseConnector for database update
- Returns true on success, false on failure

### hardDelete()
```php
public function hardDelete(): bool
```
Permanently removes the record from database:
- Actually deletes the record (cannot be restored)
- Use with caution - data loss is permanent
- Returns true on success, false on failure

### restore()
```php
public function restore(): bool
```
Restores a soft-deleted record:
- Clears deleted_at and deleted_by fields
- Updates internal deleted flags
- Calls `update()` to save changes
- Returns true on success, false on failure

## Static Finder Methods

### find(array $criteria = [], array $orderBy = [], int $limit = null, int $offset = null)
```php
public static function find(array $criteria = [], array $orderBy = [], int $limit = null, int $offset = null): array
```
Finds multiple records matching criteria:
- `$criteria`: Associative array of field => value pairs
- `$orderBy`: Associative array of field => direction ('ASC'/'DESC')
- `$limit`: Maximum number of records to return
- `$offset`: Number of records to skip
- Returns array of model instances

### findById($id)
```php
public static function findById($id)
```
Finds a single record by ID. Returns model instance or null.

### findFirst(array $criteria = [], array $orderBy = [])
```php
public static function findFirst(array $criteria = [], array $orderBy = [])
```
Finds the first record matching criteria. Returns model instance or null.

### findAll(array $orderBy = [])
```php
public static function findAll(array $orderBy = []): array
```
Finds all records with optional ordering. Returns array of model instances.

## Soft Delete Management

### isDeleted()
```php
public function isDeleted(): bool
```
Checks if the model is soft deleted by examining deleted_at field or internal flags.

### setAuditFieldsForSoftDelete()
```php
protected function setAuditFieldsForSoftDelete(): void
```
Sets deleted_at timestamp and deleted_by user ID for soft delete operations.

### clearSoftDeleteFields()
```php
protected function clearSoftDeleteFields(): void
```
Clears deleted_at and deleted_by fields for restore operations.

## Audit Trail Management

### setAuditFieldsForCreate()
```php
protected function setAuditFieldsForCreate(): void
```
Sets created_at, updated_at, created_by, and updated_by fields for new records.

### setAuditFieldsForUpdate()
```php
protected function setAuditFieldsForUpdate(): void
```
Sets updated_at and updated_by fields for record updates.

### getCurrentUserId()
```php
protected function getCurrentUserId(): ?string
```
Gets the current user ID for audit fields. Currently returns null - implement proper session management.

## Validation

### validate()
```php
public function validate(): bool
```
Validates the entire model:
- Validates all fields using their validation rules
- Validates all relationships
- Runs model-level validation rules
- Returns true if all validation passes

### getValidationErrors()
```php
protected function getValidationErrors(): array
```
Returns array of validation errors from all fields.

## Utility Methods

### getTableName()
```php
public function getTableName(): string
```
Returns the database table name from metadata or falls back to lowercase class name.

### getDisplayName()
```php
public function getDisplayName(): string
```
Returns human-readable name for the model from metadata.

### generateUuid()
```php
protected function generateUuid(): string
```
Generates a UUID v4 string for ID fields.

## Relationships and Validation Rules

### initializeRelationships()
```php
protected function initializeRelationships(): void
```
Creates relationship instances based on metadata.

### initializeValidationRules()
```php
protected function initializeValidationRules(): void
```
Creates validation rule instances based on metadata.

## Example Usage

```php
// Create a new user
$user = ServiceLocator::create(Users::class);
$user->set('username', 'john@example.com');
$user->set('password', 'securepassword');
$user->set('first_name', 'John');
$user->set('last_name', 'Doe');
$user->set('user_type', 'admin');

if ($user->create()) {
    echo "User created with ID: " . $user->get('id');
}

// Find users
$admins = Users::find(['user_type' => 'admin']);
$user = Users::findById('some-uuid');

// Update user
$user->set('last_name', 'Smith');
$user->update();

// Soft delete (default)
$user->delete(); // Sets deleted_at and deleted_by

// Restore
$user->restore(); // Clears deleted_at and deleted_by

// Hard delete (permanent)
$user->hardDelete(); // Actually removes from database
```

## Dependencies
- Requires Logger injection for logging operations
- Uses ServiceLocator for DatabaseConnector access
- Integrates with FieldFactory for field creation
- Uses GCException for error handling
