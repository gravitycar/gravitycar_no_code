# ModelBase class

## Overview
The ModelBase class is the foundational class for all models in the Gravitycar framework. It provides a set of common properties and methods that are shared across all models, ensuring consistency and ease of use.
Database fields should not be defined as properties of the ModelBase class, or of any of its subclasses. Instead, they should be defined in the metadata files for each model, which will be ingested by the ModelBase class during instantiation. This allows for a flexible and extensible data model that can be easily modified without changing the codebase.

## Properties, their default values and descriptions
- `name`: '' - The name of the model, used to identify it in the database and in the UI. When instantiating a ModelBase, the 'name' value must be present in the metadata passed in from the model or relationship and cannot be empty. It must be an alphanumeric string with no spaces or special characters.
- `label`: '' - The label displayed in the UI for the model. Default is the same as the `name` value.
- `labelSingular`: '' - The singular label for the model, used in contexts where a single instance is referenced. Default is the same as the `label` value.
- `db`: null - The database connection used by the model. This is typically set to the default database connection. NOTE: this should be a singleton instance, or be derived from some mechanism that ensures only one instance of the database connection is used across the application.
- `fields`: [] - An array of FieldBase objects that define the fields of the model. Each field is an instance of a subclass of FieldBase, such as TextField, BooleanField, EnumField, etc. The keys of this associative array are the field names, and the values are the FieldBase instances.
- `table`: '' - The name of the database table associated with the model. This is used to perform CRUD operations on the model's data.
- `recordExistsInDb`: false - A boolean indicating whether the model's record exists in the database. This is used to determine if the model is new or if it has been persisted.
- `log`: null - An instance of the Logger class used for logging messages related to the model's operations. This is typically set to a singleton instance of the Logger class.
- `validationErrors`: [] - An array of validation error messages. This is used to store any validation errors that occur when validating the model's fields.
- `coreFieldsMetadataFilePath`: '' - The file path to the core fields metadata file. This file contains the definitions of the core fields that are used by all models. The metadata for these core fields should be stored in a separate metadata file, which is loaded when the model is instantiated.
- `validationStatus`: 'pending' - The validation status of the model. This can be 'pending', 'passed', or 'failed'. It is used to track the validation state of all of the model's fields. If any field fails validation the validation status should be set to 'failed'. If all fields pass validation, the validation status should be set to 'passed'. If no validation has been performed yet, the validation status should be set to 'pending'. If any field's value is changed, the validation status should be set to 'pending'.
## Methods, their return types and descriptions
- `getMetaDataFilePaths`: function(): array returns an array of these file paths for the metadata files: this->coreFieldsMetadataFilePath and `src/Models/{modelName}/metadata.php`
  - Returns an array of file paths for the field files associated with the model. All models start with the same set of fields, which will be stored in the same location
- `ingestMetadata`: function(): void
  - Calls the `getMetaDataFilePaths` method to retrieve the file paths for the metadata files, and then loads the metadata from these files.
  - Will merge the metadata from all files from `getMetaDataFilePaths` into a single array. It's very important that the fields defined in one file are merged into the same array as fields defined in subsequent files. If subsequent files define the same field by name, the last definition will override the previous one.
  - Ingests metadata for the model, which includes properties such as `name`, `label`, `fields`, and other model-specific configurations. The metadata MUST include `fields`, which will be an associative array formatted like this: 
```
'fields' => [
    'fieldName' => [
        'name' => 'fieldName',
        'type' => 'FieldType',
        // other field properties...
    ],
    // other fields...
]
```
  - This method initializes the model's properties based on the provided metadata.
  - This method will use the FieldFactory class to create instances of the fields defined in the metadata. The FieldFactory will instantiate the correct field type based on the `type` property of each field in the metadata.
  - Any error conditions that occur during the ingestion of metadata MUST throw a GCException with a descriptive error message.
  - This method should also validate the metadata to ensure that all required properties are present and correctly formatted. If any metadata is missing or invalid, it should throw a GCException with a descriptive error message.
  - This method's processes should be idempotent, meaning that calling it multiple times with the same metadata should not change the state of the model.
  - This method's logic can be broken down into smaller methods for better readability and maintainability and testing, but the overall flow should remain consistent.
- `getFields`: function(): array
  - Returns an associative array of FieldBase objects that define the fields of the model. The keys of this array are the field names, and the values are the FieldBase instances.
    - This method should return the `fields` property of the model, which is an associative array of FieldBase objects.
    - This method should also ensure that all fields are properly initialized and ready for use.
- `getField(string $fieldName): ?FieldBase`
  - Returns the FieldBase object for the specified field name. If the field does not exist, it returns null.
  - This method should check if the field exists in the `fields` array and return the corresponding FieldBase object. If the field does not exist, it should return null.
  - This method should also handle any exceptions that may occur when accessing the field, such as if the field is not properly initialized or if there is an error in the underlying data source.
- `get`(string $fieldName): mixed
  - Returns the value of the specified field using the field's get() method. If the field does not exist, it returns null.
  - This method should check if the field exists in the `fields` array and return its value. If the field does not exist, it should return null.
  - This method should also handle any exceptions that may occur when accessing the field's value, such as if the field is not properly initialized or if there is an error in the underlying data source.
- `set`(string $fieldName, mixed $value): void
  - Sets the value of the specified field using the field's set() method. If the field does not exist, it throws a GCException.
  - This method should check if the field exists in the `fields` array and set its value using the field's set() method. If the field does not exist, it should throw a GCException with a descriptive error message.
  - This method should also handle any exceptions that may occur when setting the field's value, such as if the field is not properly initialized or if there is an error in the underlying data source.
  - If the field's set() method fails validation, all validation errors should be added to the `validationErrors` property of the model.
- `collectValidationErrors()`: function(): array
  - Returns an array of validation error messages for the model's fields. This method should iterate over all fields and collect any validation errors that have occurred.
  - If there are no validation errors, it should return an empty array.
  - This method should also handle any exceptions that may occur when collecting validation errors, such as if the field is not properly initialized or if there is an error in the underlying data source.
- `populateFromRequest(array $request)` : void
  - Populates the model's fields from the provided request data. This method should iterate over the request data and set the values for each field using the field's set() method.
  - This method should also handle any exceptions that may occur when populating the model from the request, such as if the request data is not properly formatted or if there is an error in the underlying data source.
  - If any field fails validation during this process, all validation errors should be added to the `validationErrors` property of the model.
  - $request should be an associative array where the keys are field names and the values are the corresponding values to set.
  - Any keys in $request that do not correspond to a field in the model should be ignored.
- `populateFromDB(array $data): void`
  - Populates the model's fields from the provided database record data. This method should iterate over the data and set the values for each field using the field's setValueFromDB() method.
  - This method should also handle any exceptions that may occur when populating the model from the database, such as if the data is not properly formatted or if there is an error in the underlying data source.
  - $data should be an associative array where the keys are field names and the values are the corresponding values to set.
  - It is assumed that data from the database is already in the correct format for each field type, so no validation should be performed on these values.
  - Any keys in $data that do not correspond to a field in the model should be ignored.
- `registerValidationError(string $errorMessage): void`
  - This method should append the provided error message to the `validationErrors` array.
  - After the error message is registered, it should also set the `validationStatus` property to 'failed' to indicate that validation has failed for the model.
  - The `validationErrors` array should only contain unique error messages, so if the same error message is already present, it should not be added again.
  - If the `errorMessage` is empty, it should throw a GCException with a descriptive error message.
- `create(): bool`
  - Creates a new record in the database for the model. 
  - This method will NOT prepare the SQL INSERT statement. That's done by the DatabaseConnector class.
  - Should be called after the model's fields have been populated and validated. The `populateFromRequest` method will set the fields values using each field's `set` method, which will validate the values.
  - if the model's `id` field is not set, it should be generated automatically using a UUID.
  - This method will confirm that all the model's fields have been validated and that there are no validation errors in the `validationErrors` array.
  - If there are validation errors, the record will not be created, and the method should return false.
  - If there are no validation errors, the model will pass itself to `db->create()` and return the value of that method.
  - Set the `created_by` field and the `updated_by` field to the current user's ID, and the `created_at` field and the `updated_at` field to the current timestamp.
- `update(): bool`
  - Updates the existing record in the database for the model.
  - This method will NOT prepare the SQL UPDATE statement. That's done by the DatabaseConnector class.
  - Should be called after the model's fields have been populated and validated. The `populateFromRequest` method will set the fields values using each field's `set` method, which will validate the values.
  - If the model's `id` field is not set, it should throw a GCException with a descriptive error message.
  - Set the `updated_by` field to the current user's ID, and update the `updated_at` field to the current timestamp.`
  - This method will confirm that all the model's fields have been validated and that there are no validation errors in the `validationErrors` array.
  - If there are validation errors, the record will not be updated, and the method should return false.
  - If there are no validation errors, the model will pass itself to `db->update()` and return the value of that method.
- `delete(): bool`
  - Performs a soft delete on the existing record in the database for the model.
  - This method will NOT prepare the SQL UPDATE statement. That's done by the DatabaseConnector class.
  - Should be called after confirming that the model's `id` field is set.
  - If the model's `id` field is not set, it should throw a GCException with a descriptive error message.
  - Set the `deleted_by` field to the current user's ID, and update the `deleted_at` field to the current timestamp.
  - This method will pass itself to `db->softDelete()` and return the value of that method. Note: this is an update, a soft-delete, not a hard delete.
  - If the update fails, it should throw a GCException with a descriptive error message.
- `retrieve(string $id): bool`
  - Retrieves a record from the database by its ID and populates the model's fields with the retrieved data.
  - This method will NOT prepare the SQL SELECT statement. That's done by the DatabaseConnector class.
  - It should set the `id` field to `$id` and then call `db->retrieve($this)`.
  - If the record is found, it should populate the model's fields with the retrieved data and set `recordExistsInDb` to true. If not found, it should set `recordExistsInDb` to false.
  - If any error occurs during this process, it should throw a GCException with a descriptive error message.`