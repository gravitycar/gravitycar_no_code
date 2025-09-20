/* eslint-disable @typescript-eslint/no-explicit-any */
import React, { useState, useEffect, useCallback } from 'react';
import { TMDBMovieSelector } from './TMDBMovieSelector';
import { VideoEmbed } from '../fields/VideoEmbed';
import { apiService } from '../../services/api';
import type { Movie } from '../../types';

interface TMDBMovie {
  tmdb_id: number;
  title: string;
  release_year: number;
  poster_url: string;
  overview: string;
  obscurity_score: number;
  vote_average: number;
  popularity: number;
}

interface MovieCreateFormProps {
  onSave: (movie: Partial<Movie>) => Promise<void>;
  onCancel: () => void;
  isLoading?: boolean;
  initialData?: Movie;
}

export const MovieCreateForm: React.FC<MovieCreateFormProps> = ({ 
  onSave, 
  onCancel, 
  isLoading = false,
  initialData
}) => {
  const [formData, setFormData] = useState<Partial<Movie>>(() => {
    if (initialData) {
      return {
        name: initialData.name || '',
        synopsis: initialData.synopsis || '',
        poster_url: initialData.poster_url || '',
        trailer_url: initialData.trailer_url || '',
        obscurity_score: initialData.obscurity_score,
        tmdb_id: initialData.tmdb_id,
        release_year: initialData.release_year
      };
    }
    return {
      name: '',
      synopsis: '',
      poster_url: '',
      trailer_url: '',
      obscurity_score: undefined,
      tmdb_id: undefined,
      release_year: undefined
    };
  });
  
  const [tmdbState, setTmdbState] = useState({
    isSearching: false,
    showSelector: false,
    searchResults: [] as TMDBMovie[],
    matchType: null as string | null,
    lastSearchTitle: ''
  });
  
  const [errors, setErrors] = useState<Record<string, string>>({});
  
  // Update form data when initialData changes (for switching between create/edit modes)
  useEffect(() => {
    if (initialData) {
      setFormData({
        name: initialData.name || '',
        synopsis: initialData.synopsis || '',
        poster_url: initialData.poster_url || '',
        trailer_url: initialData.trailer_url || '',
        obscurity_score: initialData.obscurity_score,
        tmdb_id: initialData.tmdb_id,
        release_year: initialData.release_year
      });
      // Reset TMDB state when switching to edit mode
      setTmdbState({
        isSearching: false,
        showSelector: false,
        searchResults: [],
        matchType: initialData.tmdb_id ? null : null, // Don't trigger search for existing movies
        lastSearchTitle: initialData.name || ''
      });
    } else {
      // Reset to empty form for create mode
      setFormData({
        name: '',
        synopsis: '',
        poster_url: '',
        trailer_url: '',
        obscurity_score: undefined,
        tmdb_id: undefined,
        release_year: undefined
      });
      setTmdbState({
        isSearching: false,
        showSelector: false,
        searchResults: [],
        matchType: null,
        lastSearchTitle: ''
      });
    }
  }, [initialData]);
  
  // Debounced TMDB search
  const searchTMDB = useCallback(async (title: string, forceShowSelector: boolean = false) => {
    if (title.length < 3 || title === tmdbState.lastSearchTitle) {
      return;
    }
    
    setTmdbState(prev => ({ ...prev, isSearching: true, lastSearchTitle: title }));
    
    try {
      const response = await apiService.searchTMDB(title);
      const { exact_match, partial_matches, match_type } = response.data;
      
      if (match_type === 'exact' && exact_match && !forceShowSelector) {
        // Auto-apply exact match only when not manually triggered
        await applyTMDBData(exact_match);
      } else if (match_type === 'multiple' && partial_matches.length > 0) {
        // Show selection dialog for multiple matches
        setTmdbState(prev => ({
          ...prev,
          isSearching: false,
          showSelector: true,
          searchResults: partial_matches,
          matchType: 'multiple'
        }));
      } else if (match_type === 'exact' && exact_match && forceShowSelector) {
        // When manually triggered, show all available matches (exact + partial)
        let allMatches = [exact_match];
        if (partial_matches && partial_matches.length > 0) {
          allMatches = allMatches.concat(partial_matches);
        }
        setTmdbState(prev => ({
          ...prev,
          isSearching: false,
          showSelector: true,
          searchResults: allMatches,
          matchType: 'manual'
        }));
      } else if (forceShowSelector && partial_matches && partial_matches.length > 0) {
        // Manual search with only partial matches
        setTmdbState(prev => ({
          ...prev,
          isSearching: false,
          showSelector: true,
          searchResults: partial_matches,
          matchType: 'manual'
        }));
      } else {
        // No matches found
        setTmdbState(prev => ({
          ...prev,
          isSearching: false,
          matchType: 'none'
        }));
      }
      
    } catch (error) {
      console.error('TMDB search failed:', error);
      setTmdbState(prev => ({ ...prev, isSearching: false }));
    }
  }, [tmdbState.lastSearchTitle]);
  
  // Debounced effect for title changes (only for create mode or when user manually changes title)
  useEffect(() => {
    // Don't auto-search if we already have TMDB data (edit mode) unless user is actively changing the title
    if (formData.name && formData.name.length >= 3 && !formData.tmdb_id) {
      const timer = setTimeout(() => {
        searchTMDB(formData.name!, false); // Auto-apply exact matches for automatic searches
      }, 500);
      
      return () => clearTimeout(timer);
    }
  }, [formData.name, searchTMDB, formData.tmdb_id]);
  
  const applyTMDBData = async (tmdbMovie: TMDBMovie) => {
    try {
      const enrichmentResponse = await apiService.enrichMovieWithTMDB(tmdbMovie.tmdb_id.toString());
      const enrichmentData = enrichmentResponse.data;
      
      setFormData(prev => ({
        ...prev,
        tmdb_id: enrichmentData.tmdb_id,
        synopsis: enrichmentData.synopsis,
        poster_url: enrichmentData.poster_url,
        trailer_url: enrichmentData.trailer_url,
        obscurity_score: enrichmentData.obscurity_score,
        release_year: enrichmentData.release_year,
        // Keep user-entered title
        name: prev.name
      }));
      
      setTmdbState(prev => ({ ...prev, showSelector: false }));
    } catch (error) {
      console.error('TMDB enrichment failed:', error);
    }
  };
  
  const handleInputChange = (field: keyof Movie, value: any) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    
    // Clear errors when user starts typing
    if (errors[field]) {
      setErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors[field];
        return newErrors;
      });
    }
  };
  
  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {};
    
    if (!formData.name?.trim()) {
      newErrors.name = 'Movie title is required';
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };
  
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }
    
    try {
      await onSave(formData);
    } catch (error) {
      console.error('Failed to save movie:', error);
    }
  };
  
  const clearTMDBData = () => {
    setFormData(prev => ({
      ...prev,
      tmdb_id: undefined,
      synopsis: '',
      poster_url: '',
      trailer_url: '',
      obscurity_score: undefined,
      release_year: undefined
    }));
    setTmdbState(prev => ({ 
      ...prev, 
      showSelector: false, 
      matchType: null,
      lastSearchTitle: ''
    }));
  };

  const handleManualTMDBSearch = () => {
    console.log('🔍 Manual TMDB Search triggered');
    console.log('Current formData.name:', formData.name);
    console.log('Name length:', formData.name?.length);
    
    if (formData.name && formData.name.length >= 3) {
      console.log('✅ Triggering TMDB search for:', formData.name);
      // Trigger manual TMDB search and always show selector
      searchTMDB(formData.name, true);
    } else {
      console.log('❌ Title too short or missing');
    }
  };
  
  return (
    <div className="max-w-2xl mx-auto">
      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Movie Title */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Movie Title *
          </label>
          <div className="relative">
            <input
              type="text"
              value={formData.name || ''}
              onChange={(e) => handleInputChange('name', e.target.value)}
              className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                errors.name ? 'border-red-300' : 'border-gray-300'
              }`}
              placeholder="Enter movie title..."
              required
            />
            {tmdbState.isSearching && (
              <div className="absolute right-3 top-3">
                <div className="animate-spin h-4 w-4 border-2 border-blue-500 border-t-transparent rounded-full"></div>
              </div>
            )}
          </div>
          {errors.name && (
            <p className="mt-1 text-sm text-red-600">{errors.name}</p>
          )}
          
          {/* TMDB Status */}
          {(() => {
            console.log('🎬 TMDB Status Debug:', {
              tmdb_id: formData.tmdb_id,
              name: formData.name,
              nameLength: formData.name?.length,
              matchType: tmdbState.matchType,
              isSearching: tmdbState.isSearching
            });
            return null;
          })()}
          {formData.tmdb_id ? (
            <div className="mt-2 flex items-center justify-between p-2 bg-green-50 border border-green-200 rounded">
              <div className="flex-1">
                <p className="text-sm text-green-700">
                  ✓ Matched with TMDB (ID: {formData.tmdb_id})
                </p>
              </div>
              <div className="flex space-x-2">
                <button
                  type="button"
                  onClick={handleManualTMDBSearch}
                  className="text-sm text-blue-600 hover:text-blue-800"
                  disabled={tmdbState.isSearching}
                >
                  Choose Different Match
                </button>
                <button
                  type="button"
                  onClick={clearTMDBData}
                  className="text-sm text-green-600 hover:text-green-800"
                >
                  Clear TMDB Data
                </button>
              </div>
            </div>
          ) : (
            <div className="mt-2">
              {tmdbState.matchType === 'none' && formData.name && formData.name.length >= 3 && !tmdbState.isSearching ? (
                <div className="flex items-center justify-between p-2 bg-yellow-50 border border-yellow-200 rounded">
                  <p className="text-sm text-yellow-600">
                    No TMDB matches found. You can enter data manually.
                  </p>
                  <button
                    type="button"
                    onClick={handleManualTMDBSearch}
                    className="text-sm text-blue-600 hover:text-blue-800"
                    disabled={tmdbState.isSearching}
                  >
                    Search TMDB Again
                  </button>
                </div>
              ) : formData.name && formData.name.length >= 3 && !tmdbState.isSearching ? (
                <div className="flex items-center justify-between p-2 bg-blue-50 border border-blue-200 rounded">
                  <p className="text-sm text-blue-600">
                    No TMDB match selected. Choose one for automatic data enrichment.
                  </p>
                  <button
                    type="button"
                    onClick={handleManualTMDBSearch}
                    className="text-sm text-blue-600 hover:text-blue-800"
                    disabled={tmdbState.isSearching}
                  >
                    Choose TMDB Match
                  </button>
                </div>
              ) : null}
            </div>
          )}
        </div>
        
        {/* Synopsis */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Synopsis
          </label>
          <textarea
            value={formData.synopsis || ''}
            onChange={(e) => handleInputChange('synopsis', e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            rows={4}
            placeholder="Enter movie synopsis..."
            readOnly={!!formData.tmdb_id}
          />
          {formData.tmdb_id && (
            <p className="mt-1 text-xs text-gray-500">
              Synopsis populated from TMDB data
            </p>
          )}
        </div>
        
        {/* Poster URL */}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Poster URL
          </label>
          <input
            type="url"
            value={formData.poster_url || ''}
            onChange={(e) => handleInputChange('poster_url', e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Enter poster image URL..."
            readOnly={!!formData.tmdb_id}
          />
          {formData.tmdb_id && (
            <p className="mt-1 text-xs text-gray-500">
              Poster URL populated from TMDB data
            </p>
          )}
        </div>
        
        {/* Poster Preview */}
        {formData.poster_url && (
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Poster Preview
            </label>
            <img
              src={formData.poster_url}
              alt="Movie poster"
              className="w-32 h-48 object-cover rounded border shadow-sm"
              onError={(e) => {
                const target = e.target as HTMLImageElement;
                target.style.display = 'none';
              }}
            />
          </div>
        )}
        
        {/* Trailer URL */}
        <div>
          <VideoEmbed
            label="Movie Trailer"
            value={formData.trailer_url || ''}
            onChange={(value) => handleInputChange('trailer_url', value)}
            readOnly={!!formData.tmdb_id}
            placeholder="Enter YouTube or Vimeo URL..."
          />
          {formData.tmdb_id && (
            <p className="mt-1 text-xs text-gray-500">
              Trailer URL populated from TMDB data
            </p>
          )}
        </div>
        
        {/* Release Year and Obscurity Score (Read-only TMDB fields) */}
        {(formData.release_year || formData.obscurity_score) && (
          <div className="grid grid-cols-2 gap-4">
            {formData.release_year && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Release Year
                </label>
                <input
                  type="number"
                  value={formData.release_year}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50"
                  readOnly
                />
              </div>
            )}
            
            {formData.obscurity_score && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Obscurity Score
                </label>
                <div className="flex items-center space-x-2">
                  <input
                    type="number"
                    value={formData.obscurity_score}
                    className="w-20 px-3 py-2 border border-gray-300 rounded-md bg-gray-50"
                    readOnly
                  />
                  <span className="text-sm text-gray-500">/ 5</span>
                </div>
              </div>
            )}
          </div>
        )}
        
        {/* Form Actions */}
        <div className="flex justify-end space-x-3 pt-6 border-t border-gray-200">
          <button
            type="button"
            onClick={onCancel}
            className="px-4 py-2 text-gray-700 border border-gray-300 rounded hover:bg-gray-50 transition-colors"
            disabled={isLoading}
          >
            Cancel
          </button>
          <button
            type="submit"
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 transition-colors"
            disabled={isLoading}
          >
            {isLoading ? 'Creating...' : 'Create Movie'}
          </button>
        </div>
      </form>
      
      {/* TMDB Movie Selector Modal */}
      <TMDBMovieSelector
        isOpen={tmdbState.showSelector}
        onClose={() => setTmdbState(prev => ({ ...prev, showSelector: false }))}
        onSelect={applyTMDBData}
        movies={tmdbState.searchResults}
        title={formData.name || ''}
      />
    </div>
  );
};
