# ID Field

## Overview
This is the field type that is used to uniquely identify a record in the database. It is always a UUID (Universally Unique Identifier). The ID field is essential for establishing relationships between different models and ensuring data integrity.

## Properties and their default values if applicable
- name: 'id' - The name of the field, used to identify it in the database and in the UI. When instantiating an ID Field, the 'name' value must be present in the metadata passed in from the model or relationship and cannot be empty.
- label: 'ID' - The label displayed in the UI for the field. Default is the same as the `name` value.
- type: 'ID' - The type of the field, which is 'Text' for this field type.
- required: true - A boolean indicating whether the field is required. Default is `true`.
- unique: true - A boolean indicating whether the field must be unique across all records. Default is `true`.
- read_only: true - A boolean indicating whether the field is read-only. Default is `true`.
- validationRules: ['ID', 'Required'] - An array of strings that map to subclasses of the ValidationRuleBase class.
  These rules define the validation logic for the field.