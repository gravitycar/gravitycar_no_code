# Password Field

## Overview
The Password Field type is used to capture and store password or other sensitive text data that should remain hidden.
This field type handles secure input with masked characters and optional visibility toggle functionality.
The field stores the password value as a string in the database, typically after hashing for security.
This field type validates password strength and format requirements based on configured validation rules.
The field supports both hidden input (default) and optional plain text viewing when the user explicitly requests it.
This field type is specifically designed for sensitive data that should not be displayed in plain text by default.

## UI
- The Password Field will be rendered as a password input field with masked characters (dots or asterisks) in the user interface.
- An optional "Show" button (eye icon) can be displayed next to the input field to toggle visibility between masked and plain text.
- When the show button is clicked, the input type changes to text and the password becomes visible until toggled back.
- The show button text/icon changes between "Show" and "Hide" states to indicate current visibility.
- The field includes standard password input behaviors like preventing copy/paste in some browsers when masked.
- Password strength indicators may be displayed below the input field when validation rules include strength requirements.
- The field supports autocomplete attributes for password managers when appropriate.
- In list views and read-only displays, the field shows placeholder text (e.g., "••••••••") instead of the actual value.
- When editing a user's password, the field should not pre-fill with the existing password for security reasons.

## Properties, their default values and descriptions
- `name`: '' - The name of the field, used to identify it in the database and in the UI. When instantiating a Password Field, the 'name' value must be present in the metadata passed in from the model or relationship and cannot be empty.
- `label`: '' - The label displayed in the UI for the field. Default is the same as the `name` value.
- `type`: 'Password' - The type of the field, which is 'Password' for this field type.
- `required`: true - A boolean indicating whether the field is required (user must provide a password).
- `maxLength`: 100 - The maximum length of the password string.
- `minLength`: 8 - The minimum length required for the password.
- `showButton`: true - Whether to display the show/hide toggle button next to the input field.
- `showButtonText`: 'Show' - The text displayed on the show button when password is hidden.
- `hideButtonText`: 'Hide' - The text displayed on the show button when password is visible.
- `placeholder`: 'Enter password' - Placeholder text for the input field.
- `autocomplete`: 'current-password' - HTML autocomplete attribute value for password managers.
- `maskCharacter`: '•' - The character used to mask the password in read-only displays.
- `hashOnSave`: true - Whether to automatically hash the password before saving to database.
- `ValidationRules`: ['Password', 'MinLength', 'MaxLength'] - An array of strings that map to subclasses of the ValidationRuleBase class. These rules define the validation logic for the field.