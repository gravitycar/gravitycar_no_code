import React, { useMemo } from 'react';

/** A date key in YYYY-MM-DD format used for selection tracking */
type DateKey = string;

interface CalendarGridProps {
  /** Currently displayed year */
  year: number;
  /** Currently displayed month (0-indexed) */
  month: number;
  /** Navigate to previous month */
  onPrevMonth: () => void;
  /** Navigate to next month */
  onNextMonth: () => void;
  /** Set of selected date keys (YYYY-MM-DD) */
  selectedDates: Set<DateKey>;
  /** Toggle a single date's selection */
  onToggleDate: (dateKey: DateKey) => void;
  /** Replace the entire selection set */
  onSetSelection: (dates: Set<DateKey>) => void;
  /** Date keys already proposed for this event, mapped to their time strings */
  existingDates: Map<DateKey, string[]>;
}

const WEEKDAY_HEADERS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

const MONTH_NAMES = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];

/** Format a Date as YYYY-MM-DD */
const toDateKey = (date: Date): DateKey => {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
};

/** Get today's date key using local time */
const todayKey = (): DateKey => toDateKey(new Date());

interface CalendarDay {
  dateKey: DateKey;
  dayOfMonth: number;
  isCurrentMonth: boolean;
  isPast: boolean;
  isExisting: boolean;
  existingTimes: string[];
}

/**
 * Build the 6-week grid of days for a given month.
 */
const buildCalendarDays = (
  year: number,
  month: number,
  existingDates: Map<DateKey, string[]>,
  today: DateKey,
): CalendarDay[] => {
  const firstDay = new Date(year, month, 1);
  const startOffset = firstDay.getDay(); // 0=Sun
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  // Previous month fill
  const prevMonthDays = new Date(year, month, 0).getDate();
  const days: CalendarDay[] = [];

  for (let i = startOffset - 1; i >= 0; i--) {
    const d = new Date(year, month - 1, prevMonthDays - i);
    const key = toDateKey(d);
    days.push({
      dateKey: key,
      dayOfMonth: prevMonthDays - i,
      isCurrentMonth: false,
      isPast: key < today,
      isExisting: existingDates.has(key),
      existingTimes: existingDates.get(key) || [],
    });
  }

  // Current month
  for (let day = 1; day <= daysInMonth; day++) {
    const d = new Date(year, month, day);
    const key = toDateKey(d);
    days.push({
      dateKey: key,
      dayOfMonth: day,
      isCurrentMonth: true,
      isPast: key < today,
      isExisting: existingDates.has(key),
      existingTimes: existingDates.get(key) || [],
    });
  }

  // Next month fill to complete the grid (always show 6 rows = 42 cells)
  const remaining = 42 - days.length;
  for (let day = 1; day <= remaining; day++) {
    const d = new Date(year, month + 1, day);
    const key = toDateKey(d);
    days.push({
      dateKey: key,
      dayOfMonth: day,
      isCurrentMonth: false,
      isPast: key < today,
      isExisting: existingDates.has(key),
      existingTimes: existingDates.get(key) || [],
    });
  }

  return days;
};

/**
 * Get all selectable date keys for a given day-of-week filter in the current month.
 * dayFilter: array of day-of-week numbers (0=Sun, 1=Mon, ... 6=Sat)
 */
const getFilteredDays = (
  year: number,
  month: number,
  dayFilter: number[],
  existingDates: Map<DateKey, string[]>,
  today: DateKey,
): Set<DateKey> => {
  const result = new Set<DateKey>();
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  for (let day = 1; day <= daysInMonth; day++) {
    const d = new Date(year, month, day);
    const key = toDateKey(d);
    if (key < today) continue;
    if (!dayFilter.includes(d.getDay())) continue;
    result.add(key);
  }

  return result;
};

const CalendarGrid: React.FC<CalendarGridProps> = ({
  year,
  month,
  onPrevMonth,
  onNextMonth,
  selectedDates,
  onToggleDate,
  onSetSelection,
  existingDates,
}) => {
  const today = todayKey();

  const days = useMemo(
    () => buildCalendarDays(year, month, existingDates, today),
    [year, month, existingDates, today],
  );

  const handleQuickSelect = (dayFilter: number[]) => {
    const filtered = getFilteredDays(year, month, dayFilter, existingDates, today);

    // Toggle behavior: if all filtered days are already selected, deselect them
    const allSelected = [...filtered].every(d => selectedDates.has(d));
    if (allSelected) {
      const next = new Set(selectedDates);
      filtered.forEach(d => next.delete(d));
      onSetSelection(next);
    } else {
      const next = new Set(selectedDates);
      filtered.forEach(d => next.add(d));
      onSetSelection(next);
    }
  };

  const handleClear = () => onSetSelection(new Set());

  const getCellClasses = (day: CalendarDay, isSelected: boolean): string => {
    const base = 'relative w-10 h-10 flex items-center justify-center rounded-md text-sm font-medium transition-colors';

    if (!day.isCurrentMonth) {
      return `${base} text-gray-300 cursor-default`;
    }
    if (day.isPast) {
      return `${base} text-gray-400 cursor-not-allowed`;
    }
    if (isSelected) {
      return `${base} bg-blue-600 text-white cursor-pointer hover:bg-blue-700`;
    }
    return `${base} text-gray-700 cursor-pointer hover:bg-blue-50`;
  };

  return (
    <div>
      {/* Month navigation */}
      <div className="flex items-center justify-between mb-3">
        <button
          type="button"
          onClick={onPrevMonth}
          className="p-1 rounded hover:bg-gray-100 text-gray-600"
          aria-label="Previous month"
        >
          &larr;
        </button>
        <span className="font-semibold text-gray-800">
          {MONTH_NAMES[month]} {year}
        </span>
        <button
          type="button"
          onClick={onNextMonth}
          className="p-1 rounded hover:bg-gray-100 text-gray-600"
          aria-label="Next month"
        >
          &rarr;
        </button>
      </div>

      {/* Weekday headers */}
      <div className="grid grid-cols-7 gap-1 mb-1">
        {WEEKDAY_HEADERS.map(h => (
          <div key={h} className="w-10 text-center text-xs font-medium text-gray-500">
            {h}
          </div>
        ))}
      </div>

      {/* Day cells */}
      <div className="grid grid-cols-7 gap-1">
        {days.map(day => {
          const isSelected = selectedDates.has(day.dateKey);
          const isClickable = day.isCurrentMonth && !day.isPast;

          return (
            <button
              key={day.dateKey}
              type="button"
              disabled={!isClickable}
              onClick={() => isClickable && onToggleDate(day.dateKey)}
              className={getCellClasses(day, isSelected)}
              title={
                day.isExisting
                  ? `Already proposed: ${day.existingTimes.join(', ')}`
                  : day.isPast
                    ? 'Past date'
                    : undefined
              }
            >
              {day.dayOfMonth}
              {day.isExisting && day.isCurrentMonth && (
                <span className="absolute bottom-0.5 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-green-500" />
              )}
            </button>
          );
        })}
      </div>

      {/* Quick-select buttons */}
      <div className="flex flex-wrap gap-2 mt-3">
        <button
          type="button"
          onClick={() => handleQuickSelect([1, 2, 3, 4, 5])}
          className="text-xs px-3 py-1 bg-blue-100 text-blue-700 rounded-full hover:bg-blue-200"
        >
          Weekdays
        </button>
        <button
          type="button"
          onClick={() => handleQuickSelect([0, 6])}
          className="text-xs px-3 py-1 bg-blue-100 text-blue-700 rounded-full hover:bg-blue-200"
        >
          Weekends
        </button>
        <button
          type="button"
          onClick={() => handleQuickSelect([1, 3, 5])}
          className="text-xs px-3 py-1 bg-blue-100 text-blue-700 rounded-full hover:bg-blue-200"
        >
          MWF
        </button>
        <button
          type="button"
          onClick={() => handleQuickSelect([2, 4])}
          className="text-xs px-3 py-1 bg-blue-100 text-blue-700 rounded-full hover:bg-blue-200"
        >
          T/Th
        </button>
        <button
          type="button"
          onClick={handleClear}
          className="text-xs px-3 py-1 bg-gray-100 text-gray-600 rounded-full hover:bg-gray-200"
        >
          Clear Selection
        </button>
      </div>

      {/* Legend */}
      <div className="flex gap-4 mt-3 text-xs text-gray-500">
        <span className="flex items-center gap-1">
          <span className="w-2.5 h-2.5 rounded-full bg-blue-600 inline-block" />
          Selected
        </span>
        <span className="flex items-center gap-1">
          <span className="w-2.5 h-2.5 rounded-full bg-green-500 inline-block" />
          Already proposed
        </span>
      </div>
    </div>
  );
};

export default CalendarGrid;
