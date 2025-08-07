# Float Field

## Overview
The Float Field type is used to capture and store decimal number values.
This field type handles both positive and negative floating-point numbers based on configuration.
The field stores float values in the database and displays them as number inputs in the UI.
This field type validates that the provided value is a valid decimal number within specified bounds.
The field supports minimum and maximum value constraints, precision settings, and optional step increments for UI controls.
This field type automatically converts string representations of decimal numbers to floats during validation.

## UI
- The Float Field will be rendered as a number input field with decimal support in the user interface.
- The input field includes increment/decrement buttons (spinners) for easy value adjustment with configurable step values.
- The field supports keyboard input with automatic validation on blur or form submission.
- Invalid entries (non-numeric characters, values outside range) are highlighted with error messages.
- The field can display helpful text showing the allowed range and precision (e.g., "Enter a decimal number between 0.0 and 100.5").
- For large ranges, the field may include a slider control as an alternative input method.
- The field automatically formats numbers with appropriate locale-specific decimal separators when displayed.
- Placeholder text can show example values or formatting hints to guide user input.

## Properties, their default values and descriptions
- `name`: '' - The name of the field, used to identify it in the database and in the UI. When instantiating a Float Field, the 'name' value must be present in the metadata passed in from the model or relationship and cannot be empty.
- `label`: '' - The label displayed in the UI for the field. Default is the same as the `name` value.
- `type`: 'Float' - The type of the field, which is 'Float' for this field type.
- `required`: false - A boolean indicating whether the field is required (user must provide a value).
- `defaultValue`: null - The default value for the field. Must be a float or null.
- `minValue`: null - The minimum allowed value for the field. If null, no minimum constraint is applied.
- `maxValue`: null - The maximum allowed value for the field. If null, no maximum constraint is applied.
- `allowNegative`: true - Whether negative numbers are allowed. If false, only zero and positive floats are accepted.
- `precision`: 2 - The number of decimal places to display and store. Values will be rounded to this precision.
- `step`: 0.01 - The increment/decrement step for UI spinner controls and validation.
- `placeholder`: 'Enter a decimal number' - Placeholder text displayed in the input field.
- `showSpinners`: true - Whether to display increment/decrement buttons in the UI.
- `formatDisplay`: false - Whether to format the displayed number with locale-specific separators (e.g., commas, decimal points).
- `ValidationRules`: ['Float', 'Range'] - An array of strings that map to subclasses of the ValidationRuleBase class. These rules define the validation logic for the field.