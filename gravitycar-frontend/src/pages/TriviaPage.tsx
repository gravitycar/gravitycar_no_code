import React from 'react';
import TriviaGamePage from '../components/trivia/TriviaGamePage';

/**
 * Trivia Page Component
 * I've added another comment.
 * Full page wrapper for the Movie Quote Trivia Game
 * Integrates with the main application layout and navigation
 */
const TriviaPage: React.FC = () => {
  return (
    <div className="min-h-screen bg-gray-50">
      <TriviaGamePage />
    </div>
  );
};

export default TriviaPage;
