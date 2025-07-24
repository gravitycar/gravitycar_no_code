# ValidationRuleBase

## Overview
The ValidationRuleBase class is the base class for all validation rules in the Gravitycar framework.
It provides the common properties and methods that all validation rule types must implement. This class is used to define the structure and behavior of validation rules that are applied to fields in models and relationships.

Validation rules are instantiated by the FieldsBase class when setting up field validation through the `setupValidationRules` method. Each validation rule class name follows the pattern `ValidationRules\{RuleName}Validation`, where `{RuleName}` is the partial class name provided in the field definition.

This class is designed to be extended by specific validation rule types, such as RequiredValidation, EmailValidation, MinLengthValidation, etc. It provides a common interface for all validation rule types, allowing them to be used consistently across different field types.

The validation system supports both simple validation rules (like required, email format) and complex validation rules that may require additional parameters or context from the model being validated.

The validation rules defined by subclasses of ValidationRuleBase are intended to be used on the server side and the client side. So the rules must include a way to provided their validation logic to the UI so validation can be performed in the browser before the form is submitted to the server.

Validation rules should be run on the client and the server, to provide consistent validation behavior. The client-side validation should be performed using JavaScript, while the server-side validation should be performed using PHP.

Validation rule error messages will not be internationalized. This application only supports English at this time.

## Properties, their default values and descriptions
- `name`: '' - Validation rule name (required, cannot be empty)
- `field`: null - The field object that this validation rule is associated with. This is the field that will be validated using this rule.
- `model`: null - The model object that this validation rule is associated with. This is the model that contains the field being validated.
- `errorMessage`: '' - Default error message when validation fails. This string should include tokens as placeholders for the name of the field and the value that failed validation, where appropriate.
- `value`: null - The value to be validated. This is the value that will be passed to the `validate` method.
- `isEnabled`: true - Whether this validation rule is currently active
- `priority`: 0 - Execution priority (lower numbers execute first)
- `stopOnFailure`: false - Whether to stop validation chain if this rule fails
- `skipIfEmpty`: false - Whether to skip validation if field value is empty
- `contextSensitive`: false - Whether rule needs access to the full model context
- `conditionalRules`: [] - Array of conditions that determine when this rule applies
- `metadata`: [] - Additional metadata for rule configuration
- `logger`: null - Logger instance for logging messages related to this validation rule

## Methods, their return types and descriptions
- `__construct(): void`
  - Constructor that initializes the ValidationRuleBase subclass.

- `validate(mixed $value): bool`
  - Primary validation method that checks if the provided value is valid according to this rule.
  - This method's logic is where the actual validation logic for the rule is implemented.
  - Returns true if validation passes, false if it fails.

- `setValue(mixed $value): void`
  - Sets the `value` property to the provided value.
  - This method is used to set the value that will be validated by this rule.
  - It should be called before calling the `validate` method.

- `setField(FieldsBase $field): void`
  - Sets the `field` property to the provided field object.
  - This method is used to associate the validation rule with a specific field.
  - It should be called before calling the `validate` method.

- `setModel(ModelBase $model): void`
  - Sets the `model` property to the provided model object.
  - This method is used to associate the validation rule with a specific model.
  - It should be called before calling the `validate` method.
  - The `$model` parameter provides access to the full model context for context-sensitive validation.

- `getErrorMessage(): string`
  - Returns the current error message for this validation rule.
  - The error message should include tokens as placeholders for the name of the field and the value that failed validation, where appropriate.
  - Other tokens may be added to error messages as needed, such as `{min}`, `{max}`, etc., depending on the validation rule.
  - Formatting the error message is done by the `formatErrorMessage` method, which replaces tokens with actual values.

- `getFormatErrorMessage(): string`
  - Formats an error message template by replacing placeholders with actual values.
  - This method calls getErrorMessage() to retrieve the error message template.
  - It replaces placeholders like `{fieldName}` and `{value}` with the actual field name and value that failed validation.
  - Other placeholders can be added as needed, but the actual values for those placeholders must be in available in this class, either as direct properties or as properties/methods on properties of this class, like field or model.
  - Returns the formatted error message with placeholders replaced.

- `isApplicable(mixed $value, FieldsBase $field, ModelBase $model = null): bool`
  - Determines whether this validation rule should be applied to the given value and context.
  - This method evaluates any conditional rules to determine if the validation should run.
  - Returns true if the rule should be applied, false if it should be skipped.
  - The default implementation checks the `skipIfEmpty` property and conditional rules.

- `getJavascriptValidation(): string`
  - Returns the JavaScript validation logic for this rule.
  - This logic should be the same as the validation logic implemented in the `validate` method.
  - This method should return a string containing the JavaScript code that implements the validation logic for this rule.
  - The returned code should be compatible with the client-side validation framework used in the UI.
  - If the rule does not have any client-side validation logic, it can return an empty string or null.