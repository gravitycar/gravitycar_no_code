/* eslint-disable @typescript-eslint/no-explicit-any */
import { useState, useEffect, useRef, useCallback } from 'react';
import { apiService } from '../../services/api';
import { fetchWithDebug } from '../../utils/apiUtils';

/** Models excluded from the linker because they are system/event-internal. */
const EXCLUDED_MODELS = [
  'Events',
  'EventProposedDates',
  'EventCommitments',
  'EventInvitations',
  'EventReminders',
  'EmailQueue',
  'Jwt_Refresh_Tokens',
  'Google_Oauth_Tokens',
  'Permissions',
  'Roles',
];

/** Fallback fields tried when displayColumns are empty or missing. */
const DISPLAY_FALLBACK_FIELDS = ['name', 'title', 'username', 'email'];

interface ModelLinkerProps {
  modelName: string | null;
  recordId: string | null;
  onModelChange: (modelName: string | null) => void;
  onRecordChange: (recordId: string | null) => void;
  disabled?: boolean;
  error?: string;
}

interface ModelOption { name: string; title: string }
interface RecordOption { value: string; label: string }

const buildDisplayLabel = (record: Record<string, any>, cols: string[]): string => {
  const parts = cols
    .map((col) => record[col])
    .filter((v) => v && String(v).trim())
    .map((v) => String(v).trim());
  if (parts.length > 0) return parts.join(' ');

  for (const fallback of DISPLAY_FALLBACK_FIELDS) {
    if (record[fallback]) return String(record[fallback]);
  }
  return `Record #${record.id}`;
};

const ModelLinker: React.FC<ModelLinkerProps> = ({
  modelName,
  recordId,
  onModelChange,
  onRecordChange,
  disabled = false,
  error,
}) => {
  const [availableModels, setAvailableModels] = useState<ModelOption[]>([]);
  const [loadingModels, setLoadingModels] = useState(true);
  const [records, setRecords] = useState<RecordOption[]>([]);
  const [loadingRecords, setLoadingRecords] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedRecordLabel, setSelectedRecordLabel] = useState<string | null>(null);
  const [isRecordDropdownOpen, setIsRecordDropdownOpen] = useState(false);
  const [displayColumns, setDisplayColumns] = useState<string[]>([]);
  const [highlightedIndex, setHighlightedIndex] = useState(-1);

  const dropdownRef = useRef<HTMLDivElement>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);

  // Fetch available models on mount
  useEffect(() => {
    const fetchModels = async () => {
      setLoadingModels(true);
      try {
        const models = await apiService.getAvailableModels();
        const linkable = models.filter((m) => !EXCLUDED_MODELS.includes(m.name));
        setAvailableModels(linkable);
      } catch {
        setAvailableModels([]);
      } finally {
        setLoadingModels(false);
      }
    };
    fetchModels();
  }, []);

  // Fetch metadata when model changes to get displayColumns
  useEffect(() => {
    if (!modelName) {
      setDisplayColumns([]);
      return;
    }
    fetchWithDebug(`/metadata/models/${modelName}`, { method: 'GET' })
      .then((res) => res.json())
      .then((data) => {
        const meta = data.data || data;
        setDisplayColumns(meta.displayColumns || meta.display_columns || ['name']);
      })
      .catch(() => setDisplayColumns(['name']));
  }, [modelName]);

  // Fetch selected record label on mount if recordId is set
  useEffect(() => {
    if (!modelName || !recordId || displayColumns.length === 0) {
      return;
    }
    fetchWithDebug(`/${modelName}/${recordId}`, { method: 'GET' })
      .then((res) => res.json())
      .then((data) => {
        const rec = data.data || data;
        setSelectedRecordLabel(buildDisplayLabel(rec, displayColumns));
      })
      .catch(() => setSelectedRecordLabel(`Record #${recordId}`));
  }, [modelName, recordId, displayColumns]);

  // Debounced record search
  useEffect(() => {
    if (!modelName || displayColumns.length === 0) return;
    const timeout = setTimeout(() => {
      fetchRecords(searchTerm);
    }, 300);
    return () => clearTimeout(timeout);
  }, [searchTerm, modelName, displayColumns, fetchRecords]);

  // Close dropdown on outside click
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setIsRecordDropdownOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const fetchRecords = useCallback(async (search: string) => {
    if (!modelName) return;
    setLoadingRecords(true);
    try {
      const params = new URLSearchParams({ limit: '20' });
      if (search.trim()) params.append('search', search.trim());
      const response = await fetchWithDebug(`/${modelName}?${params}`, { method: 'GET' });
      const data = await response.json();
      const rawRecords = data.success && Array.isArray(data.data) ? data.data : [];
      const options = rawRecords.map((rec: Record<string, any>) => ({
        value: String(rec.id),
        label: buildDisplayLabel(rec, displayColumns),
      }));
      setRecords(options);
    } catch {
      setRecords([]);
    } finally {
      setLoadingRecords(false);
    }
  }, [modelName, displayColumns]);

  const handleModelChange = (newModelName: string | null) => {
    onModelChange(newModelName);
    onRecordChange(null);
    setSelectedRecordLabel(null);
    setRecords([]);
    setSearchTerm('');
    setIsRecordDropdownOpen(false);
  };

  const handleRecordSelect = (option: RecordOption) => {
    onRecordChange(option.value);
    setSelectedRecordLabel(option.label);
    setSearchTerm('');
    setIsRecordDropdownOpen(false);
  };

  const handleClearRecord = () => {
    onRecordChange(null);
    setSelectedRecordLabel(null);
    setSearchTerm('');
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (!isRecordDropdownOpen) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setHighlightedIndex((prev) => Math.min(prev + 1, records.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setHighlightedIndex((prev) => Math.max(prev - 1, 0));
    } else if (e.key === 'Enter' && highlightedIndex >= 0) {
      e.preventDefault();
      handleRecordSelect(records[highlightedIndex]);
    } else if (e.key === 'Escape') {
      setIsRecordDropdownOpen(false);
    }
  };

  return (
    <div className="mb-4 space-y-3">
      <label className="block text-sm font-medium text-gray-700">
        Link to Model Record (optional)
      </label>

      {/* Step 1: Model Selector Dropdown */}
      <div>
        <label className="block text-xs text-gray-500 mb-1">Model</label>
        <select
          value={modelName || ''}
          onChange={(e) => handleModelChange(e.target.value || null)}
          disabled={disabled || loadingModels}
          className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm
                     focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                     disabled:bg-gray-100 disabled:cursor-not-allowed"
        >
          <option value="">-- No linked model --</option>
          {availableModels.map((m) => (
            <option key={m.name} value={m.name}>
              {m.title}
            </option>
          ))}
        </select>
      </div>

      {/* Step 2: Record Search/Select (only shown when model selected) */}
      {modelName && (
        <div className="relative" ref={dropdownRef}>
          <label className="block text-xs text-gray-500 mb-1">Record</label>

          {recordId && selectedRecordLabel ? (
            <div className="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
              <span className="flex-1 text-sm text-gray-800 truncate">
                {selectedRecordLabel}
              </span>
              {!disabled && (
                <button
                  type="button"
                  onClick={handleClearRecord}
                  className="text-gray-400 hover:text-gray-600 text-sm font-bold"
                  aria-label="Clear selection"
                >
                  x
                </button>
              )}
            </div>
          ) : (
            <>
              <input
                ref={searchInputRef}
                type="text"
                value={searchTerm}
                onChange={(e) => {
                  setSearchTerm(e.target.value);
                  setIsRecordDropdownOpen(true);
                  setHighlightedIndex(-1);
                }}
                onFocus={() => setIsRecordDropdownOpen(true)}
                onKeyDown={handleKeyDown}
                placeholder={`Search ${modelName} records...`}
                disabled={disabled}
                className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm
                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                           disabled:bg-gray-100 disabled:cursor-not-allowed"
              />

              {isRecordDropdownOpen && (
                <div className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-48 overflow-y-auto">
                  {loadingRecords ? (
                    <div className="px-3 py-2 text-sm text-gray-500">Loading...</div>
                  ) : records.length === 0 ? (
                    <div className="px-3 py-2 text-sm text-gray-500">No results</div>
                  ) : (
                    records.map((option, index) => (
                      <button
                        key={option.value}
                        type="button"
                        className={`w-full text-left px-3 py-2 text-sm hover:bg-blue-50 ${
                          index === highlightedIndex ? 'bg-blue-100' : ''
                        }`}
                        onMouseEnter={() => setHighlightedIndex(index)}
                        onClick={() => handleRecordSelect(option)}
                      >
                        {option.label}
                      </button>
                    ))
                  )}
                </div>
              )}
            </>
          )}
        </div>
      )}

      {error && <p className="text-sm text-red-600">{error}</p>}
    </div>
  );
};

export default ModelLinker;
