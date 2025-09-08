import React, { useState, useEffect } from 'react';
import { useModelMetadata } from '../../hooks/useModelMetadata';
import FieldComponent from '../fields/FieldComponent';
import RelatedRecordSelect from '../fields/RelatedRecordSelect';
import { TMDBMovieSelector } from '../movies/TMDBMovieSelector';
import type { FieldMetadata } from '../../types';
import { apiService } from '../../services/api';
import { ApiError } from '../../utils/errors';

interface ModelFormProps {
  modelName: string;
  recordId?: string; // For edit mode (UUID string)
  initialData?: Record<string, any>;
  disabled?: boolean;
  onSuccess?: (record: any) => void;
  onCancel?: () => void;
}

/**
 * Dynamic form component that generates forms based on model metadata
 */
const ModelForm: React.FC<ModelFormProps> = ({
  modelName,
  recordId,
  onSuccess,
  onCancel,
  initialData = {},
  disabled = false
}) => {
  const { metadata, loading, error } = useModelMetadata(modelName);
  const [formData, setFormData] = useState<Record<string, any>>({});
  const [validationErrors, setValidationErrors] = useState<Record<string, string>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [hasInitialized, setHasInitialized] = useState(false);
  const [loadingRecord, setLoadingRecord] = useState(false);

  // TMDB-specific state for Movies model
  const [tmdbState, setTmdbState] = useState({
    showSelector: false,
    searchResults: [] as any[],
    isSearching: false
  });

  // Load existing record data if recordId is provided
  useEffect(() => {
    const loadRecord = async () => {
      if (recordId && modelName) {
        setLoadingRecord(true);
        try {
          const response = await apiService.getById(modelName, recordId);
          const recordData = response.data as Record<string, any>;
          
          console.log(`📥 Loaded ${modelName} record:`, recordData);
          setFormData(recordData);
        } catch (error) {
          console.error(`❌ Failed to load ${modelName} record:`, error);
          setValidationErrors({
            _form: `Failed to load ${modelName} record. Please try again.`
          });
        } finally {
          setLoadingRecord(false);
        }
      }
    };

    loadRecord();
  }, [recordId, modelName]);

  // Initialize form with both initialData and default values from metadata (only once per metadata load)
  useEffect(() => {
    if (metadata && !hasInitialized && !recordId) { // Don't initialize defaults when loading existing record
      const defaultData: Record<string, any> = {};
      
      // First, apply default values from metadata
      Object.entries(metadata.fields).forEach(([fieldName, field]) => {
        if (field.default_value !== undefined && field.default_value !== null) {
          defaultData[fieldName] = field.default_value;
        }
      });
      
      // Then, override with any provided initialData
      const combinedData = { ...defaultData, ...initialData };
      
      setFormData(combinedData);
      setHasInitialized(true);
    }
  }, [metadata, modelName, recordId]); // Added recordId dependency

  const handleFieldChange = (fieldName: string, value: any) => {
    console.log(`🔄 Field change: ${fieldName} = ${value}`);
    setFormData(prev => ({
      ...prev,
      [fieldName]: value
    }));

    // Clear validation error for this field
    if (validationErrors[fieldName]) {
      setValidationErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors[fieldName];
        return newErrors;
      });
    }
  };

  // Evaluate button visibility conditions
  const evaluateShowCondition = (condition: any): boolean => {
    if (!condition || !condition.field) return true;
    
    const fieldValue = formData[condition.field];
    
    switch (condition.condition) {
      case 'has_value':
        return fieldValue !== undefined && fieldValue !== null && fieldValue !== '';
      case 'is_empty':
        return fieldValue === undefined || fieldValue === null || fieldValue === '';
      case 'equals':
        return fieldValue === condition.value;
      case 'not_equals':
        return fieldValue !== condition.value;
      default:
        return true;
    }
  };

  // Get button variant styling
  const getButtonVariantClasses = (variant?: string): string => {
    switch (variant) {
      case 'primary':
        return 'bg-blue-600 text-white border-blue-600 hover:bg-blue-700 focus:ring-blue-500';
      case 'secondary':
        return 'bg-white text-blue-600 border-blue-600 hover:bg-blue-50 focus:ring-blue-500';
      case 'danger':
        return 'bg-white text-red-600 border-red-600 hover:bg-red-50 focus:ring-red-500';
      case 'success':
        return 'bg-white text-green-600 border-green-600 hover:bg-green-50 focus:ring-green-500';
      case 'warning':
        return 'bg-white text-yellow-600 border-yellow-600 hover:bg-yellow-50 focus:ring-yellow-500';
      default:
        // Default secondary style
        return 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 focus:ring-gray-500';
    }
  };

  // Handle custom button clicks
  const handleCustomButtonClick = async (button: any) => {
    console.log(`🔘 Custom button clicked:`, button);
    
    switch (button.type) {
      case 'tmdb_search':
        await handleTMDBSearch();
        break;
      case 'tmdb_clear':
        handleTMDBClear();
        break;
      default:
        console.warn(`Unknown button type: ${button.type}`);
    }
  };

  // TMDB Search functionality
  const handleTMDBSearch = async () => {
    if (!formData.name || formData.name.length < 3) {
      console.warn('Movie title too short for TMDB search');
      return;
    }

    setTmdbState(prev => ({ ...prev, isSearching: true }));

    try {
      let response = await apiService.searchTMDB(formData.name);
      
      // Parse the JSON string response to get the actual response object
      if (typeof response === 'string') {
        response = JSON.parse(response);
      }
      
      if (!response.success || !response.data) {
        console.error('TMDB search failed:', response.message);
        setTmdbState(prev => ({ ...prev, isSearching: false }));
        return;
      }
      
      // Extract the TMDB search results from the parsed response
      const { exact_match, partial_matches } = response.data;
      
      // Combine all available matches
      let allMatches = [];
      if (exact_match) allMatches.push(exact_match);
      if (partial_matches && partial_matches.length > 0) {
        allMatches = allMatches.concat(partial_matches);
      }

      setTmdbState(prev => ({
        ...prev,
        isSearching: false,
        showSelector: true,
        searchResults: allMatches
      }));
    } catch (error) {
      console.error('TMDB search failed:', error);
      setTmdbState(prev => ({ ...prev, isSearching: false }));
    }
  };

  // Clear TMDB data
  const handleTMDBClear = () => {
    setFormData(prev => ({
      ...prev,
      tmdb_id: undefined,
      synopsis: '',
      poster_url: '',
      trailer_url: '',
      obscurity_score: undefined,
      release_year: undefined
    }));
  };

  // Apply TMDB movie selection
  const handleTMDBMovieSelect = async (tmdbMovie: any) => {
    try {
      // Enrich movie data using TMDB API
      let enrichmentResponse = await apiService.enrichMovieWithTMDB(tmdbMovie.tmdb_id.toString());
      
      // Parse the JSON string response to get the actual response object
      if (typeof enrichmentResponse === 'string') {
        enrichmentResponse = JSON.parse(enrichmentResponse);
      }
      
      if (!enrichmentResponse.success || !enrichmentResponse.data) {
        console.error('TMDB enrichment failed:', enrichmentResponse.message);
        setTmdbState(prev => ({ ...prev, showSelector: false }));
        return;
      }
      
      const enrichmentData = enrichmentResponse.data;
      
      setFormData(prev => ({
        ...prev,
        tmdb_id: enrichmentData.tmdb_id,
        synopsis: enrichmentData.synopsis,
        poster_url: enrichmentData.poster_url,
        trailer_url: enrichmentData.trailer_url,
        obscurity_score: enrichmentData.obscurity_score,
        release_year: enrichmentData.release_year,
        // Keep user-entered title
        name: prev.name
      }));
      
      setTmdbState(prev => ({ ...prev, showSelector: false }));
    } catch (error) {
      console.error('TMDB enrichment failed:', error);
      setTmdbState(prev => ({ ...prev, showSelector: false }));
    }
  };

  const validateForm = (): boolean => {
    if (!metadata) return false;

    const errors: Record<string, string> = {};
    
    Object.entries(metadata.fields).forEach(([fieldName, field]) => {
      const value = formData[fieldName];
      
      // Skip validation for readOnly fields when creating new records (they'll be auto-generated)
      if (!recordId && field.readOnly) {
        return;
      }
      
      // Required field validation
      if (field.required && (!value || (typeof value === 'string' && value.trim() === ''))) {
        errors[fieldName] = `${field.label || fieldName} is required`;
      }

      // Email validation
      if (field.type === 'EmailField' && value && typeof value === 'string') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
          errors[fieldName] = 'Please enter a valid email address';
        }
      }

      // Length validation
      if (value && typeof value === 'string') {
        if (field.max_length && value.length > field.max_length) {
          errors[fieldName] = `${field.label || fieldName} must be no more than ${field.max_length} characters`;
        }
        if (field.min_length && value.length < field.min_length) {
          errors[fieldName] = `${field.label || fieldName} must be at least ${field.min_length} characters`;
        }
      }
    });

    setValidationErrors(errors);
    return Object.keys(errors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }

    setIsSubmitting(true);

    try {
      console.log(`📤 Submitting ${recordId ? 'update' : 'create'} for ${modelName}:`, formData);
      
      let result;
      if (recordId) {
        // Update existing record
        result = await apiService.update(modelName, recordId, formData);
      } else {
        // Create new record
        result = await apiService.create(modelName, formData);
      }
      
      console.log(`✅ Successfully ${recordId ? 'updated' : 'created'} ${modelName}:`, result);
      
      if (onSuccess) {
        onSuccess(result.data);
      }
    } catch (error: any) {
      console.error(`❌ Failed to ${recordId ? 'update' : 'create'} ${modelName}:`, error);
      
      // Handle validation errors from backend
      if (error instanceof ApiError && error.status === 422) {
        const validationErrors = error.getValidationErrors();
        if (validationErrors) {
          // Convert validation errors format from string[] to string
          const formattedErrors: Record<string, string> = {};
          Object.entries(validationErrors).forEach(([field, messages]) => {
            formattedErrors[field] = Array.isArray(messages) ? messages.join(', ') : messages;
          });
          setValidationErrors(formattedErrors);
        } else {
          // No specific validation errors, show general message
          setValidationErrors({
            _form: error.getUserFriendlyMessage()
          });
        }
      } else if (error instanceof ApiError) {
        // Other API errors
        setValidationErrors({
          _form: error.getUserFriendlyMessage()
        });
      } else {
        // Fallback for other error types
        setValidationErrors({
          _form: error.message || 'An unexpected error occurred'
        });
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  // Debug logging for relationship fields
  console.log(`📊 ModelForm for ${modelName}:`, {
    hasMetadata: !!metadata,
    relationshipFields: metadata?.ui?.relationshipFields,
    loading,
    error,
    formData
  });

  if (loading || loadingRecord) {
    return (
      <div className="flex items-center justify-center py-8">
        <div className="text-gray-600">
          {loadingRecord ? `Loading ${modelName} record...` : 'Loading form...'}
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-md p-4">
        <h3 className="text-red-800 font-medium">Error Loading Form</h3>
        <p className="text-red-600 mt-1">{error}</p>
      </div>
    );
  }

  if (!metadata) {
    return (
      <div className="bg-yellow-50 border border-yellow-200 rounded-md p-4">
        <p className="text-yellow-800">No metadata available for {modelName}</p>
      </div>
    );
  }

  const renderField = (fieldName: string, field: FieldMetadata) => {
    // Skip ID field for create operations, show as readonly for edit
    if (field.type === 'IDField') {
      if (!recordId) return null; // Skip for create
      
      return (
        <div key={fieldName} className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            {field.label || fieldName}
          </label>
          <div className="px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-gray-600">
            {formData[fieldName] || recordId}
          </div>
        </div>
      );
    }

    return (
      <FieldComponent
        key={`${modelName}-${fieldName}`}
        field={field}
        value={formData[fieldName]}
        onChange={(value) => handleFieldChange(fieldName, value)}
        error={validationErrors[fieldName]}
        disabled={disabled || isSubmitting}
      />
    );
  };

  // NEW: Render relationship fields based on metadata
  const renderRelationshipField = (fieldName: string, relationshipField: any) => {
    console.log(`🔗 Rendering relationship field: ${fieldName}`, relationshipField);
    return (
      <div key={`relationship-${fieldName}`} className="mb-4">
        <RelatedRecordSelect
          value={formData[fieldName] || ''}
          onChange={(value) => handleFieldChange(fieldName, value)}
          error={validationErrors[fieldName]}
          disabled={disabled || isSubmitting}
          required={relationshipField.required}
          fieldMetadata={{
            name: fieldName,
            label: relationshipField.label,
            required: relationshipField.required,
            related_model: relationshipField.relatedModel,
            display_field: relationshipField.displayField,
            type: 'RelatedRecord',
            react_component: 'RelatedRecordSelect',
          }}
          placeholder={`Search for ${relationshipField.label?.toLowerCase()}...`}
          relationshipContext={{
            type: relationshipField.mode === 'parent_selection' ? 'OneToMany' : 'ManyToMany',
            parentModel: modelName,
            parentId: recordId,
            relationship: relationshipField.relationship,
            allowCreate: relationshipField.allowCreate || false,
          }}
          allowDirectEdit={true}
          showPreview={true}
          onCreateNew={() => {
            console.log(`Create new ${relationshipField.relatedModel} requested`);
            // TODO: Implement GenericCreateModal
          }}
        />
      </div>
    );
  };

  return (
    <div className="w-full">
      <form onSubmit={handleSubmit} className="space-y-6">
          {/* Form-level error display */}
          {validationErrors._form && (
            <div className="mb-4 bg-red-50 border border-red-200 rounded-md p-4">
              <p className="text-red-800">{validationErrors._form}</p>
            </div>
          )}

          <div className="space-y-4">
            {/* Render regular fields based on UI metadata - use editFields for edit mode, createFields for create mode */}
            {(() => {
              const isEditMode = !!recordId;
              const fieldsToRender = isEditMode 
                ? metadata.ui?.editFields || metadata.ui?.createFields 
                : metadata.ui?.createFields;
              
              return fieldsToRender?.map(fieldName => {
                const field = metadata.fields[fieldName];
                if (!field) {
                  console.warn(`⚠️ Field '${fieldName}' specified in UI ${isEditMode ? 'editFields' : 'createFields'} but not found in model fields`);
                  return null;
                }
                return renderField(fieldName, field);
              }) || 
              /* Fallback to all fields if no UI metadata */
              Object.entries(metadata.fields).map(([fieldName, field]) => 
                renderField(fieldName, field)
              );
            })()}

            {/* NEW: Render relationship fields */}
            {metadata.ui?.relationshipFields && Object.entries(metadata.ui.relationshipFields).map(([fieldName, relationshipField]) => 
              renderRelationshipField(fieldName, relationshipField)
            )}
          </div>

          <div className="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
            {onCancel && (
              <button
                type="button"
                onClick={onCancel}
                disabled={isSubmitting}
                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Cancel
              </button>
            )}
            
            {/* Custom edit buttons from metadata */}
            {recordId && metadata.ui?.editButtons && metadata.ui.editButtons
              .filter((button: any) => evaluateShowCondition(button.showWhen))
              .map((button: any) => (
                <button
                  key={button.name}
                  type="button"
                  onClick={() => handleCustomButtonClick(button)}
                  disabled={isSubmitting || (button.type === 'tmdb_search' && tmdbState.isSearching)}
                  className={`px-4 py-2 text-sm font-medium border rounded-md focus:outline-none focus:ring-2 disabled:opacity-50 disabled:cursor-not-allowed ${getButtonVariantClasses(button.variant)}`}
                  title={button.description}
                >
                  {button.type === 'tmdb_search' && tmdbState.isSearching ? 'Searching...' : button.label}
                </button>
              ))
            }
            
            <button
              type="submit"
              disabled={isSubmitting}
              className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isSubmitting ? 'Saving...' : (recordId ? 'Update' : 'Create')}
            </button>
          </div>
        </form>

        {/* TMDB Movie Selector Modal - only for Movies model */}
        {modelName === 'Movies' && (
          <TMDBMovieSelector
            isOpen={tmdbState.showSelector}
            onClose={() => setTmdbState(prev => ({ ...prev, showSelector: false }))}
            onSelect={handleTMDBMovieSelect}
            movies={tmdbState.searchResults}
            title={formData.name || ''}
          />
        )}
    </div>
  );
};

export default ModelForm;
