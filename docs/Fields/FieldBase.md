# FieldBase

## Overview
The FieldBase class is the abstract foundation for all field types in the Gravitycar framework.
It provides comprehensive metadata management, validation, and utility methods for easy field configuration and operation.

## Core Features
- **Metadata-Driven Configuration**: Fields are configured through metadata arrays
- **Comprehensive Metadata Utilities**: Easy access to metadata values with type checking
- **Validation Framework**: Built-in validation with extensible rules
- **Database Integration**: Automatic handling of database field inclusion/exclusion
- **Dependency Injection**: Full integration with Aura.DI container

## Constructor
```php
public function __construct(array $metadata, Logger $logger)
```
- Validates that metadata contains required 'name' field
- Sets up field properties from metadata
- Initializes default value and validation rules
- Stores logger for error reporting

## Core Properties
- `$name`: Field name from metadata
- `$value`: Current field value
- `$originalValue`: Value before last change
- `$metadata`: Complete metadata array
- `$validationRules`: Array of validation rule names
- `$logger`: Logger instance for error reporting

## Core Methods

### getName()
```php
public function getName(): string
```
Returns the field name.

### getValue()
```php
public function getValue()
```
Returns the current field value.

### setValue($value)
```php
public function setValue($value): void
```
Sets the field value and triggers validation.

### validate()
```php
public function validate(): bool
```
Validates the current value against all validation rules.

## Metadata Utility Methods

### getMetadata()
```php
public function getMetadata(): array
```
Returns the complete metadata array for the field.

### getMetadataValue(string $key, $default = null)
```php
public function getMetadataValue(string $key, $default = null)
```
Gets a specific metadata value with optional default fallback.

### hasMetadata(string $key)
```php
public function hasMetadata(string $key): bool
```
Checks if a metadata key exists.

### metadataEquals(string $key, $expectedValue)
```php
public function metadataEquals(string $key, $expectedValue): bool
```
Checks if a metadata key has a specific value (strict comparison).

### metadataIsTrue(string $key)
```php
public function metadataIsTrue(string $key): bool
```
Checks if a metadata key is truthy (useful for boolean flags).

### metadataIsFalse(string $key)
```php
public function metadataIsFalse(string $key): bool
```
Checks if a metadata key is falsy.

## Database Integration Methods

### isDBField()
```php
public function isDBField(): bool
```
Checks if this field should be stored in the database:
- Returns true by default
- Returns false if metadata has `'isDBField' => false`
- Used by DatabaseConnector to determine which fields to include in queries

### isRequired()
```php
public function isRequired(): bool
```
Checks if the field is required based on metadata `'required'` key.

### isReadonly()
```php
public function isReadonly(): bool
```
Checks if the field is readonly based on metadata `'readonly'` key.

### isUnique()
```php
public function isUnique(): bool
```
Checks if the field must be unique based on metadata `'unique'` key.

## Example Metadata Structure
```php
$fieldMetadata = [
    'name' => 'email',
    'type' => 'Email',
    'required' => true,
    'unique' => true,
    'maxLength' => 255,
    'validation' => ['Email', 'Required', 'Unique'],
    'isDBField' => true,
    'readonly' => false,
    'defaultValue' => null
];
```

## Usage Examples

### Basic Field Usage
```php
// Field creation through ServiceLocator (recommended)
$emailField = ServiceLocator::createField(EmailField::class, $metadata);

// Access metadata
if ($emailField->isRequired()) {
    echo "Email is required";
}

if ($emailField->isDBField()) {
    echo "Email will be stored in database";
}

// Get specific metadata values
$maxLength = $emailField->getMetadataValue('maxLength', 255);
$validation = $emailField->getMetadataValue('validation', []);
```

### Metadata Checking Patterns
```php
// Check boolean flags
if ($field->metadataIsTrue('required')) {
    // Field is required
}

if ($field->metadataEquals('type', 'Password')) {
    // Field is password type
}

// Safe metadata access with defaults
$placeholder = $field->getMetadataValue('placeholder', 'Enter value...');
$cssClass = $field->getMetadataValue('cssClass', 'form-control');
```

### Database Field Control
```php
// Fields marked as non-database fields won't be included in CRUD operations
$metadata = [
    'name' => 'confirmPassword',
    'type' => 'Password',
    'isDBField' => false, // This field won't be saved to database
    'required' => true
];
```

## Field Type Implementation
When creating custom field types, extend FieldBase and implement specific behavior:

```php
class CustomField extends FieldBase {
    public function validate(): bool {
        // Custom validation logic
        if (!parent::validate()) {
            return false;
        }
        
        // Additional custom validation
        return $this->customValidation();
    }
    
    private function customValidation(): bool {
        // Implement field-specific validation
        return true;
    }
}
```

## Integration with Models
Fields are automatically created by ModelBase during initialization:

```php
// In model metadata file
return [
    'fields' => [
        'email' => [
            'type' => 'Email',
            'required' => true,
            'unique' => true,
            'validation' => ['Email', 'Required', 'Unique']
        ],
        'temp_field' => [
            'type' => 'Text',
            'isDBField' => false // Won't be saved to database
        ]
    ]
];
```

## Dependencies
- Requires Logger injection for error reporting
- Uses validation rule classes for field validation
- Integrates with DatabaseConnector for CRUD operations
- Works with ServiceLocator for dependency injection
