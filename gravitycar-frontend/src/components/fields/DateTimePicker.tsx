/* eslint-disable @typescript-eslint/no-explicit-any */
import React from 'react';
import type { FieldComponentProps } from '../../types';
import { useAuth } from '../../hooks/useAuth';
import {
  getUserTimezone,
  localDateTimeToUTC,
  formatDateTimeForInput,
  formatDateTimeInTimezone,
} from '../../utils/timezone';

/**
 * Date-time picker component for DateTimeField.
 *
 * Displays UTC values converted to the authenticated user's timezone and
 * converts user-local input back to UTC before calling onChange.
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
  const { user } = useAuth();
  const userTimezone = getUserTimezone(user?.user_timezone);
  const displayLabel = label || fieldMetadata?.label || fieldMetadata?.name;

  /**
   * Convert value to YYYY-MM-DDTHH:mm format for input[type="datetime-local"],
   * interpreting the stored value as UTC and converting to the user's timezone.
   */
  const formatForInput = (dateTimeValue: any): string => {
    if (!dateTimeValue) return '';

    try {
      return formatDateTimeForInput(String(dateTimeValue), userTimezone);
    } catch {
      return '';
    }
  };

  /**
   * Handle datetime-local input changes.  The input value represents the user's
   * local time in their configured timezone -- convert it to UTC before passing
   * it upstream.
   */
  const handleDateTimeChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const dateTimeValue = e.target.value;
    if (dateTimeValue === '') {
      onChange(null);
      return;
    }

    try {
      const utcDate = localDateTimeToUTC(dateTimeValue, userTimezone);
      // Format as Y-m-d H:i:s to match backend DateTimeValidation expectation
      const pad = (n: number) => n.toString().padStart(2, '0');
      const formatted = `${utcDate.getUTCFullYear()}-${pad(utcDate.getUTCMonth() + 1)}-${pad(utcDate.getUTCDate())} ${pad(utcDate.getUTCHours())}:${pad(utcDate.getUTCMinutes())}:${pad(utcDate.getUTCSeconds())}`;
      onChange(formatted);
    } catch {
      // If conversion fails, pass the raw value so the caller can decide
      onChange(dateTimeValue);
    }
  };

  /**
   * Format display value for read-only mode, rendering the UTC date in the
   * user's configured timezone.
   */
  const formatDisplayValue = (dateTimeValue: any): string => {
    if (!dateTimeValue) return '-';

    try {
      return formatDateTimeInTimezone(String(dateTimeValue), userTimezone);
    } catch {
      return String(dateTimeValue);
    }
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
        value={formatForInput(value)}
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
