import React from 'react';
import type { FieldComponentProps } from '../../types';

/**
 * Email input component for EmailField
 */
const EmailInput: React.FC<FieldComponentProps> = ({
  value,
  onChange,
  error,
  disabled = false,
  readOnly = false,
  required = false,
  fieldMetadata,
  placeholder,
  label
}) => {
  const displayLabel = label || fieldMetadata?.label || fieldMetadata?.name;
  const displayPlaceholder = placeholder || fieldMetadata?.placeholder || 'Enter email address';

  // If readOnly, render as a styled read-only display
  if (readOnly) {
    return (
      <div className="mb-4">
        {displayLabel && (
          <label className="block text-sm font-medium text-gray-700 mb-2">
            {displayLabel}
            {required && <span className="text-red-500 ml-1">*</span>}
          </label>
        )}
        
        <div className={`
          w-full px-3 py-2 border rounded-md shadow-sm bg-gray-50 text-gray-700
          ${error ? 'border-red-500' : 'border-gray-300'}
        `}>
          {value || '-'}
        </div>
        
        {error && (
          <p className="mt-1 text-sm text-red-600">{error}</p>
        )}
        
        {fieldMetadata?.help_text && !error && (
          <p className="mt-1 text-sm text-gray-500">{fieldMetadata.help_text}</p>
        )}
      </div>
    );
  }

  return (
    <div className="mb-4">
      {displayLabel && (
        <label className="block text-sm font-medium text-gray-700 mb-2">
          {displayLabel}
          {required && <span className="text-red-500 ml-1">*</span>}
        </label>
      )}
      
      <input
        type="email"
        value={value || ''}
        onChange={(e) => onChange(e.target.value)}
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

export default EmailInput;
