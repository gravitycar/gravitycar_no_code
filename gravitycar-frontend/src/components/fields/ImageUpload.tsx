import React from 'react';
import type { FieldComponentProps } from '../../types';

export const ImageUpload: React.FC<FieldComponentProps> = ({ 
  value, 
  onChange, 
  fieldMetadata,
  error,
  label
}) => {
  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      // For now, just store the file name - in a real implementation
      // you would upload the file to a server and get back a URL
      onChange(file.name);
    }
  };

  return (
    <div className="mb-4">
      <label htmlFor={fieldMetadata.name} className="block text-sm font-medium text-gray-700 mb-2">
        {label || fieldMetadata.label || fieldMetadata.name}
        {fieldMetadata.required && <span className="text-red-500 ml-1">*</span>}
      </label>
      
      <div className="space-y-2">
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
        
        {value && (
          <div className="text-sm text-gray-600">
            Current: {value}
          </div>
        )}
        
        <div className="text-xs text-gray-500">
          Note: This is a placeholder implementation. File upload functionality needs backend integration.
        </div>
      </div>
      
      {error && (
        <p className="mt-1 text-sm text-red-600">{error}</p>
      )}
    </div>
  );
};
