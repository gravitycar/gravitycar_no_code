interface AdminControlsProps {
  eventId: string;
}

const AdminControls: React.FC<AdminControlsProps> = ({ eventId }) => {
  return (
    <div className="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
      <h3 className="font-semibold text-gray-700 mb-2">Admin Controls</h3>
      <div className="flex gap-4 flex-wrap">
        <a
          href={`/event_proposed_dates?event_id=${eventId}`}
          className="text-indigo-600 hover:underline text-sm"
        >
          Manage Proposed Dates
        </a>
        <a
          href={`/event_invitations?event_id=${eventId}`}
          className="text-indigo-600 hover:underline text-sm"
        >
          Manage Invitations
        </a>
        <a
          href={`/event_reminders?event_id=${eventId}`}
          className="text-indigo-600 hover:underline text-sm"
        >
          Manage Reminders
        </a>
      </div>
    </div>
  );
};

export default AdminControls;
