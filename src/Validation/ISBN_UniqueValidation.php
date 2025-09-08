<?php
namespace Gravitycar\Validation;

/**
 * ISBN_UniqueValidation: Ensures an ISBN is unique in the database.
 * Uses the standard unique validation but with a custom error message.
 */
class ISBN_UniqueValidation extends UniqueValidation
{
    public function __construct()
    {
        parent::__construct();
        
        $this->errorMessage = 'This ISBN is already associated with another book in the database.';
        $this->name = 'ISBN_Unique';
    }
}
