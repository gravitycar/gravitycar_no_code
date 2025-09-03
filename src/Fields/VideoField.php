<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;

class VideoField extends FieldBase {
    protected string $type = 'Video';
    protected string $label = '';
    protected bool $required = false;
    protected int $maxLength = 500;
    protected string $placeholder = 'Enter video URL (YouTube, Vimeo, etc.)';
    protected string $reactComponent = 'VideoEmbed';
    protected array $operators = ['equals', 'notEquals', 'isNull', 'isNotNull'];
    
    // Video-specific properties
    protected array $supportedPlatforms = ['youtube', 'vimeo', 'dailymotion'];
    protected bool $autoplay = false;
    protected bool $showControls = true;
    protected int $width = 560;
    protected int $height = 315;
    
    public function __construct(array $metadata) {
        parent::__construct($metadata);
    }
    
    /**
     * Extract video ID from URL for embedding
     */
    public function getVideoId(): ?string {
        $url = $this->getValue();
        
        // YouTube
        if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }
        
        // Vimeo
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get embed URL for iframe
     */
    public function getEmbedUrl(): ?string {
        $videoId = $this->getVideoId();
        if (!$videoId) return null;
        
        $url = $this->getValue();
        
        if (strpos($url, 'youtube') !== false) {
            return "https://www.youtube.com/embed/{$videoId}";
        }
        
        if (strpos($url, 'vimeo') !== false) {
            return "https://player.vimeo.com/video/{$videoId}";
        }
        
        return null;
    }
    
    /**
     * Generate OpenAPI schema for video field
     */
    public function generateOpenAPISchema(): array {
        $schema = [
            'type' => 'string',
            'format' => 'uri'
        ];
        
        if (isset($this->metadata['description'])) {
            $schema['description'] = $this->metadata['description'];
        }
        
        if (isset($this->metadata['maxLength'])) {
            $schema['maxLength'] = $this->metadata['maxLength'];
        }
        
        if (isset($this->metadata['example'])) {
            $schema['example'] = $this->metadata['example'];
        }
        
        return $schema;
    }
}
