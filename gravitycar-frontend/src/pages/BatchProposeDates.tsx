import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { apiService } from '../services/api';
import {
  getUserTimezone,
  formatDateTimeInTimezone,
  localDateTimeToUTC,
} from '../utils/timezone';
import CalendarGrid from '../components/events/CalendarGrid';
import TimePicker, {
  formatTimeValue,
  to24Hour,
  type TimeValue,
} from '../components/events/TimePicker';
import PendingProposalsList, {
  type PendingEntry,
} from '../components/events/PendingProposalsList';

interface ExistingProposedDate {
  id: string;
  proposed_date: string; // UTC datetime string
}

const DEFAULT_TIME: TimeValue = { hour: 7, minute: 0, ampm: 'PM' };

const BatchProposeDates: React.FC = () => {
  const { eventId } = useParams<{ eventId: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();
  const userTimezone = getUserTimezone(user?.user_timezone);

  // Calendar state
  const now = new Date();
  const [year, setYear] = useState(now.getFullYear());
  const [month, setMonth] = useState(now.getMonth());
  const [selectedDates, setSelectedDates] = useState<Set<string>>(new Set());
  const [time, setTime] = useState<TimeValue>(DEFAULT_TIME);

  // Pending batch
  const [pendingEntries, setPendingEntries] = useState<PendingEntry[]>([]);

  // Existing proposed dates
  const [existingDates, setExistingDates] = useState<ExistingProposedDate[]>([]);
  const [eventName, setEventName] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState<Set<string>>(new Set());
  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  const fetchExistingData = useCallback(async () => {
    if (!eventId) return;
    setLoading(true);
    setError(null);
    try {
      const [eventResp, datesResp] = await Promise.all([
        apiService.getById('Events', eventId),
        apiService.getList('EventProposedDates', 1, 200, { event_id: eventId }),
      ]);

      setEventName((eventResp.data as Record<string, string>).name || 'Event');

      const records = (datesResp.data as ExistingProposedDate[]) || [];
      setExistingDates(records);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to load data';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [eventId]);

  const handleDeleteProposedDate = async (id: string) => {
    setDeleting(prev => new Set(prev).add(id));
    setError(null);
    try {
      await apiService.delete('EventProposedDates', id);
      setExistingDates(prev => prev.filter(d => d.id !== id));
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to delete';
      setError(message);
    } finally {
      setDeleting(prev => {
        const next = new Set(prev);
        next.delete(id);
        return next;
      });
    }
  };

  useEffect(() => {
    fetchExistingData();
  }, [fetchExistingData]);

  // Build a Map<dateKey, timeStrings[]> from existing proposed dates for CalendarGrid
  const existingDateMap = buildExistingDateMap(existingDates, userTimezone);

  // Calendar navigation
  const handlePrevMonth = () => {
    if (month === 0) {
      setMonth(11);
      setYear(y => y - 1);
    } else {
      setMonth(m => m - 1);
    }
  };

  const handleNextMonth = () => {
    if (month === 11) {
      setMonth(0);
      setYear(y => y + 1);
    } else {
      setMonth(m => m + 1);
    }
  };

  const handleToggleDate = (dateKey: string) => {
    setSelectedDates(prev => {
      const next = new Set(prev);
      if (next.has(dateKey)) {
        next.delete(dateKey);
      } else {
        next.add(dateKey);
      }
      return next;
    });
  };

  // Add selected dates + time to the pending batch
  const handleAddToBatch = () => {
    if (selectedDates.size === 0) return;

    const timeLabel = formatTimeValue(time);
    const newEntries: PendingEntry[] = [];

    for (const dateKey of selectedDates) {
      // Skip if this exact date+time is already pending
      const alreadyPending = pendingEntries.some(
        e => e.dateKey === dateKey && e.timeLabel === timeLabel,
      );
      if (!alreadyPending) {
        newEntries.push({ dateKey, timeLabel });
      }
    }

    setPendingEntries(prev => [...prev, ...newEntries]);
    setSelectedDates(new Set());
    setSuccessMsg(null);
  };

  const handleRemoveEntry = (dateKey: string, timeLabel: string) => {
    setPendingEntries(prev =>
      prev.filter(e => !(e.dateKey === dateKey && e.timeLabel === timeLabel)),
    );
  };

  const handleRemoveGroup = (timeLabel: string) => {
    setPendingEntries(prev => prev.filter(e => e.timeLabel !== timeLabel));
  };

  const handleClearAll = () => setPendingEntries([]);

  // Save all pending entries to the backend
  const handleSaveAll = async () => {
    if (!eventId || pendingEntries.length === 0) return;
    setSaving(true);
    setError(null);
    setSuccessMsg(null);

    try {
      const utcDates = pendingEntries.map(entry => {
        return entryToUTCString(entry, userTimezone);
      });

      const response = await apiService.batchCreateProposedDates(eventId, utcDates);
      const data = response.data as { created: number; skipped: number };

      setPendingEntries([]);
      setSuccessMsg(
        `Created ${data.created} proposed date(s)` +
        (data.skipped > 0 ? `, ${data.skipped} duplicate(s) skipped` : ''),
      );

      // Refresh existing dates
      await fetchExistingData();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to save';
      setError(message);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="max-w-3xl mx-auto py-8 px-4">
        <div className="text-gray-500">Loading...</div>
      </div>
    );
  }

  return (
    <div className="max-w-3xl mx-auto py-8 px-4">
      {/* Header */}
      <div className="mb-6">
        <button
          type="button"
          onClick={() => navigate(`/events/${eventId}/chart`)}
          className="text-sm text-blue-600 hover:underline mb-2 inline-block"
        >
          &larr; Back to Chart of Goodness
        </button>
        <h1 className="text-2xl font-bold text-gray-900">
          Propose Dates for: {eventName}
        </h1>
      </div>

      {/* Error / Success banners */}
      {error && (
        <div className="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
          {error}
        </div>
      )}
      {successMsg && (
        <div className="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
          {successMsg}
        </div>
      )}

      {/* Calendar + Time picker row */}
      <div className="flex flex-col md:flex-row gap-6 mb-6">
        <div className="flex-shrink-0">
          <CalendarGrid
            year={year}
            month={month}
            onPrevMonth={handlePrevMonth}
            onNextMonth={handleNextMonth}
            selectedDates={selectedDates}
            onToggleDate={handleToggleDate}
            onSetSelection={setSelectedDates}
            existingDates={existingDateMap}
          />
        </div>

        <div className="flex flex-col gap-4">
          <TimePicker value={time} onChange={setTime} />

          <button
            type="button"
            onClick={handleAddToBatch}
            disabled={selectedDates.size === 0}
            className={`
              px-4 py-2 rounded-md font-medium text-sm transition-colors
              ${selectedDates.size === 0
                ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                : 'bg-blue-600 text-white hover:bg-blue-700'
              }
            `}
          >
            Add to Batch
            {selectedDates.size > 0 && ` (${selectedDates.size} day${selectedDates.size > 1 ? 's' : ''})`}
          </button>
        </div>
      </div>

      {/* Pending proposals */}
      <div className="border-t border-gray-200 pt-4 mb-6">
        <PendingProposalsList
          entries={pendingEntries}
          onRemoveEntry={handleRemoveEntry}
          onRemoveGroup={handleRemoveGroup}
          onClearAll={handleClearAll}
        />
      </div>

      {/* Save button */}
      {pendingEntries.length > 0 && (
        <button
          type="button"
          onClick={handleSaveAll}
          disabled={saving}
          className={`
            w-full px-4 py-3 rounded-md font-semibold text-sm transition-colors
            ${saving
              ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
              : 'bg-green-600 text-white hover:bg-green-700'
            }
          `}
        >
          {saving ? 'Saving...' : `Save All Proposed Dates (${pendingEntries.length})`}
        </button>
      )}

      {/* Existing proposed dates section */}
      <div className="mt-8 border-t border-gray-200 pt-4">
        <h3 className="font-semibold text-gray-700 mb-3">
          Existing Proposed Dates ({existingDates.length})
        </h3>
        {existingDates.length === 0 ? (
          <p className="text-sm text-gray-500 italic">No proposed dates yet.</p>
        ) : (
          <div className="space-y-1">
            {existingDates
              .slice()
              .sort((a, b) => a.proposed_date.localeCompare(b.proposed_date))
              .map(d => (
                <div
                  key={d.id}
                  className="flex items-center justify-between bg-white border border-gray-200 rounded-lg px-3 py-2"
                >
                  <div className="flex items-center gap-2 text-sm text-gray-700">
                    <span className="w-2 h-2 rounded-full bg-green-500 flex-shrink-0" />
                    {formatDateTimeInTimezone(d.proposed_date, userTimezone)}
                  </div>
                  <button
                    type="button"
                    onClick={() => handleDeleteProposedDate(d.id)}
                    disabled={deleting.has(d.id)}
                    className={`text-sm ${
                      deleting.has(d.id)
                        ? 'text-gray-400 cursor-not-allowed'
                        : 'text-red-600 hover:text-red-800'
                    }`}
                  >
                    {deleting.has(d.id) ? 'Deleting...' : 'Delete'}
                  </button>
                </div>
              ))}
          </div>
        )}
      </div>
    </div>
  );
};

/**
 * Build a Map from dateKey (YYYY-MM-DD in user timezone) to display time strings,
 * used by CalendarGrid to show which dates already have proposals.
 */
function buildExistingDateMap(
  dates: ExistingProposedDate[],
  timezone: string,
): Map<string, string[]> {
  const map = new Map<string, string[]>();

  for (const d of dates) {
    // Convert UTC proposed_date to user timezone for display
    const normalized = d.proposed_date.includes('T')
      ? d.proposed_date
      : d.proposed_date.replace(' ', 'T') + 'Z';
    const dateObj = new Date(normalized);
    if (isNaN(dateObj.getTime())) continue;

    // Get date key in user's timezone
    const formatter = new Intl.DateTimeFormat('en-CA', {
      timeZone: timezone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    });
    const dateKey = formatter.format(dateObj);

    // Get time display in user's timezone
    const timeFormatter = new Intl.DateTimeFormat(undefined, {
      timeZone: timezone,
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    });
    const timeStr = timeFormatter.format(dateObj);

    const existing = map.get(dateKey) || [];
    existing.push(timeStr);
    map.set(dateKey, existing);
  }

  return map;
}

/**
 * Convert a PendingEntry (dateKey + timeLabel like "6:00 PM") to a UTC datetime
 * string in Y-m-d H:i:s format, applying timezone conversion.
 */
function entryToUTCString(entry: PendingEntry, timezone: string): string {
  // Parse the timeLabel back to components
  const timeMatch = entry.timeLabel.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
  if (!timeMatch) {
    throw new Error(`Invalid time label: ${entry.timeLabel}`);
  }

  let hours = parseInt(timeMatch[1], 10);
  const minutes = parseInt(timeMatch[2], 10);
  const ampm = timeMatch[3].toUpperCase();

  if (ampm === 'PM' && hours !== 12) hours += 12;
  if (ampm === 'AM' && hours === 12) hours = 0;

  // Build a datetime-local string in user's timezone
  const hh = String(hours).padStart(2, '0');
  const mm = String(minutes).padStart(2, '0');
  const localStr = `${entry.dateKey}T${hh}:${mm}`;

  // Convert to UTC using the same utility as DateTimePicker
  const utcDate = localDateTimeToUTC(localStr, timezone);

  const pad = (n: number) => n.toString().padStart(2, '0');
  return `${utcDate.getUTCFullYear()}-${pad(utcDate.getUTCMonth() + 1)}-${pad(utcDate.getUTCDate())} ${pad(utcDate.getUTCHours())}:${pad(utcDate.getUTCMinutes())}:${pad(utcDate.getUTCSeconds())}`;
}

export default BatchProposeDates;
