/* eslint-disable @typescript-eslint/no-explicit-any */
import { useState, useEffect } from 'react';
import { apiService } from '../../services/api';
import type { ChartEvent } from '../../types';

/** Fallback fields tried when determining display name for a linked record. */
const LINKED_RECORD_DISPLAY_FIELDS = ['name', 'title', 'username', 'email'];

interface EventHeaderProps {
  event: ChartEvent;
}

interface LinkedRecordInfo {
  record: Record<string, any>;
  imageFieldName: string | null;
  modelName: string;
}

const getLinkedRecordDisplayName = (record: Record<string, any>): string => {
  for (const field of LINKED_RECORD_DISPLAY_FIELDS) {
    if (record[field]) return String(record[field]);
  }
  return `Record #${record.id}`;
};

const EventHeader: React.FC<EventHeaderProps> = ({ event }) => {
  const [linkedRecord, setLinkedRecord] = useState<LinkedRecordInfo | null>(null);

  useEffect(() => {
    if (!event.linked_model_name || !event.linked_record_id) {
      setLinkedRecord(null);
      return;
    }

    const fetchLinkedRecord = async () => {
      try {
        const result = await apiService.getRecordWithImageInfo(
          event.linked_model_name!,
          event.linked_record_id!
        );
        setLinkedRecord({
          ...result,
          modelName: event.linked_model_name!,
        });
      } catch {
        setLinkedRecord(null);
      }
    };

    fetchLinkedRecord();
  }, [event.linked_model_name, event.linked_record_id]);

  return (
    <div className="mb-6">
      <h1 className="text-2xl font-bold text-gray-900">{event.name}</h1>
      {event.description && (
        <p className="mt-1 text-gray-600">{event.description}</p>
      )}
      {event.location && (
        <p className="mt-1 text-gray-500 text-sm">
          <span className="inline-block mr-1" aria-label="Location">&#128205;</span>
          {event.location}
        </p>
      )}
      {event.duration_hours > 0 && (
        <p className="mt-1 text-gray-500 text-sm">
          Duration: {event.duration_hours} hour{event.duration_hours !== 1 ? 's' : ''}
        </p>
      )}

      {/* Linked Record Display */}
      {linkedRecord && (
        <div className="mt-4 p-4 bg-gray-50 border border-gray-200 rounded-lg flex items-center gap-4">
          {linkedRecord.imageFieldName &&
            linkedRecord.record[linkedRecord.imageFieldName] && (
              <img
                src={linkedRecord.record[linkedRecord.imageFieldName]}
                alt={`${linkedRecord.modelName} image`}
                className="w-20 h-20 object-cover rounded-md flex-shrink-0"
              />
            )}
          <div>
            <p className="text-xs text-gray-500 uppercase tracking-wide">
              Linked {linkedRecord.modelName.replace(/_/g, ' ')}
            </p>
            <a
              href={`/${linkedRecord.modelName.toLowerCase()}/${linkedRecord.record.id}`}
              className="text-indigo-600 hover:underline font-medium"
            >
              {getLinkedRecordDisplayName(linkedRecord.record)}
            </a>
          </div>
        </div>
      )}
    </div>
  );
};

export default EventHeader;
