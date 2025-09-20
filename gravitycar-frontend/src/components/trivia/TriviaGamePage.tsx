import React from 'react';
import TriviaGameBoard from './TriviaGameBoard';
import TriviaGameComplete from './TriviaGameComplete';
import TriviaHighScores from './TriviaHighScores';
import useGameState from '../../hooks/useGameState';
import { ErrorBoundary } from '../error/ErrorBoundary';
// import type { TriviaGame } from './types';

/**
 * Main Trivia Game Component
 * Manages game state and renders different phases
 */
export default function TriviaGamePage(): React.ReactElement {
  const {
    currentGame,
    gamePhase,
    isLoading,
    error,
    currentQuestionIndex,
    startNewGame,
    submitAnswer,
    completeGame,
    showHighScores,
    returnToWelcome,
    resetGame
  } = useGameState();

  const renderWelcomeScreen = () => (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center px-4">
      <div className="max-w-2xl w-full bg-white rounded-xl shadow-lg p-8 text-center">
        <div className="mb-8">
          <h1 className="text-4xl font-bold text-gray-900 mb-4">
            🎬 Movie Quote Trivia
          </h1>
          <p className="text-xl text-gray-600 mb-6">
            Test your knowledge of famous movie quotes! Can you identify which movie each quote came from?
          </p>
          <div className="bg-blue-50 rounded-lg p-4 mb-6">
            <h3 className="font-semibold text-blue-900 mb-2">How to Play:</h3>
            <ul className="text-sm text-blue-800 space-y-1">
              <li>• Read the movie quote carefully</li>
              <li>• Choose from 3 possible movie titles</li>
              <li>• Answer 15 questions total</li>
              <li>• Earn points for correct answers and speed</li>
            </ul>
          </div>
        </div>

        <div className="space-y-4">
          <button
            onClick={startNewGame}
            disabled={isLoading}
            className="w-full bg-blue-600 text-white py-4 px-6 rounded-lg font-semibold text-lg hover:bg-blue-700 transition-colors shadow-lg hover:shadow-xl transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {isLoading ? 'Starting Game...' : '🎮 Start New Game'}
          </button>

          <button
            onClick={showHighScores}
            className="w-full bg-purple-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-purple-700 transition-colors"
          >
            🏆 View High Scores
          </button>
        </div>

        {error && (
          <div className="mt-6 bg-red-50 border border-red-200 rounded-lg p-4">
            <p className="text-red-700">{error}</p>
            <button
              onClick={resetGame}
              className="mt-2 text-red-600 hover:text-red-800 font-medium"
            >
              Try Again
            </button>
          </div>
        )}
      </div>
    </div>
  );

  const renderContent = () => {
    switch (gamePhase) {
      case 'welcome':
        return renderWelcomeScreen();

      case 'playing':
        if (!currentGame) {
          return renderWelcomeScreen();
        }
        return (
          <TriviaGameBoard
            game={currentGame}
            currentQuestionIndex={currentQuestionIndex}
            onAnswer={submitAnswer}
            onComplete={completeGame}
            isLoading={isLoading}
          />
        );

      case 'complete':
        if (!currentGame) {
          return renderWelcomeScreen();
        }
        return (
          <TriviaGameComplete
            game={currentGame}
            onPlayAgain={startNewGame}
            onViewHighScores={showHighScores}
            onReturnHome={returnToWelcome}
          />
        );

      case 'high-scores':
        return (
          <TriviaHighScores
            onBackToGame={returnToWelcome}
            onPlayAgain={startNewGame}
          />
        );

      default:
        return renderWelcomeScreen();
    }
  };

  return (
    <ErrorBoundary>
      {renderContent()}
    </ErrorBoundary>
  );
}
