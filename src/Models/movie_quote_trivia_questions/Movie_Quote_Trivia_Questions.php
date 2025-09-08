<?php
namespace Gravitycar\Models\movie_quote_trivia_questions;

use Gravitycar\Models\ModelBase;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Exceptions\GCException;

/**
 * Movie Quote Trivia Questions model class for Gravitycar framework.
 * 
 * This model represents trivia questions based on movie quotes, where users
 * select the correct movie from three options. Used as a component for
 * a future Movie Quote Trivia Game.
 */
class Movie_Quote_Trivia_Questions extends ModelBase {
    
    /**
     * Cached movie quote model instance
     * @var \Gravitycar\Models\ModelBase|null
     */
    private $movieQuoteModel = null;
    
    /**
     * Cached movie quote ID for the current instance
     * @var string|null
     */
    private $cachedMovieQuoteId = null;
    
    /**
     * Cached movie model instance (related to the movie quote)
     * @var \Gravitycar\Models\ModelBase|null
     */
    private $movieModel = null;
    
    /**
     * Cached movie quote ID for the movie model cache
     * @var string|null
     */
    private $cachedMovieQuoteIdForMovie = null;
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Get the movie quote model instance for this question (public getter)
     * @return \Gravitycar\Models\ModelBase|null
     */
    public function getQuoteModel(): ?\Gravitycar\Models\ModelBase {
        $movieQuoteId = $this->get('movie_quote_id');
        if (!$movieQuoteId) {
            return null;
        }
        return $this->getMovieQuoteModel($movieQuoteId);
    }
    
    /**
     * Get the movie quote ID for this question
     * @return string|null
     */
    public function getMovieQuoteId(): ?string {
        return $this->get('movie_quote_id');
    }
    
    /**
     * Override create to implement automatic question generation
     * 
     * When creating a new trivia question, this method will:
     * 1. Select a random movie quote (if not specified), excluding already used quotes
     * 2. Get the movie associated with that quote (correct answer)
     * 3. Select 2 random movies as distractors
     * 4. Randomly assign the 3 movies to the answer options
     * 
     * @param array $excludedIds Array of movie quote IDs to exclude from selection
     */
    public function create(array $excludedIds = []): bool {
        // If no movie quote is specified, select one randomly excluding already used quotes
        if (!$this->get('movie_quote_id')) {
            $randomQuoteId = $this->selectRandomMovieQuote($excludedIds);
            if (!$randomQuoteId) {
                throw new GCException("No movie quotes available for trivia question generation");
            }
            $this->set('movie_quote_id', $randomQuoteId);
        }
        
        // Get the correct movie from the movie quote
        $movieQuoteId = $this->get('movie_quote_id');
        $correctMovieId = $this->getMovieFromQuote($movieQuoteId);
        if (!$correctMovieId) {
            throw new GCException("Movie quote does not have an associated movie. Movie Quote ID: {$movieQuoteId}");
        }
        
        // Generate random distractor movies
        $distractorMovies = $this->selectRandomDistractorMovies($correctMovieId, 2);
        if (count($distractorMovies) < 2) {
            // For now, use dummy IDs if we can't find distractors
            $this->logger->warning("Insufficient distractor movies, using fallbacks", [
                'correct_movie_id' => $correctMovieId,
                'distractor_count' => count($distractorMovies),
                'required_count' => 2
            ]);
            $distractorMovies = [$correctMovieId, $correctMovieId]; // Use same movie as fallback for testing
        }
        
        // Combine correct answer with distractors and shuffle
        $allOptions = array_merge([$correctMovieId], $distractorMovies);
        shuffle($allOptions);
        
        // Assign to answer option fields
        $this->set('answer_option_1', $allOptions[0]);
        $this->set('answer_option_2', $allOptions[1]);
        $this->set('answer_option_3', $allOptions[2]);
        $this->set('correct_answer', $correctMovieId);
        
        // Set the answers field to empty initially (will be set when user selects an answer)
        $this->set('answers', '');
        
        // Initialize as not answered
        $this->set('answered_correctly', 0);
        
        // Populate the obscurity score from the related movie
        $obscurityScore = $this->getObscurityScoreFromMovie();
        if ($obscurityScore !== null) {
            $this->set('obscurity_score', $obscurityScore);
        }
        
        return parent::create();
    }
    
    /**
     * Override update to refresh obscurity_score from the related movie
     */
    public function update(): bool {
        // Always update the obscurity score to ensure it's current
        $obscurityScore = $this->getObscurityScoreFromMovie();
        if ($obscurityScore !== null) {
            $this->set('obscurity_score', $obscurityScore);
        }
        
        return parent::update();
    }
    
    /**
     * Validate if the selected answer is correct
     * 
     * @param string $selectedMovieId The ID of the movie selected by the user
     * @return bool True if the answer is correct
     */
    public function validateAnswer(string $selectedMovieId): bool {
        $isCorrect = ($selectedMovieId === $this->get('correct_answer'));
        $this->set('answered_correctly', $isCorrect ? 1 : 0);
        $this->update();
        
        return $isCorrect;
    }
    
    /**
     * Get formatted answer options for UI display
     * 
     * @return array Array of answer options with movie titles and IDs
     */
    public function getAnswerOptions(): array {
        $options = [];
        $movieIds = [
            $this->get('answer_option_1'),
            $this->get('answer_option_2'),
            $this->get('answer_option_3')
        ];
        
        foreach ($movieIds as $index => $movieId) {
            if ($movieId) {
                $movieTitle = $this->getMovieTitle($movieId);
                $options[] = [
                    'id' => $movieId,
                    'title' => $movieTitle,
                    'label' => $movieTitle,
                    'value' => $movieId
                ];
            }
        }
        
        return $options;
    }
    
    /**
     * Static method to provide answer options for RadioButtonSet field
     * This method is called by the RadioButtonSetField via optionsClass/optionsMethod
     * 
     * @return array Array of options for the RadioButtonSet field
     */
    public static function getAnswerOptionsForField(): array {
        // This is called during field initialization, but we need instance data
        // For now, return empty array - options will be populated dynamically
        // when the question is created and has actual movie options
        return [];
    }
    
    /**
     * Check if this question has been answered correctly
     * 
     * @return bool True if answered correctly
     */
    public function isAnsweredCorrectly(): bool {
        return (bool) $this->get('answered_correctly');
    }
    
    /**
     * Get the movie quote model instance, cached for performance
     * 
     * @param string $movieQuoteId The movie quote ID to retrieve
     * @return \Gravitycar\Models\ModelBase|null The movie quote model or null if not found
     */
    private function getMovieQuoteModel(string $movieQuoteId): ?\Gravitycar\Models\ModelBase {
        // If we have a cached model and the ID hasn't changed, return it
        if ($this->movieQuoteModel && $this->cachedMovieQuoteId === $movieQuoteId) {
            return $this->movieQuoteModel;
        }
        
        // Retrieve and cache the movie quote model
        try {
            $this->movieQuoteModel = ModelFactory::retrieve('Movie_Quotes', $movieQuoteId);
            $this->cachedMovieQuoteId = $movieQuoteId;
            
            return $this->movieQuoteModel;
        } catch (\Exception $e) {
            $this->logger->error("Exception retrieving movie quote model", [
                'movie_quote_id' => $movieQuoteId,
                'error' => $e->getMessage(),
                'method' => 'getMovieQuoteModel'
            ]);
            
            // Clear cache on error
            $this->movieQuoteModel = null;
            $this->cachedMovieQuoteId = null;
            
            return null;
        }
    }
    
    /**
     * Get the movie model instance related to the movie quote, cached for performance
     * 
     * @param string $movieQuoteId The movie quote ID to get the related movie for
     * @return \Gravitycar\Models\ModelBase|null The movie model or null if not found
     */
    private function getMovieModel(string $movieQuoteId): ?\Gravitycar\Models\ModelBase {
        // If we have a cached movie model and the quote ID hasn't changed, return it
        if ($this->movieModel && $this->cachedMovieQuoteIdForMovie === $movieQuoteId) {
            return $this->movieModel;
        }
        
        try {
            // Get the movie quote model first
            $movieQuoteModel = $this->getMovieQuoteModel($movieQuoteId);
            if (!$movieQuoteModel) {
                $this->logger->warning("Movie quote model not found", [
                    'movie_quote_id' => $movieQuoteId,
                    'method' => 'getMovieModel'
                ]);
                return null;
            }
            
            $this->logger->debug("Retrieved movie quote model, checking relationships", [
                'movie_quote_id' => $movieQuoteId,
                'method' => 'getMovieModel'
            ]);
            
            // Use the relationship system to get related movies
            $relatedMovies = $movieQuoteModel->getRelatedModels('movies_movie_quotes');
            
            if (empty($relatedMovies)) {
                $this->logger->warning("No related movies found for quote", [
                    'movie_quote_id' => $movieQuoteId,
                    'quote_model_class' => get_class($movieQuoteModel),
                    'relationship_name' => 'movies_movie_quotes',
                    'method' => 'getMovieModel'
                ]);
                return null;
            }
            
            $this->logger->debug("Found related movies", [
                'movie_quote_id' => $movieQuoteId,
                'related_movies_count' => count($relatedMovies),
                'method' => 'getMovieModel'
            ]);
            
            // For OneToMany relationship, should be exactly one movie
            $this->movieModel = $relatedMovies[0];
            $this->cachedMovieQuoteIdForMovie = $movieQuoteId;
            
            return $this->movieModel;
            
        } catch (\Exception $e) {
            $this->logger->error("Exception retrieving movie model", [
                'movie_quote_id' => $movieQuoteId,
                'error' => $e->getMessage(),
                'method' => 'getMovieModel'
            ]);
            
            // Clear cache on error
            $this->movieModel = null;
            $this->cachedMovieQuoteIdForMovie = null;
            
            return null;
        }
    }
    
    /**
     * Select a random movie quote that has a valid movie relationship
     * 
     * @param array $excludedIds Array of movie quote IDs to exclude from selection
     * @return string|null The quote ID or null if none found
     */
    private function selectRandomMovieQuote(array $excludedIds = []): ?string {
        $db = ServiceLocator::getDatabaseConnector();
        
        // Use ModelFactory to get a MovieQuote model instance
        $movieQuoteModel = ModelFactory::new('Movie_Quotes');
        
        // If we have excluded IDs, use the new validated filters approach
        if (!empty($excludedIds)) {
            // First, get all quotes that have movie relationships using the relationship approach
            $rel = $movieQuoteModel->getRelationship('movies_movie_quotes');
            $movieQuoteModelField = $rel->getModelIdField($movieQuoteModel);

            // Define criteria using the relationship format to ensure the quote has a movie
            $criteria = [
                'deleted_at' => null,
                'movies_movie_quotes.' . $movieQuoteModelField => '__NOT_NULL__',
            ];
            
            // Get all valid quotes with movies first
            $allValidQuotes = $db->find($movieQuoteModel, $criteria, ['id'], []);
            
            if (empty($allValidQuotes)) {
                return null;
            }
            
            // Filter out excluded IDs
            $validQuoteIds = [];
            foreach ($allValidQuotes as $quote) {
                $quoteId = $quote['id'];
                if (!in_array($quoteId, $excludedIds)) {
                    $validQuoteIds[] = $quoteId;
                }
            }
            
            if (empty($validQuoteIds)) {
                return null; // No valid quotes remaining after exclusions
            }
            
            // Now build validated filters to get a random quote from the remaining valid IDs
            $validatedFilters = [
                [
                    'field' => 'deleted_at',
                    'operator' => 'isNull',
                    'value' => null
                ],
                [
                    'field' => 'id',
                    'operator' => 'in',
                    'value' => $validQuoteIds
                ]
            ];
            
            // Use the new method that supports filtering
            return $db->getRandomRecordWithValidatedFilters($movieQuoteModel, $validatedFilters);
        } else {
            // For backward compatibility, use the existing relationship-based approach
            $rel = $movieQuoteModel->getRelationship('movies_movie_quotes');
            $movieQuoteModelField = $rel->getModelIdField($movieQuoteModel);

            // Define criteria using the new relationship format
            $criteria = [
                'deleted_at' => null,                           // Direct field: movie quote not deleted
                'movies_movie_quotes.' . $movieQuoteModelField => '__NOT_NULL__',                   // Direct field: has a movie relationship
            ];
            
            // Use the existing getRandomRecord method with relationship support
            return $db->getRandomRecord($movieQuoteModel, $criteria);
        }
    }
    
    /**
     * Get the movie ID associated with a movie quote using the relationship system
     * 
     * @param string $movieQuoteId The movie quote ID
     * @return string|null The associated movie ID or null if not found
     */
    private function getMovieFromQuote(string $movieQuoteId): ?string {
        try {
            $this->logger->debug("Getting movie from quote", [
                'movie_quote_id' => $movieQuoteId,
                'method' => 'getMovieFromQuote'
            ]);
            
            // Use the cached movie model
            $movieModel = $this->getMovieModel($movieQuoteId);
            
            if (!$movieModel) {
                $this->logger->warning("Could not retrieve movie model for quote", [
                    'movie_quote_id' => $movieQuoteId,
                    'method' => 'getMovieFromQuote'
                ]);
                return null;
            }
            
            $movieId = $movieModel->get('id');
            
            $this->logger->debug("Found movie via relationship", [
                'movie_quote_id' => $movieQuoteId,
                'movie_id' => $movieId,
                'method' => 'getMovieFromQuote'
            ]);
            return $movieId;
            
        } catch (\Exception $e) {
            $this->logger->error("Exception in getMovieFromQuote", [
                'movie_quote_id' => $movieQuoteId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'method' => 'getMovieFromQuote'
            ]);
            return null;
        }
    }
    
    /**
     * Select random movies as distractors (excluding the correct answer)
     * 
     * @param string $excludeMovieId Movie ID to exclude (the correct answer)
     * @param int $count Number of distractor movies to select
     * @return array Array of movie IDs
     */
    private function selectRandomDistractorMovies(string $excludeMovieId, int $count): array {
        try {
            $db = ServiceLocator::getDatabaseConnector();
            
            // Use ModelFactory to get a Movies model instance for query building
            $movieModel = ModelFactory::new('Movies');
            
            $this->logger->debug("Selecting random distractor movies", [
                'exclude_movie_id' => $excludeMovieId,
                'requested_count' => $count,
                'method' => 'selectRandomDistractorMovies'
            ]);
            
            $distractors = [];
            $excludeList = [$excludeMovieId]; // Start with the correct answer to exclude
            
            // Try to get the requested number of random movies
            for ($i = 0; $i < $count; $i++) {
                // Build criteria - the applyCriteria method doesn't support NOT IN directly
                // So we need to use a different approach: get random movies and filter client-side
                $criteria = [
                    'deleted_at' => null,  // Only active movies
                ];
                
                // Try multiple times to find a movie not in our exclude list
                $attempts = 0;
                $maxAttempts = 20; // Prevent infinite loops
                $randomMovieId = null;
                
                while ($attempts < $maxAttempts) {
                    $candidateId = $db->getRandomRecord($movieModel, $criteria);
                    
                    if ($candidateId && !in_array($candidateId, $excludeList)) {
                        $randomMovieId = $candidateId;
                        break;
                    }
                    $attempts++;
                }
                
                if ($randomMovieId) {
                    $distractors[] = $randomMovieId;
                    $excludeList[] = $randomMovieId; // Add to exclude list for next iteration
                } else {
                    // If we can't find more unique movies after max attempts, break early
                    $this->logger->debug("Could not find unique distractor movie after max attempts", [
                        'exclude_list' => $excludeList,
                        'attempts' => $attempts,
                        'iteration' => $i,
                        'method' => 'selectRandomDistractorMovies'
                    ]);
                    break;
                }
            }
            
            $this->logger->debug("Selected distractor movies", [
                'exclude_movie_id' => $excludeMovieId,
                'requested_count' => $count,
                'actual_count' => count($distractors),
                'distractor_ids' => $distractors,
                'method' => 'selectRandomDistractorMovies'
            ]);
            
            return $distractors;
            
        } catch (\Exception $e) {
            $this->logger->error("Exception in selectRandomDistractorMovies", [
                'exclude_movie_id' => $excludeMovieId,
                'requested_count' => $count,
                'error' => $e->getMessage(),
                'method' => 'selectRandomDistractorMovies'
            ]);
            
            // Return empty array on error - the calling method will handle fallback
            return [];
        }
    }
    
    /**
     * Get movie title by ID
     * 
     * @param string $movieId The movie ID
     * @return string The movie title or 'Unknown Movie' if not found
     */
    private function getMovieTitle(string $movieId): string {
        // Use ModelFactory to retrieve the specific movie record
        $movieModel = ModelFactory::retrieve('Movies', $movieId);
        
        return $movieModel ? ($movieModel->get('name') ?? 'Unknown Movie') : 'Unknown Movie';
    }
    
    /**
     * Get the obscurity score from the movie associated with the movie quote using relationships
     * 
     * @return int|null The obscurity score or null if not found
     */
    private function getObscurityScoreFromMovie(): ?int {
        $movieQuoteId = $this->get('movie_quote_id');
        if (!$movieQuoteId) {
            return null;
        }
        
        try {
            // Use the cached movie model
            $movieModel = $this->getMovieModel($movieQuoteId);
            if (!$movieModel) {
                return null;
            }
            
            return $movieModel->get('obscurity_score');
            
        } catch (\Exception $e) {
            $this->logger->error("Exception in getObscurityScoreFromMovie", [
                'movie_quote_id' => $movieQuoteId,
                'error' => $e->getMessage(),
                'method' => 'getObscurityScoreFromMovie'
            ]);
            return null;
        }
    }
}
