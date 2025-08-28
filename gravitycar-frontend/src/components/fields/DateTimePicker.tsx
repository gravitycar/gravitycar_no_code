import React from 'react';
import type { FieldComponentProps } from '../../types';

/**
 * Date-time picker component for DateTimeField
 */
const DateTimePicker: React.FC<FieldComponentProps> = ({
  value,
  onChange,
  error,
  disabled = false,
  readOnly = false,
  required = false,
  fieldMetadata,
  label
}) => {
  const displayLabel = label || fieldMetadata?.label || fieldMetadata?.name;

  // Convert value to YYYY-MM-DDTHH:mm format for input[type="datetime-local"]
  const formatDateTimeForInput = (dateTimeValue: any): string => {
    if (!dateTimeValue) return '';
    
    // If it's already a string in the correct format, use it
    if (typeof dateTimeValue === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(dateTimeValue)) {
      return dateTimeValue.slice(0, 16); // Keep only YYYY-MM-DDTHH:mm
    }
    
    // Try to parse it as a Date
    try {
      const date = new Date(dateTimeValue);
      if (!isNaN(date.getTime())) {
        // Convert to local datetime string format
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
      }
    } catch (e) {
      // Ignore parsing errors
    }
    
    return '';
  };

  const handleDateTimeChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const dateTimeValue = e.target.value;
    if (dateTimeValue === '') {
      onChange(null);
    } else {
      // Convert to ISO string for backend compatibility
      const date = new Date(dateTimeValue);
      onChange(date.toISOString());
    }
  };

  // Format display value for read-only mode
  const formatDisplayValue = (dateTimeValue: any): string => {
    if (!dateTimeValue) return '-';
    
    try {
      const date = new Date(dateTimeValue);
      if (!isNaN(date.getTime())) {
        return date.toLocaleString();
      }
    } catch (e) {
      // Ignore parsing errors
    }
    
    return String(dateTimeValue);
  };

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
          {formatDisplayValue(value)}
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
        type="datetime-local"
        value={formatDateTimeForInput(value)}
        onChange={handleDateTimeChange}
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

export default DateTimePicker;
