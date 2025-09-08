import React, { useState, useEffect } from 'react';
import TriviaAnswerOption from './TriviaAnswerOption.tsx';
import type { TriviaQuestion as TriviaQuestionType } from './types';

interface TriviaQuestionProps {
  question: TriviaQuestionType;
  onAnswer: (selectedOption: number) => void;
  isLoading: boolean;
  questionNumber: number;
  totalQuestions: number;
}

/**
 * Individual Question Component
 * Displays a movie quote and multiple choice answers
 */
const TriviaQuestion: React.FC<TriviaQuestionProps> = ({
  question,
  onAnswer,
  isLoading,
  questionNumber,
  totalQuestions
}) => {
  const [selectedOption, setSelectedOption] = useState<number | null>(null);

  // Reset selected option when question changes
  useEffect(() => {
    setSelectedOption(null);
  }, [question.id]);

  const handleAnswer = (optionIndex: number) => {
    if (isLoading || selectedOption !== null) return;
    
    setSelectedOption(optionIndex);
    
    // Small delay for visual feedback
    setTimeout(() => {
      onAnswer(optionIndex);
    }, 150);
  };

  return (
    <div className="bg-white rounded-xl shadow-lg border border-gray-200 p-8 mb-6">
      {/* Question Header */}
      <div className="mb-6">
        <div className="flex items-center justify-between mb-4">
          <span className="text-sm font-medium text-blue-600 bg-blue-50 px-3 py-1 rounded-full">
            Question {questionNumber} of {totalQuestions}
          </span>
          <span className="text-sm text-gray-500">
            Movie Quote Trivia
          </span>
        </div>
        
        <h2 className="text-lg font-semibold text-gray-900 mb-2">
          Which movie is this quote from?
        </h2>
      </div>

      {/* Movie Quote */}
      <div className="mb-8">
        <blockquote className="text-2xl md:text-3xl font-serif text-gray-800 italic text-center py-6 px-4 bg-gray-50 rounded-lg border-l-4 border-blue-500">
          "{question.quote}"
        </blockquote>
        
        {question.character && (
          <p className="text-center text-gray-600 mt-3 text-lg">
            — {question.character}
          </p>
        )}
      </div>

      {/* Answer Options */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {question.options.map((movieOption, index) => (
          <TriviaAnswerOption
            key={index}
            movieOption={movieOption}
            optionIndex={index}
            isSelected={selectedOption === index}
            isDisabled={isLoading || selectedOption !== null}
            onSelect={() => handleAnswer(index)}
          />
        ))}
      </div>

      {/* Loading State */}
      {isLoading && selectedOption !== null && (
        <div className="mt-6 text-center">
          <div className="inline-flex items-center text-blue-600">
            <svg
              className="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-600"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                className="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                strokeWidth="4"
              />
              <path
                className="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              />
            </svg>
            Submitting answer...
          </div>
        </div>
      )}

      {/* Instructions */}
      <div className="mt-6 text-center text-sm text-gray-500">
        Click on the movie title you think matches this quote
      </div>
    </div>
  );
};

export default TriviaQuestion;
