# RelatedRecord Field

## Overview
The RelatedRecord Field type is used to capture a reference to a record in another model.
The metadata for the field must specify a class name and a method name on that class that will return an array of options for the field.
The options for the field are provided by a method on a class, which returns an array of options for the field. Those options should represent records in the related model.
The options list should be formatted as an associative array in this format: 'id_of_related_model' => 'name_of_related_model'.


## UI
- The RelatedRecord Field will be rendered as a dropdown list in the user interface.
- The dropdown will display the options returned by the method specified in the `className` and `methodName` properties.
- The user can select one related record from the dropdown list.
- If the user tries to submit a value that is not in the list of options, an error message will be displayed.
- The UI for the field will always include a search box to search through the options.

## Properties, their default values and descriptions
- `name`: '' - The name of the field, used to identify it in the database and in the UI. When instantiating an Enum Field, the 'name' value must be present in the metadata passed in from the model or relationship and cannot be empty.
- `label`: '' - The label displayed in the UI for the field. Default is the same as the `name` value.
- `type`: 'RelatedRecord' - The type of the field, which is 'RelatedRecord' for this field type.
- `required`: false - A boolean indicating whether the field is required.
- `relatedModel`: '' - The name of the related model that this field references. This model must be defined in the metadata.
- `maxLength`: 36 - The maximum length of the RelatedRecord input. Since the related record field stores the ID of other models, the maximum length is set to 36 characters to accommodate UUIDs.
- `className`: '' - The name of the class that contains the method to retrieve the options for the field. This class must be defined in the metadata.
- `methodName`: '' - The name of the method in the class specified by `className` that returns the options for the field. This method must return an associative array in the format: 'value_to_store_in_db' => 'value_to_display_in_ui'.
- `ValidationRules`: ['InOptions'] - An array of strings that map to subclasses of the ValidationRuleBase class.
  These rules define the validation logic for the field. Default is an empty array.