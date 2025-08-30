import React, { useState } from 'react';
import GenericCrudPage from '../components/crud/GenericCrudPage';
import RelatedItemsSection from '../components/relationships/RelatedItemsSection';
import Modal from '../components/ui/Modal';
import type { Movie, ModelMetadata } from '../types';

/**
 * Custom grid renderer for movies with poster images and quote management
 */
const movieGridRenderer = (
  movie: Movie, 
  metadata: ModelMetadata, 
  onEdit: (movie: Movie) => void, 
  onDelete: (movie: Movie) => void,
  onViewQuotes?: (movie: Movie) => void
) => (
  <div className="bg-white rounded-lg shadow border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow">
    {/* Movie poster */}
    {movie.poster_url && (
      <div className="w-full h-48 bg-gray-200 flex items-center justify-center">
        <img
          src={movie.poster_url}
          alt={movie.name}
          className="max-w-full max-h-full object-cover"
          onError={(e) => {
            const target = e.target as HTMLImageElement;
            target.style.display = 'none';
          }}
        />
        {!movie.poster_url && (
          <div className="text-gray-400 text-sm">No poster</div>
        )}
      </div>
    )}
    
    <div className="p-6">
      <h3 className="text-lg font-semibold text-gray-900 mb-2">
        {movie.name}
      </h3>
      
      {movie.synopsis && (
        <p className="text-gray-600 text-sm mb-4 line-clamp-3">
          {movie.synopsis}
        </p>
      )}
      
      <div className="flex justify-between items-center mt-4 pt-4 border-t border-gray-200">
        <div className="text-xs text-gray-500">
          ID: {movie.id}
        </div>
        <div className="flex space-x-2">
          <button
            onClick={() => onViewQuotes?.(movie)}
            className="text-green-600 hover:text-green-700 text-sm"
          >
            Quotes
          </button>
          <button
            onClick={() => onEdit(movie)}
            className="text-blue-600 hover:text-blue-700 text-sm"
          >
            Edit
          </button>
          <button
            onClick={() => onDelete(movie)}
            className="text-red-600 hover:text-red-700 text-sm"
          >
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>
);

/**
 * Enhanced Movies Management Page with Quote Relationship Management
 * 
 * This page demonstrates the One-to-Many relationship management between
 * Movies and Movie_Quotes using our new relationship UI components.
 */
const MoviesPage: React.FC = () => {
  const [selectedMovie, setSelectedMovie] = useState<Movie | null>(null);
  const [isQuotesModalOpen, setIsQuotesModalOpen] = useState(false);

  const handleViewQuotes = (movie: Movie) => {
    setSelectedMovie(movie);
    setIsQuotesModalOpen(true);
  };

  const handleCloseQuotesModal = () => {
    setSelectedMovie(null);
    setIsQuotesModalOpen(false);
  };

  // Enhanced grid renderer with quote management
  const enhancedMovieGridRenderer = (
    movie: Movie, 
    metadata: ModelMetadata, 
    onEdit: (movie: Movie) => void, 
    onDelete: (movie: Movie) => void
  ) => movieGridRenderer(movie, metadata, onEdit, onDelete, handleViewQuotes);

  return (
    <>
      <GenericCrudPage
        modelName="Movies"
        title="Movies Management"
        description="Manage your movie collection and their memorable quotes"
        defaultDisplayMode="grid"
        customGridRenderer={enhancedMovieGridRenderer}
      />

      {/* Movie Quotes Management Modal */}
      {isQuotesModalOpen && selectedMovie && (
        <Modal
          isOpen={isQuotesModalOpen}
          onClose={handleCloseQuotesModal}
          title={`Manage Quotes: ${selectedMovie.name}`}
          size="lg"
        >
          <div className="max-h-[70vh] overflow-y-auto">
            <RelatedItemsSection
              title="Movie Quotes"
              parentModel="Movies"
              parentId={selectedMovie.id}
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
          </div>
          
          <div className="mt-6 flex justify-end">
            <button
              onClick={handleCloseQuotesModal}
              className="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300"
            >
              Close
            </button>
          </div>
        </Modal>
      )}
    </>
  );
};

export default MoviesPage;
