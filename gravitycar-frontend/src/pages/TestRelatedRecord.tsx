/* eslint-disable @typescript-eslint/no-explicit-any */
import React, { useState } from 'react';
import RelatedRecordSelect from '../components/fields/RelatedRecordSelect';

const TestRelatedRecordPage: React.FC = () => {
  const [selectedUser, setSelectedUser] = useState<number | null>(null);

  const testFieldMetadata = {
    name: 'created_by',
    type: 'RelatedRecord',
    label: 'Created By',
    description: 'User who created this record',
    required: false,
    readOnly: false,
    isDBField: true,
    nullable: true,
    relatedModel: 'Users',
    relatedFieldName: 'id',
    displayFieldName: 'username',
    validationRules: [],
    react_component: 'RelatedRecordSelect',
    react_validation: [],
    component_props: []
  };

  return (
    <div className="container mx-auto p-4">
      <h1 className="text-2xl font-bold mb-4">Test Enhanced RelatedRecordSelect Component</h1>
      
      <div className="max-w-md">
        <h2 className="text-lg font-semibold mb-4">Enhanced Search-and-Select Component</h2>
        <p className="text-sm text-gray-600 mb-4">
          This component now features:
          <br />• Search-as-you-type with debouncing (300ms)
          <br />• Pagination (limited to 20 results based on config)
          <br />• Keyboard navigation (Arrow keys, Enter, Escape)
          <br />• Clear selection button
          <br />• Loading states and error handling
        </p>
        
        <RelatedRecordSelect
          value={selectedUser}
          onChange={(value) => {
            console.log('Selected user changed to:', value);
            setSelectedUser(value);
          }}
          fieldMetadata={testFieldMetadata as any}
          label="Search Users"
          error=""
          disabled={false}
          required={false}
        />
        
        <div className="mt-4 p-4 bg-gray-100 rounded">
          <h3 className="font-semibold">Current Selection:</h3>
          <p>{selectedUser ? `User ID: ${selectedUser}` : 'No user selected'}</p>
        </div>
        
        <div className="mt-6 p-4 bg-blue-50 rounded">
          <h3 className="font-semibold text-blue-800">Usage Instructions:</h3>
          <ul className="text-sm text-blue-700 mt-2 space-y-1">
            <li>• Click in the input field to see initial options</li>
            <li>• Type to search users (searches username, email, first_name, etc.)</li>
            <li>• Use arrow keys to navigate options</li>
            <li>• Press Enter to select the highlighted option</li>
            <li>• Press Escape to close the dropdown</li>
            <li>• Click the ✕ button to clear your selection</li>
          </ul>
        </div>
      </div>
    </div>
  );
};

export default TestRelatedRecordPage;
