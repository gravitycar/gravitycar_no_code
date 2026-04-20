/* eslint-disable @typescript-eslint/no-explicit-any, react-hooks/exhaustive-deps */
import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { apiService } from '../../services/api';
import { useModelMetadata } from '../../hooks/useModelMetadata';

interface RelatedItemsSectionProps {
  title: string;
  parentModel: string;
  parentId: string;
  relationship: string;
  relatedModel: string;
  displayColumns: string[];
  actions?: ('create' | 'edit' | 'delete')[];
  createFields?: string[];
  editFields?: string[];
  allowInlineCreate?: boolean;
  allowInlineEdit?: boolean;
  /** 'link' for ManyToMany (pick existing records), 'children' for OneToMany (create new) */
  mode?: 'children_management' | 'link';
  /** URL pattern for an external "Add New" page. {parentId} is replaced at runtime. */
  addNewUrl?: string;
  permissions?: {
    canCreate: boolean;
    canEdit: boolean;
    canDelete: boolean;
    canReorder: boolean;
  };
}

const RelatedItemsSection: React.FC<RelatedItemsSectionProps> = ({
  title,
  parentModel,
  parentId,
  relationship,
  relatedModel,
  displayColumns,
  actions = ['create', 'delete'],
  mode = 'children_management',
  addNewUrl,
  permissions = { canCreate: true, canEdit: true, canDelete: true, canReorder: false }
}) => {
  const navigate = useNavigate();
  const [relatedItems, setRelatedItems] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showPicker, setShowPicker] = useState(false);
  const { metadata } = useModelMetadata(relatedModel);

  const isManyToMany = mode === 'link' || relationship.includes('_users_') || relationship.includes('invitations');

  const loadRelatedItems = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);

      if (isManyToMany) {
        // The link API returns join table records with a display_value field
        // that contains the related model's display columns concatenated.
        const linkResponse = await apiService.getRelatedRecords(parentModel, parentId, relationship);
        setRelatedItems(linkResponse.data || []);
      } else {
        const parentField = findForeignKeyField(metadata, parentModel);
        const response = await apiService.getList(relatedModel, 1, 100, {
          [parentField]: parentId
        });
        setRelatedItems(response.data || []);
      }
    } catch (err: any) {
      setError(err.message || 'Failed to load items');
    } finally {
      setLoading(false);
    }
  }, [parentModel, parentId, relationship, relatedModel, isManyToMany, metadata]);

  useEffect(() => {
    loadRelatedItems();
  }, [loadRelatedItems]);

  const handleUnlink = async (item: any) => {
    if (!confirm(`Remove this ${relatedModel.replace(/([A-Z])/g, ' $1').trim().toLowerCase()} from the ${title.toLowerCase()}?`)) return;
    try {
      if (isManyToMany) {
        // For ManyToMany, unlink using the related model's ID from the join record
        const relatedIdField = `${relatedModel.toLowerCase()}_id`;
        const relatedId = item[relatedIdField] || item.id;
        await apiService.unlinkRecord(parentModel, parentId, relationship, relatedId);
      } else {
        await apiService.delete(relatedModel, item.id);
      }
      setRelatedItems(prev => prev.filter(i => i.id !== item.id));
    } catch (err: any) {
      setError(err.message || 'Failed to remove item');
    }
  };

  const handleLinked = (newItem: any) => {
    // Build a join-table-like record with display_value for immediate display
    const relatedIdField = `${relatedModel.toLowerCase()}_id`;
    const displayValue = displayColumns.map(col => newItem[col] || '').filter(Boolean).join(' ');
    const joinRecord = {
      id: `temp-${newItem.id}`,  // Temporary ID until reload
      [relatedIdField]: newItem.id,
      display_value: displayValue,
    };
    setRelatedItems(prev => [...prev, joinRecord]);
    setShowPicker(false);
  };

  const getFieldLabel = (fieldName: string): string => {
    return metadata?.fields?.[fieldName]?.label || fieldName;
  };

  return (
    <div className="related-items-section">
      <div className="flex justify-between items-center mb-4">
        <h3 className="text-lg font-semibold text-gray-900">
          {title} ({relatedItems.length})
        </h3>
        {permissions.canCreate && (
          <button
            onClick={() => {
              if (addNewUrl) {
                navigate(addNewUrl.replace('{parentId}', parentId));
              } else {
                setShowPicker(true);
              }
            }}
            className="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700"
          >
            {isManyToMany ? `Add ${relatedModel}` : `Add New`}
          </button>
        )}
      </div>

      {error && (
        <div className="bg-red-50 border border-red-200 rounded-md p-3 mb-4">
          <p className="text-red-800 text-sm">{error}</p>
        </div>
      )}

      {loading && (
        <div className="flex items-center justify-center p-6">
          <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
          <span className="ml-2 text-gray-600 text-sm">Loading...</span>
        </div>
      )}

      {showPicker && isManyToMany && (
        <RecordPicker
          parentModel={parentModel}
          parentId={parentId}
          relationship={relationship}
          relatedModel={relatedModel}
          displayColumns={displayColumns}
          existingIds={relatedItems.map(i => {
            const relatedIdField = `${relatedModel.toLowerCase()}_id`;
            return i[relatedIdField] || i.id;
          })}
          onLinked={handleLinked}
          onClose={() => setShowPicker(false)}
        />
      )}

      {!loading && relatedItems.length > 0 && (
        <div className="space-y-2">
          {relatedItems.map((item) => (
            <div key={item.id} className="bg-white border border-gray-200 rounded-lg p-3 flex justify-between items-center">
              <div className="flex-1">
                {isManyToMany && item.display_value ? (
                  <span className="text-sm text-gray-900">{item.display_value}</span>
                ) : (
                  <div className="grid grid-cols-1 gap-1">
                    {displayColumns.map((fieldName) => (
                      <div key={fieldName} className="flex">
                        <span className="text-sm font-medium text-gray-500 w-28 flex-shrink-0">
                          {getFieldLabel(fieldName)}:
                        </span>
                        <span className="text-sm text-gray-900">{item[fieldName] || '-'}</span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
              {actions.includes('delete') && permissions.canDelete && (
                <button
                  onClick={() => handleUnlink(item)}
                  className="text-red-600 hover:text-red-800 text-sm ml-4"
                >
                  {isManyToMany ? 'Remove' : 'Delete'}
                </button>
              )}
            </div>
          ))}
        </div>
      )}

      {!loading && relatedItems.length === 0 && (
        <div className="text-center py-6 text-gray-500 border-2 border-dashed border-gray-300 rounded-lg">
          <p>No {title.toLowerCase()} yet.</p>
        </div>
      )}
    </div>
  );
};

/** Picker component for searching and linking existing records */
const RecordPicker: React.FC<{
  parentModel: string;
  parentId: string;
  relationship: string;
  relatedModel: string;
  displayColumns: string[];
  existingIds: string[];
  onLinked: (item: any) => void;
  onClose: () => void;
}> = ({ parentModel, parentId, relationship, relatedModel, displayColumns, existingIds, onLinked, onClose }) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [results, setResults] = useState<any[]>([]);
  const [searching, setSearching] = useState(false);
  const [linking, setLinking] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  useEffect(() => {
    const timer = setTimeout(async () => {
      if (searchTerm.length < 1) {
        setResults([]);
        return;
      }
      setSearching(true);
      try {
        const response = await apiService.getList(relatedModel, 1, 20, {}, searchTerm);
        const filtered = (response.data || []).filter((r: any) => !existingIds.includes(r.id));
        setResults(filtered);
      } catch {
        setResults([]);
      } finally {
        setSearching(false);
      }
    }, 300);
    return () => clearTimeout(timer);
  }, [searchTerm, relatedModel, existingIds]);

  const handleLink = async (record: any) => {
    setLinking(record.id);
    try {
      const response = await apiService.linkRecord(parentModel, parentId, relationship, record.id);
      if (response.success !== false) {
        onLinked(record);
      }
    } catch (err: any) {
      console.error('Failed to link record:', err);
    } finally {
      setLinking(null);
    }
  };

  const getDisplayValue = (record: any): string => {
    return displayColumns.map(col => record[col] || '').filter(Boolean).join(' ');
  };

  return (
    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
      <div className="flex justify-between items-center mb-3">
        <h4 className="text-md font-medium text-blue-800">Search {relatedModel}</h4>
        <button onClick={onClose} className="text-gray-500 hover:text-gray-700 text-sm">Cancel</button>
      </div>
      <input
        ref={inputRef}
        type="text"
        value={searchTerm}
        onChange={(e) => setSearchTerm(e.target.value)}
        placeholder={`Type to search ${relatedModel.toLowerCase()}...`}
        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 mb-3"
      />
      {searching && <p className="text-sm text-gray-500 mb-2">Searching...</p>}
      {results.length > 0 && (
        <div className="space-y-1 max-h-48 overflow-y-auto">
          {results.map((record) => (
            <div key={record.id} className="flex justify-between items-center bg-white border border-gray-200 rounded px-3 py-2">
              <span className="text-sm text-gray-900">{getDisplayValue(record)}</span>
              <button
                onClick={() => handleLink(record)}
                disabled={linking === record.id}
                className="px-2 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50"
              >
                {linking === record.id ? 'Adding...' : 'Add'}
              </button>
            </div>
          ))}
        </div>
      )}
      {searchTerm.length > 0 && !searching && results.length === 0 && (
        <p className="text-sm text-gray-500">No results found.</p>
      )}
    </div>
  );
};

/**
 * Find the foreign key field name in the related model's metadata that
 * references the parent model. Falls back to a lowercase guess if
 * metadata hasn't loaded yet.
 */
function findForeignKeyField(metadata: any, parentModel: string): string {
  if (metadata?.fields) {
    for (const [fieldName, field] of Object.entries<any>(metadata.fields)) {
      if (field.type === 'RelatedRecord' && field.relatedModel === parentModel) {
        return fieldName;
      }
    }
  }
  // Fallback: strip trailing 's' and append '_id'
  const singular = parentModel.replace(/s$/i, '').toLowerCase();
  return `${singular}_id`;
}

export default RelatedItemsSection;
