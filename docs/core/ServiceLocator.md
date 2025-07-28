# ServiceLocator Class Documentation

## Overview
The ServiceLocator provides centralized access to framework services with full dependency injection support. It acts as a facade over the Aura.DI container while providing type-safe service access and auto-wiring capabilities.

## Core Features
- **Centralized Service Access**: Single point for accessing all framework services
- **Auto-Wiring Support**: Automatic dependency resolution for new classes
- **Type-Safe Methods**: Dedicated methods for common services with proper return types
- **Container Integration**: Full integration with Aura.DI container
- **Error Recovery**: Graceful handling of service initialization failures
- **Future-Proof Design**: New services work automatically without configuration

## Initialization

### initialize()
```php
public static function initialize(): void
```
**NEW METHOD** - Initializes the service locator and container:
- Forces container creation and configuration
- Called at application startup
- Required before using any services

## Core Service Access Methods

### getContainer()
```php
public static function getContainer(): Container
```
Returns the Aura.DI container instance with lazy initialization.

### getLogger()
```php
public static function getLogger(): Logger
```
Returns the Monolog Logger instance for application logging.

### getConfig()
```php
public static function getConfig(): Config
```
Returns the Config service with error recovery (returns stub on failure).

### getDatabaseConnector()
```php
public static function getDatabaseConnector(): DatabaseConnector
```
Returns the DatabaseConnector service:
- **ENHANCED** - Now includes comprehensive error handling
- Checks for stub services and throws descriptive GCException
- Essential for all CRUD operations

### getMetadataEngine()
```php
public static function getMetadataEngine(): MetadataEngine
```
Returns the MetadataEngine service with stub fallback on failure.

### getSchemaGenerator()
```php
public static function getSchemaGenerator(): SchemaGenerator
```
Returns the SchemaGenerator service for database schema management.

## Factory Methods

### createModel(string $modelClass, array $metadata)
```php
public static function createModel(string $modelClass, array $metadata = []): object
```
Creates model instances with proper dependency injection.

### createField(string $fieldClass, array $metadata)
```php
public static function createField(string $fieldClass, array $metadata): object
```
**ENHANCED** - Creates field instances with metadata and logger injection:
- Used by ModelBase for field initialization
- Ensures proper dependency injection for all field types

### createValidationRule(string $ruleClass)
```php
public static function createValidationRule(string $ruleClass): object
```
Creates validation rule instances with dependency injection.

## Auto-Wiring Methods

### get(string $serviceName)
```php
public static function get(string $serviceName): object
```
**ENHANCED** - Generic service access with auto-wiring fallback:
- Tries container first, then auto-wiring
- Works with any framework class
- Future-proof for new services

### create(string $className, array $parameters)
```php
public static function create(string $className, array $parameters = []): object
```
**ENHANCED** - Auto-wiring class creation:
- Pure auto-wiring if no parameters provided
- Mixed auto-wiring and explicit parameters
- Avoids container locking issues
- Essential for model and field creation

## Utility Methods

### setContainer(Container $container)
```php
public static function setContainer(Container $container): void
```
Sets container for testing purposes.

### reset()
```php
public static function reset(): void
```
Resets container state (useful for testing).

## Usage Examples

### Basic Service Access
```php
// Initialize framework
ServiceLocator::initialize();

// Get core services
$logger = ServiceLocator::getLogger();
$config = ServiceLocator::getConfig();
$dbConnector = ServiceLocator::getDatabaseConnector();
```

### Model and Field Creation
```php
// Create model with auto-wiring
$user = ServiceLocator::create(Users::class);

// Create field with metadata
$emailField = ServiceLocator::createField(EmailField::class, [
    'name' => 'email',
    'type' => 'Email',
    'required' => true
]);
```

### Auto-Wiring Examples
```php
// Get any framework service
$customService = ServiceLocator::get(CustomService::class);

// Create with mixed parameters
$service = ServiceLocator::create(SomeClass::class, [
    'customParam' => 'value'
    // Other dependencies auto-wired
]);
```

## Error Handling
The ServiceLocator includes comprehensive error handling:
- Database service failures throw descriptive GCException
- Config failures return functional stubs
- Auto-wiring failures provide clear error messages
- Service initialization errors are logged with context

## Dependencies
- Uses Aura\Di\Container for dependency injection
- Integrates with ContainerConfig for auto-wiring
- Provides access to all framework services
- Essential for model CRUD operations
