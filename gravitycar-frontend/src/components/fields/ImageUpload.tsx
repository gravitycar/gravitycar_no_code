import React, { useState } from 'react';
import type { FieldComponentProps } from '../../types';

export const ImageUpload: React.FC<FieldComponentProps> = ({ 
  value, 
  onChange, 
  fieldMetadata,
  error,
  label,
  readOnly = false
}) => {
  const [showUrlInput, setShowUrlInput] = useState(false);
  const [urlValue, setUrlValue] = useState(value || '');
  
  const allowRemote = fieldMetadata.allowRemote !== false; // Default to true
  const allowLocal = fieldMetadata.allowLocal !== false;   // Default to true
  
  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      // For now, just store the file name - in a real implementation
      // you would upload the file to a server and get back a URL
      onChange(file.name);
    }
  };

  const handleUrlSubmit = () => {
    onChange(urlValue);
    setShowUrlInput(false);
  };

  const handleUrlCancel = () => {
    setUrlValue(value || '');
    setShowUrlInput(false);
  };

  const handleClearImage = () => {
    onChange('');
    setUrlValue('');
  };

  if (readOnly) {
    return (
      <div className="mb-4">
        <label className="block text-sm font-medium text-gray-700 mb-2">
          {label || fieldMetadata.label || fieldMetadata.name}
        </label>
        {value ? (
          <div className="space-y-2">
            <img
              src={String(value)}
              alt={fieldMetadata.altText || 'Image'}
              className="max-w-xs max-h-48 object-contain rounded border"
              style={{
                width: fieldMetadata.width ? `${fieldMetadata.width}px` : undefined,
                height: fieldMetadata.height ? `${fieldMetadata.height}px` : undefined,
              }}
              onError={(e) => {
                (e.target as HTMLImageElement).style.display = 'none';
              }}
            />
            <p className="text-sm text-gray-600 break-all">{value}</p>
          </div>
        ) : (
          <p className="text-sm text-gray-500">No image</p>
        )}
      </div>
    );
  }

  return (
    <div className="mb-4">
      <label htmlFor={fieldMetadata.name} className="block text-sm font-medium text-gray-700 mb-2">
        {label || fieldMetadata.label || fieldMetadata.name}
        {fieldMetadata.required && <span className="text-red-500 ml-1">*</span>}
      </label>
      
      <div className="space-y-3">
        {/* Current Image Display */}
        {value && (
          <div className="space-y-2">
            <div className="relative inline-block">
              <img
                src={String(value)}
                alt={fieldMetadata.altText || 'Current image'}
                className="max-w-xs max-h-48 object-contain rounded border"
                style={{
                  width: fieldMetadata.width ? `${fieldMetadata.width}px` : undefined,
                  height: fieldMetadata.height ? `${fieldMetadata.height}px` : undefined,
                }}
                onError={(e) => {
                  (e.target as HTMLImageElement).style.display = 'none';
                }}
              />
              <button
                type="button"
                onClick={handleClearImage}
                className="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600"
                title="Remove image"
              >
                ×
              </button>
            </div>
            <div className="text-sm text-gray-600">
              <p className="font-medium">Current URL:</p>
              <p className="break-all">{value}</p>
            </div>
          </div>
        )}

        {/* URL Input (when editing or no current image) */}
        {allowRemote && (showUrlInput || !value) && (
          <div className="space-y-2">
            <div className="flex space-x-2">
              <input
                type="url"
                value={urlValue}
                onChange={(e) => setUrlValue(e.target.value)}
                placeholder={fieldMetadata.placeholder || 'Enter image URL...'}
                className="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <button
                type="button"
                onClick={handleUrlSubmit}
                className="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                Set
              </button>
              {value && (
                <button
                  type="button"
                  onClick={handleUrlCancel}
                  className="px-3 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500"
                >
                  Cancel
                </button>
              )}
            </div>
          </div>
        )}

        {/* Action Buttons */}
        {!showUrlInput && value && allowRemote && (
          <div className="flex space-x-2">
            <button
              type="button"
              onClick={() => setShowUrlInput(true)}
              className="text-sm text-blue-600 hover:text-blue-800"
            >
              Change URL
            </button>
          </div>
        )}

        {/* File Upload (if local files are allowed) */}
        {allowLocal && (
          <div className="space-y-2">
            <div className="border-t pt-3">
              <label className="block text-sm font-medium text-gray-600 mb-2">
                Or upload a file:
              </label>
              <input
                type="file"
                id={fieldMetadata.name}
                accept="image/*"
                onChange={handleFileChange}
                className="block w-full text-sm text-gray-500
                           file:mr-4 file:py-2 file:px-4
                           file:rounded-full file:border-0
                           file:text-sm file:font-semibold
                           file:bg-violet-50 file:text-violet-700
                           hover:file:bg-violet-100"
              />
              <div className="text-xs text-gray-500 mt-1">
                Note: File upload functionality needs backend integration.
              </div>
            </div>
          </div>
        )}
      </div>
      
      {error && (
        <p className="mt-1 text-sm text-red-600">{error}</p>
      )}
    </div>
  );
};
