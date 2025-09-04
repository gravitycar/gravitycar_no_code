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
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Override create to implement automatic question generation
     * 
     * When creating a new trivia question, this method will:
     * 1. Select a random movie quote (if not specified)
     * 2. Get the movie associated with that quote (correct answer)
     * 3. Select 2 random movies as distractors
     * 4. Randomly assign the 3 movies to the answer options
     */
    public function create(): bool {
        // If no movie quote is specified, select one randomly
        if (!$this->get('movie_quote_id')) {
            $randomQuoteId = $this->selectRandomMovieQuote();
            if (!$randomQuoteId) {
                throw new GCException("No movie quotes available for trivia question generation");
            }
            $this->set('movie_quote_id', $randomQuoteId);
        }
        
        // Get the correct movie from the movie quote
        $correctMovieId = $this->getMovieFromQuote($this->get('movie_quote_id'));
        if (!$correctMovieId) {
            throw new GCException("Movie quote does not have an associated movie");
        }
        
        // Generate random distractor movies
        $distractorMovies = $this->selectRandomDistractorMovies($correctMovieId, 2);
        if (count($distractorMovies) < 2) {
            throw new GCException("Insufficient movies available for generating distractor options");
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
        
        return parent::create();
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
     * Select a random movie quote that has a valid movie relationship
     * 
     * @return string|null The quote ID or null if none found
     */
    private function selectRandomMovieQuote(): ?string {
        $db = ServiceLocator::getDatabaseConnector();
        
        // Use ModelFactory to get a MovieQuote model instance
        $movieQuoteModel = ModelFactory::new('MovieQuote');
        
        // Define criteria using the new relationship format
        $criteria = [
            'deleted_at' => null,                           // Direct field: movie quote not deleted
            'movie_id' => '__NOT_NULL__',                   // Direct field: has a movie relationship
            'movies_movie_quotes.movies.deleted_at' => null // Related model field: related movie not deleted
        ];
        
        // Use the new getRandomRecord method with relationship support
        return $db->getRandomRecord($movieQuoteModel, $criteria);
    }
    
    /**
     * Get the movie ID associated with a movie quote
     * 
     * @param string $movieQuoteId The movie quote ID
     * @return string|null The associated movie ID or null if not found
     */
    private function getMovieFromQuote(string $movieQuoteId): ?string {
        try {
            // Use ModelFactory to retrieve the specific movie quote record
            $movieQuoteModel = ModelFactory::retrieve('Movie_Quote', $movieQuoteId);
            
            if (!$movieQuoteModel) {
                return null;
            }
            
            // Get the relationship object for movies_movie_quotes
            $relationship = $movieQuoteModel->getRelationship('movies_movie_quotes');
            
            if (!$relationship) {
                return null;
            }
            
            // Cast to OneToManyRelationship to access the specific method
            if ($relationship instanceof \Gravitycar\Relationships\OneToManyRelationship) {
                // Since this is a one-to-many relationship and we want the 'one' side (movie),
                // call getRelatedFromMany() which returns the single "one" record
                $relatedRecord = $relationship->getRelatedFromMany($movieQuoteModel);
                
                // Return the movie ID from the related record
                return $relatedRecord ? $relatedRecord['id'] : null;
            }
            
            return null;
            
        } catch (\Exception $e) {
            // Log the error and return null if anything goes wrong
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
        $db = ServiceLocator::getDatabaseConnector();
        
        // Use ModelFactory to get a Movie model instance
        $movieModel = ModelFactory::new('Movie');
        
        // Define criteria to exclude the correct movie and deleted movies
        $criteria = [
            'deleted_at' => null
        ];
        
        // Use the enhanced find method with parameters for random selection and exclusion
        $parameters = [
            'orderBy' => ['id' => 'RAND()'],
            'limit' => $count + 10, // Get extra in case we need to filter out the excluded movie
        ];
        
        $results = $db->find($movieModel, $criteria, ['id'], $parameters);
        
        // Filter out the excluded movie and limit to requested count
        $movieIds = [];
        foreach ($results as $row) {
            if ($row['id'] !== $excludeMovieId && count($movieIds) < $count) {
                $movieIds[] = $row['id'];
                // Remove duplicates after each addition
                $movieIds = array_unique($movieIds);
            }
        }
        
        return $movieIds;
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
}
