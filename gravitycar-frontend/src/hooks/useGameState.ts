import { useState, useCallback } from 'react';
import { apiService } from '../services/api';
import type { TriviaGame, TriviaQuestion, TriviaHighScore } from '../components/trivia/types';

interface GameStateHookReturn {
  // Game State
  currentGame: TriviaGame | null;
  gamePhase: 'welcome' | 'playing' | 'complete' | 'high-scores';
  isLoading: boolean;
  error: string | null;
  currentQuestionIndex: number;

  // Game Actions
  startNewGame: () => Promise<void>;
  submitAnswer: (questionIndex: number, selectedOption: number) => Promise<{correct: boolean, correctMovie: string}>;
  completeGame: () => Promise<void>;
  resetGame: () => void;

  // Navigation Actions
  showHighScores: () => void;
  returnToWelcome: () => void;

  // High Scores
  highScores: TriviaHighScore[];
  fetchHighScores: () => Promise<void>;
}

// API Configuration - now using centralized API service
// const API_BASE_URL = 'http://localhost:8081'; // No longer needed

/**
 * Custom Hook for Managing Trivia Game State
 * Handles all game logic, API calls, and state management
 */
export const useGameState = (): GameStateHookReturn => {
  // Core State
  const [currentGame, setCurrentGame] = useState<TriviaGame | null>(null);
  const [gamePhase, setGamePhase] = useState<'welcome' | 'playing' | 'complete' | 'high-scores'>('welcome');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [currentQuestionIndex, setCurrentQuestionIndex] = useState(0);
  const [highScores, setHighScores] = useState<TriviaHighScore[]>([]);

  // Start a new trivia game
  const startNewGame = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);

      // Call the start game API using authenticated service
      const gameData = await apiService.startTriviaGame();

      // Transform API response to our TriviaGame format
      const newGame: TriviaGame = {
        id: gameData.data.game_id,
        score: gameData.data.score || 0,
        totalQuestions: gameData.data.questions.length,
        questions: gameData.data.questions.map((q: any): TriviaQuestion => ({
          id: q.id,
          quote: q.quote,
          character: q.character || undefined,
          correctAnswer: q.options.find((opt: any) => opt.is_correct)?.name || q.options[0]?.name,
          options: q.options.map((opt: any) => ({
            movie_id: opt.movie_id,
            name: opt.name,
            year: opt.year || '',
            poster_url: opt.poster_url || '',
            option_number: opt.option_number
          })),
          answered: false,
          correct: false,
          timeTaken: 0
        })),
        startTime: gameData.data.game_started_at || new Date().toISOString(),
        endTime: null,
        isComplete: false
      };

      setCurrentGame(newGame);
      setCurrentQuestionIndex(0);
      setGamePhase('playing');

    } catch (err) {
      console.error('Error starting new game:', err);
      setError(err instanceof Error ? err.message : 'Failed to start new game');
    } finally {
      setIsLoading(false);
    }
  }, []);

  // Submit an answer for the current question
  const submitAnswer = useCallback(async (questionIndex: number, selectedOption: number): Promise<{correct: boolean, correctMovie: string}> => {
    if (!currentGame) {
      throw new Error('No active game');
    }

    try {
      setIsLoading(true);
      setError(null);

      const question = currentGame.questions[questionIndex];
      
      // Call the submit answer API using authenticated service
      const result = await apiService.submitTriviaAnswer(
        currentGame.id,
        question.id,
        selectedOption + 1, // Convert 0-based index to 1-based option number
        0 // TODO: Calculate actual time taken
      );

      // Update the game state with the result
      const updatedQuestions = [...currentGame.questions];
      updatedQuestions[questionIndex] = {
        ...question,
        answered: true,
        correct: result.data.correct,
        timeTaken: result.data.time_taken || 0
      };

      const updatedGame: TriviaGame = {
        ...currentGame,
        questions: updatedQuestions,
        score: result.data.new_score || currentGame.score
      };

      setCurrentGame(updatedGame);
      
      // Move to next question if not the last one
      if (questionIndex < currentGame.questions.length - 1) {
        setCurrentQuestionIndex(questionIndex + 1);
      }

      // Return the result for feedback
      return {
        correct: result.data.correct,
        correctMovie: result.data.correct_movie || 'Unknown'
      };

    } catch (err) {
      console.error('Error submitting answer:', err);
      setError(err instanceof Error ? err.message : 'Failed to submit answer');
      throw err;
    } finally {
      setIsLoading(false);
    }
  }, [currentGame]);

  // Complete the current game
  const completeGame = useCallback(async () => {
    if (!currentGame) {
      throw new Error('No active game');
    }

    try {
      setIsLoading(true);
      setError(null);

      // Call the complete game API using authenticated service
      const result = await apiService.completeTriviaGame(currentGame.id);

      // Transform the completed game data
      const completedGame: TriviaGame = {
        ...currentGame,
        score: result.data.final_score || currentGame.score,
        endTime: new Date().toISOString(),
        isComplete: true,
        questions: currentGame.questions // Keep existing question data as-is
      };

      setCurrentGame(completedGame);
      setGamePhase('complete');

    } catch (err) {
      console.error('Error completing game:', err);
      setError(err instanceof Error ? err.message : 'Failed to complete game');
    } finally {
      setIsLoading(false);
    }
  }, [currentGame]);

  // Fetch high scores
  const fetchHighScores = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);

      const scoresData = await apiService.getTriviaHighScores();
      
      // Sort by score in descending order (highest first)
      const sortedScores = [...scoresData.data].sort((a, b) => b.score - a.score);
      
      // Transform API response to TriviaHighScore format
      const transformedScores: TriviaHighScore[] = sortedScores.map((score: any, index: number) => ({
        id: score.id || `score-${index}`,
        playerName: score.created_by_name || 'Anonymous',
        score: score.score || 0,
        // Since detailed game stats aren't available in high scores API, use defaults
        accuracy: 0, // Will show as 0% instead of NaN%
        totalQuestions: 0,
        correctAnswers: 0,
        timeElapsed: 0, // Will show as 0:00 instead of NaN:NaN
        dateCompleted: score.game_completed_at ? new Date(score.game_completed_at).toLocaleDateString() : 'Invalid Date',
        rank: index + 1
      }));

      setHighScores(transformedScores);

    } catch (err) {
      console.error('Error fetching high scores:', err);
      setError(err instanceof Error ? err.message : 'Failed to fetch high scores');
      
      // Fallback to empty array on error
      setHighScores([]);
    } finally {
      setIsLoading(false);
    }
  }, []);

  // Navigation actions
  const showHighScores = useCallback(() => {
    setGamePhase('high-scores');
    fetchHighScores();
  }, [fetchHighScores]);

  const returnToWelcome = useCallback(() => {
    setGamePhase('welcome');
    setCurrentGame(null);
    setCurrentQuestionIndex(0);
    setError(null);
  }, []);

  const resetGame = useCallback(() => {
    setCurrentGame(null);
    setCurrentQuestionIndex(0);
    setGamePhase('welcome');
    setError(null);
    setIsLoading(false);
  }, []);

  return {
    // Game State
    currentGame,
    gamePhase,
    isLoading,
    error,
    currentQuestionIndex,

    // Game Actions
    startNewGame,
    submitAnswer,
    completeGame,
    resetGame,

    // Navigation Actions
    showHighScores,
    returnToWelcome,

    // High Scores
    highScores,
    fetchHighScores
  };
};

export default useGameState;
