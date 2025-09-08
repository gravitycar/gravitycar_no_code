/**
 * TypeScript interfaces and types for the Trivia Game components
 */

export interface TriviaGame {
  id: string;
  score: number;
  totalQuestions: number;
  questions: TriviaQuestion[];
  startTime: string;
  endTime: string | null;
  isComplete: boolean;
}

export interface TriviaMovieOption {
  movie_id: string;
  name: string;
  year?: string;
  poster_url?: string;
  option_number: number;
}

export interface TriviaQuestion {
  id: string;
  quote: string;
  character?: string;
  correctAnswer: string;
  options: TriviaMovieOption[];
  answered?: boolean;
  correct?: boolean;
  timeTaken?: number;
}

export interface TriviaHighScore {
  id: string;
  playerName: string;
  score: number;
  accuracy: number;
  totalQuestions: number;
  correctAnswers: number;
  timeElapsed: number;
  dateCompleted: string;
  rank: number;
}
