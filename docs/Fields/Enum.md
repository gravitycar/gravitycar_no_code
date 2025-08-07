# Enum Field

## Overview
The Enum Field type is used to capture a single value from a predefined set of options.
The options are provided by a method on a class. The metadata for the field must specify 
a class name and a method name on that class that will return an array of options for the field.
The options list should be formatted as an associative array in this format: 'value_to_store_in_db' => 'value_to_display_in_ui'.
This field type should only allow a single value to be selected from the list of options.
This field type should only allow values listed as keys in the options to be stored in the database.
This field type must confirm that the methodName property is set to a valid method in the class specified by the className property.

## UI
- The Enum Field will be rendered as a dropdown list in the user interface.
- The dropdown will display the options returned by the method specified in the `className` and `methodName` properties.
- The user can select one option from the dropdown list.
- If the user tries to submit a value that is not in the list of options, an error message will be displayed.
- The UI for the field will include an option to search through the options if the list is long.

## Properties, their default values and descriptions
- `name`: '' - The name of the field, used to identify it in the database and in the UI. When instantiating an Enum Field, the 'name' value must be present in the metadata passed in from the model or relationship and cannot be empty.
- `label`: '' - The label displayed in the UI for the field. Default is the same as the `name` value.
- `type`: 'Enum' - The type of the field, which is 'Enum' for this field type.
- `required`: false - A boolean indicating whether the field is required.
- `maxLength`: 255 - The maximum length of the Enum input.
- `className`: '' - The name of the class that contains the method to retrieve the options for the field. This class must be defined in the metadata.
- `methodName`: '' - The name of the method in the class specified by `className` that returns the options for the field. This method must return an associative array in the format: 'value_to_store_in_db' => 'value_to_display_in_ui'.
- `ValidationRules`: ['InOptions'] - An array of strings that map to subclasses of the ValidationRuleBase class.
  These rules define the validation logic for the field. Default is an empty array.