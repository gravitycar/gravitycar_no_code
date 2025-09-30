/* eslint-disable @typescript-eslint/no-explicit-any */
import React, { useState } from 'react';
import { apiService } from '../../services/api';
import { fetchWithDebug } from '../../utils/apiUtils';
import type { ModelMetadata } from '../../types';

interface TMDBEnhancedCreateFormProps {
  metadata: ModelMetadata;
  onSuccess: (data: any) => void;
  onCancel: () => void;
}

/**
 * TMDB-enhanced movie creation form that integrates with the metadata-driven system
 * This form only handles the title input and TMDB enrichment, then uses standard ModelForm
 */
export const TMDBEnhancedCreateForm: React.FC<TMDBEnhancedCreateFormProps> = ({
  onSuccess,
  onCancel
}) => {
  const [title, setTitle] = useState('');
  const [isSearching, setIsSearching] = useState(false);
  const [isCreating, setIsCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [searchResults, setSearchResults] = useState<any[]>([]);
  const [selectedMovie, setSelectedMovie] = useState<any | null>(null);
  const [showResults, setShowResults] = useState(false);

  const handleSearchMovies = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!title.trim()) {
      setError('Movie title is required');
      return;
    }

    setIsSearching(true);
    setError(null);
    setSearchResults([]);
    setShowResults(false);

    try {
      console.log('🎬 Searching TMDB for:', title);
      
      const response = await fetchWithDebug('/movies/tmdb/search', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ title: title.trim() }),
      });
      
      if (response.ok) {
        const responseText = await response.text();
        console.log('🔍 Raw response text length:', responseText.length);
        console.log('🔍 Raw response (first 200 chars):', responseText.substring(0, 200));
        
        // Handle malformed response - backend might be outputting twice
        let searchResult;
        try {
          // Try to parse as normal JSON first
          searchResult = JSON.parse(responseText);
        } catch (parseError) {
          console.warn('⚠️ Failed to parse full response, looking for first valid JSON:', parseError);
          
          // Look for the first complete JSON object in the response
          let braceCount = 0;
          let firstJsonEnd = -1;
          
          for (let i = 0; i < responseText.length; i++) {
            if (responseText[i] === '{') {
              braceCount++;
            } else if (responseText[i] === '}') {
              braceCount--;
              if (braceCount === 0) {
                firstJsonEnd = i + 1;
                break;
              }
            }
          }
          
          if (firstJsonEnd > 0) {
            const firstJson = responseText.substring(0, firstJsonEnd);
            console.log('🔍 Extracted first JSON:', firstJson);
            try {
              searchResult = JSON.parse(firstJson);
              console.log('✅ Successfully parsed first JSON object');
            } catch (secondParseError) {
              console.error('❌ Failed to parse extracted JSON:', secondParseError);
              throw secondParseError;
            }
          } else {
            throw parseError;
          }
        }
        
        console.log('🔍 Parsed search result:', searchResult);
        console.log('🔍 Type of searchResult.data:', typeof searchResult.data);
        
        // Handle double-encoded JSON response from backend
        let tmdbData;
        if (typeof searchResult.data === 'string') {
          // Data is JSON string, needs to be parsed
          console.log('🔍 Parsing nested JSON string data');
          try {
            tmdbData = JSON.parse(searchResult.data);
            console.log('🔍 Parsed TMDB data:', tmdbData);
          } catch (parseError) {
            console.error('❌ JSON parse error on nested data:', parseError);
            throw parseError;
          }
        } else {
          // Data is already an object
          tmdbData = searchResult.data;
        }
        
        if (searchResult.success && tmdbData) {
          // Collect all matches for user selection
          const allMatches = [];
          
          // Add exact match to the list (if it exists)
          if (tmdbData.exact_match) {
            allMatches.push({ ...tmdbData.exact_match, matchType: 'exact' });
          }
          
          // Add all partial matches
          if (tmdbData.partial_matches?.length > 0) {
            allMatches.push(...tmdbData.partial_matches.map((match: any) => ({ ...match, matchType: 'partial' })));
          }
          
          if (allMatches.length > 0) {
            console.log('✅ Found', allMatches.length, 'movie matches:', allMatches);
            setSearchResults(allMatches);
            setShowResults(true);
          } else {
            setError('No movies found matching that title. You can still create the movie manually.');
          }
        } else {
          console.warn('⚠️ TMDB search response invalid:', searchResult);
          setError('TMDB search failed. You can still create the movie manually.');
        }
      } else {
        console.error('❌ TMDB search request failed:', response.status, response.statusText);
        setError('TMDB search failed. You can still create the movie manually.');
      }
    } catch (tmdbError) {
      console.warn('⚠️ TMDB search failed:', tmdbError);
      setError('TMDB search failed. You can still create the movie manually.');
    } finally {
      setIsSearching(false);
    }
  };

  const handleCreateMovie = async (tmdbMovie?: any) => {
    setIsCreating(true);
    setError(null);

    try {
      let movieData: Record<string, any> = { name: title.trim() };

      if (tmdbMovie) {
        // Enrich the movie data with selected TMDB details
        movieData = {
          name: title.trim(),
          tmdb_id: tmdbMovie.tmdb_id,
          synopsis: tmdbMovie.overview, // TMDB uses 'overview' not 'synopsis'
          poster_url: tmdbMovie.poster_url,
          release_year: tmdbMovie.release_year,
          obscurity_score: tmdbMovie.obscurity_score
          // Note: trailer_url not provided by TMDB search, leave as null/undefined
        };
        
        console.log('✅ Movie enriched with TMDB data:', movieData);
      } else {
        console.log('🎬 Creating movie without TMDB enrichment');
      }

      // Create the movie with (possibly enriched) data
      console.log('🎬 Creating movie with data:', movieData);
      const createResult = await apiService.create('Movies', movieData);
      
      if (createResult.success) {
        console.log('✅ Movie created successfully:', createResult.data);
        onSuccess(createResult.data);
      } else {
        console.error('❌ Movie creation failed:', createResult);
        throw new Error(createResult.message || 'Failed to create movie');
      }
    } catch (error: any) {
      console.error('❌ Failed to create movie:', error);
      
      // Handle ApiError with validation messages
      let errorMessage = 'Failed to create movie';
      
      if (error.getUserFriendlyMessage) {
        // This is an ApiError with proper validation error handling
        errorMessage = error.getUserFriendlyMessage();
      } else if (error.message) {
        errorMessage = error.message;
      }
      
      setError(errorMessage);
    } finally {
      setIsCreating(false);
    }
  };

  const handleSelectMovie = (movie: any) => {
    setSelectedMovie(movie);
    console.log('🎯 Selected movie:', movie);
  };

  const handleCreateWithSelection = () => {
    if (selectedMovie) {
      handleCreateMovie(selectedMovie);
    }
  };

  const handleCreateWithoutTMDB = () => {
    handleCreateMovie(); // Create without TMDB data
  };

  const handleBackToSearch = () => {
    setShowResults(false);
    setSearchResults([]);
    setSelectedMovie(null);
    setError(null);
  };

  return (
    <div className="p-6">
      {!showResults ? (
        // Search Form
        <form onSubmit={handleSearchMovies} className="space-y-6">
          {/* Title Input with TMDB indicator */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Movie Title *
            </label>
            <div className="relative">
              <input
                type="text"
                value={title}
                onChange={(e) => {
                  setTitle(e.target.value);
                  setError(null);
                }}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Enter movie title..."
                disabled={isSearching}
                autoFocus
              />
              {isSearching && (
                <div className="absolute right-3 top-2">
                  <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                </div>
              )}
            </div>
            <p className="mt-2 text-sm text-gray-500 flex items-center space-x-1">
              <svg className="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>Search TMDB to find matches, then select the correct movie</span>
            </p>
          </div>

          {/* Error Display */}
          {error && (
            <div className="p-3 bg-red-50 border border-red-200 rounded-md">
              <p className="text-sm text-red-700">{error}</p>
            </div>
          )}

          {/* Form Actions */}
          <div className="flex justify-end space-x-3 pt-4 border-t border-gray-200">
            <button
              type="button"
              onClick={onCancel}
              disabled={isSearching}
              className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              type="button"
              onClick={handleCreateWithoutTMDB}
              disabled={isSearching || !title.trim()}
              className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
            >
              Create Without TMDB
            </button>
            <button
              type="submit"
              disabled={isSearching || !title.trim()}
              className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isSearching ? (
                <span className="flex items-center space-x-2">
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                  <span>Searching TMDB...</span>
                </span>
              ) : (
                'Search TMDB'
              )}
            </button>
          </div>
        </form>
      ) : (
        // Results Selection
        <div className="space-y-6">
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-medium text-gray-900">
              Choose the correct movie for "{title}"
            </h3>
            <button
              onClick={handleBackToSearch}
              className="text-sm text-blue-600 hover:text-blue-700"
            >
              ← Back to search
            </button>
          </div>

          {/* Movie Results Grid */}
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {searchResults.map((movie, index) => (
              <div
                key={`${movie.tmdb_id}-${index}`}
                className={`relative border rounded-lg p-4 cursor-pointer transition-all ${
                  selectedMovie?.tmdb_id === movie.tmdb_id
                    ? 'border-blue-500 bg-blue-50'
                    : 'border-gray-200 hover:border-gray-300'
                }`}
                onClick={() => handleSelectMovie(movie)}
              >
                {/* Match Type Badge */}
                <div className="absolute top-2 right-2">
                  <span
                    className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                      movie.matchType === 'exact'
                        ? 'bg-green-100 text-green-800'
                        : 'bg-yellow-100 text-yellow-800'
                    }`}
                  >
                    {movie.matchType === 'exact' ? 'Exact Match' : 'Partial Match'}
                  </span>
                </div>

                {/* Selection Indicator */}
                {selectedMovie?.tmdb_id === movie.tmdb_id && (
                  <div className="absolute top-2 left-2">
                    <div className="w-5 h-5 bg-blue-600 rounded-full flex items-center justify-center">
                      <svg className="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                      </svg>
                    </div>
                  </div>
                )}

                {/* Movie Poster */}
                <div className="mb-3">
                  {movie.poster_url ? (
                    <img
                      src={movie.poster_url}
                      alt={movie.title}
                      className="w-full h-48 object-cover rounded"
                      onError={(e) => {
                        (e.target as HTMLImageElement).style.display = 'none';
                      }}
                    />
                  ) : (
                    <div className="w-full h-48 bg-gray-200 rounded flex items-center justify-center">
                      <svg className="w-12 h-12 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clipRule="evenodd" />
                      </svg>
                    </div>
                  )}
                </div>

                {/* Movie Details */}
                <div className="space-y-2">
                  <h4 className="font-medium text-gray-900 leading-tight">
                    {movie.title}
                  </h4>
                  <p className="text-sm text-gray-600">
                    Released: {movie.release_year}
                  </p>
                  <p className="text-sm text-gray-600">
                    TMDB ID: {movie.tmdb_id}
                  </p>
                  {movie.vote_average && (
                    <p className="text-sm text-gray-600">
                      Rating: {movie.vote_average}/10
                    </p>
                  )}
                  {movie.overview && (
                    <p className="text-xs text-gray-500 line-clamp-3">
                      {movie.overview.length > 100 
                        ? `${movie.overview.substring(0, 100)}...`
                        : movie.overview
                      }
                    </p>
                  )}
                </div>
              </div>
            ))}
          </div>

          {/* Error Display */}
          {error && (
            <div className="p-3 bg-red-50 border border-red-200 rounded-md">
              <p className="text-sm text-red-700">{error}</p>
            </div>
          )}

          {/* Action Buttons */}
          <div className="flex justify-end space-x-3 pt-4 border-t border-gray-200">
            <button
              type="button"
              onClick={onCancel}
              disabled={isCreating}
              className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
            >
              Cancel
            </button>
            <button
              type="button"
              onClick={handleCreateWithoutTMDB}
              disabled={isCreating}
              className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
            >
              Create Without TMDB Data
            </button>
            <button
              type="button"
              onClick={handleCreateWithSelection}
              disabled={isCreating || !selectedMovie}
              className="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {isCreating ? (
                <span className="flex items-center space-x-2">
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                  <span>Creating...</span>
                </span>
              ) : (
                'Create Movie with Selected Data'
              )}
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default TMDBEnhancedCreateForm;
