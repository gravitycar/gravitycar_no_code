import React from 'react';
import GenericCrudPage from '../components/crud/GenericCrudPage';
import type { MovieQuote, ModelMetadata, ModelRecord } from '../types';

/**
 * Enhanced grid renderer for movie quotes with movie relationship display
 */
const enhancedMovieQuoteGridRenderer = (
  item: ModelRecord, 
  _metadata: ModelMetadata, 
  onEdit: (item: ModelRecord) => void, 
  onDelete: (item: ModelRecord) => void
) => {
  // Type assertion to MovieQuote since we know this is used for Movie_Quotes model
  const quote = item as unknown as MovieQuote;
  
  return (
    <div className="bg-white rounded-lg shadow border border-gray-200 p-6 hover:shadow-lg transition-shadow">
      <div className="mb-4">
        <blockquote className="text-gray-900 italic text-lg mb-3">
          "{quote.quote}"
        </blockquote>
      </div>
      
      {/* Movie relationship information */}
      <div className="mb-4 p-3 bg-gray-50 rounded-lg">
        <div className="flex items-center space-x-3">
          {quote.movie_poster && (
            <img
              src={quote.movie_poster}
              alt={quote.movie || 'Movie poster'}
              className="w-12 h-16 object-cover rounded"
              onError={(e) => {
                const target = e.target as HTMLImageElement;
                target.style.display = 'none';
              }}
            />
          )}
          <div>
            <div className="text-sm font-medium text-gray-900">
              {quote.movie || 'Unknown Movie'}
            </div>
            <div className="text-xs text-gray-500">
              Movie ID: {quote.movie_id}
            </div>
          </div>
        </div>
      </div>
      
      <div className="flex justify-between items-center pt-4 border-t border-gray-200">
        <div className="text-xs text-gray-500">
          Quote #{quote.id}
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
  );
};

/**
 * Enhanced Movie Quotes Management Page
 * 
 * This page now uses the RelatedRecordField for movie selection,
 * allowing users to search and select movies when creating/editing quotes.
 * The movie_id field in the metadata has been updated to use RelatedRecord type.
 */
const MovieQuotesPageEnhanced: React.FC = () => {
  return (
    <GenericCrudPage
      modelName="Movie_Quotes"
      title="Movie Quotes Management"
      description="Manage memorable quotes from your movies with enhanced movie selection"
      defaultDisplayMode="grid"
      customGridRenderer={enhancedMovieQuoteGridRenderer}
    />
  );
};

export default MovieQuotesPageEnhanced;
