import React from 'react';
import type { FieldComponentProps } from '../../types';

interface RadioGroupProps extends FieldComponentProps {
  fieldMetadata: any;
}

/**
 * RadioGroup component for RadioButtonSetField
 * Allows single option selection from a list of predefined values
 */
const RadioGroup: React.FC<RadioGroupProps> = ({
  value,
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
  const selectedValue = value || '';

  const handleOptionChange = (optionValue: string) => {
    if (readOnly || disabled) return;
    onChange(optionValue);
  };

  const clearSelection = () => {
    if (readOnly || disabled) return;
    onChange('');
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
        {/* Clear selection button */}
        {selectedValue && !readOnly && !disabled && (
          <div className="pb-2 border-b border-gray-200">
            <button
              type="button"
              onClick={clearSelection}
              className="text-xs px-2 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200"
            >
              Clear Selection
            </button>
          </div>
        )}

        {/* Radio options */}
        <div className="space-y-1">
          {options.length === 0 ? (
            <p className="text-sm text-gray-500 italic">
              {placeholder || 'No options available'}
            </p>
          ) : (
            options.map((option: any) => {
              const isChecked = selectedValue === option.value;
              return (
                <label
                  key={option.value}
                  className={`flex items-center space-x-2 p-2 rounded hover:bg-gray-50 cursor-pointer ${disabled || readOnly ? 'cursor-not-allowed opacity-50' : ''}`}
                >
                  <input
                    type="radio"
                    name={`radio-${fieldMetadata?.name || 'field'}`}
                    value={option.value}
                    checked={isChecked}
                    onChange={() => handleOptionChange(option.value)}
                    disabled={disabled || readOnly}
                    className="text-blue-600 focus:ring-blue-500"
                  />
                  <span className="text-sm text-gray-700">
                    {option.label || option.value}
                  </span>
                </label>
              );
            })
          )}
        </div>

        {/* Selected indicator */}
        {selectedValue && (
          <div className="pt-2 border-t border-gray-200">
            <p className="text-xs text-gray-500">
              Selected: {options.find((opt: any) => opt.value === selectedValue)?.label || selectedValue}
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

export default RadioGroup;
