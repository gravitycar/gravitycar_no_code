# Component Generator Base

## Overview
The ComponentGeneratorBase class is an abstract base class that provides a foundation for generating React components in the Gravitycar framework.
The data needed to generate the component is provided by the field object that this component generator is associated with.
Specific field types will extend this class to implement the `generateFormComponent` and the `generateListViewComponent` methods, which will return the React component code for that field type.
Differnt field types will contain different types of metadata specific tot that type, and relevant to the component generation process. Component generators should use the field's properties, which are set by the framework's metadata, to generate the appropriate component code.
Child classes may implmement additional methods as needed to support the specific requirements of the field type they are generating components for.

## Properties, their default values and descriptions
`field`: null - The field object that this component generator is associated with. This is the field that will be used to generate the component.
`model`: null - The model object that this component generator is associated with. This is the model that contains the field being used to generate the component.

## Methods, their return types and descriptions
`__construct(FieldsBase $field, ModelBase $model = null): void` - Constructor that initializes the component generator with a field object. This method sets the `field` property to the provided field object, and the `model` property to the provided model object if it is not null. This method prepares the component generator for generating components based on the field's properties.

`generateFormComponent(): string` - returns a string of the React component code for the form field. This method must be implemented by subclasses to generate the specific component code based on the field type.

`generateListViewComponent(): string` - returns a string of the React component code for the list view field. This method must be implemented by subclasses to generate the specific component code based on the field type.

`gererateValidationCode(): string` - returns a string of the validation code for the field. The actual validation javascript code will be returned by the ValidationRulesBase objects using `$this->field->validationRules->getJavascriptValidation()`. This method should concatenate all of the validation code returned by all of the validation rules for this object's field. 

`getField(): FieldsBase` - returns the field object that this component generator is associated with. This method is used to access the field properties and methods in the component generation process.

`getModel(): ModelBase` - returns the model object that this component generator is associated with. This method is used to access the model properties and methods in the component generation process.

`getComponentName(): string` - returns the name of the component to be generated. The name will be the name of the field formatted as a React component name (e.g., `MyFieldName`).