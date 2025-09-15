<?php

namespace Gravitycar\Api;

use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Gravitycar\Core\Config;
use Gravitycar\Models\movie_quote_trivia_games\Movie_Quote_Trivia_Games;
use Gravitycar\Models\movie_quote_trivia_questions\Movie_Quote_Trivia_Questions;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * API Controller for Movie Quote Trivia Game functionality
 * 
 * Handles trivia game sessions including:
 * - Starting new games
 * - Submitting answers
 * - Completing games
 * - High scores display
 * Pure dependency injection - all dependencies explicitly injected via constructor.
 */
class TriviaGameAPIController extends ApiControllerBase
{
    /**
     * Pure dependency injection constructor - all dependencies explicitly provided
     * 
     * @param Logger $logger = null
     * @param ModelFactory $modelFactory = null
     * @param DatabaseConnectorInterface $databaseConnector = null
     * @param MetadataEngineInterface $metadataEngine = null
     * @param Config $config = null
     * @param CurrentUserProviderInterface $currentUserProvider = null
     */
    public function __construct(
        Logger $logger = null,
        ModelFactory $modelFactory = null,
        DatabaseConnectorInterface $databaseConnector = null,
        MetadataEngineInterface $metadataEngine = null,
        Config $config = null,
        CurrentUserProviderInterface $currentUserProvider = null
    ) {
        // All dependencies explicitly injected - no ServiceLocator fallbacks
        parent::__construct($logger, $modelFactory, $databaseConnector, $metadataEngine, $config, $currentUserProvider);
    }
    /**
     * Register routes for trivia game endpoints
     */
    public function registerRoutes(): array
    {
        return [
            [
                'method' => 'POST',
                'path' => '/trivia/start-game',
                'apiClass' => '\\Gravitycar\\Api\\TriviaGameAPIController',
                'apiMethod' => 'startGame',
                'parameterNames' => []
            ],
            [
                'method' => 'GET',
                'path' => '/trivia/game/{gameId}',
                'apiClass' => '\\Gravitycar\\Api\\TriviaGameAPIController',
                'apiMethod' => 'getGameWithQuestions',
                'parameterNames' => ['gameId']
            ],
            [
                'method' => 'PUT',
                'path' => '/trivia/answer',
                'apiClass' => '\\Gravitycar\\Api\\TriviaGameAPIController',
                'apiMethod' => 'submitAnswer',
                'parameterNames' => []
            ],
            [
                'method' => 'PUT',
                'path' => '/trivia/complete-game/{gameId}',
                'apiClass' => '\\Gravitycar\\Api\\TriviaGameAPIController',
                'apiMethod' => 'completeGame',
                'parameterNames' => ['gameId']
            ],
            [
                'method' => 'GET',
                'path' => '/trivia/high-scores',
                'apiClass' => '\\Gravitycar\\Api\\TriviaGameAPIController',
                'apiMethod' => 'getHighScores',
                'parameterNames' => []
            ]
        ];
    }
    
    /**
     * Start a new trivia game session
     * POST /trivia/start-game
     */
    public function startGame(): array
    {
        try {
            // Get current user (if authenticated) or null for guest
            $currentUser = $this->getCurrentUser();
            
            // Create new game using inherited ModelFactory
            /** @var Movie_Quote_Trivia_Games $game */
            $game = $this->modelFactory->new('Movie_Quote_Trivia_Games');
            
            // Generate game name based on user type
            $game->generateGameName($currentUser);
            $game->set('score', 100);
            $game->set('game_started_at', date('Y-m-d H:i:s'));
            
            // Create the game record
            if (!$game->create()) {
                throw new GCException('Failed to create game session');
            }
            
            // Generate 15 questions for the game
            try {
                $questions = $game->generateQuestions();
            } catch (\Exception $e) {
                // If question generation fails, clean up the game
                $game->delete();
                throw new GCException('Failed to generate trivia questions: ' . $e->getMessage());
            }
            
            // Format questions for frontend
            $questionData = [];
            foreach ($questions as $question) {
                $questionData[] = [
                    'id' => $question->get('id'),
                    'quote' => $this->getQuoteText($question->get('movie_quote_id')),
                    'options' => $this->getAnswerOptionsForQuestion($question),
                    'order' => $question->get('question_order')
                ];
            }
            
            return [
                'success' => true,
                'data' => [
                    'game_id' => $game->get('id'),
                    'score' => $game->get('score'),
                    'questions' => $questionData
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get a game with its questions
     * GET /trivia/game/{gameId}
     */
    public function getGameWithQuestions(Request $request): array
    {
        try {
            $gameId = $request->get('gameId');
            if (!$gameId) {
                throw new GCException('Game ID is required');
            }

            /** @var Movie_Quote_Trivia_Games $game */
            $game = $this->modelFactory->new('Movie_Quote_Trivia_Games');
            if (!$game->findById($gameId)) {
                throw new GCException('Game not found');
            }
            
            $questions = $game->getQuestionsInOrder();
            
            $questionData = [];
            foreach ($questions as $question) {
                $questionData[] = [
                    'id' => $question->get('id'),
                    'quote' => $this->getQuoteText($question->get('movie_quote_id')),
                    'options' => $this->getAnswerOptionsForQuestion($question),
                    'order' => $question->get('question_order'),
                    'user_selected_option' => $question->get('user_selected_option'),
                    'answered_correctly' => $question->get('answered_correctly'),
                    'answered_at' => $question->get('answered_at'),
                    'time_taken_seconds' => $question->get('time_taken_seconds')
                ];
            }
            
            return [
                'success' => true,
                'data' => [
                    'game_id' => $game->get('id'),
                    'name' => $game->get('name'),
                    'score' => $game->get('score'),
                    'game_started_at' => $game->get('game_started_at'),
                    'game_completed_at' => $game->get('game_completed_at'),
                    'questions' => $questionData
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Submit an answer for a trivia question
     * PUT /trivia/answer
     */
    public function submitAnswer(Request $request): array
    {
        try {
            $input = $request->getRequestData();
            
            // Validate required fields
            $required = ['game_id', 'question_id', 'selected_option', 'time_taken'];
            foreach ($required as $field) {
                if (!isset($input[$field])) {
                    throw new GCException("Missing required field: {$field}");
                }
            }
            
            // Get the question
            $question = $this->modelFactory->new('Movie_Quote_Trivia_Questions');
            if (!$question->findById($input['question_id'])) {
                throw new GCException('Question not found');
            }
            
            // Verify question belongs to this game
            if ($question->get('game_id') !== $input['game_id']) {
                throw new GCException('Question does not belong to this game');
            }
            
            // Get the game
            /** @var Movie_Quote_Trivia_Games $game */
            $game = $this->modelFactory->new('Movie_Quote_Trivia_Games');
            if (!$game->findById($input['game_id'])) {
                throw new GCException('Game not found');
            }
            
            // Update question with user's answer
            $question->set('user_selected_option', (int)$input['selected_option']);
            $question->set('answered_at', date('Y-m-d H:i:s'));
            $question->set('time_taken_seconds', (int)$input['time_taken']);
            
            // Determine if answer is correct
            $isCorrect = $this->validateAnswer($question, (int)$input['selected_option']);
            $question->set('answered_correctly', $isCorrect ? 1 : 0);
            
            // Save question
            $question->update();
            
            // Update game score
            $obscurityScore = $question->get('obscurity_score') ?? 1;
            $game->applyAnswerScore($isCorrect, $obscurityScore, (int)$input['time_taken']);
            $game->update();
            
            // Get correct movie name for response
            $correctMovieName = $this->getCorrectMovieName($question);
            
            return [
                'success' => true,
                'data' => [
                    'correct' => $isCorrect,
                    'new_score' => $game->get('score'),
                    'correct_movie' => $correctMovieName
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Complete a trivia game session
     * PUT /trivia/complete-game/{gameId}
     */
    public function completeGame(Request $request): array
    {
        try {
            $gameId = $request->get('gameId');
            if (!$gameId) {
                throw new GCException('Game ID is required');
            }

            /** @var Movie_Quote_Trivia_Games $game */
            $game = $this->modelFactory->new('Movie_Quote_Trivia_Games');
            if (!$game->findById($gameId)) {
                throw new GCException('Game not found');
            }
            
            // Mark game as completed
            $game->completeGame();
            
            // Get game statistics
            $stats = $game->getGameStats();
            
            // Calculate rank (simple implementation - count games with higher scores)
            $rank = $this->calculateGameRank($game->get('score'));
            
            return [
                'success' => true,
                'data' => [
                    'final_score' => $stats['final_score'],
                    'total_questions' => $stats['total_questions'],
                    'correct_answers' => $stats['correct_answers'],
                    'game_completed' => $stats['game_completed'],
                    'rank' => $rank
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get high scores
     * GET /trivia/high-scores
     */
    public function getHighScores(): array
    {
        try {
            $gameModel = $this->modelFactory->new('Movie_Quote_Trivia_Games');
            
            // Find top 10 completed games ordered by score
            $criteria = [
                'game_completed_at' => '__NOT_NULL__'  // Only completed games
            ];
            $orderBy = ['score' => 'DESC'];
            $results = $this->databaseConnector->find($gameModel, $criteria, [], [], $orderBy, 10);
            
            $highScores = [];
            foreach ($results as $row) {
                $highScores[] = [
                    'name' => $row['name'],
                    'score' => $row['score'],
                    'game_completed_at' => $row['game_completed_at'],
                    'created_by_name' => $row['created_by_name'] ?? 'Guest Player'
                ];
            }
            
            return [
                'success' => true,
                'data' => $highScores
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Helper method to get quote text
     */
    private function getQuoteText(string $quoteId): string
    {
        $quote = $this->modelFactory->new('Movie_Quotes');
        if ($quote->findById($quoteId)) {
            return $quote->get('quote') ?? '';
        }
        return '';
    }
    
    /**
     * Helper method to get answer options for a question
     */
    private function getAnswerOptionsForQuestion(Movie_Quote_Trivia_Questions $question): array
    {
        $options = [];
        
        // Get the three movie options
        for ($i = 1; $i <= 3; $i++) {
            $movieId = $question->get("answer_option_{$i}");
            if ($movieId) {
                $movie = $this->modelFactory->new('Movies');
                if ($movie->findById($movieId)) {
                    $options[] = [
                        'option_number' => $i,
                        'movie_id' => $movieId,
                        'name' => $movie->get('name') ?? 'Unknown Movie',
                        'year' => $movie->get('year') ?? '',
                        'poster_url' => $movie->get('poster_url') ?? ''
                    ];
                }
            }
        }
        
        return $options;
    }
    
    /**
     * Helper method to validate if selected option is correct
     */
    private function validateAnswer(Movie_Quote_Trivia_Questions $question, int $selectedOption): bool
    {
        $correctAnswerId = $question->get('correct_answer');
        $selectedAnswerId = $question->get("answer_option_{$selectedOption}");
        
        return $correctAnswerId === $selectedAnswerId;
    }
    
    /**
     * Helper method to get correct movie name
     */
    private function getCorrectMovieName(Movie_Quote_Trivia_Questions $question): string
    {
        $correctMovieId = $question->get('correct_answer');
        if ($correctMovieId) {
            $movie = $this->modelFactory->new('Movies');
            if ($movie->findById($correctMovieId)) {
                return $movie->get('name') ?? 'Unknown Movie';
            }
        }
        return 'Unknown Movie';
    }
    
    /**
     * Helper method to calculate game rank
     */
    private function calculateGameRank(int $score): int
    {
        $gameModel = $this->modelFactory->new('Movie_Quote_Trivia_Games');
        
        // Count completed games with higher scores
        $criteria = [
            'game_completed_at' => '__NOT_NULL__',
            'score' => ['operator' => '>', 'value' => $score]
        ];
        
        $higherScoreGames = $this->databaseConnector->find($gameModel, $criteria);
        
        return count($higherScoreGames) + 1; // +1 because rank is 1-based
    }
    
    /**
     * Helper method to get request data
     */
    private function getRequestData(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fallback to POST data
            $data = $_POST;
        }
        
        return $data ?? [];
    }
}
