/* eslint-disable @typescript-eslint/no-explicit-any, react-hooks/exhaustive-deps */
import React, { useState, useEffect, useCallback } from 'react';
import { apiService } from '../../services/api';
// import { useModelMetadata } from '../../hooks/useModelMetadata';

// Simple error handling function
const getErrorMessage = (error: any): string => {
  if (error.response?.data?.message) {
    return error.response.data.message;
  }
  if (error.message) {
    return error.message;
  }
  return 'An unexpected error occurred';
};

// Simple notification function (console log for now)
const notify = {
  success: (message: string) => console.log('SUCCESS:', message),
  error: (message: string) => console.error('ERROR:', message)
};

interface ManyToManyManagerProps {
  title: string;
  sourceModel: string;
  sourceId: string;
  relationship: string;
  targetModel: string;
  displayColumns: string[];
  additionalFields?: string[];
  allowBulkAssign?: boolean;
  allowBulkRemove?: boolean;
  showHistory?: boolean;
  searchable?: boolean;
  filterable?: boolean;
  permissions?: {
    canAssign: boolean;
    canRemove: boolean;
    canViewHistory: boolean;
  };
}

interface AssignedItemsPaneProps {
  title: string;
  items: any[];
  selectedItems: Set<string>;
  onSelectionChange: (selected: Set<string>) => void;
  onRemove: (itemIds: string[]) => void;
  additionalFields?: string[];
  permissions: {
    canRemove: boolean;
  };
}

interface AvailableItemsPaneProps {
  title: string;
  items: any[];
  selectedItems: Set<string>;
  onSelectionChange: (selected: Set<string>) => void;
  onAssign: (itemIds: string[]) => void;
  searchable?: boolean;
  filterable?: boolean;
  permissions: {
    canAssign: boolean;
  };
}

const AssignedItemsPane: React.FC<AssignedItemsPaneProps> = ({
  title,
  items,
  selectedItems,
  onSelectionChange,
  onRemove,
  permissions
}) => {
  const handleItemToggle = (itemId: string) => {
    const newSelected = new Set(selectedItems);
    if (newSelected.has(itemId)) {
      newSelected.delete(itemId);
    } else {
      newSelected.add(itemId);
    }
    onSelectionChange(newSelected);
  };

  const handleSelectAll = () => {
    if (selectedItems.size === items.length) {
      onSelectionChange(new Set());
    } else {
      onSelectionChange(new Set(items.map(item => item.id)));
    }
  };

  const handleRemoveSelected = () => {
    if (selectedItems.size > 0) {
      onRemove(Array.from(selectedItems));
    }
  };

  return (
    <div className="bg-white border border-gray-200 rounded-lg p-4">
      <div className="flex justify-between items-center mb-4">
        <h4 className="text-md font-semibold text-gray-900">{title}</h4>
        {permissions.canRemove && selectedItems.size > 0 && (
          <button
            onClick={handleRemoveSelected}
            className="px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700"
          >
            Remove ({selectedItems.size})
          </button>
        )}
      </div>

      {items.length > 0 && (
        <div className="mb-2">
          <label className="flex items-center text-sm text-gray-600">
            <input
              type="checkbox"
              checked={selectedItems.size === items.length && items.length > 0}
              onChange={handleSelectAll}
              className="mr-2"
            />
            Select All ({items.length})
          </label>
        </div>
      )}

      <div className="space-y-2 max-h-64 overflow-y-auto">
        {items.map((item) => (
          <div
            key={item.id}
            className={`flex items-center justify-between p-2 border rounded ${
              selectedItems.has(item.id) ? 'bg-blue-50 border-blue-200' : 'border-gray-200'
            }`}
          >
            <label className="flex items-center cursor-pointer flex-1">
              <input
                type="checkbox"
                checked={selectedItems.has(item.id)}
                onChange={() => handleItemToggle(item.id)}
                className="mr-2"
              />
              <span className="text-sm">
                {item.name || item.title || item.username || `#${item.id}`}
              </span>
            </label>
          </div>
        ))}
      </div>

      {items.length === 0 && (
        <div className="text-center py-8 text-gray-500">
          <p>No items assigned</p>
        </div>
      )}
    </div>
  );
};

const AvailableItemsPane: React.FC<AvailableItemsPaneProps> = ({
  title,
  items,
  selectedItems,
  onSelectionChange,
  onAssign,
  searchable = true,
  permissions
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [filteredItems, setFilteredItems] = useState(items);

  useEffect(() => {
    if (searchTerm) {
      const filtered = items.filter(item => {
        const searchableText = `${item.name || ''} ${item.title || ''} ${item.username || ''} ${item.email || ''}`.toLowerCase();
        return searchableText.includes(searchTerm.toLowerCase());
      });
      setFilteredItems(filtered);
    } else {
      setFilteredItems(items);
    }
  }, [items, searchTerm]);

  const handleItemToggle = (itemId: string) => {
    const newSelected = new Set(selectedItems);
    if (newSelected.has(itemId)) {
      newSelected.delete(itemId);
    } else {
      newSelected.add(itemId);
    }
    onSelectionChange(newSelected);
  };

  const handleSelectAll = () => {
    if (selectedItems.size === filteredItems.length) {
      onSelectionChange(new Set());
    } else {
      onSelectionChange(new Set(filteredItems.map(item => item.id)));
    }
  };

  const handleAssignSelected = () => {
    if (selectedItems.size > 0) {
      onAssign(Array.from(selectedItems));
    }
  };

  return (
    <div className="bg-white border border-gray-200 rounded-lg p-4">
      <div className="flex justify-between items-center mb-4">
        <h4 className="text-md font-semibold text-gray-900">{title}</h4>
        {permissions.canAssign && selectedItems.size > 0 && (
          <button
            onClick={handleAssignSelected}
            className="px-3 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700"
          >
            Assign ({selectedItems.size})
          </button>
        )}
      </div>

      {searchable && (
        <div className="mb-4">
          <input
            type="text"
            placeholder="Search available items..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
      )}

      {filteredItems.length > 0 && (
        <div className="mb-2">
          <label className="flex items-center text-sm text-gray-600">
            <input
              type="checkbox"
              checked={selectedItems.size === filteredItems.length && filteredItems.length > 0}
              onChange={handleSelectAll}
              className="mr-2"
            />
            Select All ({filteredItems.length})
          </label>
        </div>
      )}

      <div className="space-y-2 max-h-64 overflow-y-auto">
        {filteredItems.map((item) => (
          <div
            key={item.id}
            className={`flex items-center justify-between p-2 border rounded ${
              selectedItems.has(item.id) ? 'bg-green-50 border-green-200' : 'border-gray-200'
            }`}
          >
            <label className="flex items-center cursor-pointer flex-1">
              <input
                type="checkbox"
                checked={selectedItems.has(item.id)}
                onChange={() => handleItemToggle(item.id)}
                className="mr-2"
              />
              <span className="text-sm">
                {item.name || item.title || item.username || `#${item.id}`}
              </span>
            </label>
          </div>
        ))}
      </div>

      {filteredItems.length === 0 && searchTerm && (
        <div className="text-center py-8 text-gray-500">
          <p>No items found matching "{searchTerm}"</p>
        </div>
      )}

      {filteredItems.length === 0 && !searchTerm && (
        <div className="text-center py-8 text-gray-500">
          <p>No items available</p>
        </div>
      )}
    </div>
  );
};

const ManyToManyManager: React.FC<ManyToManyManagerProps> = ({
  title,
  sourceModel,
  sourceId,
  relationship,
  targetModel,
  // displayColumns,
  // allowBulkAssign = true,
  // allowBulkRemove = true,
  showHistory = false,
  searchable = true,
  filterable = false,
  permissions = { canAssign: true, canRemove: true, canViewHistory: false }
}) => {
  // State for assigned and available items
  const [assignedItems, setAssignedItems] = useState<any[]>([]);
  const [availableItems, setAvailableItems] = useState<any[]>([]);
  const [selectedAssigned, setSelectedAssigned] = useState<Set<string>>(new Set());
  const [selectedAvailable, setSelectedAvailable] = useState<Set<string>>(new Set());
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // const { metadata } = useModelMetadata(targetModel);

  // Load assigned items
  const loadAssignedItems = useCallback(async () => {
    try {
      setError(null);
      // This would need to be implemented in the backend API to get related items
      // For now, we'll use a placeholder implementation
      const response = await apiService.getList(targetModel, 1, 100);
      
      // TODO: Filter to only assigned items - this would need backend support
      setAssignedItems(response.data || []);
    } catch (err) {
      const errorMessage = getErrorMessage(err);
      setError(errorMessage);
      notify.error(`Failed to load assigned ${targetModel}s: ${errorMessage}`);
    }
  }, [sourceModel, sourceId, relationship, targetModel]);

  // Load available items
  const loadAvailableItems = useCallback(async () => {
    try {
      setError(null);
      const response = await apiService.getList(targetModel, 1, 100);
      
      // TODO: Filter to exclude assigned items - this would need backend support
      const assignedIds = assignedItems.map(item => item.id);
      const available = (response.data || []).filter((item: any) => !assignedIds.includes(item.id));
      setAvailableItems(available);
    } catch (err) {
      const errorMessage = getErrorMessage(err);
      setError(errorMessage);
      notify.error(`Failed to load available ${targetModel}s: ${errorMessage}`);
    }
  }, [targetModel, assignedItems]);

  // Load data on mount
  useEffect(() => {
    const loadData = async () => {
      setLoading(true);
      await loadAssignedItems();
      setLoading(false);
    };
    loadData();
  }, [loadAssignedItems]);

  useEffect(() => {
    loadAvailableItems();
  }, [loadAvailableItems]);

  // Assignment operations
  const handleAssignItems = useCallback(async (itemIds: string[]) => {
    try {
      // TODO: Implement backend API call for assigning relationship
      // For now, simulate the operation
      const itemsToAssign = availableItems.filter(item => itemIds.includes(item.id));
      setAssignedItems(prev => [...prev, ...itemsToAssign]);
      setAvailableItems(prev => prev.filter(item => !itemIds.includes(item.id)));
      setSelectedAvailable(new Set());
      
      notify.success(`Assigned ${itemIds.length} ${targetModel}(s)`);
    } catch (err) {
      notify.error(`Failed to assign ${targetModel}s: ${getErrorMessage(err)}`);
    }
  }, [availableItems, targetModel]);

  const handleRemoveItems = useCallback(async (itemIds: string[]) => {
    try {
      // TODO: Implement backend API call for removing relationship
      // For now, simulate the operation
      const itemsToRemove = assignedItems.filter(item => itemIds.includes(item.id));
      setAvailableItems(prev => [...prev, ...itemsToRemove]);
      setAssignedItems(prev => prev.filter(item => !itemIds.includes(item.id)));
      setSelectedAssigned(new Set());
      
      notify.success(`Removed ${itemIds.length} ${targetModel}(s)`);
    } catch (err) {
      notify.error(`Failed to remove ${targetModel}s: ${getErrorMessage(err)}`);
    }
  }, [assignedItems, targetModel]);

  if (loading) {
    return (
      <div className="flex items-center justify-center p-8">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span className="ml-2 text-gray-600">Loading {title.toLowerCase()}...</span>
      </div>
    );
  }

  return (
    <div className="many-to-many-manager">
      <div className="mb-6">
        <h3 className="text-xl font-semibold text-gray-900">{title}</h3>
        {error && (
          <div className="mt-2 bg-red-50 border border-red-200 rounded-md p-3">
            <p className="text-red-800 text-sm">{error}</p>
          </div>
        )}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Assigned Items Pane */}
        <AssignedItemsPane
          title={`Assigned ${targetModel}s`}
          items={assignedItems}
          selectedItems={selectedAssigned}
          onSelectionChange={setSelectedAssigned}
          onRemove={handleRemoveItems}
          permissions={{ canRemove: permissions.canRemove }}
        />
        
        {/* Available Items Pane */}
        <AvailableItemsPane
          title={`Available ${targetModel}s`}
          items={availableItems}
          selectedItems={selectedAvailable}
          onSelectionChange={setSelectedAvailable}
          onAssign={handleAssignItems}
          searchable={searchable}
          filterable={filterable}
          permissions={{ canAssign: permissions.canAssign }}
        />
      </div>
      
      {/* Bulk Actions Summary */}
      {(selectedAssigned.size > 0 || selectedAvailable.size > 0) && (
        <div className="mt-6 bg-gray-50 border border-gray-200 rounded-lg p-4">
          <div className="flex justify-between items-center">
            <div className="text-sm text-gray-600">
              {selectedAssigned.size > 0 && (
                <span className="mr-4">
                  {selectedAssigned.size} assigned item(s) selected for removal
                </span>
              )}
              {selectedAvailable.size > 0 && (
                <span>
                  {selectedAvailable.size} available item(s) selected for assignment
                </span>
              )}
            </div>
            
            <div className="flex space-x-2">
              {selectedAssigned.size > 0 && permissions.canRemove && (
                <button
                  onClick={() => handleRemoveItems(Array.from(selectedAssigned))}
                  className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
                >
                  Remove Selected
                </button>
              )}
              {selectedAvailable.size > 0 && permissions.canAssign && (
                <button
                  onClick={() => handleAssignItems(Array.from(selectedAvailable))}
                  className="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                >
                  Assign Selected
                </button>
              )}
            </div>
          </div>
        </div>
      )}
      
      {/* Assignment History */}
      {showHistory && permissions.canViewHistory && (
        <div className="mt-6 bg-white border border-gray-200 rounded-lg p-4">
          <h4 className="text-md font-semibold text-gray-900 mb-3">Assignment History</h4>
          <p className="text-gray-500 text-sm">History functionality would be implemented here...</p>
        </div>
      )}
    </div>
  );
};

export default ManyToManyManager;
