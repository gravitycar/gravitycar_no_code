import React, { useState, useEffect } from 'react';
import type { FieldComponentProps } from '../../types';

/**
 * Related record selection component for RelatedRecordField
 * This is a basic implementation - can be enhanced with search/autocomplete later
 */
const RelatedRecordSelect: React.FC<FieldComponentProps> = ({
  value,
  onChange,
  error,
  disabled = false,
  required = false,
  fieldMetadata,
  placeholder,
  label
}) => {
  const [options, setOptions] = useState<Array<{value: any, label: string}>>([]);
  const [loading, setLoading] = useState(false);
  
  const displayLabel = label || fieldMetadata?.label || fieldMetadata?.name;
  const displayPlaceholder = placeholder || `Select ${displayLabel?.toLowerCase()}`;
  // Handle both camelCase and snake_case property names from API
  const relatedModel = fieldMetadata?.related_model || (fieldMetadata as any)?.relatedModel;
  const displayField = fieldMetadata?.display_field || (fieldMetadata as any)?.displayFieldName || 'name';

  console.log('RelatedRecordSelect DEBUG:', {
    fieldMetadata,
    relatedModel,
    displayField,
    value,
    options: options.length
  });

  // Fetch related records
  useEffect(() => {
    if (!relatedModel) {
      console.log('RelatedRecordSelect: No related model specified');
      return;
    }

    const fetchRelatedRecords = async () => {
      try {
        setLoading(true);
        console.log(`RelatedRecordSelect: Fetching records from ${relatedModel}`);
        
        // Make API call to fetch related records
        const url = `http://localhost:8081/${relatedModel}`;
        console.log(`RelatedRecordSelect: Making request to ${url}`);
        
        const response = await fetch(url, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${localStorage.getItem('auth_token') || ''}`
          }
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
            // Try multiple display field options from the record
            const possibleDisplayFields = [
              displayField,
              'name', 
              'title', 
              'username', 
              'email',
              'first_name',
              'label'
            ];
            
            let optionLabel = '';
            for (const fieldName of possibleDisplayFields) {
              if (record[fieldName]) {
                optionLabel = record[fieldName];
                break;
              }
            }
            
            // If still no label found, create a composite one
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
              possibleDisplayFields,
              optionLabel
            });
            return {
              value: record.id,
              label: optionLabel
            };
          });

          console.log(`RelatedRecordSelect: Final options:`, recordOptions);
          setOptions(recordOptions);
        } else {
          console.error(`RelatedRecordSelect: Failed to fetch ${relatedModel} records:`, response.statusText);
        }
      } catch (error) {
        console.error(`RelatedRecordSelect: Error fetching ${relatedModel} records:`, error);
      } finally {
        setLoading(false);
      }
    };

    fetchRelatedRecords();
  }, [relatedModel, displayField]);

  return (
    <div className="mb-4">
      {displayLabel && (
        <label className="block text-sm font-medium text-gray-700 mb-2">
          {displayLabel}
          {required && <span className="text-red-500 ml-1">*</span>}
        </label>
      )}
      
      <select
        value={value || ''}
        onChange={(e) => {
          const val = e.target.value;
          onChange(val === '' ? null : parseInt(val, 10));
        }}
        disabled={disabled || loading}
        required={required}
        className={`
          w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
          ${error ? 'border-red-500' : 'border-gray-300'}
          ${disabled || loading ? 'bg-gray-100 cursor-not-allowed' : 'bg-white'}
        `}
      >
        <option value="">
          {loading ? 'Loading...' : displayPlaceholder}
        </option>
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      
      {/* DEBUG INFO */}
      <div className="mt-2 text-xs text-gray-500">
        DEBUG: Related Model: {relatedModel}, Display Field: {displayField}, Options: {options.length}
      </div>
      
      {error && (
        <p className="mt-1 text-sm text-red-600">{error}</p>
      )}
      
      {fieldMetadata?.help_text && !error && (
        <p className="mt-1 text-sm text-gray-500">{fieldMetadata.help_text}</p>
      )}
      
      {relatedModel && (
        <p className="mt-1 text-xs text-gray-400">Related to: {relatedModel}</p>
      )}
    </div>
  );
};

export default RelatedRecordSelect;
