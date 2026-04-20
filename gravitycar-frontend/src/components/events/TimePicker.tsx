import React from 'react';

export interface TimeValue {
  hour: number;   // 1-12
  minute: number;  // 0-55 in steps of 5
  ampm: 'AM' | 'PM';
}

interface TimePickerProps {
  value: TimeValue;
  onChange: (value: TimeValue) => void;
}

const HOURS = Array.from({ length: 12 }, (_, i) => i + 1);
const MINUTES = Array.from({ length: 12 }, (_, i) => i * 5);

/**
 * Format a TimeValue as a human-readable string like "6:00 PM".
 */
export const formatTimeValue = (t: TimeValue): string => {
  const mm = String(t.minute).padStart(2, '0');
  return `${t.hour}:${mm} ${t.ampm}`;
};

/**
 * Convert a TimeValue to 24-hour format values.
 */
export const to24Hour = (t: TimeValue): { hours: number; minutes: number } => {
  let hours = t.hour;
  if (t.ampm === 'AM' && hours === 12) hours = 0;
  if (t.ampm === 'PM' && hours !== 12) hours += 12;
  return { hours, minutes: t.minute };
};

const TimePicker: React.FC<TimePickerProps> = ({ value, onChange }) => {
  const selectClass =
    'px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white text-sm ' +
    'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500';

  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-2">
        Time for selected dates
      </label>
      <div className="flex gap-2 items-center">
        {/* Hour */}
        <select
          value={value.hour}
          onChange={e => onChange({ ...value, hour: Number(e.target.value) })}
          className={selectClass}
          aria-label="Hour"
        >
          {HOURS.map(h => (
            <option key={h} value={h}>{h}</option>
          ))}
        </select>

        <span className="text-gray-500 font-medium">:</span>

        {/* Minute */}
        <select
          value={value.minute}
          onChange={e => onChange({ ...value, minute: Number(e.target.value) })}
          className={selectClass}
          aria-label="Minute"
        >
          {MINUTES.map(m => (
            <option key={m} value={m}>{String(m).padStart(2, '0')}</option>
          ))}
        </select>

        {/* AM/PM */}
        <select
          value={value.ampm}
          onChange={e => onChange({ ...value, ampm: e.target.value as 'AM' | 'PM' })}
          className={selectClass}
          aria-label="AM or PM"
        >
          <option value="AM">AM</option>
          <option value="PM">PM</option>
        </select>
      </div>
    </div>
  );
};

export default TimePicker;
