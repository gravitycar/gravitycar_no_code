# Date Field

## Overview
The Date type field is used to capture date values from users or other processes in the framework.
Date values are always sent from the UI in the user's time zone (see the user_timezone field in the users model).
Date values are always converted FROM the user's time zone TO UTC before being stored in the database.
When retrieving date values from the database, they are converted FROM UTC TO the user's time zone before being sent to the UI.
The UI should send date values in the format 'YYYY-MM-DD' (e.g., '2023-10-01').

## Properties, their default values and descriptions
- `name`: '' - The name of the field, used to identify it in the database and in the UI. When instantiating a Date Field, the 'name' value must be present in the metadata passed in from the model or relationship and cannot be empty.
- `label`: '' - The label displayed in the UI for the field. Default is the same as the `name` value.
- `type`: 'Date' - The type of the field, which is 'Date' for this field type.
- `required`: false - A boolean indicating whether the field is required. 
- `maxLength`: 10 - The maximum length of the date input.
- `ValidationRules`: ['Date'] - An array of strings that map to subclasses of the ValidationRuleBase class.
  These rules define the validation logic for the field.