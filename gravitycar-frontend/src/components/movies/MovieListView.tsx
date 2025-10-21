/* eslint-disable @typescript-eslint/no-explicit-any, react-hooks/exhaustive-deps */
import React, { useState, useEffect } from 'react';
import { apiService } from '../../services/api';
import type { Movie } from '../../types';
import { MovieCreateForm } from './MovieCreateForm';

interface MovieListViewProps {
  refreshTrigger?: number;
}

export const MovieListView: React.FC<MovieListViewProps> = ({ refreshTrigger = 0 }) => {
  const [movies, setMovies] = useState<Movie[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [editingMovie, setEditingMovie] = useState<Movie | null>(null);
  const [sortBy, setSortBy] = useState<'name' | 'release_year' | 'created_at'>('name');
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('asc');

  // Load movies
  const loadMovies = async () => {
    try {
      setIsLoading(true);
      setError(null);
      
      const response = await apiService.getMovies(1, 50); // Get first 50 movies
      if (response.success) {
        const sortedMovies = response.data;
        
        // Sort movies
        sortedMovies.sort((a, b) => {
          let aValue: any = a[sortBy];
          let bValue: any = b[sortBy];
          
          if (sortBy === 'name') {
            aValue = aValue?.toLowerCase() || '';
            bValue = bValue?.toLowerCase() || '';
          }
          
          if (sortOrder === 'asc') {
            return aValue > bValue ? 1 : -1;
          } else {
            return aValue < bValue ? 1 : -1;
          }
        });
        
        setMovies(sortedMovies);
      } else {
        setError(response.message || 'Failed to load movies');
      }
    } catch (err) {
      setError('Failed to load movies');
      console.error('Error loading movies:', err);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    loadMovies();
  }, [refreshTrigger, sortBy, sortOrder]);

  const handleCreateMovie = async (movieData: Partial<Movie>) => {
    try {
      const response = await apiService.create<Movie>('movies', movieData);
      if (response.success) {
        setShowCreateForm(false);
        loadMovies(); // Refresh the list
      } else {
        throw new Error(response.message || 'Failed to create movie');
      }
    } catch (error) {
      console.error('Error creating movie:', error);
      throw error; // Re-throw to be handled by the form
    }
  };

  const handleEditMovie = async (movieData: Partial<Movie>) => {
    if (!editingMovie) return;
    
    try {
      const response = await apiService.update<Movie>('movies', editingMovie.id, movieData);
      if (response.success) {
        setEditingMovie(null);
        loadMovies(); // Refresh the list
      } else {
        throw new Error(response.message || 'Failed to update movie');
      }
    } catch (error) {
      console.error('Error updating movie:', error);
      throw error; // Re-throw to be handled by the form
    }
  };

  const handleDeleteMovie = async (movie: Movie) => {
    if (!confirm(`Are you sure you want to delete "${movie.name}"?`)) {
      return;
    }
    
    try {
      const response = await apiService.delete('movies', movie.id);
      if (response.success) {
        loadMovies(); // Refresh the list
      } else {
        alert(response.message || 'Failed to delete movie');
      }
    } catch (error) {
      console.error('Error deleting movie:', error);
      alert('Failed to delete movie');
    }
  };

  const formatReleaseYear = (movie: Movie): string => {
    return movie.release_year?.toString() || 'Unknown';
  };

  const getMoviePoster = (movie: Movie): string => {
    // Use TMDB poster if available, otherwise use a placeholder
    if (movie.poster_url && movie.poster_url.startsWith('http')) {
      return movie.poster_url;
    }
    return '/api/placeholder/300/450';
  };

  const renderMovieRow = (movie: Movie) => (
    <tr key={movie.id} className="hover:bg-gray-50">
      <td className="px-6 py-4 whitespace-nowrap">
        <div className="flex items-center">
          <img
            className="h-16 w-12 rounded object-cover mr-4"
            src={getMoviePoster(movie)}
            alt={movie.name}
            onError={(e) => {
              e.currentTarget.src = '/api/placeholder/300/450';
            }}
          />
          <div>
            <div className="text-sm font-medium text-gray-900">{movie.name}</div>
            {movie.tmdb_id && (
              <div className="text-xs text-blue-600">TMDB ID: {movie.tmdb_id}</div>
            )}
          </div>
        </div>
      </td>
      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
        {formatReleaseYear(movie)}
      </td>
      <td className="px-6 py-4 text-sm text-gray-900 max-w-xs">
        <div className="line-clamp-2">{movie.synopsis || 'No description'}</div>
      </td>
      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
        {movie.obscurity_score ? `${movie.obscurity_score}%` : 'N/A'}
      </td>
      <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
        <div className="flex space-x-2 justify-end">
          <button
            onClick={() => setEditingMovie(movie)}
            className="text-blue-600 hover:text-blue-900"
          >
            Edit
          </button>
          <button
            onClick={() => handleDeleteMovie(movie)}
            className="text-red-600 hover:text-red-900"
          >
            Delete
          </button>
          {movie.trailer_url && (
            <a
              href={movie.trailer_url}
              target="_blank"
              rel="noopener noreferrer"
              className="text-green-600 hover:text-green-900"
            >
              Trailer
            </a>
          )}
        </div>
      </td>
    </tr>
  );

  if (showCreateForm) {
    return (
      <MovieCreateForm
        onSave={handleCreateMovie}
        onCancel={() => setShowCreateForm(false)}
      />
    );
  }

  if (editingMovie) {
    return (
      <MovieCreateForm
        onSave={handleEditMovie}
        onCancel={() => setEditingMovie(null)}
        initialData={editingMovie}
      />
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold text-gray-900">Movies</h1>
        <button
          onClick={() => setShowCreateForm(true)}
          className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
        >
          Add Movie
        </button>
      </div>

      {/* Controls */}
      <div className="flex justify-between items-center bg-white p-4 rounded-lg shadow">
        <div className="flex items-center space-x-4">
          <div className="flex items-center space-x-2">
            <label className="text-sm font-medium text-gray-700">Sort by:</label>
            <select
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value as any)}
              className="border border-gray-300 rounded px-2 py-1 text-sm"
            >
              <option value="name">Title</option>
              <option value="release_year">Release Year</option>
              <option value="created_at">Date Added</option>
            </select>
          </div>
          
          <div className="flex items-center space-x-2">
            <label className="text-sm font-medium text-gray-700">Order:</label>
            <select
              value={sortOrder}
              onChange={(e) => setSortOrder(e.target.value as any)}
              className="border border-gray-300 rounded px-2 py-1 text-sm"
            >
              <option value="asc">Ascending</option>
              <option value="desc">Descending</option>
            </select>
          </div>
        </div>
      </div>

      {/* Content */}
      {isLoading ? (
        <div className="flex justify-center items-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        </div>
      ) : error ? (
        <div className="text-center py-12">
          <p className="text-red-600 mb-4">{error}</p>
          <button
            onClick={loadMovies}
            className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
          >
            Retry
          </button>
        </div>
      ) : movies.length === 0 ? (
        <div className="text-center py-12">
          <p className="text-gray-500 mb-4">No movies found.</p>
          <button
            onClick={() => setShowCreateForm(true)}
            className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700"
          >
            Add First Movie
          </button>
        </div>
      ) : (
        <div className="bg-white shadow overflow-hidden sm:rounded-md">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Movie
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Year
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Description
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Obscurity
                </th>
                <th className="relative px-6 py-3">
                  <span className="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {movies.map(renderMovieRow)}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
};
