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
  // If the param already contains mixed case (PascalCase or has underscores
  // with capitals), it's already a proper model name — use it directly.
  // Examples: "EventProposedDates", "Movie_Quote_Trivia_Games"
  if (/[A-Z]/.test(urlParam.slice(1)) || urlParam.includes('_')) {
    return urlParam;
  }

  // Handle legacy lowercase URL formats (e.g., "users" -> "Users")
  return urlParam.charAt(0).toUpperCase() + urlParam.slice(1);
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