<?php
namespace Gravitycar\Validation;

/**
 * TMDBID_UniqueValidation: Ensures a TMDB ID is unique in the database.
 * Provides a specific error message for duplicate movies.
 */
class TMDBID_UniqueValidation extends UniqueValidation {
    
    public function __construct() {
        // Call parent constructor but override the error message
        parent::__construct();
        
        // Set a more specific error message for TMDB ID uniqueness
        $this->errorMessage = 'This movie already exists in the database. Please search for existing movies before creating a new one.';
        
        // Update the name to reflect this specific validation
        $this->name = 'TMDBID_Unique';
    }
}
