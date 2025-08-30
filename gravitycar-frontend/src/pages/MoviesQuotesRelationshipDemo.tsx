import React, { useState } from 'react';
import RelatedItemsSection from '../components/relationships/RelatedItemsSection';
import RelatedRecordSelect from '../components/fields/RelatedRecordSelect';

/**
 * Movies-Movie Quotes Relationship Demo
 * 
 * This component demonstrates the complete implementation of the one-to-many
 * relationship between Movies and Movie_Quotes using our new relationship UI components.
 */
const MoviesQuotesRelationshipDemo: React.FC = () => {
  const [selectedMovieId, setSelectedMovieId] = useState<string>('');
  const [selectedQuoteId, setSelectedQuoteId] = useState<string>('');

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-8">
      <div className="text-center mb-8">
        <h1 className="text-3xl font-bold text-gray-900 mb-4">
          Movies ↔ Movie Quotes Relationship Demo
        </h1>
        <p className="text-gray-600 max-w-3xl mx-auto">
          This demo showcases the One-to-Many relationship between Movies and Movie Quotes.
          Each movie can have many quotes, and each quote belongs to one movie.
        </p>
      </div>

      {/* Enhanced RelatedRecordSelect for Movie Selection */}
      <section className="bg-white rounded-lg shadow p-6">
        <h2 className="text-xl font-semibold text-gray-900 mb-4">
          Enhanced Movie Selection with RelatedRecordSelect
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Select Movie (for Quote Creation)
            </label>
            <RelatedRecordSelect
              value={selectedMovieId}
              onChange={setSelectedMovieId}
              fieldMetadata={{
                name: 'movie_id',
                type: 'RelatedRecordField',
                react_component: 'RelatedRecordSelect',
                label: 'Movie',
                required: true,
                related_model: 'Movies',
                display_field: 'name'
              }}
              placeholder="Search for a movie..."
              relationshipContext={{
                type: 'OneToMany',
                parentModel: 'Movie_Quotes',
                parentId: 'demo',
                relationship: 'movies_movie_quotes',
                allowCreate: true
              }}
              allowDirectEdit={true}
              showPreview={true}
              onCreateNew={() => console.log('Create new movie from quote form')}
            />
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Select Quote (for Editing)
            </label>
            <RelatedRecordSelect
              value={selectedQuoteId}
              onChange={setSelectedQuoteId}
              fieldMetadata={{
                name: 'quote_id',
                type: 'RelatedRecordField',
                react_component: 'RelatedRecordSelect',
                label: 'Quote',
                required: false,
                related_model: 'Movie_Quotes',
                display_field: 'quote'
              }}
              placeholder="Search for a quote..."
            />
          </div>
        </div>
        
        <div className="mt-4 p-3 bg-blue-50 rounded border border-blue-200">
          <p className="text-blue-800 text-sm">
            <strong>Enhancement:</strong> The movie_id field in Movie_Quotes metadata has been updated
            from type 'ID' to 'RelatedRecord' with related_model='Movies' and display_field='name'.
            This enables searchable movie selection with create new capabilities.
          </p>
        </div>
      </section>

      {/* One-to-Many Relationship Management */}
      {selectedMovieId && (
        <section className="bg-white rounded-lg shadow p-6">
          <h2 className="text-xl font-semibold text-gray-900 mb-4">
            One-to-Many Relationship: Movie → Quotes Management
          </h2>
          <RelatedItemsSection
            title="Movie Quotes"
            parentModel="Movies"
            parentId={selectedMovieId}
            relationship="quotes"
            relatedModel="Movie_Quotes"
            displayColumns={['quote']}
            actions={['create', 'edit', 'delete']}
            createFields={['quote']}
            editFields={['quote']}
            allowInlineCreate={true}
            allowInlineEdit={true}
            permissions={{
              canCreate: true,
              canEdit: true,
              canDelete: true,
              canReorder: false
            }}
          />
        </section>
      )}

      {/* Relationship Information */}
      <section className="bg-gray-50 rounded-lg p-6">
        <h3 className="text-lg font-medium text-gray-900 mb-3">
          Relationship Implementation Details
        </h3>
        
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <h4 className="font-medium text-gray-900 mb-2">Backend Changes Made:</h4>
            <ul className="list-disc list-inside space-y-1 text-gray-700 text-sm">
              <li><strong>Movie_Quotes metadata updated:</strong> movie_id field changed from 'ID' to 'RelatedRecord'</li>
              <li><strong>Related model specified:</strong> 'Movies' with display_field 'name'</li>
              <li><strong>Searchable enabled:</strong> Users can search movies by name</li>
              <li><strong>Relationship preserved:</strong> movies_movie_quotes OneToMany relationship maintained</li>
            </ul>
          </div>
          
          <div>
            <h4 className="font-medium text-gray-900 mb-2">Frontend Features Added:</h4>
            <ul className="list-disc list-inside space-y-1 text-gray-700 text-sm">
              <li><strong>Enhanced Movie Selection:</strong> Searchable dropdown with create new option</li>
              <li><strong>Quote Management Interface:</strong> CRUD operations for movie quotes</li>
              <li><strong>Inline Editing:</strong> Edit quotes directly in the list view</li>
              <li><strong>Relationship Context:</strong> Auto-populate movie_id when creating quotes</li>
            </ul>
          </div>
        </div>

        <div className="mt-4 p-3 bg-green-50 rounded border border-green-200">
          <p className="text-green-800 text-sm">
            <strong>Usage:</strong> Select a movie above to see the relationship management interface.
            The RelatedItemsSection will show all quotes for that movie and allow you to create, edit, or delete quotes.
          </p>
        </div>
      </section>

      {/* API Integration Information */}
      <section className="bg-white border border-gray-200 rounded-lg p-6">
        <h3 className="text-lg font-medium text-gray-900 mb-3">
          Expected API Endpoints
        </h3>
        <p className="text-gray-600 mb-3">
          This relationship interface expects the following backend endpoints to be implemented:
        </p>
        
        <div className="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm">
          <div className="space-y-1">
            <div># Get quotes for a specific movie</div>
            <div>GET /Movies/{`{movie_id}`}/relationships/quotes</div>
            <div></div>
            <div># Create a new quote for a movie</div>
            <div>POST /Movie_Quotes (with movie_id in payload)</div>
            <div></div>
            <div># Update/Delete quotes</div>
            <div>PUT /Movie_Quotes/{`{quote_id}`}</div>
            <div>DELETE /Movie_Quotes/{`{quote_id}`}</div>
            <div></div>
            <div># Search movies for selection</div>
            <div>GET /Movies?search=movie_name</div>
          </div>
        </div>
      </section>
    </div>
  );
};

export default MoviesQuotesRelationshipDemo;
