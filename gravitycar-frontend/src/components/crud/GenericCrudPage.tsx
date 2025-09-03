import React, { useState, useEffect } from 'react';
import { useNotify } from '../../contexts/NotificationContext';
import { useModelMetadata } from '../../hooks/useModelMetadata';
import { apiService } from '../../services/api';
import { ErrorBoundary } from '../error/ErrorBoundary';
import { DataWrapper } from '../error/DataWrapper';
import ModelForm from '../forms/ModelForm';
import Modal from '../ui/Modal';
import { getErrorMessage } from '../../utils/errors';
import type { PaginatedResponse, ModelMetadata, FieldMetadata } from '../../types';
import TMDBEnhancedCreateForm from '../movies/TMDBEnhancedCreateForm';

interface GenericCrudPageProps {
  modelName: string;
  title: string;
  description?: string;
  defaultDisplayMode?: 'table' | 'grid' | 'list';
  customGridRenderer?: (item: any, metadata: ModelMetadata, onEdit: (item: any) => void, onDelete: (item: any) => void) => React.ReactNode;
}

interface PageState {
  items: any[];
  loading: boolean;
  error: string | null;
  pagination: {
    page: number;
    limit: number;
    total: number;
    totalPages: number;
  };
  searchTerm: string;
  isCreateModalOpen: boolean;
  isEditModalOpen: boolean;
  selectedItem: any | null;
  deletingItem: any | null;
  displayMode: 'table' | 'grid' | 'list';
  sorting: {
    field: string | null;
    direction: 'asc' | 'desc';
  };
}

/**
 * Generic CRUD page component that works with any model based on metadata
 * This ensures all models use the same consistent UI patterns and behavior
 */
const GenericCrudPage: React.FC<GenericCrudPageProps> = ({
  modelName,
  title,
  description,
  defaultDisplayMode = 'table',
  customGridRenderer
}) => {
  const notify = useNotify();
  const { metadata, loading: metadataLoading, error: metadataError } = useModelMetadata(modelName);
  
  const [state, setState] = useState<PageState>({
    items: [],
    loading: true,
    error: null,
    pagination: {
      page: 1,
      limit: 10,
      total: 0,
      totalPages: 0
    },
    searchTerm: '',
    isCreateModalOpen: false,
    isEditModalOpen: false,
    selectedItem: null,
    deletingItem: null,
    displayMode: defaultDisplayMode,
    sorting: {
      field: null,
      direction: 'asc'
    }
  });

  // Load items from API
  const loadItems = async (page: number = 1, search: string = '', sortField?: string, sortDirection?: 'asc' | 'desc') => {
    try {
      setState(prev => ({ ...prev, loading: true, error: null }));
      
      const filters: any = {};
      if (search.trim()) {
        filters.search = search.trim();
      }
      
      // Add sorting if specified
      const currentSortField = sortField ?? state.sorting.field;
      const currentSortDirection = sortDirection ?? state.sorting.direction;
      
      if (currentSortField) {
        filters.sort = `${currentSortField}:${currentSortDirection}`;
      }

      const response: PaginatedResponse<any> = await apiService.getList(
        modelName,
        page,
        state.pagination.limit,
        filters
      );
      
      setState(prev => ({
        ...prev,
        items: response.data || [],
        pagination: {
          ...prev.pagination,
          page: response.pagination?.current_page || page,
          total: response.pagination?.total_items || 0,
          totalPages: response.pagination?.total_pages || 1
        },
        sorting: {
          field: currentSortField,
          direction: currentSortDirection
        },
        loading: false,
        error: null,
      }));
    } catch (error) {
      console.error(`Failed to load ${modelName}:`, error);
      const errorMessage = getErrorMessage(error);
      setState(prev => ({
        ...prev,
        loading: false,
        error: errorMessage,
      }));
      notify.error(`Failed to load ${title.toLowerCase()}. Please try again.`);
    }
  };

  // Handle search
  const handleSearch = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    loadItems(1, state.searchTerm, state.sorting.field || undefined, state.sorting.direction);
  };

  // Handle sorting
  const handleSort = (fieldName: string) => {
    const isCurrentField = state.sorting.field === fieldName;
    const newDirection: 'asc' | 'desc' = isCurrentField && state.sorting.direction === 'asc' ? 'desc' : 'asc';
    
    loadItems(state.pagination.page, state.searchTerm, fieldName, newDirection);
  };

  // Handle pagination
  const handlePageChange = (newPage: number) => {
    if (newPage >= 1 && newPage <= state.pagination.totalPages) {
      loadItems(newPage, state.searchTerm, state.sorting.field || undefined, state.sorting.direction);
    }
  };

  // CRUD operations
  const handleCreate = () => {
    setState(prev => ({ ...prev, isCreateModalOpen: true }));
  };

  const handleEdit = (item: any) => {
    setState(prev => ({ 
      ...prev, 
      selectedItem: item, 
      isEditModalOpen: true 
    }));
  };

  const handleDelete = async (item: any) => {
    const itemName = getItemDisplayName(item, metadata);
    if (!window.confirm(`Are you sure you want to delete "${itemName}"?`)) {
      return;
    }

    try {
      await apiService.delete(modelName, item.id);
      notify.success(`${modelName.slice(0, -1)} deleted successfully`);
      loadItems(state.pagination.page, state.searchTerm);
    } catch (error) {
      console.error(`Failed to delete ${modelName}:`, error);
      const errorMessage = getErrorMessage(error);
      notify.error(`Failed to delete item: ${errorMessage}`);
    }
  };

  const handleFormSuccess = async (data?: any) => {
    // Handle success for both standard and custom forms
    if (data) {
      console.log(`✅ Successfully ${state.isCreateModalOpen ? 'created' : 'updated'} ${modelName}:`, data);
      if (state.isCreateModalOpen) {
        notify.success(`${modelName.slice(0, -1)} created successfully`);
      } else {
        notify.success(`${modelName.slice(0, -1)} updated successfully`);
      }
    }

    setState(prev => ({ 
      ...prev, 
      isCreateModalOpen: false, 
      isEditModalOpen: false, 
      selectedItem: null 
    }));
    loadItems(state.pagination.page, state.searchTerm);
  };

  const handleFormCancel = () => {
    setState(prev => ({ 
      ...prev, 
      isCreateModalOpen: false, 
      isEditModalOpen: false, 
      selectedItem: null 
    }));
  };

  // Get display name for an item
  const getItemDisplayName = (item: any, metadata: ModelMetadata | null): string => {
    if (!metadata || !item) return 'Item';
    
    // Try common name fields first
    const nameFields = ['name', 'title', 'username', 'email', 'label'];
    for (const field of nameFields) {
      if (item[field]) return String(item[field]);
    }
    
    // Use first string field
    const stringField = Object.entries(metadata.fields || {}).find(
      ([_, fieldMeta]) => fieldMeta.type === 'String'
    );
    if (stringField && item[stringField[0]]) {
      return String(item[stringField[0]]);
    }
    
    // Fallback to ID
    return `${modelName.slice(0, -1)} ${item.id}`;
  };

  // Render field value based on metadata
  const renderFieldValue = (_fieldName: string, value: any, fieldMeta: FieldMetadata) => {
    if (value === null || value === undefined || value === '') {
      return <span className="text-gray-400 italic">—</span>;
    }

    switch (fieldMeta.type) {
      case 'Boolean':
        return (
          <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
            value ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
          }`}>
            {value ? 'Yes' : 'No'}
          </span>
        );

      case 'DateTime':
        try {
          const date = new Date(value);
          return <span className="text-gray-700">{date.toLocaleDateString()}</span>;
        } catch {
          return <span className="text-gray-400">{String(value)}</span>;
        }

      case 'Email':
        return (
          <a href={`mailto:${value}`} className="text-blue-600 hover:text-blue-800">
            {value}
          </a>
        );

      case 'Image':
        return (
          <div className="flex items-center space-x-2">
            <img
              src={String(value)}
              alt={fieldMeta.label || 'Image'}
              className="w-12 h-16 object-cover rounded border border-gray-200"
              onError={(e) => {
                const target = e.target as HTMLImageElement;
                target.style.display = 'none';
                target.nextElementSibling?.classList.remove('hidden');
              }}
            />
            <div className="hidden text-gray-400 text-xs">
              <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" />
              </svg>
            </div>
          </div>
        );

      case 'Video':
        return (
          <a
            href={String(value)}
            target="_blank"
            rel="noopener noreferrer"
            className="text-blue-600 hover:text-blue-800 text-sm flex items-center space-x-1"
          >
            <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path d="M2 6a2 2 0 012-2h6l2 2h6a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" />
            </svg>
            <span>Watch</span>
          </a>
        );

      default:
        return <span className="text-gray-900">{String(value)}</span>;
    }
  };

  // Render table view
  const renderTableView = () => {
    if (!metadata?.ui?.listFields) return null;

    const listFields = metadata.ui.listFields;

    return (
      <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                {listFields.map((fieldName) => {
                  const fieldMeta = metadata.fields?.[fieldName];
                  const isCurrentSort = state.sorting.field === fieldName;
                  const sortDirection = isCurrentSort ? state.sorting.direction : null;
                  
                  return (
                    <th 
                      key={fieldName}
                      className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                      <button
                        onClick={() => handleSort(fieldName)}
                        className="flex items-center space-x-1 hover:text-gray-700 focus:outline-none"
                      >
                        <span>{fieldMeta?.label || fieldName.replace(/_/g, ' ')}</span>
                        <div className="flex flex-col">
                          <svg 
                            className={`w-3 h-3 ${isCurrentSort && sortDirection === 'asc' ? 'text-blue-600' : 'text-gray-400'}`}
                            fill="currentColor" 
                            viewBox="0 0 20 20"
                          >
                            <path fillRule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clipRule="evenodd" />
                          </svg>
                          <svg 
                            className={`w-3 h-3 -mt-1 ${isCurrentSort && sortDirection === 'desc' ? 'text-blue-600' : 'text-gray-400'}`}
                            fill="currentColor" 
                            viewBox="0 0 20 20"
                          >
                            <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                          </svg>
                        </div>
                      </button>
                    </th>
                  );
                })}
                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {state.items.length === 0 ? (
                <tr>
                  <td colSpan={listFields.length + 1} className="px-6 py-12 text-center text-gray-500">
                    {state.loading ? 'Loading...' : `No ${title.toLowerCase()} found`}
                  </td>
                </tr>
              ) : (
                state.items.map((item) => (
                  <tr key={item.id} className="hover:bg-gray-50">
                    {listFields.map((fieldName) => {
                      const fieldMeta = metadata.fields?.[fieldName];
                      return (
                        <td key={fieldName} className="px-6 py-4 whitespace-nowrap">
                          {fieldMeta ? renderFieldValue(fieldName, item[fieldName], fieldMeta) : String(item[fieldName] || '')}
                        </td>
                      );
                    })}
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <div className="flex justify-end space-x-2">
                        <button
                          onClick={() => handleEdit(item)}
                          className="text-blue-600 hover:text-blue-700"
                        >
                          Edit
                        </button>
                        <button
                          onClick={() => handleDelete(item)}
                          className="text-red-600 hover:text-red-700"
                        >
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    );
  };

  // Render grid view
  const renderGridView = () => {
    if (customGridRenderer) {
      return (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {state.items.map(item => (
            <div key={item.id}>
              {customGridRenderer(item, metadata!, handleEdit, handleDelete)}
            </div>
          ))}
        </div>
      );
    }

    if (!metadata) return null;

    // Default grid rendering with enhanced Image field support
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {state.items.map(item => {
          const listFields = metadata.ui?.listFields || [];
          
          // Find the first Image field for featured display
          const imageField = listFields.find(fieldName => 
            metadata.fields?.[fieldName]?.type === 'Image'
          );
          const imageValue = imageField ? item[imageField] : null;
          
          // Get remaining fields (excluding the featured image)
          const otherFields = listFields.filter(fieldName => fieldName !== imageField);
          
          return (
            <div key={item.id} className="bg-white rounded-lg shadow border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow">
              {/* Featured Image */}
              {imageValue && imageField && (
                <div className="w-full h-48 bg-gray-200 flex items-center justify-center">
                  <img
                    src={String(imageValue)}
                    alt={metadata.fields?.[imageField]?.label || 'Image'}
                    className="max-w-full max-h-full object-cover"
                    onError={(e) => {
                      const target = e.target as HTMLImageElement;
                      target.style.display = 'none';
                      const parent = target.parentElement;
                      if (parent) {
                        parent.innerHTML = `
                          <div class="text-gray-400 text-center p-4">
                            <svg class="w-12 h-12 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                              <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" />
                            </svg>
                            <span class="text-sm">Image not available</span>
                          </div>
                        `;
                      }
                    }}
                  />
                </div>
              )}
              
              <div className="p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">
                  {getItemDisplayName(item, metadata)}
                </h3>
                
                {otherFields.slice(0, 3).map(fieldName => {
                  const fieldMeta = metadata.fields?.[fieldName];
                  const value = item[fieldName];
                  if (!value) return null;
                  
                  return (
                    <div key={fieldName} className="mb-2">
                      <span className="text-sm font-medium text-gray-500">
                        {fieldMeta?.label || fieldName.replace(/_/g, ' ')}:
                      </span>
                      <div className="mt-1">
                        {fieldMeta ? renderFieldValue(fieldName, value, fieldMeta) : String(value)}
                      </div>
                    </div>
                  );
                })}
                
                <div className="flex justify-end space-x-2 mt-4 pt-4 border-t border-gray-200">
                  <button
                    onClick={() => handleEdit(item)}
                    className="text-blue-600 hover:text-blue-700 text-sm"
                  >
                    Edit
                  </button>
                  <button
                    onClick={() => handleDelete(item)}
                    className="text-red-600 hover:text-red-700 text-sm"
                  >
                    Delete
                  </button>
                </div>
              </div>
            </div>
          );
        })}
      </div>
    );
  };

  // Render pagination
  const renderPagination = () => {
    if (state.pagination.totalPages <= 1) return null;

    return (
      <div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
        <div className="flex items-center justify-between">
          <div className="flex-1 flex justify-between sm:hidden">
            <button
              onClick={() => handlePageChange(state.pagination.page - 1)}
              disabled={state.pagination.page <= 1}
              className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
            >
              Previous
            </button>
            <button
              onClick={() => handlePageChange(state.pagination.page + 1)}
              disabled={state.pagination.page >= state.pagination.totalPages}
              className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
            >
              Next
            </button>
          </div>
          <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p className="text-sm text-gray-700">
                Showing {((state.pagination.page - 1) * state.pagination.limit) + 1} to{' '}
                {Math.min(state.pagination.page * state.pagination.limit, state.pagination.total)} of{' '}
                {state.pagination.total} results
              </p>
            </div>
            <div>
              <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                {[...Array(Math.min(5, state.pagination.totalPages))].map((_, i) => {
                  const pageNum = i + 1;
                  return (
                    <button
                      key={pageNum}
                      onClick={() => handlePageChange(pageNum)}
                      className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                        pageNum === state.pagination.page
                          ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                          : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                      }`}
                    >
                      {pageNum}
                    </button>
                  );
                })}
              </nav>
            </div>
          </div>
        </div>
      </div>
    );
  };

  // Load data on mount and when dependencies change
  useEffect(() => {
    if (!metadataLoading && metadata) {
      loadItems();
    }
  }, [modelName, metadataLoading, metadata]);

  // Show loading state
  if (metadataLoading || (state.loading && state.items.length === 0)) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading {title.toLowerCase()}...</p>
        </div>
      </div>
    );
  }

  // Show metadata error
  if (metadataError) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <p className="text-red-600">Failed to load metadata: {metadataError}</p>
        </div>
      </div>
    );
  }

  return (
    <ErrorBoundary>
      <div className="min-h-screen bg-gray-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          {/* Header */}
          <div className="mb-8">
            <div className="flex justify-between items-center">
              <div>
                <h1 className="text-3xl font-bold text-gray-900">{title}</h1>
                {description && <p className="text-gray-600 mt-2">{description}</p>}
              </div>
              <div className="flex items-center space-x-4">
                {/* Display Mode Toggle */}
                <div className="flex border border-gray-300 rounded-md">
                  <button
                    onClick={() => setState(prev => ({ ...prev, displayMode: 'table' }))}
                    className={`px-3 py-1 text-sm ${
                      state.displayMode === 'table' 
                        ? 'bg-blue-600 text-white' 
                        : 'bg-white text-gray-700 hover:bg-gray-50'
                    }`}
                  >
                    Table
                  </button>
                  <button
                    onClick={() => setState(prev => ({ ...prev, displayMode: 'grid' }))}
                    className={`px-3 py-1 text-sm border-l border-gray-300 ${
                      state.displayMode === 'grid' 
                        ? 'bg-blue-600 text-white' 
                        : 'bg-white text-gray-700 hover:bg-gray-50'
                    }`}
                  >
                    Grid
                  </button>
                </div>
                
                <button
                  onClick={handleCreate}
                  className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors"
                >
                  Add New {modelName.slice(0, -1)}
                </button>
              </div>
            </div>
          </div>

          {/* Search */}
          <div className="bg-white p-4 rounded-lg shadow mb-6">
            <form onSubmit={handleSearch} className="flex gap-4">
              <input
                type="text"
                placeholder={`Search ${title.toLowerCase()}...`}
                value={state.searchTerm}
                onChange={(e) => setState(prev => ({ ...prev, searchTerm: e.target.value }))}
                className="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <button
                type="submit"
                className="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition-colors"
              >
                Search
              </button>
              {state.searchTerm && (
                <button
                  type="button"
                  onClick={() => {
                    setState(prev => ({ ...prev, searchTerm: '' }));
                    loadItems(1, '');
                  }}
                  className="bg-gray-400 text-white px-4 py-2 rounded-md hover:bg-gray-500 transition-colors"
                >
                  Clear
                </button>
              )}
            </form>
          </div>

          {/* Data Display */}
          <DataWrapper
            loading={state.loading}
            error={state.error}
            data={state.items}
            retry={() => loadItems(state.pagination.page, state.searchTerm)}
            fallback={
              <div className="text-center py-12 bg-white rounded-lg shadow">
                <h3 className="text-lg font-medium text-gray-900 mb-2">No {title.toLowerCase()} found</h3>
                <p className="text-gray-500 mb-4">
                  {state.searchTerm ? 'Try adjusting your search terms.' : `Get started by adding your first ${modelName.slice(0, -1).toLowerCase()}.`}
                </p>
                <button
                  onClick={handleCreate}
                  className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors"
                >
                  Add First {modelName.slice(0, -1)}
                </button>
              </div>
            }
          >
            {() => (
              <div>
                {state.displayMode === 'table' ? renderTableView() : renderGridView()}
                {renderPagination()}
              </div>
            )}
          </DataWrapper>

          {/* Create Modal */}
          {state.isCreateModalOpen && metadata && (
            <Modal
              isOpen={state.isCreateModalOpen}
              onClose={handleFormCancel}
              title={`Create New ${modelName.slice(0, -1)}`}
              size="2xl"
            >
              {modelName === 'Movies' ? (
                <TMDBEnhancedCreateForm
                  metadata={metadata}
                  onSuccess={handleFormSuccess}
                  onCancel={handleFormCancel}
                />
              ) : (
                <ModelForm
                  modelName={modelName}
                  onSuccess={handleFormSuccess}
                  onCancel={handleFormCancel}
                />
              )}
            </Modal>
          )}

          {/* Edit Modal */}
          {state.isEditModalOpen && state.selectedItem && metadata && (
            <Modal
              isOpen={state.isEditModalOpen}
              onClose={handleFormCancel}
              title={`Edit ${modelName.slice(0, -1)}`}
              size="2xl"
            >
              <ModelForm
                modelName={modelName}
                recordId={state.selectedItem.id}
                onSuccess={handleFormSuccess}
                onCancel={handleFormCancel}
              />
            </Modal>
          )}
        </div>
      </div>
    </ErrorBoundary>
  );
};

export default GenericCrudPage;
