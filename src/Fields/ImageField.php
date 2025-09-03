<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * ImageField: Field for storing and displaying image file paths or URLs.
 */
class ImageField extends FieldBase {
    protected string $type = 'Image';
    protected string $label = '';
    protected bool $required = false;
    protected int $maxLength = 500;
    protected $width = null;
    protected $height = null;
    protected string $altText = '';
    protected bool $allowLocal = true;
    protected bool $allowRemote = true;
    protected string $placeholder = 'Enter image path or URL';
    protected string $reactComponent = 'ImageUpload';
    
    // Thumbnail support
    protected int $thumbnailWidth = 150;
    protected int $thumbnailHeight = 225; // Movie poster ratio
    protected bool $showThumbnail = true;
    protected string $thumbnailSize = 'w185'; // TMDB size
    
    /** @var array Image fields have very limited filtering capabilities */
    protected array $operators = ['equals', 'notEquals', 'isNull', 'isNotNull'];

    public function __construct(array $metadata) {
        parent::__construct($metadata);
        // ingestMetadata() in parent constructor now handles all property assignments
    }
    
    /**
     * Get thumbnail URL for display
     */
    public function getThumbnailUrl(): ?string {
        $url = $this->getValue();
        if (!$url) return null;
        
        // For TMDB URLs, replace size parameter
        if (strpos($url, 'image.tmdb.org') !== false) {
            return preg_replace('/\/w\d+\//', "/{$this->thumbnailSize}/", $url);
        }
        
        return $url;
    }

    /**
     * Get current thumbnail size
     */
    public function getThumbnailSize(): string {
        return $this->thumbnailSize;
    }

    /**
     * Set thumbnail size for TMDB images
     */
    public function setThumbnailSize(string $size): void {
        $this->thumbnailSize = $size;
    }
}
