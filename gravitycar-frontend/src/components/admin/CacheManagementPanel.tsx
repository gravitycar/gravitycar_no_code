import React, { useState } from 'react';
import ConfirmRebuildModal from './ConfirmRebuildModal';

// ---------------------------------------------------------------------------
// Module-level constants — not re-created on every render
// ---------------------------------------------------------------------------

const CACHE_COMPONENTS: Array<{ id: string; label: string }> = [
  { id: 'metadata',   label: 'Metadata Cache' },
  { id: 'routes',     label: 'API Routes Cache' },
  { id: 'docs',       label: 'Documentation Cache' },
  { id: 'navigation', label: 'Navigation Cache' },
];

const ALL_COMPONENT_IDS: string[] = CACHE_COMPONENTS.map(c => c.id);

// ---------------------------------------------------------------------------
// CacheManagementPanel — self-contained cache rebuild controls
// ---------------------------------------------------------------------------

const CacheManagementPanel: React.FC = () => {
  const [selectedComponents, setSelectedComponents] = useState<string[]>(ALL_COMPONENT_IDS);
  const [updateSchema, setUpdateSchema]             = useState<boolean>(true);
  const [updatePermissions, setUpdatePermissions]   = useState<boolean>(true);
  const [isModalOpen, setIsModalOpen]               = useState<boolean>(false);

  const isMetadataSelected: boolean = selectedComponents.includes('metadata');
  const isRebuildDisabled:  boolean = selectedComponents.length === 0;

  const options = {
    components:        selectedComponents,
    updateSchema:      isMetadataSelected && updateSchema,
    updatePermissions: isMetadataSelected && updatePermissions,
  };

  function handleComponentToggle(componentId: string): void {
    setSelectedComponents(prev =>
      prev.includes(componentId)
        ? prev.filter(id => id !== componentId)
        : [...prev, componentId]
    );
  }

  function handleCloseModal(): void {
    setIsModalOpen(false);
  }

  return (
    <section className="bg-white rounded-lg shadow p-6">
      <h2 className="text-xl font-semibold text-gray-900 mb-2">Cache Management</h2>
      <p className="text-sm text-gray-600 mb-4">
        Rebuild one or more cache components. A backup archive is created before any files are cleared.
      </p>

      <fieldset className="border-0 p-0 m-0">
        <legend className="text-sm font-medium text-gray-700 mb-2">
          Select components to rebuild:
        </legend>
        {CACHE_COMPONENTS.map(({ id, label }) => (
          <div key={id} className="flex items-center gap-2 mb-2">
            <input
              id={`component-${id}`}
              type="checkbox"
              checked={selectedComponents.includes(id)}
              onChange={() => handleComponentToggle(id)}
              className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <label htmlFor={`component-${id}`} className="text-sm text-gray-700">
              {label}
            </label>
          </div>
        ))}
      </fieldset>

      {isMetadataSelected && (
        <div className="mt-4 pl-4 border-l-2 border-gray-200">
          <fieldset className="border-0 p-0 m-0">
            <legend className="text-sm font-medium text-gray-700 mb-2">
              Additional options:
            </legend>
            <div className="flex items-center gap-2 mb-2">
              <input
                id="update-schema"
                type="checkbox"
                checked={updateSchema}
                onChange={e => setUpdateSchema(e.target.checked)}
                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <label htmlFor="update-schema" className="text-sm text-gray-700">
                Update Database Schema
              </label>
            </div>
            <div className="flex items-center gap-2 mb-2">
              <input
                id="update-permissions"
                type="checkbox"
                checked={updatePermissions}
                onChange={e => setUpdatePermissions(e.target.checked)}
                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <label htmlFor="update-permissions" className="text-sm text-gray-700">
                Update Permissions
              </label>
            </div>
          </fieldset>
        </div>
      )}

      <button
        onClick={() => setIsModalOpen(true)}
        disabled={isRebuildDisabled}
        className="mt-6 px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        Rebuild Cache
      </button>

      <ConfirmRebuildModal
        isOpen={isModalOpen}
        onClose={handleCloseModal}
        options={options}
      />
    </section>
  );
};

export default CacheManagementPanel;
