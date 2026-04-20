import React from 'react';

export interface PendingEntry {
  dateKey: string;   // YYYY-MM-DD
  timeLabel: string; // e.g. "6:00 PM"
}

interface PendingProposalsListProps {
  entries: PendingEntry[];
  onRemoveEntry: (dateKey: string, timeLabel: string) => void;
  onRemoveGroup: (timeLabel: string) => void;
  onClearAll: () => void;
}

/** Format YYYY-MM-DD as "Mon Apr 6" */
const formatDateChip = (dateKey: string): string => {
  const [y, m, d] = dateKey.split('-').map(Number);
  const date = new Date(y, m - 1, d);
  return date.toLocaleDateString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  });
};

/** Group entries by timeLabel */
const groupByTime = (entries: PendingEntry[]): Map<string, string[]> => {
  const groups = new Map<string, string[]>();
  for (const entry of entries) {
    const existing = groups.get(entry.timeLabel) || [];
    existing.push(entry.dateKey);
    groups.set(entry.timeLabel, existing);
  }
  return groups;
};

const PendingProposalsList: React.FC<PendingProposalsListProps> = ({
  entries,
  onRemoveEntry,
  onRemoveGroup,
  onClearAll,
}) => {
  const groups = groupByTime(entries);

  if (entries.length === 0) {
    return (
      <div className="text-sm text-gray-500 italic py-4">
        No dates queued yet. Select days on the calendar, set a time, and click
        &ldquo;Add to Batch&rdquo;.
      </div>
    );
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-3">
        <h3 className="font-semibold text-gray-700">
          Pending Proposals ({entries.length})
        </h3>
        <button
          type="button"
          onClick={onClearAll}
          className="text-xs text-red-600 hover:text-red-800"
        >
          Clear All
        </button>
      </div>

      <div className="space-y-3">
        {[...groups.entries()].map(([timeLabel, dateKeys]) => (
          <div
            key={timeLabel}
            className="border border-gray-200 rounded-lg p-3 bg-white"
          >
            <div className="flex items-center justify-between mb-2">
              <span className="font-medium text-gray-800 text-sm">
                {timeLabel}
              </span>
              <button
                type="button"
                onClick={() => onRemoveGroup(timeLabel)}
                className="text-xs text-red-500 hover:text-red-700"
              >
                Remove group
              </button>
            </div>
            <div className="flex flex-wrap gap-1.5">
              {dateKeys.sort().map(dk => (
                <span
                  key={dk}
                  className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-50 text-blue-800 text-xs"
                >
                  {formatDateChip(dk)}
                  <button
                    type="button"
                    onClick={() => onRemoveEntry(dk, timeLabel)}
                    className="ml-0.5 text-blue-400 hover:text-red-500 font-bold leading-none"
                    aria-label={`Remove ${formatDateChip(dk)}`}
                  >
                    &times;
                  </button>
                </span>
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default PendingProposalsList;
