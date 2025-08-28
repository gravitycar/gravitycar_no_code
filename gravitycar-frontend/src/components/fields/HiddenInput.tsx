import React from 'react';
import type { FieldComponentProps } from '../../types';

/**
 * Hidden input component for IDField and other hidden fields
 */
const HiddenInput: React.FC<FieldComponentProps> = ({
  value,
  onChange,
  fieldMetadata
}) => {
  return (
    <input
      type="hidden"
      value={value || ''}
      onChange={(e) => onChange(e.target.value)}
      name={fieldMetadata?.name}
    />
  );
};

export default HiddenInput;
