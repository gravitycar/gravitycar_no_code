import React, { useState, useEffect } from 'react';
import TriviaQuestion from './TriviaQuestion.tsx';
import type { TriviaGame } from './types';

interface TriviaGameBoardProps {
  game: TriviaGame;
  currentQuestionIndex: number;
  onAnswer: (questionIndex: number, selectedOption: number) => Promise<{correct: boolean, correctMovie: string}>;
  onComplete: () => Promise<void>;
  isLoading: boolean;
}

/**
 * Main Game Board Component
 * Displays current question and manages the game flow
 */
const TriviaGameBoard: React.FC<TriviaGameBoardProps> = ({
  game,
  currentQuestionIndex,
  onAnswer,
  onComplete,
  isLoading
}) => {
  const [baseScore, setBaseScore] = useState(game.score);
  const [timeElapsed, setTimeElapsed] = useState(0);
  const [feedback, setFeedback] = useState<{
    show: boolean;
    type: 'correct' | 'incorrect';
    message: string;
  }>({ show: false, type: 'correct', message: '' });

  // Track time elapsed and calculate current score
  useEffect(() => {
    const timer = setInterval(() => {
      setTimeElapsed(prev => prev + 1);
    }, 1000);

    return () => clearInterval(timer);
  }, []);

  // Update base score when game score changes (from correct answers)
  useEffect(() => {
    setBaseScore(game.score);
    // Reset time elapsed when score changes (new question/answer)
    setTimeElapsed(0);
  }, [game.score]);

  // Calculate current score = base score - time elapsed
  const currentScore = Math.max(0, baseScore - timeElapsed);

  const currentQuestion = game.questions[currentQuestionIndex];
  const answeredQuestions = game.questions.slice(0, currentQuestionIndex);
  const isLastQuestion = currentQuestionIndex === game.questions.length - 1;

  const handleAnswer = async (selectedOption: number) => {
    try {
      const startTime = Date.now();
      
      // Submit answer and get result
      const result = await onAnswer(currentQuestionIndex, selectedOption);
      const endTime = Date.now();
      
      // Calculate time taken for potential future use
      const timeTaken = Math.floor((endTime - startTime) / 1000);
      console.log(`Question answered in ${timeTaken} seconds`);
      
      // Show feedback based on actual result
      setFeedback({
        show: true,
        type: result.correct ? 'correct' : 'incorrect',
        message: result.correct ? 'Right!' : 'Wrong!'
      });

      // Hide feedback after 1.5 seconds
      setTimeout(() => {
        setFeedback({ show: false, type: 'correct', message: '' });
        
        // Move to next question or complete game
        if (isLastQuestion) {
          setTimeout(() => onComplete(), 500);
        }
      }, 1500);

    } catch (error) {
      console.error('Error submitting answer:', error);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 relative">
      {/* Feedback Flash */}
      {feedback.show && (
        <div className={`fixed inset-0 z-50 flex items-center justify-center pointer-events-none`}>
          <div
            className={`text-6xl font-bold px-8 py-4 rounded-xl ${
              feedback.type === 'correct'
                ? 'bg-green-500 text-white'
                : 'bg-red-500 text-white'
            } animate-pulse shadow-2xl`}
          >
            {feedback.message}
          </div>
        </div>
      )}

      {/* Main Layout Container */}
      <div className="max-w-7xl mx-auto px-4 py-8">
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
          
          {/* Left Side - Questions and Game Content */}
          <div className="lg:col-span-3">
            {/* Answered Questions Row */}
            {answeredQuestions.length > 0 && (
              <div className="mb-8">
                <h3 className="text-sm font-medium text-gray-500 mb-3">Answered Questions:</h3>
                <div className="flex flex-wrap gap-2">
                  {answeredQuestions.map((question, index) => (
                    <div
                      key={question.id}
                      className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold text-white ${
                        question.correct ? 'bg-green-500' : 'bg-red-500'
                      } animate-pulse`}
                      title={`Question ${index + 1}: ${question.correct ? 'Correct' : 'Incorrect'}`}
                    >
                      {index + 1}
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Current Question */}
            {currentQuestion && (
              <div
                className={`transition-all duration-300 ${
                  feedback.show
                    ? feedback.type === 'correct'
                      ? 'border-green-500 border-4 rounded-lg'
                      : 'border-red-500 border-4 rounded-lg'
                    : ''
                }`}
              >
                <TriviaQuestion
                  question={currentQuestion}
                  onAnswer={handleAnswer}
                  isLoading={isLoading}
                  questionNumber={currentQuestionIndex + 1}
                  totalQuestions={game.questions.length}
                />
              </div>
            )}

            {/* Progress Bar */}
            <div className="mt-8">
              <div className="bg-gray-200 rounded-full h-2">
                <div
                  className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                  style={{
                    width: `${((currentQuestionIndex + 1) / game.questions.length) * 100}%`
                  }}
                />
              </div>
              <p className="text-center text-sm text-gray-600 mt-2">
                Progress: {currentQuestionIndex + 1} / {game.questions.length}
              </p>
            </div>
          </div>

          {/* Right Side - Score Display */}
          <div className="lg:col-span-1">
            <div className="bg-white rounded-lg shadow-lg p-6 sticky top-8">
              {/* Question Counter */}
              <div className="text-center mb-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-2">Movie Quote Trivia</h2>
                <p className="text-gray-600 text-sm">
                  Question {currentQuestionIndex + 1} of {game.questions.length}
                </p>
              </div>

              {/* Score Display */}
              <div className="text-center">
                <div className="text-gray-500 text-sm mb-2">Score</div>
                <div 
                  className={`text-4xl font-bold transition-colors duration-300 ${
                    currentScore < 100 
                      ? 'text-red-600' 
                      : currentScore > 150 
                        ? 'text-green-600' 
                        : 'text-blue-600'
                  }`}
                >
                  {currentScore.toLocaleString()}
                </div>
                <div className="text-xs text-gray-400 mt-2">
                  Decreases 1 point/second
                </div>
              </div>

              {/* Score Indicator */}
              <div className="mt-6">
                <div className="text-xs text-gray-500 mb-2">Score Range</div>
                <div className="space-y-1">
                  <div className="flex items-center text-xs">
                    <div className="w-3 h-3 bg-green-600 rounded mr-2"></div>
                    <span>Excellent: 150+</span>
                  </div>
                  <div className="flex items-center text-xs">
                    <div className="w-3 h-3 bg-blue-600 rounded mr-2"></div>
                    <span>Good: 100-150</span>
                  </div>
                  <div className="flex items-center text-xs">
                    <div className="w-3 h-3 bg-red-600 rounded mr-2"></div>
                    <span>Fair: 0-99</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  );
};

export default TriviaGameBoard;
