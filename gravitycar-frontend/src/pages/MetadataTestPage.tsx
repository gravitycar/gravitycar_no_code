import React, { useState } from 'react';
import ModelForm from '../components/forms/ModelForm';

/**
 * Test page to demonstrate metadata-driven form generation
 */
const MetadataTestPage: React.FC = () => {
  const [selectedModel, setSelectedModel] = useState<string>('Users');
  const [showForm, setShowForm] = useState(false);

  // Available models from Gravitycar backend
  const availableModels = [
    'Users',
    'Movies', 
    'Movie_Quotes',
    'Roles',
    'Permissions'
  ];

  const handleFormSuccess = (data: any) => {
    console.log('✅ Form submitted successfully:', data);
    alert(`Successfully created/updated ${selectedModel}!`);
    setShowForm(false);
  };

  const handleFormCancel = () => {
    setShowForm(false);
  };

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">Metadata-Driven Form Testing</h1>
          <p className="mt-2 text-gray-600">
            Test the metadata-driven form system with different Gravitycar models.
            Forms are automatically generated based on backend field definitions.
          </p>
        </div>

        {/* Model Selection */}
        <div className="bg-white shadow rounded-lg p-6 mb-8">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Select a Model to Test</h2>
          
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            {availableModels.map((model) => (
              <button
                key={model}
                onClick={() => setSelectedModel(model)}
                className={`
                  p-4 border-2 rounded-lg text-left transition-colors
                  ${selectedModel === model 
                    ? 'border-blue-500 bg-blue-50 text-blue-900' 
                    : 'border-gray-200 hover:border-gray-300 text-gray-700'
                  }
                `}
              >
                <div className="font-medium">{model}</div>
                <div className="text-sm opacity-75">
                  {model === 'Users' && 'User management and authentication'}
                  {model === 'Movies' && 'Movie catalog with IMDB integration'}
                  {model === 'Movie_Quotes' && 'Movie quotes with relationships'}
                  {model === 'Roles' && 'User roles and permissions'}
                  {model === 'Permissions' && 'Permission management'}
                </div>
              </button>
            ))}
          </div>

          <div className="flex space-x-4">
            <button
              onClick={() => setShowForm(true)}
              disabled={!selectedModel}
              className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Generate Form for {selectedModel}
            </button>
            
            {showForm && (
              <button
                onClick={() => setShowForm(false)}
                className="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
              >
                Hide Form
              </button>
            )}
          </div>
        </div>

        {/* Form Display */}
        {showForm && selectedModel && (
          <div className="bg-white shadow rounded-lg p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-6">
              Generated Form for {selectedModel}
            </h2>
            
            <ModelForm
              key={selectedModel} // Only remount when model changes
              modelName={selectedModel}
              onSuccess={handleFormSuccess}
              onCancel={handleFormCancel}
            />
          </div>
        )}

        {/* Info Panel */}
        <div className="bg-blue-50 border border-blue-200 rounded-lg p-6 mt-8">
          <h3 className="text-lg font-semibold text-blue-900 mb-3">How This Works</h3>
          <ul className="space-y-2 text-blue-800">
            <li className="flex items-start">
              <span className="inline-block w-2 h-2 bg-blue-600 rounded-full mt-2 mr-3 flex-shrink-0"></span>
              Forms are generated dynamically based on model metadata from the backend
            </li>
            <li className="flex items-start">
              <span className="inline-block w-2 h-2 bg-blue-600 rounded-full mt-2 mr-3 flex-shrink-0"></span>
              Field types automatically map to appropriate React components (TextInput, EmailInput, etc.)
            </li>
            <li className="flex items-start">
              <span className="inline-block w-2 h-2 bg-blue-600 rounded-full mt-2 mr-3 flex-shrink-0"></span>
              Validation rules come from the backend FieldBase definitions
            </li>
            <li className="flex items-start">
              <span className="inline-block w-2 h-2 bg-blue-600 rounded-full mt-2 mr-3 flex-shrink-0"></span>
              No manual form coding required - everything adapts to backend changes
            </li>
          </ul>
        </div>

        {/* Technical Details */}
        <div className="bg-gray-50 border border-gray-200 rounded-lg p-6 mt-6">
          <h3 className="text-lg font-semibold text-gray-900 mb-3">Technical Implementation</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
            <div>
              <h4 className="font-medium text-gray-900 mb-2">Frontend Components</h4>
              <ul className="space-y-1 text-gray-600">
                <li>• useModelMetadata hook</li>
                <li>• ModelForm component</li>
                <li>• FieldComponent mapper</li>
                <li>• Field-specific components</li>
                <li>• Metadata caching system</li>
              </ul>
            </div>
            <div>
              <h4 className="font-medium text-gray-900 mb-2">Backend Integration</h4>
              <ul className="space-y-1 text-gray-600">
                <li>• /metadata/models/{'{modelName}'} API</li>
                <li>• FieldBase subclass mapping</li>
                <li>• ReactComponentMapper service</li>
                <li>• Enhanced field metadata</li>
                <li>• Validation rule processing</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default MetadataTestPage;
