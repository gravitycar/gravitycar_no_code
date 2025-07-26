# Field Factory

## Overview
The FieldFactory class is responsible for creating instances of field classes based on field type definitions found in model metadata. It provides a centralized way to instantiate the appropriate field class for each field definition, ensuring that the correct field type logic is applied based on the metadata specifications.

## Properties, their default values and descriptions
- `fieldDefinition`: null - The field definition array from metadata that this field factory is processing. This contains all the metadata properties for the field including type, name, validation rules, etc.
- `fieldTypeMap`: [] - An associative array mapping field type names to their corresponding field class names. This array is used to determine which field class to instantiate based on the field type specified in the metadata.
- `defaultFieldType`: 'TextField' - The default field class to use if no specific field type is found for the specified type. This should be a class that extends FieldsBase and is suitable for handling basic text field functionality.
- `fieldClass`: null - The class name of the field to be instantiated. This is determined based on the field type from the metadata and the `fieldTypeMap` array.
- `field`: null - The instance of the field class that will be created and configured. This is set after the field class is determined and instantiated.
- `model`: null - The model object that this field factory is associated with. This is the model that will contain the field being created.
- `logger`: null - An instance of the Logger class for logging messages related to field creation. This is used to log any errors or important information during the field instantiation process.

## Methods, their return types and descriptions
- `__construct(): void`
  - Constructor that initializes the field factory.
- `createField(array $fieldDefinition, ModelBase $model = null): FieldsBase`
  - This method sets the `fieldDefinition` and `model` properties.
  - Returns an instance of the field class associated with the field definition.
  - The field class is determined by checking the `fieldTypeMap` array for a matching field type from the metadata.
  - If no specific field type is found, it defaults to the `defaultFieldType`. The field type and model name should be logged for debugging purposes with a helpful error message.
  - The method then sets the `field` property to the instantiated object and returns it.
- `getFieldDefinition(): array`
  - Returns the field definition array that this field factory is processing.
  - This method is used to access the field metadata properties and configuration in the field creation process.
- `getModel(): ModelBase`
  - Returns the model object that this field factory is associated with.
  - This method is used to access the model properties and methods in the field creation process.
