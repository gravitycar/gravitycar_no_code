import React from 'react';
import type { FieldComponentProps } from '../../types';

/**
 * Select dropdown component for EnumField
 */
const Select: React.FC<FieldComponentProps> = ({
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
  const displayPlaceholder = placeholder || `Select ${displayLabel?.toLowerCase()}`;
  
  // Handle different options formats from backend
  let options: Array<{value: string, label: string}> = [];
  
  if (fieldMetadata?.options) {
    if (Array.isArray(fieldMetadata.options)) {
      // Already in array format - convert to consistent format
      options = fieldMetadata.options.map(option => ({
        value: String(option.value),
        label: option.label
      }));
    } else if (typeof fieldMetadata.options === 'object') {
      // Convert object format from backend to array
      options = Object.entries(fieldMetadata.options).map(([key, value]) => ({
        value: key,
        label: String(value)
      }));
    }
  }
  
  // Also check component_props.options for backend format
  if (fieldMetadata?.component_props?.options && options.length === 0) {
    if (typeof fieldMetadata.component_props.options === 'object') {
      options = Object.entries(fieldMetadata.component_props.options).map(([key, value]) => ({
        value: key,
        label: String(value)
      }));
    }
  }

  return (
    <div className="mb-4">
      {displayLabel && (
        <label className="block text-sm font-medium text-gray-700 mb-2">
          {displayLabel}
          {required && <span className="text-red-500 ml-1">*</span>}
        </label>
      )}
      
      <select
        value={value || ''}
        onChange={(e) => onChange(e.target.value)}
        disabled={disabled}
        required={required}
        className={`
          w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
          ${error ? 'border-red-500' : 'border-gray-300'}
          ${disabled ? 'bg-gray-100 cursor-not-allowed' : 'bg-white'}
        `}
      >
        <option value="">{displayPlaceholder}</option>
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      
      {error && (
        <p className="mt-1 text-sm text-red-600">{error}</p>
      )}
      
      {fieldMetadata?.help_text && !error && (
        <p className="mt-1 text-sm text-gray-500">{fieldMetadata.help_text}</p>
      )}
    </div>
  );
};

export default Select;
