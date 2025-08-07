# DatabaseConnector Class Documentation


The DatabaseConnector provides DBAL connection management and comprehensive CRUD operations for the Gravitycar framework. It handles all database interactions with proper parameter binding, transaction management, and error handling.

## Core Features
- **Doctrine DBAL Integration**: Type-safe database operations with query builder
- **Complete CRUD Operations**: Create, Read, Update, Delete with proper parameter binding
- **Soft Delete Support**: Built-in soft delete and restore functionality
- **Connection Management**: Automatic connection pooling and error recovery
- **Schema Integration**: Database and table existence checking
- **Comprehensive Logging**: Full operation logging with context

## Constructor
```php
public function __construct(Logger $logger, array $dbParams)
```
- Takes database connection parameters and logger via dependency injection
- Connection is lazy-loaded on first use
- Parameters include: driver, host, dbname, user, password, charset

## Connection Management

### getConnection()
```php
public function getConnection(): Connection
```
Returns Doctrine DBAL connection instance:
- Creates connection on first call (lazy loading)
- Reuses existing connection for subsequent calls
- Throws GCException on connection failure

### testConnection()
```php
public function testConnection(): bool
```
Tests database connectivity and returns boolean result.

### createDatabaseIfNotExists()
```php
public function createDatabaseIfNotExists(): bool
```
Creates the database if it doesn't exist:
- Uses connection parameters to determine database name
- Creates with UTF8MB4 charset and unicode collation
- Returns true on success, false on failure

### tableExists(string $tableName)
```php
public function tableExists(string $tableName): bool
```
Checks if a specific table exists in the database.

## CRUD Operations

### create($model)
```php
public function create($model): bool
```
Creates a new record in the database:
- Extracts database field data from model using `isDBField()` check
- Uses DBAL QueryBuilder with proper parameter binding
- Handles auto-generated IDs and updates model with new ID
- Sets audit fields automatically
- Returns true on success, false on failure

### update($model)
```php
public function update($model): bool
```
Updates an existing record:
- Requires model to have ID field set
- Extracts all database fields including null values (needed for restore operations)
- Excludes ID from update data
- Uses proper parameter binding for all fields
- Returns true on success, false on failure

### softDelete($model)
```php
public function softDelete($model): bool
```
**Default delete behavior** - performs soft delete:
- Updates deleted_at and deleted_by fields
- Preserves all other data
- Can be restored later
- Uses UPDATE SQL statement
- Returns true on success, false on failure

### hardDelete($model)
```php
public function hardDelete($model): bool
```
Permanently removes record from database:
- Actually deletes the record (permanent data loss)
- Cannot be restored
- Use with extreme caution
- Returns true on success, false on failure

## Query Operations

### find(string $modelClass, array $criteria, array $orderBy, int $limit, int $offset)
```php
public function find(string $modelClass, array $criteria = [], array $orderBy = [], int $limit = null, int $offset = null): array
```
Flexible record finding with proper parameter binding and comprehensive options.

### findById(string $modelClass, $id)
```php
public function findById(string $modelClass, $id)
```
Convenience method to find a single record by ID.

## Parameter Binding System
All database operations use consistent parameter binding:
```php
$queryBuilder->setValue($field, ":$field");
$queryBuilder->setParameter($field, $value);
$result = $queryBuilder->executeStatement();
```

## Dependencies
- Requires Logger injection for operation logging
- Uses Doctrine DBAL for database operations
- Integrates with GCException for error handling
- Works with ServiceLocator for dependency injection
