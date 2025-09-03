<?php
namespace Gravitycar\Models\movies;

use Gravitycar\Models\ModelBase;
use Gravitycar\Services\MovieTMDBIntegrationService;
use Gravitycar\Core\ServiceLocator;

/**
 * Movies model class for Gravitycar framework.
 */
class Movies extends ModelBase {
    private ?MovieTMDBIntegrationService $tmdbIntegration = null;
    
    public function __construct() {
        parent::__construct();
        $this->tmdbIntegration = new MovieTMDBIntegrationService();
    }
    
    /**
     * Search TMDB for movie matches
     */
    public function searchTMDBMovies(string $title): array {
        return $this->tmdbIntegration->searchMovie($title);
    }
    
    /**
     * Apply TMDB data to movie fields
     */
    public function enrichFromTMDB(int $tmdbId): void {
        $enrichmentData = $this->tmdbIntegration->enrichMovieData($tmdbId);
        
        foreach ($enrichmentData as $fieldName => $value) {
            if ($this->hasField($fieldName) && !empty($value)) {
                $this->set($fieldName, $value);
            }
        }
    }
    
    /**
     * Override create to handle TMDB enrichment and read-only behavior
     */
    public function create(): bool {
        // Call parent create to handle normal saving process
        $result = parent::create();
        
        // After successful creation, make title read-only for future updates
        if ($result) {
            $nameField = $this->getField('name');
            if ($nameField) {
                $nameField->setReadOnly(true);
            }
        }
        
        return $result;
    }
}
