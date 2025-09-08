import React, { useState, useEffect } from 'react';
import type { TriviaGame } from './types';

interface TriviaGameCompleteProps {
  game: TriviaGame;
  onPlayAgain: () => void;
  onViewHighScores: () => void;
  onReturnHome: () => void;
}

/**
 * Game Complete Component
 * Shows final score, stats, and actions after game completion
 */
const TriviaGameComplete: React.FC<TriviaGameCompleteProps> = ({
  game,
  onPlayAgain,
  onViewHighScores,
  onReturnHome
}) => {
  const [showCelebration, setShowCelebration] = useState(true);
  const [gameStats, setGameStats] = useState({
    correctAnswers: 0,
    totalQuestions: game.questions.length,
    accuracy: 0,
    totalTime: 0,
    averageTimePerQuestion: 0
  });

  useEffect(() => {
    // Calculate game statistics
    const correctAnswers = game.questions.filter(q => q.correct).length;
    const accuracy = Math.round((correctAnswers / game.questions.length) * 100);
    
    // Calculate actual time stats using game start and end times
    let actualTotalTime = 0;
    let averageTimePerQuestion = 0;
    
    if (game.startTime && game.endTime) {
      const startTime = new Date(game.startTime).getTime();
      const endTime = new Date(game.endTime).getTime();
      actualTotalTime = Math.floor((endTime - startTime) / 1000);
      averageTimePerQuestion = Math.round(actualTotalTime / game.questions.length);
    } else {
      // Fallback to estimation if timing data is not available
      actualTotalTime = game.questions.length * 15; // Estimate 15 seconds per question
      averageTimePerQuestion = Math.round(actualTotalTime / game.questions.length);
    }

    setGameStats({
      correctAnswers,
      totalQuestions: game.questions.length,
      accuracy,
      totalTime: actualTotalTime,
      averageTimePerQuestion
    });

    // Hide celebration after 3 seconds
    const timer = setTimeout(() => {
      setShowCelebration(false);
    }, 3000);

    return () => clearTimeout(timer);
  }, [game]);

  const getPerformanceMessage = (accuracy: number): { message: string; color: string; emoji: string } => {
    if (accuracy >= 90) {
      return { 
        message: "Outstanding! You're a movie quote master!", 
        color: "text-green-600", 
        emoji: "🏆" 
      };
    } else if (accuracy >= 75) {
      return { 
        message: "Great job! You know your movies well!", 
        color: "text-blue-600", 
        emoji: "🎬" 
      };
    } else if (accuracy >= 60) {
      return { 
        message: "Good effort! Keep watching more movies!", 
        color: "text-yellow-600", 
        emoji: "⭐" 
      };
    } else {
      return { 
        message: "Time to binge some classic movies!", 
        color: "text-red-600", 
        emoji: "📚" 
      };
    }
  };

  const performance = getPerformanceMessage(gameStats.accuracy);

  const formatTime = (seconds: number): string => {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-purple-50 to-blue-100 py-8">
      {/* Celebration Animation */}
      {showCelebration && (
        <div className="fixed inset-0 z-50 flex items-center justify-center pointer-events-none">
          <div className="text-8xl animate-bounce">
            {performance.emoji}
          </div>
        </div>
      )}

      <div className="max-w-4xl mx-auto px-4">
        {/* Header */}
        <div className="text-center mb-8">
          <h1 className="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
            Game Complete!
          </h1>
          <p className={`text-xl md:text-2xl font-semibold ${performance.color}`}>
            {performance.message}
          </p>
        </div>

        {/* Score Card */}
        <div className="bg-white rounded-2xl shadow-xl border border-gray-200 p-8 mb-8">
          <div className="text-center mb-8">
            <div className="text-6xl font-bold text-blue-600 mb-2">
              {game.score.toLocaleString()}
            </div>
            <div className="text-gray-600 text-lg">Final Score</div>
          </div>

          {/* Stats Grid */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
            <div className="text-center p-4 bg-green-50 rounded-lg">
              <div className="text-2xl font-bold text-green-600">
                {gameStats.correctAnswers}
              </div>
              <div className="text-green-700 text-sm">Correct</div>
            </div>

            <div className="text-center p-4 bg-blue-50 rounded-lg">
              <div className="text-2xl font-bold text-blue-600">
                {gameStats.accuracy}%
              </div>
              <div className="text-blue-700 text-sm">Accuracy</div>
            </div>

            <div className="text-center p-4 bg-purple-50 rounded-lg">
              <div className="text-2xl font-bold text-purple-600">
                {formatTime(gameStats.totalTime)}
              </div>
              <div className="text-purple-700 text-sm">Total Time</div>
            </div>

            <div className="text-center p-4 bg-orange-50 rounded-lg">
              <div className="text-2xl font-bold text-orange-600">
                {gameStats.averageTimePerQuestion}s
              </div>
              <div className="text-orange-700 text-sm">Avg/Question</div>
            </div>
          </div>

          {/* Question Breakdown */}
          <div className="border-t pt-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Question Breakdown</h3>
            <div className="grid grid-cols-5 md:grid-cols-10 gap-2">
              {game.questions.map((question, index) => (
                <div
                  key={question.id}
                  className={`
                    w-10 h-10 rounded-full flex items-center justify-center text-xs font-semibold text-white
                    ${question.correct ? 'bg-green-500' : 'bg-red-500'}
                  `}
                  title={`Question ${index + 1}: ${question.correct ? 'Correct' : 'Incorrect'}`}
                >
                  {index + 1}
                </div>
              ))}
            </div>
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
            onClick={onViewHighScores}
            className="px-8 py-4 bg-purple-600 text-white font-semibold rounded-lg hover:bg-purple-700 transition-colors shadow-lg hover:shadow-xl transform hover:scale-105"
          >
            🏆 High Scores
          </button>

          <button
            onClick={onReturnHome}
            className="px-8 py-4 bg-gray-600 text-white font-semibold rounded-lg hover:bg-gray-700 transition-colors shadow-lg hover:shadow-xl transform hover:scale-105"
          >
            🏠 Return Home
          </button>
        </div>

        {/* Share Section */}
        <div className="mt-8 text-center">
          <p className="text-gray-600 mb-4">Share your score:</p>
          <div className="flex justify-center gap-4">
            <button className="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
              Share on Twitter
            </button>
            <button className="px-6 py-2 bg-blue-700 text-white rounded-lg hover:bg-blue-800 transition-colors">
              Share on Facebook
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default TriviaGameComplete;
