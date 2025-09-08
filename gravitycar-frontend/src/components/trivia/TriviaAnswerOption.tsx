import React from 'react';
import type { TriviaMovieOption } from './types';

interface TriviaAnswerOptionProps {
  movieOption: TriviaMovieOption;
  optionIndex: number;
  isSelected: boolean;
  isDisabled: boolean;
  onSelect: () => void;
}

/**
 * Individual Answer Option Component
 * Displays a movie poster and title option with interactive styling
 */
const TriviaAnswerOption: React.FC<TriviaAnswerOptionProps> = ({
  movieOption,
  optionIndex,
  isSelected,
  isDisabled,
  onSelect
}) => {
  const optionLabels = ['A', 'B', 'C'];
  const optionLabel = optionLabels[optionIndex] || `${optionIndex + 1}`;

  return (
    <button
      onClick={onSelect}
      disabled={isDisabled}
      className={`
        w-full p-4 text-center rounded-lg border-2 transition-all duration-200 font-medium
        ${
          isSelected
            ? 'border-blue-500 bg-blue-50 text-blue-900 shadow-lg transform scale-105'
            : 'border-gray-200 bg-white text-gray-800 hover:border-blue-300 hover:bg-blue-25'
        }
        ${
          isDisabled && !isSelected
            ? 'opacity-50 cursor-not-allowed'
            : 'cursor-pointer hover:shadow-md'
        }
        focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2
      `}
    >
      <div className="flex flex-col items-center space-y-3">
        {/* Option Label */}
        <div
          className={`
            w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
            ${
              isSelected
                ? 'bg-blue-500 text-white'
                : 'bg-gray-100 text-gray-600'
            }
          `}
        >
          {optionLabel}
        </div>

        {/* Movie Poster */}
        <div className="w-full h-64 bg-gray-100 rounded-md overflow-hidden">
          {movieOption.poster_url ? (
            <img
              src={movieOption.poster_url}
              alt={`${movieOption.name} movie poster`}
              className="w-full h-full object-cover"
              onError={(e) => {
                // Fallback to placeholder if image fails to load
                (e.target as HTMLImageElement).src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjMwMCIgdmlld0JveD0iMCAwIDIwMCAzMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMzAwIiBmaWxsPSIjRjNGNEY2Ii8+CjxwYXRoIGQ9Ik04NyAxMDBIMTEzVjEyNkg4N1YxMDBaIiBmaWxsPSIjOUI5QjlCIi8+CjxwYXRoIGQ9Ik02MiAxNTBIMTM4VjE3NEg2MlYxNTBaIiBmaWxsPSIjOUI5QjlCIi8+CjxwYXRoIGQ9Ik03NSAxODhIMTI1VjIwMEg3NVYxODhaIiBmaWxsPSIjOUI5QjlCIi8+Cjx0ZXh0IHg9IjEwMCIgeT0iMjMwIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5QjlCOUIiPk5vIEltYWdlPC90ZXh0Pgo8L3N2Zz4K';
              }}
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center text-gray-400 text-sm">
              <div className="text-center">
                <div className="text-4xl mb-2">🎬</div>
                <div>No Poster</div>
              </div>
            </div>
          )}
        </div>

        {/* Movie Title */}
        <div className="w-full">
          <h3 className="text-lg font-medium leading-tight">
            {movieOption.name}
          </h3>
          {movieOption.year && (
            <p className="text-sm text-gray-500 mt-1">
              {movieOption.year}
            </p>
          )}
        </div>

        {/* Selection Indicator */}
        {isSelected && (
          <div className="text-blue-500">
            <svg
              className="w-6 h-6"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M5 13l4 4L19 7"
              />
            </svg>
          </div>
        )}
      </div>
    </button>
  );
};

export default TriviaAnswerOption;
