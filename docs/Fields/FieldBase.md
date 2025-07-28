# FieldBase

## Overview
The FieldBase class is the base class for all field types in the Gravitycar framework.
It provides the common properties and methods that all field types must implement. This class is used to define the structure and behavior of fields in models and relationships. It's also used by the SchemaGenerator to generate the database schema based on the metadata files.
The FieldBase class DOES NOT contain a property for the model it belongs to. Instead, 
it is expected that the model will pass itself as the third parameter to the `set` method 
when setting a field value. The prevents circular dependencies between the FieldBase class and the ModelBase class.
By having the model pass itself to the `set` method, the FieldBase class can access the model's properties and methods as needed without creating a direct dependency on the ModelBase class.
This class is designed to be extended by specific field types, such as TextField, BooleanField, EnumField, etc.
It provides a common interface for all field types, allowing them to be used interchangeably in models and relationships.

## Properties, their default values and descriptions
- `name`: '' - Field name (required, cannot be empty)
- `label`: '' - Display label for UI (defaults to name if empty)
- `type`: '' - Field type (e.g., 'Text', 'Email', 'Integer')
- `phpDataType`: 'string' - PHP data type (string, int, float, bool, etc.)
- `databaseType`: 'VARCHAR' - MySQL data type (VARCHAR, INT, FLOAT, DATETIME, etc.)
- `uiDataType`: 'text' - UI input type for rendering
- `value`: null - Current field value
- `originalValue`: null - Original value from database (for change detection)
- `defaultValue`: null - Default value when no value is set
- `validationRules`: [] - Array of validation rule class names
- `required`: false - Whether field is required
- `unique`: false - Whether field value must be unique
- `maxLength`: null - Maximum length for string fields
- `minLength`: null - Minimum length for string fields
- `minValue`: null - Minimum value for numeric fields
- `maxValue`: null - Maximum value for numeric fields
- `readOnly`: false - Whether field is read-only
- `requiredUserType`: null - Required user type to view/edit field
- `searchable`: true - Whether field is searchable in UI
- `isDbField`: true - Whether field is stored in database
- `isPrimaryKey`: false - Whether field is primary key
- `isIndexed`: false - Whether field should be indexed
- `allowedValues`: [] - Whitelist of allowed values
- `forbiddenValues`: [] - Blacklist of forbidden values
- `optionsClass`: null - Class name for dynamic options
- `optionsMethod`: null - Method name for dynamic options
- `placeholder`: '' - Placeholder text for input fields
- `description`: '' - Field description for documentation
- `helpText`: '' - Help text displayed in UI
- `showInList`: true - Whether to show field in list views
- `showInForm`: true - Whether to show field in forms
- `metadata`: [] - Additional metadata from model definition
- `validationErrors`: [] - Current validation errors for this field
- `valueHasBeenChanged`: false - Whether the field value has changed since initially set
- `logger`: null - Logger instance for logging messages related to this field

## Methods, their return types and descriptions
- `__construct(array $fieldDefinition): void`
  - Constructor that initializes the field properties based on the provided field definition array.
  - This method should set the properties of the field based on the keys and values in the `$fieldDefinition` array using the `ingestFieldDefinitions` method.
  - This method should set up the validation rules for the field using the `setupValidationRules` method.
- `get(string $fieldName): mixed`
  - Returns the value property of this field.
  - If the value is not set, it should return the default value.
- `set(string $fieldName, mixed $value, ModelBase $model): void`
  - This method must validate the `$value` using the field's validation rules by calling the `validate` method.
  - If the field does not exist, it should throw a GCException with a descriptive error message.
  - If the value fails validation, it should add all the validation error messages to the model's `validationErrors` array using the `registerValidationError` method on the model.
  - If the value passes validation, it should set the `value` property to the provided `$value`.
  - If the value is different from the original value, it should call `hasChanged(true)`.
- `getValueForApi(): mixed`
  - Returns the value of the field formatted for API responses.
  - This method should handle any necessary formatting or transformation of the field value before returning it.
- `setValueFromDB(mixed $value): void`
  - Sets the value of the field from a database record.
  - This method should handle any necessary transformations or formatting of the value before setting it.
  - It should also set the `originalValue` property to the value being set, to track changes.
  - Values from the DB are assumed to be in the correct format for the field type. No validation should be performed on this value.
- `ingestFieldDefinitions(array $fieldDefinitions): void`
  - Ingests field definitions from an array derived from the metadata for a model or a relationship.
  - "Ingests" means that it will iterate through all the keys in the associative array $fieldDefinitions and set the properties of the field based on the values in that array for each key.
  - This method should populate the field's properties based on the provided definitions.
  - Any key listed in the $fieldDefinitions that is not defined as a property in the class should be ignored.
  - If any required properties are missing or invalid, it should throw a GCException with a descriptive error message.
- `setupValidationRules(): void`
  - Sets up the validation rules for the field based on the validationRules property.
  - Rule class names are expected to be partial names of classes. For example 'AlphaNumeric' would map to the class `ValidationRules\AlphaNumericValidation`.'
  - This method should use the ValidationRuleFactory to instantiate the validation rules.
  - As each rule class is instantiated, replace the string class name in the validationRules array with the instantiated object. 
  - If any rule class does not exist or is invalid, it should throw a GCException with a descriptive error message.
- `hasChanged(mixed $state = null): bool`
  - Checks if the field value has changed from its original value.
  - If `$state` is provided, the valueHasBeenChanged property to the provided state. 
  - Returns valueHasBeenChanged.
- `validate(): bool`
  - Iterates over the validation rules.
  - Call these methods in this order for each rule:
    - `setValue($this->getValue()`
    - `setField($this)`
    - `setModel($model)` (if applicable)
    - `validate()`
    - If the rule's `validate()` method returns false, call the rule's `getFormatErrorMessage()` method and pass that value to this field's `registerValidationError()` method and the model's `registerValidationError()` method.
  - if any rule fails validation return false. 
  - if all rules pass validation, return true.

- `registerValidationError(string $errorMessage): void`
  - Registers a validation error message for this field.
  - This method should append the provided error message to the `validationErrors` array.
  - If the `errorMessage` is empty, it should throw a GCException with a descriptive error message.
