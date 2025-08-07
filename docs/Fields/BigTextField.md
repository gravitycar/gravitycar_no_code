# Big Text Field

## Overview
The Text Field is a basic input field used to capture text data from users. 
It is commonly used for fields such as names, titles, descriptions, and other textual information. 
The Text Field can be configured with various properties to control its behavior and appearance in the user interface.

## Properties, their default values and descriptions
- `name`: '' - The name of the field, used to identify it in the database and in the UI. When instantiating a BigText Field, the 'name' value must be present in the metadata passed in from the model or relationship and cannot be empty.
- `label`: '' - The label displayed in the UI for the field. Default is the same as the `name` value.
- `type`: 'BigText' - The type of the field, which is 'BigText' for this field type.
- `required`: false - A boolean indicating whether the field is required.
- `maxLength`: 16000 - The maximum length of the text input.
- `ValidationRules`: [] - An array of strings that map to subclasses of the ValidationRuleBase class. 
  These rules define the validation logic for the field. Default is an empty array.
