/* eslint-disable @typescript-eslint/no-explicit-any */
import React from 'react';
import type { FieldComponentProps } from '../../types';

interface MultiSelectProps extends FieldComponentProps {
  fieldMetadata: any;
}

/**
 * MultiSelect component for MultiEnumField
 * Allows multiple option selection from a list of predefined values
 */
const MultiSelect: React.FC<MultiSelectProps> = ({
  value = [],
  onChange,
  error,
  disabled = false,
  readOnly = false,
  required = false,
  fieldMetadata,
  label,
  placeholder
}) => {
  const options = fieldMetadata?.options || [];
  const selectedValues = Array.isArray(value) ? value : [];

  const handleOptionChange = (optionValue: string, checked: boolean) => {
    if (readOnly || disabled) return;

    let newValues;
    if (checked) {
      // Add option to selection
      newValues = [...selectedValues, optionValue];
    } else {
      // Remove option from selection
      newValues = selectedValues.filter(v => v !== optionValue);
    }
    
    onChange(newValues);
  };

  const selectAll = () => {
    if (readOnly || disabled) return;
    const allValues = options.map((option: any) => option.value);
    onChange(allValues);
  };

  const clearAll = () => {
    if (readOnly || disabled) return;
    onChange([]);
  };

  return (
    <div className="space-y-2">
      {label && (
        <label className={`block text-sm font-medium text-gray-700 ${required ? 'required' : ''}`}>
          {label}
          {required && <span className="text-red-500 ml-1">*</span>}
        </label>
      )}
      
      <div className={`border rounded-md p-3 space-y-2 ${error ? 'border-red-500' : 'border-gray-300'} ${disabled ? 'bg-gray-50' : 'bg-white'}`}>
        {/* Select All / Clear All buttons */}
        {options.length > 0 && !readOnly && !disabled && (
          <div className="flex gap-2 pb-2 border-b border-gray-200">
            <button
              type="button"
              onClick={selectAll}
              className="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200"
            >
              Select All
            </button>
            <button
              type="button"
              onClick={clearAll}
              className="text-xs px-2 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200"
            >
              Clear All
            </button>
          </div>
        )}

        {/* Options list */}
        <div className="space-y-1 max-h-48 overflow-y-auto">
          {options.length === 0 ? (
            <p className="text-sm text-gray-500 italic">
              {placeholder || 'No options available'}
            </p>
          ) : (
            options.map((option: any) => {
              const isChecked = selectedValues.includes(option.value);
              return (
                <label
                  key={option.value}
                  className={`flex items-center space-x-2 p-2 rounded hover:bg-gray-50 cursor-pointer ${disabled || readOnly ? 'cursor-not-allowed opacity-50' : ''}`}
                >
                  <input
                    type="checkbox"
                    checked={isChecked}
                    onChange={(e) => handleOptionChange(option.value, e.target.checked)}
                    disabled={disabled || readOnly}
                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                  <span className="text-sm text-gray-700">
                    {option.label || option.value}
                  </span>
                </label>
              );
            })
          )}
        </div>

        {/* Selected count */}
        {selectedValues.length > 0 && (
          <div className="pt-2 border-t border-gray-200">
            <p className="text-xs text-gray-500">
              {selectedValues.length} item{selectedValues.length !== 1 ? 's' : ''} selected
            </p>
          </div>
        )}
      </div>

      {error && (
        <p className="text-sm text-red-600">{error}</p>
      )}
    </div>
  );
};

export default MultiSelect;
