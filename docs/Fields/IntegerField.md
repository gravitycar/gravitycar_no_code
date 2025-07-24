# Integer Field

## Overview
The Integer Field type is used to capture and store whole number values.
This field type handles both positive and negative integers based on configuration.
The field stores integer values in the database and displays them as number inputs in the UI.
This field type validates that the provided value is a valid integer within specified bounds.
The field supports minimum and maximum value constraints and optional step increments for UI controls.
This field type automatically converts string representations of numbers to integers during validation.

## UI
- The Integer Field will be rendered as a number input field in the user interface.
- The input field includes increment/decrement buttons (spinners) for easy value adjustment.
- The field supports keyboard input with automatic validation on blur or form submission.
- Invalid entries (non-numeric characters, decimals) are highlighted with error messages.
- The field can display helpful text showing the allowed range (e.g., "Enter a number between 1 and 100").
- For large ranges, the field may include a slider control as an alternative input method.
- The field automatically formats numbers with appropriate locale-specific separators when displayed.
- Placeholder text can show example values or formatting hints to guide user input.

## Properties, their default values and descriptions
- `name`: '' - The name of the field, used to identify it in the database and in the UI. When instantiating an Integer Field, the 'name' value must be present in the metadata passed in from the model or relationship and cannot be empty.
- `label`: '' - The label displayed in the UI for the field. Default is the same as the `name` value.
- `type`: 'Integer' - The type of the field, which is 'Integer' for this field type.
- `required`: false - A boolean indicating whether the field is required (user must provide a value).
- `defaultValue`: null - The default value for the field. Must be an integer or null.
- `minValue`: null - The minimum allowed value for the field. If null, no minimum constraint is applied.
- `maxValue`: null - The maximum allowed value for the field. If null, no maximum constraint is applied.
- `allowNegative`: true - Whether negative numbers are allowed. If false, only zero and positive integers are accepted.
- `step`: 1 - The increment/decrement step for UI spinner controls and validation.
- `placeholder`: 'Enter a number' - Placeholder text displayed in the input field.
- `showSpinners`: true - Whether to display increment/decrement buttons in the UI.
- `formatDisplay`: false - Whether to format the displayed number with locale-specific separators (e.g., commas).
- `ValidationRules`: ['Integer', 'Range'] - An array of strings that map to subclasses of the ValidationRuleBase class. These rules define the validation logic for the field.