import { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { apiService } from '../services/api';
import { getUserTimezone, formatDateTimeInTimezone } from '../utils/timezone';
import type { ChartData, ChartUser, MostPopularDateData } from '../types';
import EventHeader from './chartOfGoodness/EventHeader';
import { AcceptedDateBanner, MostPopularBanner } from './chartOfGoodness/ChartBanners';
import ChartGrid from './chartOfGoodness/ChartGrid';
import AdminControls from './chartOfGoodness/AdminControls';

const ChartOfGoodness: React.FC = () => {
  const { eventId } = useParams<{ eventId: string }>();
  const { user, isAuthenticated } = useAuth();
  const userTimezone = getUserTimezone(user?.user_timezone);

  const [chartData, setChartData] = useState<ChartData | null>(null);
  const [popularData, setPopularData] = useState<MostPopularDateData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [savingCells, setSavingCells] = useState<Set<string>>(new Set());

  const fetchData = useCallback(async () => {
    if (!eventId) return;
    setLoading(true);
    setError(null);
    try {
      const [chartResponse, popularResponse] = await Promise.all([
        apiService.getEventChart(eventId),
        apiService.getMostPopularDate(eventId),
      ]);
      setChartData(chartResponse.data as ChartData);
      setPopularData(popularResponse.data as MostPopularDateData);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to load chart';
      setError(message);
    } finally {
      setLoading(false);
    }
  }, [eventId]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const handleToggle = async (proposedDateId: string) => {
    if (!chartData || !chartData.current_user_id || !eventId) return;
    const cellKey = `${chartData.current_user_id}:${proposedDateId}`;
    const currentValue = chartData.commitments[cellKey] ?? false;
    const newValue = !currentValue;

    // Optimistic update
    setChartData((prev) => {
      if (!prev) return prev;
      return {
        ...prev,
        commitments: { ...prev.commitments, [cellKey]: newValue },
      };
    });
    setSavingCells((prev) => new Set(prev).add(cellKey));

    try {
      await apiService.upsertCommitments(eventId, [
        { proposed_date_id: proposedDateId, is_available: newValue },
      ]);
      // Refresh popular date after toggle
      const popularResponse = await apiService.getMostPopularDate(eventId);
      setPopularData(popularResponse.data as MostPopularDateData);
    } catch {
      // Rollback on failure
      setChartData((prev) => {
        if (!prev) return prev;
        return {
          ...prev,
          commitments: { ...prev.commitments, [cellKey]: currentValue },
        };
      });
    } finally {
      setSavingCells((prev) => {
        const next = new Set(prev);
        next.delete(cellKey);
        return next;
      });
    }
  };

  const handleAcceptAll = async () => {
    if (!chartData || !chartData.current_user_id || !eventId) return;
    try {
      await apiService.acceptAllDates(eventId);
      await fetchData();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to accept all';
      setError(message);
    }
  };

  const handleIcsDownload = async () => {
    if (!eventId) return;
    try {
      await apiService.downloadIcs(eventId);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to download ICS';
      setError(message);
    }
  };

  const handleSetAcceptedDate = async (proposedDateId: string) => {
    if (!eventId || !chartData?.is_admin) return;
    if (!window.confirm('Set this as the accepted date for the event?')) return;
    try {
      await apiService.setAcceptedDate(eventId, proposedDateId);
      await fetchData();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to set accepted date';
      setError(message);
    }
  };

  const getUserDisplayName = (chartUser: ChartUser): string => {
    if (!chartData) return '';
    return chartData.user_display_columns
      .map((col) => chartUser[col] ?? '')
      .filter(Boolean)
      .join(' ');
  };

  const formatProposedDate = (dateStr: string) => {
    // Normalize bare "Y-m-d H:i:s" UTC strings to ISO 8601 so Date parses as UTC
    const normalized = dateStr.includes('T') || dateStr.endsWith('Z')
      ? dateStr
      : dateStr.replace(' ', 'T') + 'Z';
    const date = new Date(normalized);
    const dayOfWeek = new Intl.DateTimeFormat('en-US', {
      weekday: 'short',
      timeZone: userTimezone,
    }).format(date);
    const monthDay = new Intl.DateTimeFormat('en-US', {
      month: 'short',
      day: 'numeric',
      timeZone: userTimezone,
    }).format(date);
    const time = new Intl.DateTimeFormat('en-US', {
      hour: 'numeric',
      minute: '2-digit',
      timeZone: userTimezone,
    }).format(date);
    return { dayOfWeek, monthDay, time };
  };

  const formatFullDate = (dateStr: string): string => {
    return formatDateTimeInTimezone(dateStr, userTimezone);
  };

  if (loading) {
    return <div className="p-8 text-center text-gray-500">Loading chart...</div>;
  }

  if (error) {
    return <div className="p-8 text-center text-red-600">{error}</div>;
  }

  if (!chartData) {
    return <div className="p-8 text-center text-gray-500">No chart data available.</div>;
  }

  const { event, is_admin } = chartData;
  const isGuest = !isAuthenticated;
  const canEdit = !isGuest && chartData.current_user_id !== null;

  return (
    <div className="p-4 sm:p-6 lg:p-8 max-w-full">
      <EventHeader event={event} />

      {event.accepted_date && (
        <AcceptedDateBanner
          event={event}
          formatFullDate={formatFullDate}
          onIcsDownload={handleIcsDownload}
        />
      )}

      {popularData &&
        popularData.most_popular_dates.length > 0 &&
        !event.accepted_date && (
          <MostPopularBanner
            popularData={popularData}
            formatProposedDate={formatProposedDate}
          />
        )}

      {canEdit && !event.accepted_date && (
        <div className="mb-4">
          <button
            onClick={handleAcceptAll}
            className="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm"
          >
            Accept All Dates
          </button>
        </div>
      )}

      <ChartGrid
        chartData={chartData}
        savingCells={savingCells}
        userTimezone={userTimezone}
        onToggle={handleToggle}
        onSetAcceptedDate={handleSetAcceptedDate}
        formatProposedDate={formatProposedDate}
        getUserDisplayName={getUserDisplayName}
        canEdit={canEdit}
      />

      {is_admin && eventId && <AdminControls eventId={eventId} />}
    </div>
  );
};

export default ChartOfGoodness;
