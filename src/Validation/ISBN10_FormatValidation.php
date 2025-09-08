<?php
namespace Gravitycar\Validation;

/**
 * ISBN10_FormatValidation: Validates ISBN-10 format
 * Ensures the value follows ISBN-10 format (10 characters, digits and possibly X)
 */
class ISBN10_FormatValidation extends ValidationRuleBase
{
    public function __construct()
    {
        parent::__construct('ISBN10_Format', 'Invalid ISBN-10 format. Must be 10 digits (last character may be X).');
    }
    
    /**
     * Validate ISBN-10 format
     * 
     * @param mixed $value The ISBN-10 value to validate
     * @param \Gravitycar\Models\ModelBase|null $model Optional model context
     * @return bool True if valid, false otherwise
     */
    public function validate($value, $model = null): bool
    {
        // Skip validation for empty values
        if (!$this->shouldValidateValue($value)) {
            return true;
        }
        
        // Clean the ISBN (remove any formatting except X)
        $cleanIsbn = preg_replace('/[^0-9X]/', '', strtoupper($value));
        
        // Check if it's exactly 10 characters
        if (strlen($cleanIsbn) !== 10) {
            return false;
        }
        
        // Check format: 9 digits followed by digit or X
        if (!preg_match('/^\d{9}[\dX]$/', $cleanIsbn)) {
            return false;
        }
        
        // Optional: Validate ISBN-10 checksum
        if (!$this->validateISBN10Checksum($cleanIsbn)) {
            $this->errorMessage = 'Invalid ISBN-10 checksum.';
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate ISBN-10 checksum using the modulo 11 algorithm
     * 
     * @param string $isbn Clean 10-character ISBN
     * @return bool True if checksum is valid
     */
    private function validateISBN10Checksum(string $isbn): bool
    {
        if (strlen($isbn) !== 10) {
            return false;
        }
        
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $digit = (int)$isbn[$i];
            $sum += $digit * (10 - $i);
        }
        
        $checkCharacter = $isbn[9];
        $checkValue = ($checkCharacter === 'X') ? 10 : (int)$checkCharacter;
        
        $calculatedCheck = (11 - ($sum % 11)) % 11;
        if ($calculatedCheck === 10) {
            $calculatedCheck = 'X';
        }
        
        return ($checkValue == $calculatedCheck);
    }
}
