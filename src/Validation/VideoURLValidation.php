<?php
namespace Gravitycar\Validation;

/**
 * VideoURLValidation: Ensures a value is a valid video URL (YouTube, Vimeo, etc.).
 */
class VideoURLValidation extends ValidationRuleBase {
    public function __construct() {
        parent::__construct('VideoURL', 'Invalid video URL format. Please enter a valid YouTube or Vimeo URL.');
    }

    public function validate($value, $model = null): bool {
        // Skip validation for empty values (let Required rule handle that)
        if (empty($value)) {
            return true;
        }
        
        return $this->isValidVideoUrl($value);
    }

    /**
     * Check if URL is a valid video URL
     */
    private function isValidVideoUrl(string $url): bool {
        // YouTube URL patterns
        if (preg_match('/^https?:\/\/(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)/', $url)) {
            return true;
        }
        
        // Vimeo URL patterns  
        if (preg_match('/^https?:\/\/(www\.)?vimeo\.com\/\d+/', $url)) {
            return true;
        }
        
        return false;
    }

    /**
     * Get JavaScript validation logic for client-side validation
     */
    public function getJavascriptValidation(): string {
        return "
        function validateVideoURL(value, fieldName) {
            // Skip validation for empty values (let Required rule handle that)
            if (!value || value === '') {
                return { valid: true };
            }
            
            // YouTube URL patterns
            const youtubeRegex = /^https?:\/\/(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)/;
            
            // Vimeo URL patterns
            const vimeoRegex = /^https?:\/\/(www\.)?vimeo\.com\/\d+/;
            
            if (youtubeRegex.test(value) || vimeoRegex.test(value)) {
                return { valid: true };
            }
            
            return { 
                valid: false, 
                message: 'Invalid video URL format. Please enter a valid YouTube or Vimeo URL.' 
            };
        }";
    }
}
