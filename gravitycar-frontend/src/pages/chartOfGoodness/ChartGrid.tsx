import type { ChartData, ProposedDate, ChartUser } from '../../types';

interface DateParts {
  dayOfWeek: string;
  monthDay: string;
  time: string;
}

interface ChartGridProps {
  chartData: ChartData;
  savingCells: Set<string>;
  userTimezone: string;
  onToggle: (proposedDateId: string) => void;
  onSetAcceptedDate: (proposedDateId: string) => void;
  formatProposedDate: (dateStr: string) => DateParts;
  getUserDisplayName: (user: ChartUser) => string;
  canEdit: boolean;
}

const ChartGrid: React.FC<ChartGridProps> = ({
  chartData,
  savingCells,
  onToggle,
  onSetAcceptedDate,
  formatProposedDate,
  getUserDisplayName,
  canEdit,
}) => {
  const { event, proposed_dates, users, commitments, current_user_id, is_admin } = chartData;

  const renderUserHeader = (user: ChartUser) => {
    const isCurrentUser = user.id === current_user_id;
    return (
      <th
        key={user.id}
        className={`border border-gray-300 p-2 text-center min-w-[100px] ${
          isCurrentUser ? 'bg-yellow-50' : 'bg-gray-50'
        }`}
      >
        <div className="text-sm font-medium">{getUserDisplayName(user)}</div>
      </th>
    );
  };

  const renderCell = (user: ChartUser, pd: ProposedDate) => {
    const cellKey = `${user.id}:${pd.id}`;
    const isAvailable = commitments[cellKey] ?? false;
    const isSaving = savingCells.has(cellKey);
    const isCurrentUser = user.id === current_user_id;

    if (isCurrentUser && canEdit && !event.accepted_date) {
      return (
        <td key={user.id} className="border border-gray-300 p-2 text-center bg-yellow-50">
          <button
            onClick={() => onToggle(pd.id)}
            disabled={isSaving}
            className={`w-8 h-8 rounded ${
              isAvailable ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-400'
            } ${isSaving ? 'opacity-50' : 'hover:opacity-80'}`}
          >
            {isAvailable ? '\u2713' : ''}
          </button>
        </td>
      );
    }

    return (
      <td key={user.id} className={`border border-gray-300 p-2 text-center ${
        isCurrentUser ? 'bg-yellow-50' : ''
      }`}>
        <span
          className={`inline-block w-8 h-8 rounded ${
            isAvailable ? 'bg-green-500' : 'bg-gray-200'
          }`}
        />
      </td>
    );
  };

  const renderDateRow = (pd: ProposedDate) => {
    const fmt = formatProposedDate(pd.proposed_date);
    return (
      <tr key={pd.id}>
        <td className="border border-gray-300 p-2 sticky left-0 bg-white z-10">
          <div className="text-xs text-gray-500">{fmt.dayOfWeek}</div>
          <div className="text-sm font-medium">{fmt.monthDay}</div>
          <div className="text-xs text-gray-500">{fmt.time}</div>
          {is_admin && !event.accepted_date && (
            <button
              className="mt-1 text-xs text-indigo-600 hover:underline"
              onClick={() => onSetAcceptedDate(pd.id)}
            >
              Set as Accepted
            </button>
          )}
        </td>
        {users.map((user) => renderCell(user, pd))}
      </tr>
    );
  };

  return (
    <div className="overflow-x-auto">
      <table className="min-w-full border-collapse border border-gray-300">
        <thead>
          <tr>
            <th className="border border-gray-300 p-2 bg-gray-50 sticky left-0 z-10">
              Date
            </th>
            {users.map(renderUserHeader)}
          </tr>
        </thead>
        <tbody>{proposed_dates.map(renderDateRow)}</tbody>
      </table>
    </div>
  );
};

export default ChartGrid;
