# DateTime Field

## Overview
The DateTime type field is used to capture date and time values from users or other processes in the framework.
DateTime values are always sent from the UI in the user's time zone (see the user_timezone field in the users model).
DateTime values are always converted FROM the user's time zone TO UTC before being stored in the database.
When retrieving DateTime values from the database, they are converted FROM UTC TO the user's time zone before being sent to the UI.
The UI should send date values in the format 'YYYY-MM-DD HH:MM:SS' (e.g., '2023-10-01 14:15:00').

## Properties, their default values and descriptions
- `name`: '' - The name of the field, used to identify it in the database and in the UI. When instantiating a DateTime Field, the 'name' value must be present in the metadata passed in from the model or relationship and cannot be empty.
- `label`: '' - The label displayed in the UI for the field. Default is the same as the `name` value.
- `type`: 'DateTime' - The type of the field, which is 'DateTime' for this field type.
- `required`: false - A boolean indicating whether the field is required. Default is `false`.
- `maxLength`: 19 - The maximum length of the DateTime input.
- `ValidationRules`: ['DateTime'] - An array of strings that map to subclasses of the ValidationRuleBase class.
  These rules define the validation logic for the field.