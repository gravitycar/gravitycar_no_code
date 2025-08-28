import React, { useState, useEffect } from 'react';
import { useModelMetadata } from '../../hooks/useModelMetadata';
import FieldComponent from '../fields/FieldComponent';
import type { FieldMetadata } from '../../types';

interface ModelFormProps {
  modelName: string;
  recordId?: string | number; // For edit mode
  onSuccess?: (data: any) => void;
  onCancel?: () => void;
  initialData?: Record<string, any>;
  disabled?: boolean;
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

  // Initialize form with both initialData and default values from metadata (only once per metadata load)
  useEffect(() => {
    if (metadata && !hasInitialized) {
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
  }, [metadata, modelName]); // Only depend on metadata and modelName, not initialData

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

  const validateForm = (): boolean => {
    if (!metadata) return false;

    const errors: Record<string, string> = {};
    
    Object.entries(metadata.fields).forEach(([fieldName, field]) => {
      const value = formData[fieldName];
      
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
      
      // TODO: Implement actual API call here
      // For now, just simulate success
      await new Promise(resolve => setTimeout(resolve, 1000));
      
      console.log(`✅ Successfully ${recordId ? 'updated' : 'created'} ${modelName}`);
      
      if (onSuccess) {
        onSuccess(formData);
      }
    } catch (error: any) {
      console.error(`❌ Failed to ${recordId ? 'update' : 'create'} ${modelName}:`, error);
      // Handle API errors here
    } finally {
      setIsSubmitting(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-8">
        <div className="text-gray-600">Loading form...</div>
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

  return (
    <div className="max-w-2xl mx-auto">
      <div className="bg-white shadow-sm rounded-lg border border-gray-200">
        <div className="px-6 py-4 border-b border-gray-200">
          <h2 className="text-lg font-semibold text-gray-900">
            {recordId ? `Edit ${modelName}` : `Create New ${modelName}`}
          </h2>
          {metadata.description && (
            <p className="text-sm text-gray-600 mt-1">{metadata.description}</p>
          )}
        </div>

        <form onSubmit={handleSubmit} className="px-6 py-4">
          <div className="space-y-4">
            {Object.entries(metadata.fields).map(([fieldName, field]) => 
              renderField(fieldName, field)
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
            
            <button
              type="submit"
              disabled={isSubmitting}
              className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isSubmitting ? 'Saving...' : (recordId ? 'Update' : 'Create')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default ModelForm;
