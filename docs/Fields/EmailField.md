# Email Field

## Overview
The Email Field type is used to capture and store email address values.
This field type extends the functionality of a text field but restricts input to valid email address formats.
The field stores email addresses as strings in the database and validates them against standard email format rules.
This field type automatically normalizes email addresses by converting them to lowercase for consistency.
The field supports standard email validation including domain validation and character restrictions.
This field type is designed specifically for email addresses and should not be used for general text input.

## UI
- The Email Field will be rendered as a text input field with email-specific validation in the user interface.
- The input field includes the HTML input type="email" for browser-level validation and mobile keyboard optimization.
- Invalid email formats are highlighted with error messages indicating the specific validation failure.
- The field automatically converts input to lowercase to ensure consistency in email storage.
- Email addresses are validated in real-time as the user types, with immediate feedback on format errors.
- The field supports autocomplete functionality for previously entered email addresses.
- In list views, email addresses may be displayed as clickable mailto links for easy contact.
- The field includes helpful placeholder text showing expected email format (e.g., "user@example.com").

## Properties, their default values and descriptions
- `name`: '' - The name of the field, used to identify it in the database and in the UI. When instantiating an Email Field, the 'name' value must be present in the metadata passed in from the model or relationship and cannot be empty.
- `label`: '' - The label displayed in the UI for the field. Default is the same as the `name` value.
- `type`: 'Email' - The type of the field, which is 'Email' for this field type.
- `required`: false - A boolean indicating whether the field is required (user must provide an email address).
- `maxLength`: 254 - The maximum length of the email address string (RFC 5321 standard maximum).
- `defaultValue`: '' - The default value for the field. Must be a valid email address or empty string.
- `placeholder`: 'Enter email address' - Placeholder text displayed in the input field.
- `normalize`: true - Whether to automatically convert email addresses to lowercase for consistency.
- `allowMailtoLink`: true - Whether to display the email as a clickable mailto link in list views.
- `domainValidation`: true - Whether to perform basic domain validation (checks for valid domain format).
- `autocomplete`: 'email' - HTML autocomplete attribute value for browser autofill functionality.
- `ValidationRules`: ['Email', 'MaxLength'] - An array of strings that map to subclasses of the ValidationRuleBase class. These rules define the validation logic for the field.