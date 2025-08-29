# Relationship Management UI - Technical Implementation Guide

## Overview
This document provides detailed technical specifications for implementing relationship management UI components in the Gravitycar React frontend, building upon the existing RelatedRecordSelect component.

## Current State Analysis

### ✅ Existing Infrastructure
- **RelatedRecordSelect Component**: Production-ready with search, metadata-driven display, keyboard navigation
- **Backend Relationship Support**: OneToManyRelationship, ManyToManyRelationship, OneToOneRelationship classes
- **API Endpoints**: Basic CRUD operations for relationships via ModelBaseAPIController
- **Metadata System**: Full relationship metadata including additionalFields support

### 🎯 Target Relationships
1. **One-to-Many**: Movies ↔ Movie_Quotes (Movies has many quotes)
2. **Many-to-Many**: Users ↔ Roles (bidirectional assignment)
3. **Many-to-Many**: Users ↔ Permissions (advanced permission system)
4. **One-to-One**: User ↔ Profile (future implementation)

## Component Architecture

### 1. Enhanced RelatedRecordSelect (Foundation)

#### 1.1 Extended Interface
```tsx
interface EnhancedRelatedRecordProps extends FieldComponentProps {
  // Existing props from current implementation
  value: any;
  onChange: (value: any) => void;
  error?: string;
  disabled?: boolean;
  required?: boolean;
  fieldMetadata: FieldMetadata;
  placeholder?: string;
  label?: string;
  
  // NEW: Relationship-specific enhancements
  relationshipContext?: {
    type: 'OneToMany' | 'ManyToMany' | 'OneToOne';
    parentModel?: string;
    parentId?: string;
    relationship?: string;
    allowCreate?: boolean;
    autoPopulateFields?: Record<string, any>;
  };
  
  // NEW: UI enhancements
  showPreview?: boolean;        // Show preview card of related record
  allowDirectEdit?: boolean;    // Edit button next to selection
  displayTemplate?: string;     // Custom display format template
  showRelatedCount?: boolean;   // Show count of related items
  
  // NEW: Behavior enhancements
  onRelatedChange?: (relatedRecord: any) => void;
  preFilterOptions?: (options: any[]) => any[];
  onCreateNew?: () => void;     // Callback for "Create New" action
}
```

#### 1.2 Enhanced Features Implementation
```tsx
// Add "Create New" option to dropdown
const enhancedOptions = useMemo(() => {
  const baseOptions = [...options];
  
  if (relationshipContext?.allowCreate && !disabled) {
    baseOptions.unshift({
      value: '__CREATE_NEW__',
      label: `+ Create New ${relatedModel}`,
      isCreateOption: true
    });
  }
  
  return baseOptions;
}, [options, relationshipContext, disabled, relatedModel]);

// Handle create new selection
const handleCreateNew = useCallback(() => {
  if (onCreateNew) {
    onCreateNew();
  } else {
    // Default behavior: open create modal
    setIsCreateModalOpen(true);
  }
}, [onCreateNew]);
```

### 2. RelatedItemsSection Component (One-to-Many)

#### 2.1 Component Design
```tsx
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
  sortable = false,
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
  const notify = useNotify();
  
  // Load related items
  const loadRelatedItems = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);
      
      const response = await apiService.getRelatedRecords(
        parentModel,
        parentId,
        relationship
      );
      
      setRelatedItems(response.data || []);
    } catch (err) {
      const errorMessage = getErrorMessage(err);
      setError(errorMessage);
      notify.error(`Failed to load ${title.toLowerCase()}: ${errorMessage}`);
    } finally {
      setLoading(false);
    }
  }, [parentModel, parentId, relationship, title, notify]);
  
  // Load data on mount
  useEffect(() => {
    loadRelatedItems();
  }, [loadRelatedItems]);
  
  // CRUD operations
  const handleCreate = useCallback(async (data: any) => {
    try {
      // Auto-populate parent relationship field
      const createData = {
        ...data,
        [`${parentModel.toLowerCase()}_id`]: parentId
      };
      
      await apiService.create(relatedModel, createData);
      await loadRelatedItems();
      setIsCreateModalOpen(false);
      setShowInlineCreate(false);
      notify.success(`${relatedModel} created successfully`);
    } catch (err) {
      notify.error(`Failed to create ${relatedModel}: ${getErrorMessage(err)}`);
    }
  }, [parentModel, parentId, relatedModel, loadRelatedItems, notify]);
  
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
            {allowInlineCreate && (
              <button
                onClick={() => setShowInlineCreate(!showInlineCreate)}
                className="btn btn-secondary btn-sm"
              >
                {showInlineCreate ? 'Cancel' : 'Quick Add'}
              </button>
            )}
            <button
              onClick={() => setIsCreateModalOpen(true)}
              className="btn btn-primary btn-sm"
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
        <InlineCreateForm
          modelName={relatedModel}
          fields={createFields}
          onSubmit={handleCreate}
          onCancel={() => setShowInlineCreate(false)}
          defaultValues={{ [`${parentModel.toLowerCase()}_id`]: parentId }}
        />
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
        <div className="text-center py-8 text-gray-500">
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
      
      {/* Create modal */}
      {isCreateModalOpen && (
        <Modal
          title={`Create ${relatedModel}`}
          isOpen={isCreateModalOpen}
          onClose={() => setIsCreateModalOpen(false)}
        >
          <ModelForm
            modelName={relatedModel}
            onSubmit={handleCreate}
            onCancel={() => setIsCreateModalOpen(false)}
            defaultValues={{ [`${parentModel.toLowerCase()}_id`]: parentId }}
          />
        </Modal>
      )}
    </div>
  );
};
```

#### 2.2 Related Item Card Component
```tsx
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
  
  if (isEditing) {
    return (
      <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div className="grid grid-cols-1 gap-4">
          {displayColumns.map((fieldName) => {
            const fieldMeta = metadata?.fields?.[fieldName];
            return (
              <FieldComponent
                key={fieldName}
                fieldMetadata={fieldMeta}
                value={editData[fieldName]}
                onChange={(value) => setEditData(prev => ({ ...prev, [fieldName]: value }))}
              />
            );
          })}
        </div>
        
        <div className="flex justify-end space-x-2 mt-4">
          <button onClick={onCancel} className="btn btn-secondary btn-sm">
            Cancel
          </button>
          <button onClick={handleSave} className="btn btn-primary btn-sm">
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
                <span className="text-sm font-medium text-gray-500 w-24">
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
```

### 3. ManyToManyManager Component

#### 3.1 Component Interface
```tsx
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
```

#### 3.2 Assignment Interface Design
```tsx
const ManyToManyManager: React.FC<ManyToManyManagerProps> = (props) => {
  // State for assigned and available items
  const [assignedItems, setAssignedItems] = useState<any[]>([]);
  const [availableItems, setAvailableItems] = useState<any[]>([]);
  const [selectedAssigned, setSelectedAssigned] = useState<Set<string>>(new Set());
  const [selectedAvailable, setSelectedAvailable] = useState<Set<string>>(new Set());
  
  // UI Layout: Split-pane design
  return (
    <div className="many-to-many-manager">
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Assigned Items Pane */}
        <AssignedItemsPane
          title={`Assigned ${targetModel}s`}
          items={assignedItems}
          selectedItems={selectedAssigned}
          onSelectionChange={setSelectedAssigned}
          onRemove={handleRemoveItems}
          additionalFields={additionalFields}
          permissions={permissions}
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
          permissions={permissions}
        />
      </div>
      
      {/* Bulk Actions Bar */}
      <BulkActionsBar
        selectedAssigned={selectedAssigned}
        selectedAvailable={selectedAvailable}
        onBulkAssign={handleBulkAssign}
        onBulkRemove={handleBulkRemove}
        permissions={permissions}
      />
      
      {/* Assignment History */}
      {showHistory && (
        <AssignmentHistory
          relationship={relationship}
          sourceId={sourceId}
          permissions={permissions}
        />
      )}
    </div>
  );
};
```

### 4. API Integration Layer

#### 4.1 New API Methods
```tsx
// Add to apiService
class ApiService {
  // Existing methods...
  
  // Relationship-specific methods
  async getRelatedRecords(
    model: string, 
    id: string, 
    relationship: string,
    options?: { page?: number; limit?: number; search?: string }
  ): Promise<PaginatedResponse<any>> {
    const params = new URLSearchParams();
    if (options?.page) params.append('page', options.page.toString());
    if (options?.limit) params.append('limit', options.limit.toString());
    if (options?.search) params.append('search', options.search);
    
    const response = await this.api.get(
      `/${model}/${id}/relationships/${relationship}?${params}`
    );
    return response.data;
  }
  
  async assignRelationship(
    model: string,
    id: string,
    relationship: string,
    targetIds: string[],
    additionalData?: Record<string, any>
  ): Promise<any> {
    const response = await this.api.post(
      `/${model}/${id}/relationships/${relationship}/assign`,
      { target_ids: targetIds, additional_data: additionalData }
    );
    return response.data;
  }
  
  async removeRelationship(
    model: string,
    id: string,
    relationship: string,
    targetIds: string[]
  ): Promise<any> {
    const response = await this.api.post(
      `/${model}/${id}/relationships/${relationship}/remove`,
      { target_ids: targetIds }
    );
    return response.data;
  }
  
  async getRelationshipHistory(
    model: string,
    id: string,
    relationship: string,
    options?: { page?: number; limit?: number }
  ): Promise<PaginatedResponse<any>> {
    const params = new URLSearchParams();
    if (options?.page) params.append('page', options.page.toString());
    if (options?.limit) params.append('limit', options.limit.toString());
    
    const response = await this.api.get(
      `/${model}/${id}/relationships/${relationship}/history?${params}`
    );
    return response.data;
  }
}
```

#### 4.2 Custom Hooks
```tsx
// useRelationshipManager hook
const useRelationshipManager = (config: {
  sourceModel: string;
  sourceId: string;
  relationship: string;
  targetModel: string;
}) => {
  const [assigned, setAssigned] = useState<any[]>([]);
  const [available, setAvailable] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  const loadAssigned = useCallback(async () => {
    try {
      setLoading(true);
      const response = await apiService.getRelatedRecords(
        config.sourceModel,
        config.sourceId,
        config.relationship
      );
      setAssigned(response.data || []);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [config]);
  
  const loadAvailable = useCallback(async (searchTerm?: string) => {
    try {
      const assignedIds = assigned.map(item => item.id);
      const response = await apiService.getList(
        config.targetModel,
        1,
        100,
        { 
          search: searchTerm,
          exclude_ids: assignedIds.join(',')
        }
      );
      setAvailable(response.data || []);
    } catch (err) {
      setError(getErrorMessage(err));
    }
  }, [config.targetModel, assigned]);
  
  const assignItems = useCallback(async (
    targetIds: string[],
    additionalData?: Record<string, any>
  ) => {
    try {
      await apiService.assignRelationship(
        config.sourceModel,
        config.sourceId,
        config.relationship,
        targetIds,
        additionalData
      );
      await loadAssigned();
      await loadAvailable();
      return true;
    } catch (err) {
      setError(getErrorMessage(err));
      return false;
    }
  }, [config, loadAssigned, loadAvailable]);
  
  const removeItems = useCallback(async (targetIds: string[]) => {
    try {
      await apiService.removeRelationship(
        config.sourceModel,
        config.sourceId,
        config.relationship,
        targetIds
      );
      await loadAssigned();
      await loadAvailable();
      return true;
    } catch (err) {
      setError(getErrorMessage(err));
      return false;
    }
  }, [config, loadAssigned, loadAvailable]);
  
  useEffect(() => {
    loadAssigned();
  }, [loadAssigned]);
  
  useEffect(() => {
    loadAvailable();
  }, [loadAvailable]);
  
  return {
    assigned,
    available,
    loading,
    error,
    assignItems,
    removeItems,
    refreshAssigned: loadAssigned,
    refreshAvailable: loadAvailable
  };
};
```

## Implementation Timeline

### Week 11: Enhanced RelatedRecordSelect
- [ ] Add create capability to existing component
- [ ] Implement preview functionality
- [ ] Add direct edit links
- [ ] Create comprehensive test suite

### Week 12: One-to-Many Implementation
- [ ] Build RelatedItemsSection component
- [ ] Implement RelatedItemCard with inline editing
- [ ] Create InlineCreateForm component
- [ ] Integrate with Movies → Movie_Quotes relationship

### Week 13: Many-to-Many Implementation  
- [ ] Build ManyToManyManager component
- [ ] Create AssignedItemsPane and AvailableItemsPane
- [ ] Implement bulk operations
- [ ] Integrate with Users → Roles relationship

### Week 14: Finalization & Polish
- [ ] Create comprehensive documentation
- [ ] Implement accessibility features
- [ ] Performance optimization
- [ ] User acceptance testing

## Testing Strategy

### Unit Tests
- [ ] All relationship management components
- [ ] Custom hooks for relationship operations
- [ ] API service methods
- [ ] Error handling scenarios

### Integration Tests
- [ ] Complete relationship workflows
- [ ] Backend API integration
- [ ] Permission system integration
- [ ] Data consistency validation

### User Experience Tests
- [ ] Usability testing with different relationship scenarios
- [ ] Performance testing with large datasets
- [ ] Accessibility compliance validation
- [ ] Cross-browser compatibility testing

This implementation will provide a comprehensive, user-friendly relationship management system that builds naturally on the existing Gravitycar infrastructure while adding powerful new capabilities for managing complex data relationships.
