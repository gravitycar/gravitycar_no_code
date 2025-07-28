# ModelMetadata Structure

## Purpose
Defines the detailed structure and requirements for model metadata files in the Gravitycar framework.

## Location
- **Metadata Files**: `src/models/<model_name>/<model_name>_metadata.php`

## Metadata File Structure
Each model metadata file should return an associative array with the following structure:

```php
$metadata = [
    'name' => 'ModelName',
    'table' => 'table_name',
    'fields' => [
        // Field definitions
    ],
    'relationships' => [
        // Relationship definitions  
    ],
    'ui' => [
        // UI component definitions
    ]
];
```

## Core Fields (Required for All Models)
All models must include these standard fields:
- `id`: Unique identifier for the record (UUID)
- `name`: Name of the record (used for display purposes)
- `created_at`: Timestamp of when the record was created
- `updated_at`: Timestamp of when the record was last updated
- `deleted_at`: Timestamp of when the record was soft-deleted (null if not deleted)
- `created_by`: User ID of the user who created the record
- `updated_by`: User ID of the user who last updated the record
- `deleted_by`: User ID of the user who soft-deleted the record (null if not deleted)

## Field Definition Structure
Each field in the `fields` array should contain:

### Required Properties
- **`name`**: The field name (must match array key)
- **`type`**: Field type matching a FieldBase subclass (e.g., 'Text', 'Email', 'DateTime')
- **`label`**: Display label for the UI

### Optional Properties
- **`required`**: Boolean (default: false)
- **`defaultValue`**: Default value for the field
- **`maxLength`**: Maximum length for text fields
- **`readOnly`**: Boolean indicating if field is read-only
- **`unique`**: Boolean indicating if field must be unique
- **`searchable`**: Boolean indicating if field is searchable in UI
- **`isDBField`**: Boolean indicating if field is stored in database (default: true)

### Validation
- **`validationRules`**: Array of validation rule class names

### Enum/Select Fields
- **`optionsClass`**: Class name that provides options
- **`optionsMethod`**: Method name on the options class that returns key-value pairs

## Example Metadata Structure

```php
$metadata = [
    'name' => 'Users',
    'table' => 'users',
    'fields' => [
        'username' => [
            'name' => 'username',
            'label' => 'Username',
            'type' => 'Text',
            'required' => true,
            'maxLength' => 50,
            'validationRules' => ['Required'],
        ],
        'email' => [
            'name' => 'email',
            'label' => 'Email',
            'type' => 'Email',
            'required' => false,
            'maxLength' => 100,
            'validationRules' => ['Email'],
        ],
        'user_type' => [
            'label' => 'User Type',
            'name' => 'user_type',
            'type' => 'Enum',
            'defaultValue' => 'regular',
            'optionsClass' => '\Gravitycar\Models\Users\Users',
            'optionsMethod' => 'getUserTypes',
            'validationRules' => ['Options'],
        ],
    ],
];
```

## Implementation Notes
- Field types must match existing classes in `src/fields/`
- Validation rules must match classes in `src/validation/`
- Options methods should return arrays in format: `'db_value' => 'display_value'`
- Non-DB fields are used for display purposes only and excluded from schema generation
