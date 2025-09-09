import React, { useState } from 'react';

interface GoogleBook {
  google_books_id: string;
  title: string;
  subtitle?: string;
  authors: string[] | string; // Handle both array and string from backend
  publisher?: string;
  publication_date?: string;
  page_count?: number;
  description?: string;
  cover_image_url?: string;
  isbn_13?: string;
  isbn_10?: string;
  language?: string;
  average_rating?: number;
  ratings_count?: number;
  genres?: string[] | string; // Handle both array and string from backend
}

interface GoogleBooksSelectorProps {
  isOpen: boolean;
  onClose: () => void;
  onSelect: (book: GoogleBook) => void;
  books: GoogleBook[];
  title: string;
}

export const GoogleBooksSelector: React.FC<GoogleBooksSelectorProps> = ({
  isOpen,
  onClose,
  onSelect,
  books,
  title
}) => {
  const [selectedBook, setSelectedBook] = useState<GoogleBook | null>(null);
  
  const handleSelect = () => {
    if (selectedBook) {
      onSelect(selectedBook);
    }
  };
  
  if (!isOpen) return null;
  
  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex items-center justify-center min-h-screen px-4">
        <div className="fixed inset-0 bg-black opacity-50" onClick={onClose}></div>
        
        <div className="relative bg-white rounded-lg max-w-4xl w-full max-h-[80vh] overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-200">
            <h2 className="text-xl font-semibold text-gray-900">
              Select Match for "{title}"
            </h2>
            <p className="text-sm text-gray-500 mt-1">
              Choose the correct book from Google Books search results
            </p>
          </div>
          
          <div className="p-6 overflow-y-auto max-h-96">
            {books.length === 0 ? (
              <div className="text-center py-8">
                <div className="text-gray-400 mb-4">
                  <svg className="w-16 h-16 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <p className="text-gray-500">No books found matching your search.</p>
                <p className="text-sm text-gray-400 mt-1">Try different keywords or add the book manually.</p>
              </div>
            ) : (
              <div className="space-y-4">
                {books.map((book) => (
                  <div
                    key={book.google_books_id}
                    className={`border rounded-lg p-4 cursor-pointer transition-colors ${
                      selectedBook?.google_books_id === book.google_books_id
                        ? 'border-blue-500 bg-blue-50'
                        : 'border-gray-200 hover:border-gray-300'
                    }`}
                    onClick={() => setSelectedBook(book)}
                  >
                    <div className="flex space-x-4">
                      {book.cover_image_url ? (
                        <img
                          src={book.cover_image_url}
                          alt={book.title}
                          className="w-16 h-24 object-cover rounded flex-shrink-0"
                          onError={(e) => {
                            const target = e.target as HTMLImageElement;
                            target.style.display = 'none';
                          }}
                        />
                      ) : (
                        <div className="w-16 h-24 bg-gray-200 rounded flex items-center justify-center flex-shrink-0">
                          <svg className="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                          </svg>
                        </div>
                      )}
                      
                      <div className="flex-1 min-w-0">
                        <h3 className="font-semibold text-lg text-gray-900">{book.title}</h3>
                        {book.subtitle && (
                          <p className="text-md text-gray-700 mt-1">{book.subtitle}</p>
                        )}
                        
                        <div className="flex flex-wrap items-center gap-4 text-sm text-gray-600 mt-2">
                          {book.authors && (
                            <span>Authors: {Array.isArray(book.authors) ? book.authors.join(', ') : book.authors}</span>
                          )}
                          {book.publication_date && (
                            <span>Published: {book.publication_date}</span>
                          )}
                          {book.publisher && (
                            <span>Publisher: {book.publisher}</span>
                          )}
                          {book.page_count && (
                            <span>Pages: {book.page_count}</span>
                          )}
                          {book.average_rating && (
                            <span>Rating: {book.average_rating.toFixed(1)}/5</span>
                          )}
                        </div>
                        
                        {book.genres && (
                          <div className="flex flex-wrap gap-1 mt-2">
                            {Array.isArray(book.genres) ? (
                              <>
                                {book.genres.slice(0, 3).map((genre: string, index: number) => (
                                  <span
                                    key={index}
                                    className="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded"
                                  >
                                    {genre}
                                  </span>
                                ))}
                                {book.genres.length > 3 && (
                                  <span className="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded">
                                    +{book.genres.length - 3} more
                                  </span>
                                )}
                              </>
                            ) : (
                              <span className="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded">
                                {book.genres}
                              </span>
                            )}
                          </div>
                        )}
                        
                        {book.description && (
                          <p className="text-sm text-gray-500 mt-2 line-clamp-3">
                            {book.description}
                          </p>
                        )}
                        
                        {(book.isbn_13 || book.isbn_10) && (
                          <div className="flex gap-2 text-xs text-gray-500 mt-2">
                            {book.isbn_13 && <span>ISBN-13: {book.isbn_13}</span>}
                            {book.isbn_10 && <span>ISBN-10: {book.isbn_10}</span>}
                          </div>
                        )}
                      </div>
                      
                      {selectedBook?.google_books_id === book.google_books_id && (
                        <div className="flex-shrink-0">
                          <div className="w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center">
                            <svg className="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                              <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                            </svg>
                          </div>
                        </div>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
          
          <div className="px-6 py-4 border-t border-gray-200 flex justify-between">
            <button
              onClick={onClose}
              className="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50 transition-colors"
            >
              Skip Google Books Match
            </button>
            <button
              onClick={handleSelect}
              disabled={!selectedBook}
              className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Select This Book
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
