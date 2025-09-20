/* eslint-disable @typescript-eslint/no-explicit-any */
import React from 'react';
import type { FieldComponentProps } from '../../types';

/**
 * Date picker component for DateField
 */
const DatePicker: React.FC<FieldComponentProps> = ({
  value,
  onChange,
  error,
  disabled = false,
  required = false,
  fieldMetadata,
  label
}) => {
  const displayLabel = label || fieldMetadata?.label || fieldMetadata?.name;

  // Convert value to YYYY-MM-DD format for input[type="date"]
  const formatDateForInput = (dateValue: any): string => {
    if (!dateValue) return '';
    
    // If it's already a string in YYYY-MM-DD format, use it
    if (typeof dateValue === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(dateValue)) {
      return dateValue;
    }
    
    // Try to parse it as a Date
    try {
      const date = new Date(dateValue);
      if (!isNaN(date.getTime())) {
        return date.toISOString().split('T')[0];
      }
    } catch {
      // Ignore parsing errors
    }
    
    return '';
  };

  const handleDateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const dateValue = e.target.value;
    if (dateValue === '') {
      onChange(null);
    } else {
      onChange(dateValue); // Keep as YYYY-MM-DD string format
    }
  };

  return (
    <div className="mb-4">
      {displayLabel && (
        <label className="block text-sm font-medium text-gray-700 mb-2">
          {displayLabel}
          {required && <span className="text-red-500 ml-1">*</span>}
        </label>
      )}
      
      <input
        type="date"
        value={formatDateForInput(value)}
        onChange={handleDateChange}
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

export default DatePicker;
