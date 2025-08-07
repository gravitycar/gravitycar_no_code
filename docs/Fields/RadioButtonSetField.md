# RadioButtonSet Field

## Overview
The RadioButtonSet Field type is used to capture a single value from a predefined set of options displayed simultaneously on screen.
The options are provided by a method on a class. The metadata for the field must specify
a class name and a method name on that class that will return an array of options for the field.
The options list should be formatted as an associative array in this format: 'value_to_store_in_db' => 'value_to_display_in_ui'.
This field type allows only one value to be selected from the list of options.
This field type should only allow values listed as keys in the options to be stored in the database.
This field type must confirm that the methodName property is set to a valid method in the class specified by the className property.
The selected value is stored in the database as the option key.

## UI
- The RadioButtonSet Field will be rendered as a set of radio buttons displayed vertically or horizontally in the user interface.
- All available options will be visible simultaneously, allowing users to see all choices at once.
- Each option will be displayed as a radio button with its corresponding label.
- Only one radio button can be selected at a time, with selection automatically deselecting any previously selected option.
- The field label will be displayed above or beside the radio button group.
- If the user tries to submit values that are not in the list of options, an error message will be displayed.
- The layout can be configured to display radio buttons vertically (default) or horizontally based on space and design requirements.
- When the field is not required, an additional "None" or "Clear" option may be provided to allow deselection.

## Properties, their default values and descriptions
- `name`: '' - The name of the field, used to identify it in the database and in the UI. When instantiating a RadioButtonSet Field, the 'name' value must be present in the metadata passed in from the model or relationship and cannot be empty.
- `label`: '' - The label displayed in the UI for the field. Default is the same as the `name` value.
- `type`: 'RadioButtonSet' - The type of the field, which is 'RadioButtonSet' for this field type.
- `required`: false - A boolean indicating whether the field is required (user must select one option).
- `className`: '' - The name of the class that contains the method to retrieve the options for the field. This class must be defined in the metadata.
- `methodName`: '' - The name of the method in the class specified by `className` that returns the options for the field. This method must return an associative array in the format: 'value_to_store_in_db' => 'value_to_display_in_ui'.
- `defaultValue`: null - The default value for the field. Must be one of the option keys or null if no default selection.
- `layout`: 'vertical' - How the radio buttons are arranged in the UI. Options: 'vertical', 'horizontal'.
- `allowClear`: false - Whether to show a "Clear" or "None" option when the field is not required, allowing deselection.
- `clearLabel`: 'None' - The label displayed for the clear/none option when `allowClear` is true.
- `ValidationRules`: ['InOptions'] - An array of strings that map to subclasses of the ValidationRuleBase class. These rules define the validation logic for the field.