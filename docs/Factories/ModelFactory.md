# ModelFactory Class Documentation

## Overview

The ModelFactory provides a centralized, convenient way to create and retrieve model instances in the Gravitycar framework. It standardizes model instantiation by accepting simple model names and handling the underlying complexity of namespace resolution, dependency injection, and database operations.

## Purpose

The ModelFactory solves the problem of inconsistent model instantiation patterns throughout the codebase. Instead of using fully qualified class names like `new Gravitycar\Models\users\Users()` or remembering complex ServiceLocator calls, developers can use simple, intuitive methods.

## Core Features

- **Simple Model Creation**: Create models using just the model name
- **Database Retrieval**: Retrieve and populate models from database by ID
- **Automatic Dependency Injection**: Leverages existing ServiceLocator for proper DI
- **Error Handling**: Comprehensive error handling with meaningful messages
- **Logging Integration**: Full logging for debugging and monitoring
- **Model Discovery**: Utility methods to discover available models

## Class Structure

```php
namespace Gravitycar\Factories;

class ModelFactory {
    public static function new(string $modelName): ModelBase
    public static function retrieve(string $modelName, string $id): ?ModelBase
    public static function getAvailableModels(): array
}
```

## Methods

### new(string $modelName): ModelBase

Creates a new, empty model instance ready for use.

**Parameters:**
- `$modelName` (string): Simple model name (e.g., 'Users', 'Movies', 'Movie_Quotes')

**Returns:**
- `ModelBase`: New model instance with all fields initialized

**Throws:**
- `GCException`: If model name is invalid or model class doesn't exist

**Example:**
```php
// Create a new user
$user = ModelFactory::new('Users');
$user->set('username', 'john@example.com');
$user->set('password', 'securepassword');
$user->set('first_name', 'John');
$user->set('last_name', 'Doe');
$user->create();

// Create a new movie
$movie = ModelFactory::new('Movies');
$movie->set('name', 'The Matrix');
$movie->create();
```

### retrieve(string $modelName, string $id): ?ModelBase

Retrieves a model instance from the database and populates it with data.

**Parameters:**
- `$modelName` (string): Simple model name
- `$id` (string): Record ID to retrieve

**Returns:**
- `ModelBase|null`: Populated model instance or null if record not found

**Throws:**
- `GCException`: If model name is invalid, model class doesn't exist, or database error occurs

**Example:**
```php
// Retrieve an existing user
$user = ModelFactory::retrieve('Users', '123');
if ($user) {
    echo "Username: " . $user->get('username');
    echo "Email: " . $user->get('email');
} else {
    echo "User not found";
}

// Retrieve and update a movie
$movie = ModelFactory::retrieve('Movies', '456');
if ($movie) {
    $movie->set('synopsis', 'Updated synopsis');
    $movie->update();
}
```

### getAvailableModels(): array

Returns a list of available model names by scanning the Models directory.

**Returns:**
- `array`: Array of available model names

**Example:**
```php
$models = ModelFactory::getAvailableModels();
// Returns: ['Users', 'Movies', 'Movie_Quotes', 'Installer']

foreach ($models as $modelName) {
    echo "Available model: $modelName\n";
}
```

## Model Name Resolution

The ModelFactory automatically converts simple model names to full namespaced class names following the framework's convention:

- **Input**: `'Users'`
- **Directory**: `src/Models/users/`
- **Class**: `Gravitycar\Models\users\Users`

- **Input**: `'Movie_Quotes'`
- **Directory**: `src/Models/movie_quotes/`
- **Class**: `Gravitycar\Models\movie_quotes\Movie_Quotes`

## Error Handling

The ModelFactory provides comprehensive error handling with detailed context:

### Invalid Model Names
```php
try {
    ModelFactory::new('Invalid@Model');
} catch (GCException $e) {
    // Error: "Model name contains invalid characters"
    // Context includes: model_name, allowed_pattern
}
```

### Non-Existent Models
```php
try {
    ModelFactory::new('NonExistentModel');
} catch (GCException $e) {
    // Error: "Model class not found: Gravitycar\Models\nonexistentmodel\NonExistentModel"
    // Context includes: model_class, expected_file_path, suggestion
}
```

### Database Errors
```php
try {
    ModelFactory::retrieve('Users', '123');
} catch (GCException $e) {
    // Error: "Failed to retrieve model 'Users' with ID '123': Database connection failed"
    // Context includes: model_name, id, original_error
}
```

## Integration with Framework

### ServiceLocator Integration

ModelFactory leverages the existing ServiceLocator for dependency injection:

```php
// Internally uses:
ServiceLocator::createModel($modelClass)     // For model creation
ServiceLocator::getDatabaseConnector()       // For database operations
ServiceLocator::getLogger()                  // For logging
```

### Dependency Injection

All model instances created by ModelFactory receive proper dependency injection:

- Logger injection for error logging and debugging
- Metadata loading and field initialization
- Relationship initialization
- Validation rule setup

## Best Practices

### When to Use ModelFactory

**Use ModelFactory when:**
- Creating new model instances in application logic
- Retrieving models by ID for display or editing
- Writing reusable code that works with different model types
- Building dynamic functionality that creates models based on user input

**Consider alternatives when:**
- Using complex queries (use ModelBase::find() methods)
- Working with relationships (use relationship methods)
- Bulk operations (use DatabaseConnector directly)

### Naming Conventions

**Valid model names:**
- `'Users'` - Standard model name
- `'Movie_Quotes'` - Model with underscores
- `'TestModel'` - CamelCase model name

**Invalid model names:**
- `'User@Model'` - Contains special characters
- `'User-Model'` - Contains hyphens
- `'User Model'` - Contains spaces
- `'123User'` - Starts with number

### Error Handling

Always handle potential exceptions:

```php
try {
    $model = ModelFactory::retrieve('Users', $userId);
    if ($model) {
        // Process the model
    } else {
        // Handle record not found
    }
} catch (GCException $e) {
    // Log error and handle gracefully
    $logger->error('Failed to retrieve user', [
        'user_id' => $userId,
        'error' => $e->getMessage()
    ]);
}
```

## Performance Considerations

### Model Creation Performance

- ModelFactory uses the same dependency injection system as direct model creation
- Minimal overhead over direct instantiation
- Model name resolution is cached internally
- No performance penalty for using factory pattern

### Memory Usage

- Models are created fresh each time (no caching)
- No memory leaks from retained instances
- Garbage collection handles cleanup automatically

### Database Operations

- Uses existing DatabaseConnector for optimal performance
- Leverages prepared statements and parameter binding
- Same performance as direct database operations

## Examples

### Basic CRUD Operations

```php
// Create
$user = ModelFactory::new('Users');
$user->set('username', 'newuser@example.com');
$user->set('password', 'password123');
$user->create();

// Retrieve
$user = ModelFactory::retrieve('Users', $user->get('id'));

// Update
if ($user) {
    $user->set('first_name', 'Updated Name');
    $user->update();
}

// Delete (soft delete)
if ($user) {
    $user->delete();
}
```

### Working with Different Models

```php
// Users
$user = ModelFactory::new('Users');
$user->set('username', 'admin@example.com');
$user->set('user_type', 'admin');

// Movies
$movie = ModelFactory::new('Movies');
$movie->set('name', 'Inception');

// Movie Quotes
$quote = ModelFactory::new('Movie_Quotes');
$quote->set('quote', 'We need to go deeper');
$quote->set('movie_id', $movie->get('id'));
```

### Dynamic Model Creation

```php
function createModelFromRequest(array $request): ModelBase {
    $modelName = $request['model_type']; // e.g., 'Users'
    $model = ModelFactory::new($modelName);
    
    foreach ($request['fields'] as $fieldName => $value) {
        if ($model->hasField($fieldName)) {
            $model->set($fieldName, $value);
        }
    }
    
    return $model;
}
```

### Bulk Operations

```php
function createMultipleUsers(array $usersData): array {
    $createdUsers = [];
    
    foreach ($usersData as $userData) {
        $user = ModelFactory::new('Users');
        foreach ($userData as $field => $value) {
            $user->set($field, $value);
        }
        
        if ($user->create()) {
            $createdUsers[] = $user;
        }
    }
    
    return $createdUsers;
}
```

## Testing

### Unit Testing with ModelFactory

```php
public function testCreateUserWithFactory(): void {
    $user = ModelFactory::new('Users');
    $this->assertInstanceOf(Users::class, $user);
    $this->assertTrue($user->hasField('username'));
}

public function testRetrieveNonExistentUser(): void {
    $user = ModelFactory::retrieve('Users', 'non-existent-id');
    $this->assertNull($user);
}
```

### Integration Testing

```php
public function testFullUserWorkflow(): void {
    // Create
    $user = ModelFactory::new('Users');
    $user->set('username', 'test@example.com');
    $this->assertTrue($user->create());
    
    $userId = $user->get('id');
    $this->assertNotEmpty($userId);
    
    // Retrieve
    $retrievedUser = ModelFactory::retrieve('Users', $userId);
    $this->assertInstanceOf(Users::class, $retrievedUser);
    $this->assertEquals('test@example.com', $retrievedUser->get('username'));
    
    // Clean up
    $retrievedUser->hardDelete();
}
```

## Migration Guide

### From Direct Instantiation

**Before:**
```php
$logger = ServiceLocator::getLogger();
$user = new \Gravitycar\Models\users\Users($logger);
```

**After:**
```php
$user = ModelFactory::new('Users');
```

### From ServiceLocator::createModel()

**Before:**
```php
$user = ServiceLocator::createModel(\Gravitycar\Models\users\Users::class);
```

**After:**
```php
$user = ModelFactory::new('Users');
```

### From Static Methods

**Before:**
```php
$user = Users::findById('123');
```

**After (when you need a fresh instance):**
```php
$user = ModelFactory::retrieve('Users', '123');
```

## Dependencies

- **ServiceLocator**: For dependency injection and service access
- **DatabaseConnector**: For database operations in retrieve()
- **ModelBase**: Base class for all models
- **GCException**: For consistent error handling
- **Logger**: For operation logging

## Files Structure

- **Source**: `src/Factories/ModelFactory.php`
- **Unit Tests**: `Tests/Unit/Factories/ModelFactoryTest.php`
- **Integration Tests**: `Tests/Integration/Factories/ModelFactoryIntegrationTest.php`
- **Documentation**: `docs/Factories/ModelFactory.md`
