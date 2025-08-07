# ValidationRuleFactory

## Overview
This factory is used to create validation rules for the Gravitycar framework. It provides a centralized method to consistently instantiate validation rule objects.

## Properties, their default values and descriptions
- `field`: null - The field object that this validation rule factory is associated with. This is the field that will be validated.
- `model`: null - The model object that this validation rule factory is associated with. This is the model that contains the field being validated.
- 
## Methods, their return types and descriptions
- `__construct(): void`
  - Constructor that initializes the validation rule factory.
- `getValidationRule(string $ruleName, FieldBase $field, ModelBase $model = null): ValidationRuleBase`
  - The rule name is expected to be a partial match for a ValidationRuleBase subclass class name. For example, you can expect 'Email' as the `ruleName`. But the class name would be 'EmailValidation' and would match the naming convention used in the framework, typically following the pattern `ValidationRules\{RuleName}Validation`.
  - If the rule does not exist, it will throw a GCException describing the error.
  - If the rule exists, it will instantiate the rule class, set the field and model properties, and return the instance.
  - Returns an instance of the validation rule class associated with the provided rule name.