import React from 'react';
import type { FieldComponentProps as BaseFieldComponentProps, FieldMetadata } from '../../types';

// Import field components
import TextInput from './TextInput';
import EmailInput from './EmailInput';
import PasswordInput from './PasswordInput';
import Checkbox from './Checkbox';
import Select from './Select';
import TextArea from './TextArea';
import NumberInput from './NumberInput';
import DatePicker from './DatePicker';
import DateTimePicker from './DateTimePicker';
import HiddenInput from './HiddenInput';
import RelatedRecordSelect from './RelatedRecordSelect';
import { ImageUpload } from './ImageUpload';
import MultiSelect from './MultiSelect';
import RadioGroup from './RadioGroup';

// Component mapping from FieldBase reactComponent names to React components
const componentMap: Record<string, React.ComponentType<BaseFieldComponentProps>> = {
  'TextInput': TextInput,
  'EmailInput': EmailInput,
  'PasswordInput': PasswordInput,
  'Checkbox': Checkbox,
  'Select': Select,
  'TextArea': TextArea,
  'NumberInput': NumberInput,
  'DatePicker': DatePicker,
  'DateTimePicker': DateTimePicker,
  'HiddenInput': HiddenInput,
  'RelatedRecordSelect': RelatedRecordSelect,
  'ImageUpload': ImageUpload,
  'MultiSelect': MultiSelect,
  'RadioGroup': RadioGroup,
};

interface FieldComponentRenderProps {
  field: FieldMetadata;
  value: any;
  onChange: (value: any) => void;
  error?: string;
  disabled?: boolean;
}

/**
 * Dynamic field component renderer that selects the appropriate component
 * based on the field's react_component metadata
 */
const FieldComponent: React.FC<FieldComponentRenderProps> = ({
  field,
  value,
  onChange,
  error,
  disabled = false
}) => {
  // Get the component based on the field's react_component property
  const Component = componentMap[field.react_component] || TextInput;
  
  console.log(`🔧 Rendering field '${field.name}' with component '${field.react_component}'`, {
    fieldType: field.type,
    reactComponent: field.react_component,
    value,
    required: field.required,
    readOnly: field.readOnly
  });

  return (
    <Component
      value={value}
      onChange={onChange}
      error={error}
      disabled={disabled}
      readOnly={field.readOnly || false}
      required={field.required}
      fieldMetadata={field}
      placeholder={field.placeholder}
      label={field.label}
    />
  );
};

export default FieldComponent;
export { componentMap };
