import React from 'react';
import { useParams } from 'react-router-dom';
import GenericCrudPage from '../crud/GenericCrudPage';

/**
 * DynamicModelRoute - Handles routing for any model using GenericCrudPage
 * This allows the navigation system to work with any model without requiring
 * explicit route definitions for each model
 */
const DynamicModelRoute: React.FC = () => {
  const { modelName } = useParams<{ modelName: string }>();
  
  if (!modelName) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-center">
          <h1 className="text-4xl font-bold text-gray-900 mb-4">Invalid Model</h1>
          <p className="text-gray-600 mb-4">No model specified</p>
          <a href="/dashboard" className="text-blue-600 hover:text-blue-800">
            Go to Dashboard
          </a>
        </div>
      </div>
    );
  }

  // Convert route parameter to proper model name format
  // e.g., 'users' -> 'Users', 'movie-quotes' -> 'Movie_Quotes'
  const formattedModelName = formatModelName(modelName);
  
  // Generate a user-friendly title
  const title = generateModelTitle(formattedModelName);
  
  return (
    <GenericCrudPage
      modelName={formattedModelName}
      title={title}
      description={`Manage ${title.toLowerCase()} in your system`}
    />
  );
};

/**
 * Convert URL parameter to proper model name format
 * Examples:
 * - 'users' -> 'Users'
 * - 'movies' -> 'Movies' 
 * - 'movie-quotes' -> 'Movie_Quotes'
 * - 'movie_quotes' -> 'Movie_Quotes'
 * - 'googleoauthtokens' -> 'GoogleOauthTokens'
 * - 'jwtrefreshtokens' -> 'JwtRefreshTokens'
 */
function formatModelName(urlParam: string): string {
  // Handle different URL formats
  const normalized = urlParam
    .replace(/-/g, '_') // Convert hyphens to underscores
    .toLowerCase();
  
  // Known model name mappings for special cases
  const modelNameMappings: { [key: string]: string } = {
    'googleoauthtokens': 'GoogleOauthTokens',
    'jwtrefreshtokens': 'JwtRefreshTokens',
    'movie_quotes': 'Movie_Quotes',
    'movie_quote_trivia_games': 'Movie_Quote_Trivia_Games',
    'movie_quote_trivia_questions': 'Movie_Quote_Trivia_Questions',
    // Add more mappings as needed
  };
  
  // Check for exact mapping first
  if (modelNameMappings[normalized]) {
    return modelNameMappings[normalized];
  }
  
  // Fall back to simple capitalization
  const parts = normalized.split('_').map(part => 
    part.charAt(0).toUpperCase() + part.slice(1)
  );
  
  return parts.join('_');
}

/**
 * Generate user-friendly title from model name
 * Examples:
 * - 'Users' -> 'Users'
 * - 'Movie_Quotes' -> 'Movie Quotes'
 */
function generateModelTitle(modelName: string): string {
  return modelName.replace(/_/g, ' ');
}

export default DynamicModelRoute;