# Boolean Field

## Overview
The Boolean Field type is used to capture true/false values.
This field type represents a simple binary state and is commonly used for flags, settings, and yes/no questions.
The field stores boolean values in the database and displays them as checkboxes, toggles, or radio buttons in the UI.
This field type only accepts true or false values (or their string/numeric equivalents like "1"/"0", "yes"/"no").

## UI
- The Boolean Field will be rendered as a checkbox, toggle switch, or radio button group in the user interface.
- The default UI component is a checkbox that can be checked (true) or unchecked (false).
- For better UX, the field can also be displayed as a toggle switch with customizable labels (e.g., "Yes/No", "On/Off", "Enabled/Disabled").
- The field label will be displayed next to the input control.
- When the field is required, the user must explicitly select a value (true or false).
- When the field is not required, it can have a default state or remain unset until the user interacts with it.

## Properties, their default values and descriptions
- `name`: '' - The name of the field, used to identify it in the database and in the UI. When instantiating a Boolean Field, the 'name' value must be present in the metadata passed in from the model or relationship and cannot be empty.
- `label`: '' - The label displayed in the UI for the field. Default is the same as the `name` value.
- `type`: 'Boolean' - The type of the field, which is 'Boolean' for this field type.
- `required`: false - A boolean indicating whether the field is required (user must explicitly select true or false).
- `defaultValue`: null - The default value for the field. Can be true, false, or null (unset).
- `trueLabel`: 'Yes' - The label displayed for the true state in the UI (e.g., "Yes", "On", "Enabled").
- `falseLabel`: 'No' - The label displayed for the false state in the UI (e.g., "No", "Off", "Disabled").
- `displayAs`: 'checkbox' - How the field is displayed in the UI. Options: 'checkbox', 'toggle', 'radio'.
- `ValidationRules`: ['Boolean'] - An array of strings that map to subclasses of the ValidationRuleBase class. These rules define the validation logic for the field.