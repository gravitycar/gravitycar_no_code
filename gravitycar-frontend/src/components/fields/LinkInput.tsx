import React from 'react';
import type { FieldComponentProps } from '../../types';

/**
 * URL input component for LinkField.
 * Edit mode: renders <input type="url"> with label and validation error display.
 * Read-only mode: renders an <a> anchor link (or a dash when empty).
 * The anchor target attribute is read from fieldMetadata.target (default: '_blank').
 */
const LinkInput: React.FC<FieldComponentProps> = ({
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
  const displayPlaceholder = placeholder || fieldMetadata?.placeholder || 'https://...';

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
          w-full px-3 py-2 border rounded-md shadow-sm bg-gray-50
          ${error ? 'border-red-500' : 'border-gray-300'}
        `}>
          {value ? (
            <a
              href={value}
              target={fieldMetadata?.target ?? '_blank'}
              rel="noopener noreferrer"
              className="text-blue-600 hover:text-blue-800 break-all underline"
            >
              {value}
            </a>
          ) : (
            <span className="text-gray-400">-</span>
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
        type="url"
        value={value || ''}
        onChange={(e) => onChange(e.target.value)}
        placeholder={displayPlaceholder}
        disabled={disabled}
        required={required}
        maxLength={fieldMetadata?.max_length}
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

export default LinkInput;
