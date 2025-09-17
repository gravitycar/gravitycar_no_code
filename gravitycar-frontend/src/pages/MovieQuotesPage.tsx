import React from 'react';
import GenericCrudPage from '../components/crud/GenericCrudPage';
import type { MovieQuote, ModelMetadata } from '../types';

/**
 * Custom grid renderer for movie quotes with quote display
 */
const movieQuoteGridRenderer = (quote: MovieQuote, _metadata: ModelMetadata, onEdit: (quote: MovieQuote) => void, onDelete: (quote: MovieQuote) => void) => (
  <div className="bg-white rounded-lg shadow border border-gray-200 p-6 hover:shadow-lg transition-shadow">
    <div className="mb-4">
      <blockquote className="text-gray-900 italic text-lg mb-3">
        "{quote.quote}"
      </blockquote>
      
      {quote.movie && (
        <p className="text-gray-600 text-sm">
          — {quote.movie}
        </p>
      )}
    </div>
    
    {quote.movie_id && (
      <div className="mb-4 text-sm text-gray-500">
        <span className="font-medium">Movie ID:</span> {quote.movie_id}
      </div>
    )}
    
    <div className="flex justify-between items-center pt-4 border-t border-gray-200">
      <div className="text-xs text-gray-500">
        Quote #{quote.id}
      </div>
      <div className="flex space-x-2">
        <button
          onClick={() => onEdit(quote)}
          className="text-blue-600 hover:text-blue-700 text-sm"
        >
          Edit
        </button>
        <button
          onClick={() => onDelete(quote)}
          className="text-red-600 hover:text-red-700 text-sm"
        >
          Delete
        </button>
      </div>
    </div>
  </div>
);

/**
 * Movie Quotes Management Page - Now uses the generic metadata-driven CRUD component
 * with a custom grid renderer for quote-specific display
 */
const MovieQuotesPage: React.FC = () => {
  return (
    <GenericCrudPage
      modelName="Movie_Quotes"
      title="Movie Quotes Management"
      description="Manage memorable quotes from your movies"
      defaultDisplayMode="grid"
      customGridRenderer={movieQuoteGridRenderer}
    />
  );
};

export default MovieQuotesPage;
