<?php
namespace Gravitycar\Validation;

/**
 * ISBN13_FormatValidation: Validates ISBN-13 format
 * Ensures the value follows ISBN-13 format (13 digits)
 */
class ISBN13_FormatValidation extends ValidationRuleBase
{
    public function __construct()
    {
        parent::__construct('ISBN13_Format', 'Invalid ISBN-13 format. Must be 13 digits.');
    }
    
    /**
     * Validate ISBN-13 format
     * 
     * @param mixed $value The ISBN-13 value to validate
     * @param \Gravitycar\Models\ModelBase|null $model Optional model context
     * @return bool True if valid, false otherwise
     */
    public function validate($value, $model = null): bool
    {
        // Skip validation for empty values
        if (!$this->shouldValidateValue($value)) {
            return true;
        }
        
        // Clean the ISBN (remove any formatting)
        $cleanIsbn = preg_replace('/[^0-9]/', '', $value);
        
        // Check if it's exactly 13 digits
        if (strlen($cleanIsbn) !== 13) {
            return false;
        }
        
        // Check if all characters are digits
        if (!ctype_digit($cleanIsbn)) {
            return false;
        }
        
        // Optional: Validate ISBN-13 checksum
        if (!$this->validateISBN13Checksum($cleanIsbn)) {
            $this->errorMessage = 'Invalid ISBN-13 checksum.';
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate ISBN-13 checksum using the modulo 10 algorithm
     * 
     * @param string $isbn Clean 13-digit ISBN
     * @return bool True if checksum is valid
     */
    private function validateISBN13Checksum(string $isbn): bool
    {
        if (strlen($isbn) !== 13) {
            return false;
        }
        
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int)$isbn[$i];
            $weight = ($i % 2 === 0) ? 1 : 3;
            $sum += $digit * $weight;
        }
        
        $checkDigit = (10 - ($sum % 10)) % 10;
        $providedCheckDigit = (int)$isbn[12];
        
        return $checkDigit === $providedCheckDigit;
    }
}
