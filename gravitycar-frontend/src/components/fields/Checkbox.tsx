import React from 'react';
import type { FieldComponentProps } from '../../types';

/**
 * Checkbox component for BooleanField
 */
const Checkbox: React.FC<FieldComponentProps> = ({
  value,
  onChange,
  error,
  disabled = false,
  required = false,
  fieldMetadata,
  label
}) => {
  const displayLabel = label || fieldMetadata?.label || fieldMetadata?.name;
  const isChecked = Boolean(value);

  return (
    <div className="mb-4">
      <div className="flex items-center">
        <input
          type="checkbox"
          checked={isChecked}
          onChange={(e) => onChange(e.target.checked)}
          disabled={disabled}
          required={required}
          className={`
            h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded
            ${error ? 'border-red-500' : 'border-gray-300'}
            ${disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'}
          `}
        />
        
        {displayLabel && (
          <label className={`ml-2 block text-sm text-gray-900 ${disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'}`}>
            {displayLabel}
            {required && <span className="text-red-500 ml-1">*</span>}
          </label>
        )}
      </div>
      
      {error && (
        <p className="mt-1 text-sm text-red-600">{error}</p>
      )}
      
      {fieldMetadata?.help_text && !error && (
        <p className="mt-1 text-sm text-gray-500">{fieldMetadata.help_text}</p>
      )}
    </div>
  );
};

export default Checkbox;
