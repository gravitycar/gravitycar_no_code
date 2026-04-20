import { useState } from 'react';
import { apiService } from '../../services/api';

interface AdminControlsProps {
  eventId: string;
  onDataChanged?: () => void;
}

const AdminControls: React.FC<AdminControlsProps> = ({ eventId, onDataChanged }) => {
  const [revoking, setRevoking] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleRevoke = async () => {
    if (!window.confirm(
      'Revoke the accepted date? This will also delete all reminders for this event.'
    )) {
      return;
    }

    setRevoking(true);
    setError(null);
    try {
      await apiService.revokeAcceptedDate(eventId);
      onDataChanged?.();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to revoke accepted date';
      setError(message);
    } finally {
      setRevoking(false);
    }
  };

  return (
    <div className="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
      <h3 className="font-semibold text-gray-700 mb-2">Admin Controls</h3>
      {error && (
        <div className="mb-2 p-2 bg-red-50 border border-red-200 text-red-700 rounded text-sm">
          {error}
        </div>
      )}
      <div className="flex gap-4 flex-wrap">
        <a
          href={`/events/${eventId}/propose-dates`}
          className="text-indigo-600 hover:underline text-sm"
        >
          Manage Proposed Dates
        </a>
        <a
          href="/events"
          className="text-indigo-600 hover:underline text-sm"
          title="Edit this event to manage invitations"
        >
          Manage Invitations
        </a>
        <a
          href={`/EventReminders?event_id=${eventId}`}
          className="text-indigo-600 hover:underline text-sm"
        >
          Manage Reminders
        </a>
        <button
          type="button"
          onClick={handleRevoke}
          disabled={revoking}
          className={`text-sm ${
            revoking
              ? 'text-gray-400 cursor-not-allowed'
              : 'text-red-600 hover:underline'
          }`}
        >
          {revoking ? 'Revoking...' : 'Revoke Accepted Date'}
        </button>
      </div>
    </div>
  );
};

export default AdminControls;
