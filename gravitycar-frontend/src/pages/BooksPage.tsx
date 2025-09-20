import React from 'react';
import GenericCrudPage from '../components/crud/GenericCrudPage';
import type { ModelMetadata, ModelRecord } from '../types';

interface Book {
  id: string;
  title: string;
  subtitle?: string;
  authors: string;
  google_books_id?: string;
  isbn_13?: string;
  isbn_10?: string;
  synopsis?: string;
  cover_image_url?: string;
  publisher?: string;
  publication_date?: string;
  page_count?: number;
  genres?: string;
  language?: string;
  average_rating?: number;
  ratings_count?: number;
  maturity_rating?: string;
  created_at: string;
  updated_at: string;
}

/**
 * Custom grid renderer for books with cover images
 */
const bookGridRenderer = (item: ModelRecord, _metadata: ModelMetadata, onEdit: (item: ModelRecord) => void, onDelete: (item: ModelRecord) => void) => {
  // Type assertion to Book since we know this is used for Books model
  const book = item as unknown as Book;
  
  return (
    <div className="bg-white rounded-lg shadow border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow">
      {/* Book cover */}
      <div className="w-full h-48 bg-gray-200 flex items-center justify-center">
        {book.cover_image_url ? (
          <img
            src={book.cover_image_url}
            alt={book.title}
            className="max-w-full max-h-full object-cover"
            onError={(e) => {
              const target = e.target as HTMLImageElement;
              target.style.display = 'none';
              target.parentElement?.appendChild(
                Object.assign(document.createElement('div'), {
                  className: 'text-gray-400 text-sm',
                  textContent: 'No cover'
                })
              );
            }}
          />
        ) : (
          <div className="text-gray-400 text-sm">No cover</div>
        )}
      </div>
      
      <div className="p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-2">
          {book.title}
        </h3>
        
        {book.subtitle && (
          <h4 className="text-md text-gray-700 mb-2 italic">
            {book.subtitle}
          </h4>
        )}
        
        {book.authors && (
          <p className="text-gray-600 text-sm mb-2">
            by {book.authors}
          </p>
        )}
        
        {book.synopsis && (
          <p className="text-gray-600 text-sm mb-4 line-clamp-3">
            {book.synopsis}
          </p>
        )}
        
        <div className="text-xs text-gray-500 mb-4">
          {book.publisher && <div>Publisher: {book.publisher}</div>}
          {book.publication_date && <div>Published: {book.publication_date}</div>}
          {book.page_count && <div>Pages: {book.page_count}</div>}
          {book.average_rating && (
            <div>Rating: {book.average_rating}/5 ({book.ratings_count} reviews)</div>
          )}
        </div>
        
        <div className="flex justify-between items-center mt-4 pt-4 border-t border-gray-200">
          <div className="text-xs text-gray-500">
            ID: {book.id?.slice(0, 8)}...
          </div>
          <div className="flex space-x-2">
            <button
              onClick={() => onEdit(item)}
              className="text-blue-600 hover:text-blue-700 text-sm font-medium"
            >
              Edit
            </button>
            <button
              onClick={() => onDelete(item)}
              className="text-red-600 hover:text-red-700 text-sm font-medium"
            >
              Delete
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

const BooksPage: React.FC = () => {
  return (
    <GenericCrudPage
      modelName="Books"
      title="Books Management"
      customGridRenderer={bookGridRenderer}
    />
  );
};

export default BooksPage;
