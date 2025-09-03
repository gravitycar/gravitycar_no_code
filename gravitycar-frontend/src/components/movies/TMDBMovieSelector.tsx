import React, { useState } from 'react';

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

interface TMDBMovieSelectorProps {
  isOpen: boolean;
  onClose: () => void;
  onSelect: (movie: TMDBMovie) => void;
  movies: TMDBMovie[];
  title: string;
}

export const TMDBMovieSelector: React.FC<TMDBMovieSelectorProps> = ({
  isOpen,
  onClose,
  onSelect,
  movies,
  title
}) => {
  const [selectedMovie, setSelectedMovie] = useState<TMDBMovie | null>(null);
  
  const handleSelect = () => {
    if (selectedMovie) {
      onSelect(selectedMovie);
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
              Choose the correct movie from TMDB search results
            </p>
          </div>
          
          <div className="p-6 overflow-y-auto max-h-96">
            <div className="space-y-4">
              {movies.map((movie) => (
                <div
                  key={movie.tmdb_id}
                  className={`border rounded-lg p-4 cursor-pointer transition-colors ${
                    selectedMovie?.tmdb_id === movie.tmdb_id
                      ? 'border-blue-500 bg-blue-50'
                      : 'border-gray-200 hover:border-gray-300'
                  }`}
                  onClick={() => setSelectedMovie(movie)}
                >
                  <div className="flex space-x-4">
                    {movie.poster_url ? (
                      <img
                        src={movie.poster_url}
                        alt={movie.title}
                        className="w-16 h-24 object-cover rounded flex-shrink-0"
                        onError={(e) => {
                          const target = e.target as HTMLImageElement;
                          target.style.display = 'none';
                        }}
                      />
                    ) : (
                      <div className="w-16 h-24 bg-gray-200 rounded flex items-center justify-center flex-shrink-0">
                        <svg className="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                          <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" />
                        </svg>
                      </div>
                    )}
                    
                    <div className="flex-1 min-w-0">
                      <h3 className="font-semibold text-lg text-gray-900">{movie.title}</h3>
                      <div className="flex items-center space-x-4 text-sm text-gray-600 mt-1">
                        {movie.release_year && <span>Year: {movie.release_year}</span>}
                        {movie.obscurity_score && (
                          <span>Obscurity: {movie.obscurity_score}/5</span>
                        )}
                        {movie.vote_average && (
                          <span>Rating: {movie.vote_average.toFixed(1)}/10</span>
                        )}
                      </div>
                      {movie.overview && (
                        <p className="text-sm text-gray-500 mt-2 line-clamp-3">
                          {movie.overview}
                        </p>
                      )}
                    </div>
                    
                    {selectedMovie?.tmdb_id === movie.tmdb_id && (
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
          </div>
          
          <div className="px-6 py-4 border-t border-gray-200 flex justify-between">
            <button
              onClick={onClose}
              className="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50 transition-colors"
            >
              Skip TMDB Match
            </button>
            <button
              onClick={handleSelect}
              disabled={!selectedMovie}
              className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              Select This Movie
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
