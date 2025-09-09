<?php

namespace Gravitycar\Models\movie_quote_trivia_games;

use Gravitycar\Models\ModelBase;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Models\movie_quote_trivia_questions\Movie_Quote_Trivia_Questions;
use Gravitycar\Models\movie_quotes\Movie_Quotes;
use Gravitycar\Models\users\Users;

class Movie_Quote_Trivia_Games extends ModelBase
{
    /**
     * Track selected quote IDs to prevent duplicates
     * @var array
     */
    private array $selectedQuoteIds = [];
    /**
     * Generate a unique game name based on the player
     */
    public function generateGameName($user = null): void
    {
        if ($user && $user instanceof Users) {
            // Authenticated user - use their name
            $firstName = $user->get('first_name') ?? 'User';
            $lastName = $user->get('last_name') ?? '';
            $fullName = trim($firstName . ' ' . $lastName);
            $date = date('F j, Y');
            $this->set('name', "{$fullName}'s game played on {$date}");
        } else {
            // Guest user - use timestamp format
            $datetime = date('Y-m-d H:i');
            $this->set('name', "Guest game {$datetime}");
        }
    }

    /**
     * Generate 15 unique trivia questions for this game
     */
    public function generateQuestions(): array
    {
        $questions = [];
        $gameId = $this->get('id');
        $this->selectedQuoteIds = []; // Reset selected quotes
        
        // Generate 15 questions
        for ($i = 0; $i < 15; $i++) {
            // Create a new trivia question using ModelFactory
            $question = $this->getModelFactory()->new('Movie_Quote_Trivia_Questions');
            
            // Set game session fields before creation
            $question->set('game_id', $gameId);
            $question->set('question_order', $i + 1);
            
            // Create the question with excluded quote IDs to prevent duplicates
            // The create() method will automatically select a random quote and generate options
            if (!$question->create($this->selectedQuoteIds)) {
                throw new \Exception("Failed to create trivia question " . ($i + 1));
            }
            
            // Add the selected quote ID to our tracking array
            $quoteId = $question->getMovieQuoteId();
            if ($quoteId) {
                $this->selectedQuoteIds[] = $quoteId;
            }
            
            $questions[] = $question;
        }
        
        return $questions;
    }

    /**
     * Apply scoring based on answer correctness, obscurity, and time taken
     */
    public function applyAnswerScore(bool $isCorrect, int $obscurityScore, int $timeTakenSeconds): void
    {
        $currentScore = $this->get('score');
        
        // Apply time penalty (1 point per second)
        $currentScore -= $timeTakenSeconds;
        
        if ($isCorrect) {
            // Correct answer: +3 points + obscurity bonus
            $currentScore += 3 + $obscurityScore;
        } else {
            // Wrong answer: -3 points
            $currentScore -= 3;
        }
        
        // Ensure score never goes below 0
        $currentScore = max(0, $currentScore);
        
        $this->set('score', $currentScore);
    }

    /**
     * Complete the game by setting completion timestamp
     */
    public function completeGame(): void
    {
        $this->set('game_completed_at', date('Y-m-d H:i:s'));
        $this->update();
    }

    /**
     * Get questions for this game in order
     */
    public function getQuestionsInOrder(): array
    {
        $databaseConnector = $this->getDatabaseConnector();
        
        $gameId = $this->get('id');
        
        // Create a model instance for querying
        $questionModel = $this->getModelFactory()->new('Movie_Quote_Trivia_Questions');
        
        // Use the find method with criteria and ordering
        $criteria = ['game_id' => $gameId];
        $orderBy = ['question_order' => 'ASC'];
        $result = $databaseConnector->find($questionModel, $criteria, [], [], $orderBy);
        
        $questions = [];
        foreach ($result as $row) {
            $question = $this->getModelFactory()->new('Movie_Quote_Trivia_Questions');
            $question->populateFromRow($row);
            $questions[] = $question;
        }
        
        return $questions;
    }

    /**
     * Get game statistics
     */
    public function getGameStats(): array
    {
        $questions = $this->getQuestionsInOrder();
        $totalQuestions = count($questions);
        $answeredQuestions = 0;
        $correctAnswers = 0;
        
        foreach ($questions as $question) {
            if (!is_null($question->get('user_selected_option'))) {
                $answeredQuestions++;
                if ($question->get('answered_correctly')) {
                    $correctAnswers++;
                }
            }
        }
        
        return [
            'total_questions' => $totalQuestions,
            'answered_questions' => $answeredQuestions,
            'correct_answers' => $correctAnswers,
            'final_score' => $this->get('score'),
            'game_completed' => !is_null($this->get('game_completed_at'))
        ];
    }
}
