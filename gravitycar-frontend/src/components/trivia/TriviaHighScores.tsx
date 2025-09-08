import React, { useState, useEffect } from 'react';
import { useGameState } from '../../hooks/useGameState';

interface TriviaHighScoresProps {
  onBackToGame: () => void;
  onPlayAgain: () => void;
}

/**
 * High Scores Component
 * Displays leaderboard and statistics
 */
const TriviaHighScores: React.FC<TriviaHighScoresProps> = ({
  onBackToGame,
  onPlayAgain
}) => {
  const { highScores, fetchHighScores, isLoading, error } = useGameState();
  const [timeFilter, setTimeFilter] = useState<'all' | 'today' | 'week' | 'month'>('all');

  useEffect(() => {
    fetchHighScores();
  }, [fetchHighScores, timeFilter]);

  const getRankIcon = (rank: number): string => {
    switch (rank) {
      case 1:
        return '🥇';
      case 2:
        return '🥈';
      case 3:
        return '🥉';
      default:
        return `#${rank}`;
    }
  };

  const getRankColor = (rank: number): string => {
    switch (rank) {
      case 1:
        return 'bg-yellow-50 border-yellow-200 text-yellow-800';
      case 2:
        return 'bg-gray-50 border-gray-200 text-gray-800';
      case 3:
        return 'bg-orange-50 border-orange-200 text-orange-800';
      default:
        return 'bg-white border-gray-200 text-gray-800';
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-purple-50 to-blue-100 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <p className="text-gray-600">Loading high scores...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-purple-50 to-blue-100 py-8">
      <div className="max-w-6xl mx-auto px-4">
        {/* Header */}
        <div className="text-center mb-8">
          <h1 className="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
            🏆 High Scores
          </h1>
          <p className="text-xl text-gray-600">
            Movie Quote Trivia Champions
          </p>
        </div>

        {/* Time Filter */}
        <div className="flex justify-center mb-8">
          <div className="bg-white rounded-lg p-1 shadow-md">
            {(['all', 'today', 'week', 'month'] as const).map((filter) => (
              <button
                key={filter}
                onClick={() => setTimeFilter(filter)}
                className={`px-4 py-2 rounded-md text-sm font-medium capitalize transition-colors ${
                  timeFilter === filter
                    ? 'bg-blue-600 text-white shadow-sm'
                    : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
                }`}
              >
                {filter === 'all' ? 'All Time' : filter}
              </button>
            ))}
          </div>
        </div>

        {/* Error Message */}
        {error && (
          <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-8">
            <p className="text-red-700">{error}</p>
          </div>
        )}

        {/* High Scores List */}
        <div className="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-8">
          <div className="bg-gray-50 px-6 py-4 border-b border-gray-200">
            <h2 className="text-lg font-semibold text-gray-900">Leaderboard</h2>
          </div>

          {highScores.length === 0 ? (
            <div className="p-8 text-center text-gray-500">
              <p>No high scores yet. Be the first to play!</p>
            </div>
          ) : (
            <div className="divide-y divide-gray-200">
              {highScores.map((score) => (
                <div
                  key={score.id}
                  className={`p-6 ${getRankColor(score.rank)} transition-colors hover:bg-opacity-75`}
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                      {/* Rank */}
                      <div className="text-2xl font-bold min-w-16 text-center">
                        {getRankIcon(score.rank)}
                      </div>

                      {/* Player Info */}
                      <div>
                        <h3 className="text-lg font-semibold text-gray-900">
                          {score.playerName}
                        </h3>
                        <p className="text-sm text-gray-600">
                          {score.dateCompleted}
                        </p>
                      </div>
                    </div>

                    {/* Stats */}
                    <div className="flex space-x-8 text-sm">
                      <div className="text-center">
                        <div className="text-2xl font-bold text-blue-600">
                          {score.score.toLocaleString()}
                        </div>
                        <div className="text-gray-500">Score</div>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Statistics Summary */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <div className="bg-white rounded-lg shadow-md p-6 text-center">
            <div className="text-3xl font-bold text-blue-600 mb-2">
              {highScores.length > 0 ? highScores[0].score.toLocaleString() : '0'}
            </div>
            <div className="text-gray-600">Highest Score</div>
          </div>

          <div className="bg-white rounded-lg shadow-md p-6 text-center">
            <div className="text-3xl font-bold text-green-600 mb-2">
              {highScores.length > 0 
                ? Math.round(highScores.reduce((acc, score) => acc + score.score, 0) / highScores.length).toLocaleString()
                : '0'}
            </div>
            <div className="text-gray-600">Average Score</div>
          </div>

          <div className="bg-white rounded-lg shadow-md p-6 text-center">
            <div className="text-3xl font-bold text-purple-600 mb-2">
              {highScores.length}
            </div>
            <div className="text-gray-600">Total Games</div>
          </div>
        </div>

        {/* Actions */}
        <div className="flex flex-col md:flex-row gap-4 justify-center">
          <button
            onClick={onPlayAgain}
            className="px-8 py-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-lg hover:shadow-xl transform hover:scale-105"
          >
            🎮 Play Again
          </button>

          <button
            onClick={onBackToGame}
            className="px-8 py-4 bg-gray-600 text-white font-semibold rounded-lg hover:bg-gray-700 transition-colors shadow-lg hover:shadow-xl transform hover:scale-105"
          >
            🏠 Back to Game
          </button>
        </div>
      </div>
    </div>
  );
};

export default TriviaHighScores;
