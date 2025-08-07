# Component Generator Factory

## Overview
The ComponentGeneratorFactory class is responsible for creating instances of component 
generator classes based on a provied FieldBase subclass. It provides a centralized way to instantiate 
the appropriate component generator for each FieldBase subclass, ensuring that the correct logic 
is applied for generating React components.

## Properties, their default values and descriptions
- `field`: null - The field object that this component generator factory is associated with. This is the field that will be used to generate the component.
- `componentGenerators`: [] - An associative array mapping field types to their corresponding component generator classes. This array is used to determine which component generator class to instantiate based on the field type.
- `defaultComponentGenerator`: 'TextFieldComponentGenerator' - The default component generator class to use if no specific generator is found for the field type. This should be a class that extends ComponentGeneratorBase and is suitable for generating a basic text field component.
- `componentGeneratorClass`: null - The class name of the component generator to be instantiated. This is determined based on the field type and the `componentGenerators` array.
- `componentGenerator`: null - The instance of the component generator class that will be used to generate the React components. This is set after the component generator class is determined.
- `model`: null - The model object that this component generator factory is associated with. This is the model that contains the field being used to generate the component.
- `logger`: null - An instance of the Logger class for logging messages related to component generation. This is used to log any errors or important information during the component generation process.

## Methods, their return types and descriptions
- `__construct(): void`
  - Constructor that initializes the component generator factory.
- `getComponentGenerator(FieldsBase $field, ModelBase $model = null): ComponentGeneratorBase`
  - Returns an instance of the component generator class associated with the field.
  - The component generator class is determined by checking the `componentGenerators` array for a matching field type.
  - If no specific generator is found, it defaults to the `defaultComponentGenerator`. The name of the model and the field should be logged for debugging purposes with a helful error message.
  - The method then sets the `componentGenerator` property to the instantiated object and returns it.
- `getField(): FieldsBase`
  - Returns the field object that this component generator factory is associated with.
  - This method is used to access the field properties and methods in the component generation process.
- `getModel(): ModelBase`
  - Returns the model object that this component generator factory is associated with.
  - This method is used to access the model properties and methods in the component generation process.