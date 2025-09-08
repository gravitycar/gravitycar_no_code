import React from 'react';

interface TriviaScoreDisplayProps {
  score: number;
  timeElapsed: number;
  totalQuestions: number;
  answeredQuestions: number;
}

/**
 * Score Display Component
 * Shows current score, time, and progress
 */
const TriviaScoreDisplay: React.FC<TriviaScoreDisplayProps> = ({
  score,
  timeElapsed,
  totalQuestions,
  answeredQuestions
}) => {
  const formatTime = (seconds: number): string => {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
  };

  const calculateAccuracy = (): number => {
    if (answeredQuestions === 0) return 0;
    // This is a simplified calculation - in reality, we'd track correct answers
    // For now, we'll estimate based on score vs time
    const estimatedCorrect = Math.floor(score / 100); // Assuming 100 points per correct answer
    return Math.round((estimatedCorrect / answeredQuestions) * 100);
  };

  const accuracy = calculateAccuracy();

  return (
    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4 min-w-48">
      <div className="grid grid-cols-2 gap-4 text-sm">
        {/* Score */}
        <div className="text-center">
          <div className="text-2xl font-bold text-blue-600">{score.toLocaleString()}</div>
          <div className="text-gray-500 text-xs">Score</div>
        </div>

        {/* Time */}
        <div className="text-center">
          <div className="text-2xl font-bold text-green-600">{formatTime(timeElapsed)}</div>
          <div className="text-gray-500 text-xs">Time</div>
        </div>

        {/* Progress */}
        <div className="text-center">
          <div className="text-lg font-bold text-purple-600">
            {answeredQuestions}/{totalQuestions}
          </div>
          <div className="text-gray-500 text-xs">Progress</div>
        </div>

        {/* Accuracy */}
        <div className="text-center">
          <div className="text-lg font-bold text-orange-600">{accuracy}%</div>
          <div className="text-gray-500 text-xs">Accuracy</div>
        </div>
      </div>

      {/* Progress Bar */}
      <div className="mt-3">
        <div className="bg-gray-200 rounded-full h-1.5">
          <div
            className="bg-blue-500 h-1.5 rounded-full transition-all duration-300"
            style={{
              width: `${(answeredQuestions / totalQuestions) * 100}%`
            }}
          />
        </div>
      </div>

      {/* Quick Stats */}
      <div className="mt-2 flex justify-between text-xs text-gray-500">
        <span>Remaining: {totalQuestions - answeredQuestions}</span>
        <span>Avg: {answeredQuestions > 0 ? Math.round(score / answeredQuestions) : 0}/Q</span>
      </div>
    </div>
  );
};

export default TriviaScoreDisplay;
