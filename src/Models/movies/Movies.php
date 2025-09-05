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
    
    /**
     * Override update to refresh TMDB data when movie is updated
     */
    public function update(): bool {
        // Store the current TMDB ID before update
        $currentTmdbId = $this->get('tmdb_id');
        
        // If we have a TMDB ID, refresh the data from TMDB before saving
        if (!empty($currentTmdbId)) {
            try {
                $this->refreshFromTMDB($currentTmdbId);
            } catch (\Exception $e) {
                // Log the error but don't fail the update - just proceed without TMDB refresh
                $logger = ServiceLocator::getLogger();
                $logger->warning('Failed to refresh TMDB data during movie update', [
                    'movie_id' => $this->get('id'),
                    'tmdb_id' => $currentTmdbId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Call parent update to handle normal saving process
        return parent::update();
    }
    
    /**
     * Refresh movie data from TMDB (public method for API endpoint)
     */
    public function refreshFromTMDB(?int $tmdbId = null): void {
        $tmdbIdToUse = $tmdbId ?? $this->get('tmdb_id');
        
        if (empty($tmdbIdToUse)) {
            throw new \InvalidArgumentException('No TMDB ID available for refresh');
        }
        
        $this->enrichFromTMDB($tmdbIdToUse);
    }
}
