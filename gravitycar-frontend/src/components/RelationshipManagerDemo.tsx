import { useState } from 'react';
import RelatedRecordSelect from './fields/RelatedRecordSelect';
import RelatedItemsSection from './relationships/RelatedItemsSection';
import ManyToManyManager from './relationships/ManyToManyManager';

// Demo component showing all relationship management features
export function RelationshipManagerDemo() {
  const [selectedUserId, setSelectedUserId] = useState<string>('');
  const [selectedMovieId, setSelectedMovieId] = useState<string>('');

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-8">
      <h1 className="text-3xl font-bold text-gray-900 mb-8">
        Relationship Management Demo
      </h1>

      {/* Enhanced Related Record Select Demo */}
      <section className="bg-white rounded-lg shadow p-6">
        <h2 className="text-xl font-semibold text-gray-900 mb-4">
          Enhanced Related Record Select
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Select User (Foreign Key)
            </label>
            <RelatedRecordSelect
              value={selectedUserId}
              onChange={setSelectedUserId}
              fieldMetadata={{
                name: 'user_id',
                type: 'RelatedRecordField',
                react_component: 'RelatedRecordSelect',
                label: 'User',
                required: false,
                related_model: 'Users',
                display_field: 'username'
              }}
              relationshipContext={{
                type: 'OneToMany',
                parentModel: 'Movies',
                parentId: selectedMovieId || 'demo',
                relationship: 'created_by',
                allowCreate: true
              }}
              allowDirectEdit={true}
              showPreview={true}
              onCreateNew={() => console.log('Create new user')}
            />
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Select Movie (Foreign Key)
            </label>
            <RelatedRecordSelect
              value={selectedMovieId}
              onChange={setSelectedMovieId}
              fieldMetadata={{
                name: 'movie_id',
                type: 'RelatedRecordField',
                react_component: 'RelatedRecordSelect',
                label: 'Movie',
                required: false,
                related_model: 'Movies',
                display_field: 'name'
              }}
              relationshipContext={{
                type: 'OneToMany',
                parentModel: 'Users',
                parentId: selectedUserId || 'demo',
                relationship: 'favorite_movie',
                allowCreate: true
              }}
              allowDirectEdit={true}
              showPreview={true}
              onCreateNew={() => console.log('Create new movie')}
            />
          </div>
        </div>
      </section>

      {/* One-to-Many Relationship Demo */}
      {selectedMovieId && (
        <section className="bg-white rounded-lg shadow p-6">
          <h2 className="text-xl font-semibold text-gray-900 mb-4">
            One-to-Many Relationship: Movie Quotes
          </h2>
          <RelatedItemsSection
            title="Movie Quotes"
            parentModel="Movies"
            parentId={selectedMovieId}
            relationship="quotes"
            relatedModel="MovieQuotes"
            displayColumns={['quote']}
            actions={['create', 'edit', 'delete']}
            createFields={['quote']}
            editFields={['quote']}
            allowInlineCreate={true}
            allowInlineEdit={true}
          />
        </section>
      )}

      {/* Many-to-Many Relationship Demo */}
      {selectedUserId && (
        <section className="bg-white rounded-lg shadow p-6">
          <h2 className="text-xl font-semibold text-gray-900 mb-4">
            Many-to-Many Relationship: User Favorite Movies
          </h2>
          <ManyToManyManager
            title="User Favorite Movies"
            sourceModel="Users"
            sourceId={selectedUserId}
            relationship="favorite_movies"
            targetModel="Movies"
            displayColumns={['name', 'synopsis']}
            allowBulkAssign={true}
            allowBulkRemove={true}
            showHistory={false}
            searchable={true}
            filterable={true}
            permissions={{
              canAssign: true,
              canRemove: true,
              canViewHistory: false
            }}
          />
        </section>
      )}

      {/* Status and Help */}
      <section className="bg-gray-50 rounded-lg p-6">
        <h3 className="text-lg font-medium text-gray-900 mb-3">
          Demo Instructions
        </h3>
        <ul className="list-disc list-inside space-y-2 text-gray-700">
          <li>Select a user and movie using the enhanced dropdowns above</li>
          <li>Use the "Create New" and "Preview/Edit" buttons to test relationship context</li>
          <li>The Movie Quotes section will appear when you select a movie</li>
          <li>The Favorite Movies section will appear when you select a user</li>
          <li>Test creating, editing, and deleting related records</li>
          <li>Try bulk operations in the many-to-many manager</li>
        </ul>
        
        <div className="mt-4 p-3 bg-blue-50 rounded border border-blue-200">
          <p className="text-blue-800 text-sm">
            <strong>Note:</strong> This demo requires the backend API to support relationship endpoints.
            Some features may not work until the backend relationship routes are implemented.
          </p>
        </div>
      </section>
    </div>
  );
}

// Simplified test component for individual relationship features
export function RelationshipQuickTest() {
  return (
    <div className="p-4 max-w-2xl mx-auto space-y-6">
      <h2 className="text-2xl font-bold text-gray-900">Quick Relationship Test</h2>
      
      {/* Basic Related Record Select */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Basic Related Record Select
        </label>
        <RelatedRecordSelect
          value=""
          onChange={() => {}}
          fieldMetadata={{
            name: 'user_id',
            type: 'RelatedRecordField',
            react_component: 'RelatedRecordSelect',
            label: 'User',
            required: false,
            related_model: 'Users',
            display_field: 'username'
          }}
          placeholder="Select a user..."
        />
      </div>

      {/* Enhanced Related Record Select */}
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Enhanced Related Record Select
        </label>
        <RelatedRecordSelect
          value=""
          onChange={() => {}}
          fieldMetadata={{
            name: 'movie_id',
            type: 'RelatedRecordField',
            react_component: 'RelatedRecordSelect',
            label: 'Movie',
            required: false,
            related_model: 'Movies',
            display_field: 'name'
          }}
          placeholder="Select a movie..."
          relationshipContext={{
            type: 'OneToMany',
            parentModel: 'Users',
            parentId: 'test-user-id',
            relationship: 'created_movies',
            allowCreate: true
          }}
          allowDirectEdit={true}
          showPreview={true}
          onCreateNew={() => console.log('Create new movie')}
        />
      </div>

      {/* Test RelatedItemsSection with static data */}
      <div className="border-t pt-6">
        <RelatedItemsSection
          title="Test Movie Quotes"
          parentModel="Movies"
          parentId="test-movie-id"
          relationship="quotes"
          relatedModel="MovieQuotes"
          displayColumns={['quote']}
          actions={['create', 'edit', 'delete']}
          createFields={['quote']}
          editFields={['quote']}
          allowInlineCreate={true}
          allowInlineEdit={true}
        />
      </div>

      {/* Test ManyToManyManager with static data */}
      <div className="border-t pt-6">
        <ManyToManyManager
          title="Test User Movies"
          sourceModel="Users"
          sourceId="test-user-id"
          relationship="favorite_movies"
          targetModel="Movies"
          displayColumns={['name']}
          allowBulkAssign={true}
          allowBulkRemove={true}
          showHistory={false}
          searchable={true}
          filterable={true}
          permissions={{
            canAssign: true,
            canRemove: true,
            canViewHistory: false
          }}
        />
      </div>
    </div>
  );
}
