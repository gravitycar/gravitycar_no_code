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
        $databaseConnector = ServiceLocator::getDatabaseConnector();
        
        // Get all available quotes to ensure uniqueness
        $movieQuotesModel = ModelFactory::new('Movie_Quotes');
        $allQuotes = $movieQuotesModel->findAll();
        
        if (count($allQuotes) < 15) {
            throw new \Exception('Insufficient movie quotes available. Need at least 15 quotes for a game.');
        }
        
        // Shuffle and take first 15 to ensure randomness and uniqueness
        shuffle($allQuotes);
        $selectedQuotes = array_slice($allQuotes, 0, 15);
        
        $questions = [];
        $gameId = $this->get('id');
        
        foreach ($selectedQuotes as $index => $quote) {
            // Create a new trivia question
            $question = ModelFactory::new('Movie_Quote_Trivia_Questions');
            
            // Set the quote
            $question->set('movie_quote_id', $quote->get('id'));
            
            // Set game session fields before creation
            $question->set('game_id', $gameId);
            $question->set('question_order', $index + 1);
            
            // Save the question (this will auto-generate the question content via the create() override)
            $question->create();
            
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
        $databaseConnector = ServiceLocator::getDatabaseConnector();
        
        $gameId = $this->get('id');
        
        // Create a model instance for querying
        $questionModel = ModelFactory::new('Movie_Quote_Trivia_Questions');
        
        // Use the find method with criteria and ordering
        $criteria = ['game_id' => $gameId];
        $orderBy = ['question_order' => 'ASC'];
        $result = $databaseConnector->find($questionModel, $criteria, [], [], $orderBy);
        
        $questions = [];
        foreach ($result as $row) {
            $question = ModelFactory::new('Movie_Quote_Trivia_Questions');
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
