<?php
namespace Gravitycar\Validation;

/**
 * GoogleBooksID_UniqueValidation: Ensures a Google Books ID is unique in the database.
 * Provides a specific error message for duplicate books.
 */
class GoogleBooksID_UniqueValidation extends UniqueValidation
{
    public function __construct()
    {
        // Call parent constructor but override the error message
        parent::__construct();
        
        // Set a more specific error message for Google Books ID uniqueness
        $this->errorMessage = 'This book already exists in the database. Please search for existing books before creating a new one.';
        
        // Update the name to reflect this specific validation
        $this->name = 'GoogleBooksID_Unique';
    }
}
