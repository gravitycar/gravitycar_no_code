import React from 'react';
import type { ModelMetadata, FieldMetadata } from '../../types';

interface ModelDataDisplayProps {
  data: Record<string, any>;
  metadata: ModelMetadata;
  displayMode?: 'card' | 'table' | 'list';
  onEdit?: (record: any) => void;
  onDelete?: (record: any) => void;
}

interface FieldDisplayProps {
  fieldName: string;
  field: FieldMetadata;
  value: any;
  displayMode?: 'card' | 'table' | 'list';
}

/**
 * Component for displaying a single field value based on its metadata
 */
const FieldDisplay: React.FC<FieldDisplayProps> = ({ fieldName, field, value, displayMode = 'card' }) => {
  // Handle empty/null values
  if (value === null || value === undefined || value === '') {
    return <span className="text-gray-400 italic">Not set</span>;
  }

  // Field-specific rendering based on type
  switch (field.type) {
    case 'Boolean':
      return (
        <span className={`px-2 py-1 rounded text-xs ${value ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
          {value ? 'Yes' : 'No'}
        </span>
      );

    case 'DateTime':
      const date = new Date(value);
      return (
        <span className="text-gray-700">
          {date.toLocaleDateString()} {date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
        </span>
      );

    case 'Email':
      return (
        <a href={`mailto:${value}`} className="text-blue-600 hover:text-blue-800">
          {value}
        </a>
      );

    case 'Image':
      return (
        <img 
          src={value} 
          alt={field.label}
          className="w-16 h-16 object-cover rounded"
          onError={(e) => {
            (e.target as HTMLImageElement).style.display = 'none';
          }}
        />
      );

    case 'Text':
      // Special handling for URL fields
      if (fieldName.includes('url') || fieldName.includes('link')) {
        return (
          <a href={value} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:text-blue-800">
            View →
          </a>
        );
      }
      
      // Handle long text in different display modes
      if (displayMode === 'table' && value.length > 50) {
        return (
          <span title={value} className="truncate block">
            {value.substring(0, 50)}...
          </span>
        );
      }
      
      return <span className="text-gray-700">{value}</span>;

    case 'BigText':
      // Always truncate big text in table/list views
      if (displayMode !== 'card' && value.length > 100) {
        return (
          <span title={value} className="text-gray-700">
            {value.substring(0, 100)}...
          </span>
        );
      }
      
      return (
        <div className="text-gray-700 leading-relaxed">
          {displayMode === 'card' ? (
            <p className="line-clamp-3">{value}</p>
          ) : (
            <span>{value.substring(0, 200)}{value.length > 200 ? '...' : ''}</span>
          )}
        </div>
      );

    case 'Enum':
      let displayValue = value;
      
      if (field.options) {
        if (Array.isArray(field.options)) {
          // Handle EnumOption[] format
          const option = field.options.find(opt => opt.value === value);
          displayValue = option ? option.label : value;
        } else if (typeof field.options === 'object') {
          // Handle Record<string, string> format
          displayValue = (field.options as Record<string, string>)[String(value)] || value;
        }
      }
      
      return (
        <span className="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
          {displayValue}
        </span>
      );

    case 'ID':
      return (
        <span className="font-mono text-xs text-gray-500">
          {value.substring(0, 8)}...
        </span>
      );

    default:
      return <span className="text-gray-700">{String(value)}</span>;
  }
};

/**
 * Metadata-driven component for displaying model data in various formats
 */
const ModelDataDisplay: React.FC<ModelDataDisplayProps> = ({ 
  data, 
  metadata, 
  displayMode = 'card', 
  onEdit, 
  onDelete 
}) => {
  // Get fields to display from UI metadata, fallback to all fields
  const displayFields = metadata.ui?.listFields || Object.keys(metadata.fields);
  
  // Filter out fields that don't exist in the data or metadata
  const validFields = displayFields.filter(fieldName => 
    metadata.fields[fieldName] && data.hasOwnProperty(fieldName)
  );

  if (displayMode === 'card') {
    return (
      <div className="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
        <div className="p-6">
          <div className="space-y-3">
            {validFields.map(fieldName => {
              const field = metadata.fields[fieldName];
              const value = data[fieldName];
              
              return (
                <div key={fieldName} className="flex flex-col">
                  <label className="text-sm font-medium text-gray-600 mb-1">
                    {field.label}
                  </label>
                  <FieldDisplay 
                    fieldName={fieldName}
                    field={field}
                    value={value}
                    displayMode="card"
                  />
                </div>
              );
            })}
          </div>

          {(onEdit || onDelete) && (
            <div className="flex justify-end space-x-2 mt-6 pt-4 border-t">
              {onEdit && (
                <button
                  onClick={() => onEdit(data)}
                  className="bg-blue-600 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700 transition-colors"
                >
                  Edit
                </button>
              )}
              {onDelete && (
                <button
                  onClick={() => onDelete(data)}
                  className="bg-red-600 text-white px-3 py-1.5 rounded text-sm hover:bg-red-700 transition-colors"
                >
                  Delete
                </button>
              )}
            </div>
          )}
        </div>
      </div>
    );
  }

  if (displayMode === 'table') {
    return (
      <tr className="hover:bg-gray-50">
        {validFields.map(fieldName => {
          const field = metadata.fields[fieldName];
          const value = data[fieldName];
          
          return (
            <td key={fieldName} className="px-6 py-4 whitespace-nowrap text-sm">
              <FieldDisplay 
                fieldName={fieldName}
                field={field}
                value={value}
                displayMode="table"
              />
            </td>
          );
        })}
        {(onEdit || onDelete) && (
          <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
            <div className="flex space-x-2">
              {onEdit && (
                <button
                  onClick={() => onEdit(data)}
                  className="text-blue-600 hover:text-blue-900"
                >
                  Edit
                </button>
              )}
              {onDelete && (
                <button
                  onClick={() => onDelete(data)}
                  className="text-red-600 hover:text-red-900"
                >
                  Delete
                </button>
              )}
            </div>
          </td>
        )}
      </tr>
    );
  }

  // List mode
  return (
    <div className="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
      <div className="flex items-center justify-between">
        <div className="flex-1 space-y-1">
          {validFields.slice(0, 3).map(fieldName => {
            const field = metadata.fields[fieldName];
            const value = data[fieldName];
            
            return (
              <div key={fieldName} className="flex items-center space-x-2">
                <span className="text-xs text-gray-500 min-w-[80px]">{field.label}:</span>
                <FieldDisplay 
                  fieldName={fieldName}
                  field={field}
                  value={value}
                  displayMode="list"
                />
              </div>
            );
          })}
        </div>
        
        {(onEdit || onDelete) && (
          <div className="flex space-x-2 ml-4">
            {onEdit && (
              <button
                onClick={() => onEdit(data)}
                className="text-blue-600 hover:text-blue-900 text-sm"
              >
                Edit
              </button>
            )}
            {onDelete && (
              <button
                onClick={() => onDelete(data)}
                className="text-red-600 hover:text-red-900 text-sm"
              >
                Delete
              </button>
            )}
          </div>
        )}
      </div>
    </div>
  );
};

export default ModelDataDisplay;
