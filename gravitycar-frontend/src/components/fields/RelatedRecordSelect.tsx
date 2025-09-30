/* eslint-disable @typescript-eslint/no-explicit-any, react-hooks/exhaustive-deps */
import React, { useState, useEffect, useRef, useCallback } from 'react';
import { fetchWithDebug } from '../../utils/apiUtils';
import type { FieldComponentProps } from '../../types';

// Enhanced props interface for relationship management
interface RelationshipContext {
  type: 'OneToMany' | 'ManyToMany' | 'OneToOne';
  parentModel?: string;
  parentId?: string;
  relationship?: string;
  allowCreate?: boolean;
  autoPopulateFields?: Record<string, any>;
}

interface EnhancedRelatedRecordProps extends FieldComponentProps {
  // NEW: Relationship-specific enhancements
  relationshipContext?: RelationshipContext;
  
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

/**
 * Enhanced Related record selection component with search functionality
 * Features:
 * - Search-as-you-type with debouncing
 * - Pagination (respects default_page_size from config)
 * - Keyboard navigation
 * - Loading states
 * - Clear selection
 * - Focus retention during API updates
 * - Create new option
 * - Preview and direct edit capabilities
 * - Relationship context awareness
 */
const RelatedRecordSelect: React.FC<EnhancedRelatedRecordProps> = ({
  value,
  onChange,
  error,
  disabled = false,
  readOnly = false,
  required = false,
  fieldMetadata,
  placeholder,
  label,
  // NEW enhanced props
  relationshipContext,
  showPreview = false,
  allowDirectEdit = false,
  // displayTemplate,
  // showRelatedCount = false,
  onRelatedChange,
  preFilterOptions,
  onCreateNew
}) => {
  const [options, setOptions] = useState<Array<{value: any, label: string}>>([]);
  const [loading, setLoading] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const [selectedOption, setSelectedOption] = useState<{value: any, label: string} | null>(null);
  const [highlightedIndex, setHighlightedIndex] = useState(-1);
  const [relatedModelMetadata, setRelatedModelMetadata] = useState<any>(null);
  
  const searchInputRef = useRef<HTMLInputElement>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const searchTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  
  const displayLabel = label || fieldMetadata?.label || fieldMetadata?.name;
  const displayPlaceholder = placeholder || `Search ${displayLabel?.toLowerCase()}`;
  // Handle both camelCase and snake_case property names from API
  const relatedModel = fieldMetadata?.related_model || (fieldMetadata as any)?.relatedModel;
  const displayField = fieldMetadata?.display_field || (fieldMetadata as any)?.displayFieldName || 'name';

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Fetch related model metadata to get displayColumns configuration
  useEffect(() => {
    const fetchRelatedModelMetadata = async () => {
      if (!relatedModel) return;
      
      try {
        console.log(`RelatedRecordSelect: Fetching metadata for related model: ${relatedModel}`);
        const response = await fetchWithDebug(`/metadata/models/${relatedModel}`, {
          method: 'GET',
        });

        if (response.ok) {
          const metadata = await response.json();
          console.log(`RelatedRecordSelect: Received metadata for ${relatedModel}:`, metadata);
          // Store the data part of the response which contains displayColumns
          setRelatedModelMetadata(metadata.data || metadata);
        } else {
          console.warn(`RelatedRecordSelect: Failed to fetch metadata for ${relatedModel}:`, response.statusText);
        }
      } catch (error) {
        console.error(`RelatedRecordSelect: Error fetching metadata for ${relatedModel}:`, error);
      }
    };

    fetchRelatedModelMetadata();
  }, [relatedModel]);

  // Fetch related records with search and pagination
  const fetchRelatedRecords = async (search: string = '') => {
    if (!relatedModel) {
      console.log('RelatedRecordSelect: No related model specified');
      return;
    }

    try {
      setLoading(true);
      console.log(`RelatedRecordSelect: Fetching records from ${relatedModel} with search: "${search}"`);
      
      // Build query parameters for search and pagination
      const params = new URLSearchParams();
      params.append('limit', '20'); // Use default_page_size from config
      
      if (search.trim()) {
        params.append('search', search.trim());
      }
      
      const endpoint = `/${relatedModel}?${params.toString()}`;
      console.log(`RelatedRecordSelect: Making request to ${endpoint}`);
      
      const response = await fetchWithDebug(endpoint, {
        method: 'GET',
      });

      console.log(`RelatedRecordSelect: Response status: ${response.status}`);

      if (response.ok) {
        const data = await response.json();
        console.log(`RelatedRecordSelect: Raw API response:`, data);
        
        // Handle different response formats
        let records = [];
        if (data.success && Array.isArray(data.data)) {
          records = data.data;
        } else if (Array.isArray(data)) {
          records = data;
        }

        console.log(`RelatedRecordSelect: Parsed records:`, records);

        // Convert records to options
        const recordOptions = records.map((record: any) => {
          let optionLabel = '';
          
          // First, try to use a pre-computed display name field if available
          // (This would be present when the API response includes RelatedRecord field joins)
          const displayNameField = `${relatedModel.toLowerCase()}_display_name`;
          if (record[displayNameField]) {
            optionLabel = record[displayNameField];
          } else {
            // Build display label from metadata displayColumns if available
            if (relatedModelMetadata?.displayColumns && Array.isArray(relatedModelMetadata.displayColumns)) {
              console.log(`RelatedRecordSelect: Using displayColumns from metadata:`, relatedModelMetadata.displayColumns);
              
              // Concatenate all displayColumns fields that have values
              const displayParts = relatedModelMetadata.displayColumns
                .map((fieldName: string) => record[fieldName])
                .filter((value: any) => value && String(value).trim())
                .map((value: any) => String(value).trim());
              
              if (displayParts.length > 0) {
                optionLabel = displayParts.join(' ');
              }
              
              console.log(`RelatedRecordSelect: Created label from displayColumns:`, {
                displayColumns: relatedModelMetadata.displayColumns,
                displayParts,
                optionLabel
              });
            }
            
            // If still no label, try the configured display field
            if (!optionLabel && displayField && record[displayField]) {
              optionLabel = record[displayField];
            }
            
            // If still no label, try common display field names
            if (!optionLabel) {
              const possibleDisplayFields = [
                'name', 
                'title', 
                'username', 
                'email',
                'first_name',
                'label'
              ];
              
              for (const fieldName of possibleDisplayFields) {
                if (record[fieldName]) {
                  optionLabel = record[fieldName];
                  break;
                }
              }
            }
          }
          
          // Final fallback: create a composite display name
          if (!optionLabel) {
            if (record.first_name && record.last_name) {
              optionLabel = `${record.first_name} ${record.last_name}`;
            } else if (record.username) {
              optionLabel = record.username;
            } else if (record.email) {
              optionLabel = record.email;
            } else {
              optionLabel = `${relatedModel} #${record.id}`;
            }
          }
          
          console.log(`RelatedRecordSelect: Creating option for record ${record.id}:`, {
            record,
            displayField,
            relatedModel,
            displayColumns: relatedModelMetadata?.displayColumns,
            optionLabel
          });
          return {
            value: record.id,
            label: optionLabel
          };
        });

        console.log(`RelatedRecordSelect: Final options:`, recordOptions);
        
        // Apply pre-filter if provided
        let finalOptions = recordOptions;
        if (preFilterOptions) {
          finalOptions = preFilterOptions(recordOptions);
        }
        
        // Add "Create New" option if allowed
        if (relationshipContext?.allowCreate && !disabled && !readOnly) {
          finalOptions.unshift({
            value: '__CREATE_NEW__',
            label: `+ Create New ${relatedModel}`,
            isCreateOption: true
          } as any);
        }
        
        setOptions(finalOptions);
        
        // Restore focus to search input after API update (FOCUS RETENTION FIX)
        // Only restore if dropdown is open (indicates user is actively searching)
        if (isOpen && searchInputRef.current) {
          // Use setTimeout to ensure DOM update is complete
          setTimeout(() => {
            if (searchInputRef.current) {
              // Restore cursor position to end of input
              const length = searchInputRef.current.value.length;
              searchInputRef.current.setSelectionRange(length, length);
              searchInputRef.current.focus();
            }
          }, 1);
        }
        
        // If this is the initial load and we have a value, find and set the selected option
        if (!search && value && !selectedOption) {
          const selected = recordOptions.find((option: {value: any, label: string}) => option.value === value);
          if (selected) {
            setSelectedOption(selected);
          } else {
            // Value not found in current options, fetch it specifically
            fetchSpecificRecord(value);
          }
        }
      } else {
        console.error(`RelatedRecordSelect: Failed to fetch ${relatedModel} records:`, response.statusText);
      }
    } catch (error) {
      console.error(`RelatedRecordSelect: Error fetching ${relatedModel} records:`, error);
    } finally {
      setLoading(false);
    }
  };

  // Fetch a specific record by ID when we have a value but it's not in current options
  const fetchSpecificRecord = async (recordId: string) => {
    if (!relatedModel || !recordId) return;
    
    try {
      const response = await fetchWithDebug(`/${relatedModel}/${recordId}`, {
        method: 'GET',
      });
      if (response.ok) {
        const responseData = await response.json();
        
        // Handle nested response structure (response.data.data)
        const record = responseData.data || responseData;
        
        // Determine the display label using the same logic as fetchRelatedRecords
        let optionLabel = '';
        
        // Build display label from metadata displayColumns if available
        if (relatedModelMetadata?.displayColumns && Array.isArray(relatedModelMetadata.displayColumns)) {
          console.log(`RelatedRecordSelect: Using displayColumns from metadata:`, relatedModelMetadata.displayColumns);
          
          // Concatenate all displayColumns fields that have values
          const displayParts = relatedModelMetadata.displayColumns
            .map((fieldName: string) => record[fieldName])
            .filter((value: any) => value && String(value).trim())
            .map((value: any) => String(value).trim());
          
          if (displayParts.length > 0) {
            optionLabel = displayParts.join(' ');
          }
          
          console.log(`RelatedRecordSelect: Created label from displayColumns:`, {
            displayColumns: relatedModelMetadata.displayColumns,
            displayParts,
            optionLabel
          });
        }
        
        if (!optionLabel && displayField && record[displayField]) {
          optionLabel = record[displayField];
        }
        
        if (!optionLabel) {
          const possibleDisplayFields = ['name', 'title', 'username', 'email', 'first_name', 'label'];
          for (const fieldName of possibleDisplayFields) {
            if (record[fieldName]) {
              optionLabel = record[fieldName];
              break;
            }
          }
        }
        
        if (!optionLabel) {
          if (record.first_name && record.last_name) {
            optionLabel = `${record.first_name} ${record.last_name}`;
          } else if (record.username) {
            optionLabel = record.username;
          } else if (record.email) {
            optionLabel = record.email;
          } else {
            optionLabel = `${relatedModel} #${record.id}`;
          }
        }
        
        const selectedOption = {
          value: record.id,
          label: optionLabel
        };
        
        setSelectedOption(selectedOption);
        console.log(`RelatedRecordSelect: Fetched specific record ${recordId}:`, selectedOption);
      } else {
        console.error(`RelatedRecordSelect: Failed to fetch specific ${relatedModel} record ${recordId}:`, response.statusText);
      }
    } catch (error) {
      console.error(`RelatedRecordSelect: Error fetching specific ${relatedModel} record ${recordId}:`, error);
    }
  };

  // Debounced search
  useEffect(() => {
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }

    searchTimeoutRef.current = setTimeout(() => {
      fetchRelatedRecords(searchTerm);
    }, 300); // 300ms debounce

    return () => {
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current);
      }
    };
  }, [searchTerm, relatedModel, displayField, relatedModelMetadata]);

  // Load initial data if we have a pre-selected value
  useEffect(() => {
    if (relatedModel && !searchTerm) {
      fetchRelatedRecords();
    }
  }, [relatedModel, relatedModelMetadata]);

  // Handle search input change
  const handleSearchChange = (newSearchTerm: string) => {
    setSearchTerm(newSearchTerm);
    setIsOpen(true);
    setHighlightedIndex(-1);
  };

  // Handle option selection
  const handleOptionSelect = (option: {value: any, label: string, isCreateOption?: boolean}) => {
    // Handle "Create New" option
    if (option.isCreateOption && option.value === '__CREATE_NEW__') {
      handleCreateNew();
      return;
    }
    
    setSelectedOption(option);
    onChange(option.value);
    setIsOpen(false);
    setSearchTerm('');
    setHighlightedIndex(-1);
    
    // Trigger related change callback if provided
    if (onRelatedChange) {
      // Find the full record data for the selected option
      const selectedRecord = options.find(opt => opt.value === option.value);
      if (selectedRecord) {
        onRelatedChange(selectedRecord);
      }
    }
  };

  // Handle create new action
  const handleCreateNew = useCallback(() => {
    if (onCreateNew) {
      onCreateNew();
    } else {
      // Default behavior: could open a modal or navigate to create page
      console.log(`RelatedRecordSelect: Create new ${relatedModel} requested`);
      // TODO: Implement default create modal or navigation
    }
    setIsOpen(false);
  }, [onCreateNew, relatedModel]);

  // Handle keyboard navigation
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (!isOpen) {
      if (e.key === 'ArrowDown' || e.key === 'Enter') {
        setIsOpen(true);
        e.preventDefault();
      }
      return;
    }

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setHighlightedIndex(prev => 
          prev < options.length - 1 ? prev + 1 : prev
        );
        break;
      case 'ArrowUp':
        e.preventDefault();
        setHighlightedIndex(prev => prev > 0 ? prev - 1 : prev);
        break;
      case 'Enter':
        e.preventDefault();
        if (highlightedIndex >= 0 && options[highlightedIndex]) {
          handleOptionSelect(options[highlightedIndex]);
        }
        break;
      case 'Escape':
        setIsOpen(false);
        setHighlightedIndex(-1);
        break;
    }
  };

  // Handle clear selection
  const handleClear = () => {
    setSelectedOption(null);
    onChange(null);
    setSearchTerm('');
    setIsOpen(false);
  };

  console.log('RelatedRecordSelect DEBUG:', {
    fieldMetadata,
    relatedModel,
    displayField,
    value,
    selectedOption,
    options: options.length,
    searchTerm,
    isOpen,
    readOnly
  });

  // If readOnly, render as a styled read-only display
  if (readOnly) {
    return (
      <div className="mb-4">
        {displayLabel && (
          <label className="block text-sm font-medium text-gray-700 mb-2">
            {displayLabel}
            {required && <span className="text-red-500 ml-1">*</span>}
          </label>
        )}
        
        <div className={`
          w-full px-3 py-2 border rounded-md shadow-sm bg-gray-50 text-gray-700
          ${error ? 'border-red-500' : 'border-gray-300'}
        `}>
          {selectedOption?.label || (value ? `${relatedModel} #${value}` : '-')}
        </div>
        
        {error && (
          <p className="mt-1 text-sm text-red-600">{error}</p>
        )}
        
        {fieldMetadata?.help_text && !error && (
          <p className="mt-1 text-sm text-gray-500">{fieldMetadata.help_text}</p>
        )}
      </div>
    );
  }

  return (
    <div className="mb-4" ref={dropdownRef}>
      {displayLabel && (
        <label className="block text-sm font-medium text-gray-700 mb-2">
          {displayLabel}
          {required && <span className="text-red-500 ml-1">*</span>}
        </label>
      )}
      
      <div className="relative">
        <div className="flex">
          <input
            ref={searchInputRef}
            type="text"
            value={isOpen ? searchTerm : (selectedOption?.label || '')}
            onChange={(e) => handleSearchChange(e.target.value)}
            onFocus={() => setIsOpen(true)}
            onKeyDown={handleKeyDown}
            placeholder={selectedOption ? selectedOption.label : displayPlaceholder}
            disabled={disabled || loading}
            required={required}
            className={`
              flex-1 px-3 py-2 border shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
              ${selectedOption && (allowDirectEdit || showPreview) ? 'rounded-l-md' : !selectedOption ? 'rounded-l-md' : 'rounded-md'}
              ${error ? 'border-red-500' : 'border-gray-300'}
              ${disabled || loading ? 'bg-gray-100 cursor-not-allowed' : 'bg-white'}
            `}
          />
          
          {/* Enhanced action buttons when option is selected */}
          {selectedOption && !disabled && (
            <div className="flex">
              {/* Preview button */}
              {showPreview && (
                <button
                  type="button"
                  onClick={() => {/* TODO: Implement preview modal */}}
                  className="px-2 py-2 bg-blue-50 border border-l-0 border-gray-300 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  title="Preview record"
                >
                  <svg className="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>
              )}
              
              {/* Direct edit button */}
              {allowDirectEdit && (
                <button
                  type="button"
                  onClick={() => {/* TODO: Implement direct edit */}}
                  className="px-2 py-2 bg-green-50 border border-l-0 border-gray-300 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-green-500"
                  title="Edit record"
                >
                  <svg className="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
              )}
              
              {/* Clear button */}
              <button
                type="button"
                onClick={handleClear}
                className={`px-3 py-2 bg-gray-200 border border-l-0 border-gray-300 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500
                  ${(showPreview || allowDirectEdit) ? 'rounded-r-md' : 'rounded-r-md'}
                `}
                title="Clear selection"
              >
                ✕
              </button>
            </div>
          )}
          
          {!selectedOption && (
            <div className="px-3 py-2 bg-gray-50 border border-l-0 border-gray-300 rounded-r-md">
              <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
              </svg>
            </div>
          )}
        </div>

        {/* Dropdown */}
        {isOpen && (
          <div className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
            {loading && (
              <div className="px-3 py-2 text-gray-500 text-center">
                <div className="inline-flex items-center">
                  <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" className="opacity-25"></circle>
                    <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" className="opacity-75"></path>
                  </svg>
                  Loading...
                </div>
              </div>
            )}
            
            {!loading && options.length === 0 && (
              <div className="px-3 py-2 text-gray-500 text-center">
                {searchTerm ? `No results found for "${searchTerm}"` : 'No options available'}
              </div>
            )}
            
            {!loading && options.map((option, index) => {
              const isCreateOption = (option as any).isCreateOption;
              return (
                <div
                  key={option.value}
                  onClick={() => handleOptionSelect(option)}
                  className={`px-3 py-2 cursor-pointer ${
                    isCreateOption 
                      ? 'bg-green-50 text-green-800 border-b border-green-200 font-medium hover:bg-green-100' 
                      : index === highlightedIndex
                        ? 'bg-blue-100 text-blue-900'
                        : 'hover:bg-gray-100'
                  } ${
                    !isCreateOption && option.value === value ? 'bg-blue-50 font-medium' : ''
                  }`}
                >
                  {isCreateOption ? (
                    <div className="flex items-center">
                      <svg className="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                      </svg>
                      {option.label}
                    </div>
                  ) : (
                    option.label
                  )}
                </div>
              );
            })}
            
            {!loading && searchTerm && options.length > 0 && (
              <div className="px-3 py-1 text-xs text-gray-500 border-t bg-gray-50">
                Showing up to 20 results. Type to search for more specific matches.
              </div>
            )}
          </div>
        )}
      </div>
      
      {error && (
        <p className="mt-1 text-sm text-red-600">{error}</p>
      )}
      
      {fieldMetadata?.help_text && !error && (
        <p className="mt-1 text-sm text-gray-500">{fieldMetadata.help_text}</p>
      )}
    </div>
  );
};

export default RelatedRecordSelect;