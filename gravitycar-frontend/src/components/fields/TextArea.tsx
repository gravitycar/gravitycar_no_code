import React from 'react';
import type { FieldComponentProps } from '../../types';

/**
 * Multi-line text area component for BigTextField
 */
const TextArea: React.FC<FieldComponentProps> = ({
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

  return (
    <div className="mb-4">
      {displayLabel && (
        <label className="block text-sm font-medium text-gray-700 mb-2">
          {displayLabel}
          {required && <span className="text-red-500 ml-1">*</span>}
        </label>
      )}
      
      <textarea
        value={value || ''}
        onChange={(e) => onChange(e.target.value)}
        placeholder={displayPlaceholder}
        disabled={disabled}
        required={required}
        maxLength={fieldMetadata?.max_length}
        rows={4}
        className={`
          w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-vertical
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

export default TextArea;
