import React from 'react';
import GenericCrudPage from '../components/crud/GenericCrudPage';
import type { Movie, ModelMetadata, ModelRecord } from '../types';

/**
 * Custom grid renderer for movies with poster images
 */
const movieGridRenderer = (item: ModelRecord, _metadata: ModelMetadata, onEdit: (item: ModelRecord) => void, onDelete: (item: ModelRecord) => void) => {
  // Type assertion to Movie since we know this is used for Movies model
  const movie = item as unknown as Movie;
  
  return (
    <div className="bg-white rounded-lg shadow border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow">
      {/* Movie poster */}
      {movie.poster && (
        <div className="w-full h-48 bg-gray-200 flex items-center justify-center">
          <img
            src={movie.poster}
            alt={movie.name}
            className="max-w-full max-h-full object-cover"
            onError={(e) => {
              const target = e.target as HTMLImageElement;
              target.style.display = 'none';
            }}
          />
          {!movie.poster && (
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
              onClick={() => onEdit(item)}
              className="text-blue-600 hover:text-blue-700 text-sm"
            >
              Edit
            </button>
            <button
              onClick={() => onDelete(item)}
              className="text-red-600 hover:text-red-700 text-sm"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

/**
 * Movies Management Page - Now uses the generic metadata-driven CRUD component
 * with a custom grid renderer for movie-specific display
 */
const MoviesPage: React.FC = () => {
  return (
    <GenericCrudPage
      modelName="Movies"
      title="Movies Management"
      description="Manage your movie collection"
      defaultDisplayMode="grid"
      customGridRenderer={movieGridRenderer}
    />
  );
};

export default MoviesPage;
