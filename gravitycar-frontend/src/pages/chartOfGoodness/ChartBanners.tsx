import type { ChartEvent, MostPopularDateData } from '../../types';

interface DateParts {
  dayOfWeek: string;
  monthDay: string;
  time: string;
}

interface AcceptedDateBannerProps {
  event: ChartEvent;
  formatFullDate: (dateStr: string) => string;
  onIcsDownload: () => void;
}

export const AcceptedDateBanner: React.FC<AcceptedDateBannerProps> = ({
  event,
  formatFullDate,
  onIcsDownload,
}) => {
  if (!event.accepted_date) {
    return null;
  }

  return (
    <div className="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg flex items-center justify-between">
      <div>
        <span className="font-semibold text-green-800">Accepted Date: </span>
        <span className="text-green-700">{formatFullDate(event.accepted_date)}</span>
      </div>
      <button
        onClick={onIcsDownload}
        className="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-sm"
      >
        Export to Calendar (.ics)
      </button>
    </div>
  );
};

interface MostPopularBannerProps {
  popularData: MostPopularDateData;
  formatProposedDate: (dateStr: string) => DateParts;
}

export const MostPopularBanner: React.FC<MostPopularBannerProps> = ({
  popularData,
  formatProposedDate,
}) => {
  if (!popularData.most_popular_dates.length) {
    return null;
  }

  const formatShortDate = (dateStr: string): string => {
    const parts = formatProposedDate(dateStr);
    return `${parts.dayOfWeek} ${parts.monthDay} @ ${parts.time}`;
  };

  const voteCount = popularData.most_popular_dates[0].vote_count;
  const dateList = popularData.most_popular_dates
    .map((d) => formatShortDate(d.proposed_date))
    .join(', ');

  const voteSuffix = popularData.tied
    ? ` (tied, ${voteCount} votes each)`
    : ` (${voteCount} vote${voteCount !== 1 ? 's' : ''})`;

  return (
    <div className="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
      <span className="font-semibold text-blue-800">Most Popular: </span>
      <span className="text-blue-700">
        {dateList}
        {voteSuffix}
      </span>
    </div>
  );
};
