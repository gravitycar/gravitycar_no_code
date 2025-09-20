/* eslint-disable @typescript-eslint/no-explicit-any, react-hooks/exhaustive-deps */
import React, { useState, useEffect, useCallback } from 'react';
import { apiService } from '../../services/api';
import { useModelMetadata } from '../../hooks/useModelMetadata';

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

interface RelatedItemsSectionProps {
  title: string;
  parentModel: string;
  parentId: string;
  relationship: string;
  relatedModel: string;
  displayColumns: string[];
  actions?: ('create' | 'edit' | 'delete' | 'reorder')[];
  createFields?: string[];
  editFields?: string[];
  allowInlineCreate?: boolean;
  allowInlineEdit?: boolean;
  maxItems?: number;
  sortable?: boolean;
  groupBy?: string;
  filterBy?: Record<string, any>;
  permissions?: {
    canCreate: boolean;
    canEdit: boolean;
    canDelete: boolean;
    canReorder: boolean;
  };
}

interface RelatedItemCardProps {
  item: any;
  modelName: string;
  displayColumns: string[];
  actions: string[];
  permissions: {
    canEdit: boolean;
    canDelete: boolean;
  };
  isEditing: boolean;
  onEdit: () => void;
  onSave: (data: any) => void;
  onCancel: () => void;
  onDelete: () => void;
}

const RelatedItemCard: React.FC<RelatedItemCardProps> = ({
  item,
  modelName,
  displayColumns,
  actions,
  permissions,
  isEditing,
  onEdit,
  onSave,
  onCancel,
  onDelete
}) => {
  const { metadata } = useModelMetadata(modelName);
  const [editData, setEditData] = useState<any>({});
  
  useEffect(() => {
    if (isEditing) {
      // Initialize edit data with current item values
      const initialData = displayColumns.reduce((acc, field) => {
        acc[field] = item[field];
        return acc;
      }, {} as any);
      setEditData(initialData);
    }
  }, [isEditing, item, displayColumns]);
  
  const handleSave = () => {
    onSave(editData);
  };
  
  const renderFieldValue = (_fieldName: string, value: any, fieldMeta: any) => {
    if (value === null || value === undefined) return '-';
    
    // Handle different field types
    switch (fieldMeta?.type) {
      case 'boolean':
        return value ? 'Yes' : 'No';
      case 'date':
        return new Date(value).toLocaleDateString();
      case 'datetime':
        return new Date(value).toLocaleString();
      default:
        return String(value);
    }
  };
  
  if (isEditing) {
    return (
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="grid grid-cols-1 gap-4">
          {displayColumns.map((fieldName) => {
            const fieldMeta = metadata?.fields?.[fieldName];
            return (
              <div key={fieldName}>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  {fieldMeta?.label || fieldName}
                </label>
                <input
                  type="text"
                  value={editData[fieldName] || ''}
                  onChange={(e) => setEditData((prev: any) => ({ ...prev, [fieldName]: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            );
          })}
        </div>
        
        <div className="flex justify-end space-x-2 mt-4">
          <button onClick={onCancel} className="px-3 py-1 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
            Cancel
          </button>
          <button onClick={handleSave} className="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700">
            Save
          </button>
        </div>
      </div>
    );
  }
  
  return (
    <div className="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition-shadow">
      <div className="flex justify-between items-start">
        <div className="flex-1 grid grid-cols-1 gap-2">
          {displayColumns.map((fieldName) => {
            const fieldMeta = metadata?.fields?.[fieldName];
            const value = item[fieldName];
            
            return (
              <div key={fieldName} className="flex">
                <span className="text-sm font-medium text-gray-500 w-24 flex-shrink-0">
                  {fieldMeta?.label || fieldName}:
                </span>
                <span className="text-sm text-gray-900 flex-1">
                  {renderFieldValue(fieldName, value, fieldMeta)}
                </span>
              </div>
            );
          })}
        </div>
        
        <div className="flex space-x-2 ml-4">
          {actions.includes('edit') && permissions.canEdit && (
            <button
              onClick={onEdit}
              className="text-blue-600 hover:text-blue-800 text-sm"
            >
              Edit
            </button>
          )}
          {actions.includes('delete') && permissions.canDelete && (
            <button
              onClick={onDelete}
              className="text-red-600 hover:text-red-800 text-sm"
            >
              Delete
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

const RelatedItemsSection: React.FC<RelatedItemsSectionProps> = ({
  title,
  parentModel,
  parentId,
  relationship,
  relatedModel,
  displayColumns,
  actions = ['create', 'edit', 'delete'],
  createFields,
  allowInlineCreate = true,
  // sortable = false,
  permissions = { canCreate: true, canEdit: true, canDelete: true, canReorder: false }
}) => {
  // State management
  const [relatedItems, setRelatedItems] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<any | null>(null);
  const [showInlineCreate, setShowInlineCreate] = useState(false);
  
  // Custom hooks
  const { metadata } = useModelMetadata(relatedModel);
  
  // Load related items
  const loadRelatedItems = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      
      // Use a generic API call to get related records
      // This would need to be implemented in the backend API
      const response = await apiService.getList(relatedModel, 1, 100, {
        [`${parentModel.toLowerCase()}_id`]: parentId
      });
      
      setRelatedItems(response.data || []);
    } catch (err) {
      const errorMessage = getErrorMessage(err);
      setError(errorMessage);
      notify.error(`Failed to load ${title.toLowerCase()}: ${errorMessage}`);
    } finally {
      setLoading(false);
    }
  }, [parentModel, parentId, relationship, title, notify, relatedModel]);
  
  // Load data on mount
  useEffect(() => {
    loadRelatedItems();
  }, [loadRelatedItems]);
  
  // CRUD operations
  // const handleCreate = useCallback(async (data: any) => {
  //   try {
  //     // Auto-populate parent relationship field
  //     const createData = {
  //       ...data,
  //       [`${parentModel.toLowerCase()}_id`]: parentId
  //     };
  //     
  //     await apiService.create(relatedModel, createData);
  //     await loadRelatedItems();
  //     setIsCreateModalOpen(false);
  //     setShowInlineCreate(false);
  //     notify.success(`${relatedModel} created successfully`);
  //   } catch (err) {
  //     notify.error(`Failed to create ${relatedModel}: ${getErrorMessage(err)}`);
  //   }
  // }, [parentModel, parentId, relatedModel, loadRelatedItems, notify]);
  
  const handleEdit = useCallback(async (item: any, data: any) => {
    try {
      await apiService.update(relatedModel, item.id, data);
      await loadRelatedItems();
      setEditingItem(null);
      notify.success(`${relatedModel} updated successfully`);
    } catch (err) {
      notify.error(`Failed to update ${relatedModel}: ${getErrorMessage(err)}`);
    }
  }, [relatedModel, loadRelatedItems, notify]);
  
  const handleDelete = useCallback(async (item: any) => {
    if (!confirm(`Are you sure you want to delete this ${relatedModel.toLowerCase()}?`)) {
      return;
    }
    
    try {
      await apiService.delete(relatedModel, item.id);
      await loadRelatedItems();
      notify.success(`${relatedModel} deleted successfully`);
    } catch (err) {
      notify.error(`Failed to delete ${relatedModel}: ${getErrorMessage(err)}`);
    }
  }, [relatedModel, loadRelatedItems, notify]);
  
  // Render component
  return (
    <div className="related-items-section">
      {/* Header */}
      <div className="flex justify-between items-center mb-4">
        <h3 className="text-lg font-semibold text-gray-900">
          {title} ({relatedItems.length})
        </h3>
        
        {permissions.canCreate && (
          <div className="flex space-x-2">
            {allowInlineCreate && createFields && (
              <button
                onClick={() => setShowInlineCreate(!showInlineCreate)}
                className="px-3 py-1 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300"
              >
                {showInlineCreate ? 'Cancel' : 'Quick Add'}
              </button>
            )}
            <button
              onClick={() => setIsCreateModalOpen(true)}
              className="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700"
            >
              Add {relatedModel}
            </button>
          </div>
        )}
      </div>
      
      {/* Error state */}
      {error && (
        <div className="bg-red-50 border border-red-200 rounded-md p-4 mb-4">
          <p className="text-red-800">{error}</p>
          <button 
            onClick={loadRelatedItems}
            className="text-red-600 hover:text-red-800 text-sm underline mt-2"
          >
            Try again
          </button>
        </div>
      )}
      
      {/* Loading state */}
      {loading && (
        <div className="flex items-center justify-center p-8">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <span className="ml-2 text-gray-600">Loading {title.toLowerCase()}...</span>
        </div>
      )}
      
      {/* Inline create form */}
      {showInlineCreate && createFields && (
        <div className="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
          <h4 className="text-md font-medium text-green-800 mb-3">Quick Add {relatedModel}</h4>
          <div className="grid grid-cols-1 gap-3">
            {createFields.map((fieldName) => {
              const fieldMeta = metadata?.fields?.[fieldName];
              return (
                <div key={fieldName}>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    {fieldMeta?.label || fieldName}
                  </label>
                  <input
                    type="text"
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                    placeholder={`Enter ${fieldMeta?.label || fieldName}`}
                  />
                </div>
              );
            })}
          </div>
          <div className="flex justify-end space-x-2 mt-4">
            <button 
              onClick={() => setShowInlineCreate(false)}
              className="px-3 py-1 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300"
            >
              Cancel
            </button>
            <button 
              onClick={() => {/* TODO: Implement quick create */}}
              className="px-3 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700"
            >
              Create
            </button>
          </div>
        </div>
      )}
      
      {/* Related items list */}
      {!loading && relatedItems.length > 0 && (
        <div className="space-y-2">
          {relatedItems.map((item) => (
            <RelatedItemCard
              key={item.id}
              item={item}
              modelName={relatedModel}
              displayColumns={displayColumns}
              actions={actions}
              permissions={permissions}
              isEditing={editingItem?.id === item.id}
              onEdit={() => setEditingItem(item)}
              onSave={(data) => handleEdit(item, data)}
              onCancel={() => setEditingItem(null)}
              onDelete={() => handleDelete(item)}
            />
          ))}
        </div>
      )}
      
      {/* Empty state */}
      {!loading && relatedItems.length === 0 && (
        <div className="text-center py-8 text-gray-500 border-2 border-dashed border-gray-300 rounded-lg">
          <p>No {title.toLowerCase()} found.</p>
          {permissions.canCreate && (
            <button
              onClick={() => setIsCreateModalOpen(true)}
              className="text-blue-600 hover:text-blue-800 mt-2"
            >
              Create the first one
            </button>
          )}
        </div>
      )}
      
      {/* TODO: Create modal would go here */}
      {isCreateModalOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 className="text-lg font-semibold mb-4">Create New {relatedModel}</h3>
            <p className="text-gray-600">Modal form would go here...</p>
            <div className="flex justify-end space-x-2 mt-4">
              <button
                onClick={() => setIsCreateModalOpen(false)}
                className="px-3 py-1 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300"
              >
                Cancel
              </button>
              <button
                onClick={() => setIsCreateModalOpen(false)}
                className="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700"
              >
                Create
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default RelatedItemsSection;
