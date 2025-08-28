import React from 'react';
import type { FieldComponentProps } from '../../types';

/**
 * Number input component for IntegerField and FloatField
 */
const NumberInput: React.FC<FieldComponentProps> = ({
  value,
  onChange,
  error,
  disabled = false,
  required = false,
  fieldMetadata,
  placeholder,
  label
}) => {
  const displayLabel = label || fieldMetadata?.label || fieldMetadata?.name;
  const displayPlaceholder = placeholder || fieldMetadata?.placeholder || `Enter ${displayLabel?.toLowerCase()}`;
  
  // Determine if this should be an integer or float field
  const isInteger = fieldMetadata?.type === 'IntegerField';
  const step = isInteger ? '1' : 'any';

  return (
    <div className="mb-4">
      {displayLabel && (
        <label className="block text-sm font-medium text-gray-700 mb-2">
          {displayLabel}
          {required && <span className="text-red-500 ml-1">*</span>}
        </label>
      )}
      
      <input
        type="number"
        step={step}
        value={value || ''}
        onChange={(e) => {
          const val = e.target.value;
          if (val === '') {
            onChange(null);
          } else {
            onChange(isInteger ? parseInt(val, 10) : parseFloat(val));
          }
        }}
        placeholder={displayPlaceholder}
        disabled={disabled}
        required={required}
        className={`
          w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
          ${error ? 'border-red-500' : 'border-gray-300'}
          ${disabled ? 'bg-gray-100 cursor-not-allowed' : 'bg-white'}
        `}
      />
      
      {error && (
        <p className="mt-1 text-sm text-red-600">{error}</p>
      )}
      
      {fieldMetadata?.help_text && !error && (
        <p className="mt-1 text-sm text-gray-500">{fieldMetadata.help_text}</p>
      )}
    </div>
  );
};

export default NumberInput;
