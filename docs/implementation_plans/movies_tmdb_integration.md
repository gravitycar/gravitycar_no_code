# Movies Model TMDB Integration Implementation Plan

## Overview
This plan outlines the integration of The Movie Database (TMDB) API with the Movies model to provide automated movie data enrichment, poster images, trailers, and enhanced user experience during movie creation.

## Requirements Summary
1. **TMDB Search Integration**: When creating movies, search TMDB for exact/partial matches
2. **Match Selection UI**: Handle exact matches, no matches, and multiple matches with user selection
3. **Data Enrichment**: Populate poster image, synopsis, trailer, and obscurity score from TMDB
4. **Field Enhancements**: Implement VideoField for trailers, enhance ImageField for posters
5. **Conditional Read-Only**: Make movie title read-only after creation
6. **List View Thumbnails**: Display poster thumbnails in movie list view

## Architecture Overview

### Framework Integration Changes
- Update `movies_metadata.php` to include new TMDB-related fields
- Run `php setup.php` to generate database schema from updated metadata
- No direct SQL ALTER TABLE statements (framework handles schema generation)

### New Components Required
1. **VideoField**: New field type for storing and displaying video URLs
2. **TMDB Integration Service**: Backend service for handling TMDB API interactions
3. **Movie Selection Dialog**: React component for choosing from multiple TMDB matches
4. **Enhanced ImageField**: Support for dimensions and thumbnail display
5. **Conditional Field Logic**: System for making fields read-only based on record state

## Implementation Plan

### Phase 1: Database and Field Infrastructure (Days 1-2)

#### Step 1.1: Update Movies Metadata
**File**: `src/Models/movies/movies_metadata.php`

Update the movies metadata to include new TMDB-related fields. The framework's SchemaGenerator will automatically create the database columns based on these field definitions:

```php
<?php
return [
    'name' => 'Movies',
    'table' => 'movies',
    'fields' => [
        'name' => [
            'name' => 'name',
            'type' => 'Text',
            'label' => 'Title',
            'required' => true,
            'validationRules' => ['Required'],
            // Will be set to readOnly dynamically after save
        ],
        'tmdb_id' => [
            'name' => 'tmdb_id',
            'type' => 'Integer',
            'label' => 'TMDB ID',
            'readOnly' => true,
            'nullable' => true,
            'description' => 'The Movie Database ID for external data linking',
        ],
        'synopsis' => [
            'name' => 'synopsis',
            'type' => 'BigText',
            'label' => 'Synopsis',
            'maxLength' => 5000,
            'readOnly' => false, // Allow manual entry if no TMDB match
        ],
        'poster_url' => [
            'name' => 'poster_url',
            'type' => 'Image',
            'label' => 'Movie Poster',
            'width' => 300,
            'height' => 450,
            'maxLength' => 1000,
            'allowRemote' => true,
            'allowLocal' => false,
            'altText' => 'Movie poster image',
        ],
        'trailer_url' => [
            'name' => 'trailer_url',
            'type' => 'Video',
            'label' => 'Movie Trailer',
            'width' => 560,
            'height' => 315,
            'showControls' => true,
            'nullable' => true,
            'maxLength' => 500,
        ],
        'obscurity_score' => [
            'name' => 'obscurity_score',
            'type' => 'Integer',
            'label' => 'Obscurity Score',
            'minValue' => 1,
            'maxValue' => 5,
            'readOnly' => true,
            'nullable' => true,
            'description' => 'Film obscurity: 1=Very Popular, 5=Very Obscure',
        ],
        'release_year' => [
            'name' => 'release_year',
            'type' => 'Integer',
            'label' => 'Release Year',
            'minValue' => 1800,
            'maxValue' => 2100,
            'readOnly' => true,
            'nullable' => true,
        ],
        // Legacy field for backwards compatibility
        'poster' => [
            'name' => 'poster',
            'type' => 'Text',
            'label' => 'Poster (Legacy)',
            'isDBField' => false,
        ],
    ],
    'validationRules' => [],
    'relationships' => ['movies_movie_quotes'],
    'ui' => [
        'listFields' => ['poster_url', 'name', 'release_year', 'obscurity_score'],
        'createFields' => ['name'], // Only title during creation, rest populated via TMDB
        'editFields' => ['name', 'synopsis', 'poster_url', 'trailer_url'],
        'relatedItemsSections' => [
            'quotes' => [
                'title' => 'Movie Quotes',
                'relationship' => 'movies_movie_quotes',
                'mode' => 'children_management',
                'relatedModel' => 'Movie_Quotes',
                'displayColumns' => ['quote'],
                'actions' => ['create', 'edit', 'delete'],
                'allowInlineCreate' => true,
                'allowInlineEdit' => true,
                'createFields' => ['quote'],
                'editFields' => ['quote'],
            ]
        ],
    ],
];
```

#### Step 1.2: Run Setup Script
After updating the metadata, run the framework's setup script to generate the database schema:

```bash
php setup.php
```

This will:
- Read the updated `movies_metadata.php` file
- Generate SQL to add the new columns (`tmdb_id`, `trailer_url`, `obscurity_score`, `release_year`)  
- Create appropriate indexes automatically
- Update the metadata cache

#### Step 1.3: Create VideoField Class
**File**: `src/Fields/VideoField.php`
```php
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
     * Validate video URL format
     */
    public function validate(): bool {
        if (!parent::validate()) {
            return false;
        }
        
        $value = $this->getValue();
        if (!empty($value) && !$this->isValidVideoUrl($value)) {
            $this->addError('Invalid video URL format');
            return false;
        }
        
        return true;
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
}
```

#### Step 1.4: Enhanced ImageField with Dimensions
**Modifications to**: `src/Fields/ImageField.php`
```php
// Add thumbnail support
protected int $thumbnailWidth = 150;
protected int $thumbnailHeight = 225; // Movie poster ratio
protected bool $showThumbnail = true;
protected string $thumbnailSize = 'w185'; // TMDB size

public function getThumbnailUrl(): ?string {
    $url = $this->getValue();
    if (!$url) return null;
    
    // For TMDB URLs, replace size parameter
    if (strpos($url, 'image.tmdb.org') !== false) {
        return preg_replace('/\/w\d+\//', "/{$this->thumbnailSize}/", $url);
    }
    
    return $url;
}

public function getThumbnailSize(): string {
    return $this->thumbnailSize;
}

public function setThumbnailSize(string $size): void {
    $this->thumbnailSize = $size;
}
```

**Note**: The existing ImageField already supports `width` and `height` properties in metadata, so the enhancement mainly adds thumbnail-specific functionality.

#### Step 1.5: Add setReadOnly() Method to FieldBase
**Modifications to**: `src/Fields/FieldBase.php`

Add the `setReadOnly()` method to allow dynamic modification of field readonly state:

```php
/**
 * Set the readonly state of this field
 * 
 * @param bool $readonly Whether the field should be readonly
 * @return void
 */
public function setReadOnly(bool $readonly): void {
    $this->metadata['readonly'] = $readonly;
}

/**
 * Set the readonly state to true (convenience method)
 * 
 * @return void
 */
public function makeReadOnly(): void {
    $this->setReadOnly(true);
}

/**
 * Set the readonly state to false (convenience method)
 * 
 * @return void
 */
public function makeEditable(): void {
    $this->setReadOnly(false);
}
```

This addition allows the Movies model to dynamically set the title field to readonly after creation, supporting the requirement that movie titles become read-only after being saved.

### Phase 2: TMDB Integration Service (Days 3-4)

#### Step 2.1: Movie TMDB Integration Service
**File**: `src/Services/MovieTMDBIntegrationService.php`
```php
<?php
namespace Gravitycar\Services;

use Gravitycar\Services\TMDBApiService;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\GCException;

class MovieTMDBIntegrationService {
    private TMDBApiService $tmdbService;
    
    public function __construct() {
        $this->tmdbService = new TMDBApiService();
    }
    
    /**
     * Search for movie and return match results
     */
    public function searchMovie(string $title): array {
        $results = $this->tmdbService->searchMovies($title);
        
        return [
            'exact_match' => $this->findExactMatch($results, $title),
            'partial_matches' => $this->filterPartialMatches($results, $title),
            'match_type' => $this->determineMatchType($results, $title)
        ];
    }
    
    /**
     * Find exact title match (case-insensitive)
     */
    private function findExactMatch(array $results, string $title): ?array {
        $normalizedTitle = $this->normalizeTitle($title);
        
        foreach ($results as $movie) {
            if ($this->normalizeTitle($movie['title']) === $normalizedTitle) {
                return $movie;
            }
        }
        
        return null;
    }
    
    /**
     * Filter results for partial matches
     */
    private function filterPartialMatches(array $results, string $title): array {
        // Return top 5 most relevant matches
        return array_slice($results, 0, 5);
    }
    
    /**
     * Determine match type: exact, multiple, none
     */
    private function determineMatchType(array $results, string $title): string {
        if (empty($results)) {
            return 'none';
        }
        
        if ($this->findExactMatch($results, $title)) {
            return 'exact';
        }
        
        return 'multiple';
    }
    
    /**
     * Enrich movie data from TMDB
     */
    public function enrichMovieData(int $tmdbId): array {
        $details = $this->tmdbService->getMovieDetails($tmdbId);
        
        return [
            'tmdb_id' => $details['tmdb_id'],
            'synopsis' => $details['overview'],
            'poster_url' => $details['poster_url'],
            'trailer_url' => $details['trailer_url'],
            'obscurity_score' => $details['obscurity_score'],
            'release_year' => $details['release_year']
        ];
    }
    
    /**
     * Normalize title for comparison
     */
    private function normalizeTitle(string $title): string {
        return strtolower(trim(preg_replace('/[^\w\s]/', '', $title)));
    }
}
```

### Phase 3: Backend API Enhancements (Days 5-6)

#### Step 3.1: Movie Creation Enhancement
**Modifications to**: `src/Models/Movies/Movies.php`
```php
<?php
namespace Gravitycar\Models\movies;

use Gravitycar\Models\ModelBase;
use Gravitycar\Services\MovieTMDBIntegrationService;
use Gravitycar\Core\ServiceLocator;

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
```

#### Step 3.2: API Endpoints for TMDB Integration
**File**: `src/Api/Movies/TMDBController.php`
```php
<?php
namespace Gravitycar\Api\Movies;

use Gravitycar\Api\ApiControllerBase;
use Gravitycar\Services\MovieTMDBIntegrationService;
use Gravitycar\Exceptions\GCException;

class TMDBController extends ApiControllerBase {
    private MovieTMDBIntegrationService $tmdbService;
    
    public function __construct() {
        parent::__construct();
        $this->tmdbService = new MovieTMDBIntegrationService();
    }
    
    /**
     * Register routes for this controller
     */
    public function registerRoutes(): array {
        return [
            [
                'method' => 'GET',
                'path' => '/movies/tmdb/search',
                'apiClass' => '\\Gravitycar\\Api\\Movies\\TMDBController',
                'apiMethod' => 'search',
                'parameterNames' => []
            ],
            [
                'method' => 'GET',
                'path' => '/movies/tmdb/enrich/?',
                'apiClass' => '\\Gravitycar\\Api\\Movies\\TMDBController',
                'apiMethod' => 'enrich',
                'parameterNames' => ['tmdbId']
            ]
        ];
    }
    
    /**
     * Search TMDB for movies
     * GET /movies/tmdb/search?title=movie+title
     */
    public function search(): array {
        $title = $_GET['title'] ?? null;
        
        if (empty($title)) {
            throw new GCException('Title parameter is required');
        }
        
        $results = $this->tmdbService->searchMovie($title);
        
        $this->jsonResponse([
            'success' => true,
            'data' => $results
        ]);
        
        return [
            'success' => true,
            'data' => $results
        ];
    }
    
    /**
     * Get enrichment data for specific TMDB ID
     * GET /movies/tmdb/enrich/{tmdb_id}
     */
    public function enrich(int $tmdbId): array {
        $enrichmentData = $this->tmdbService->enrichMovieData($tmdbId);
        
        $this->jsonResponse([
            'success' => true,
            'data' => $enrichmentData
        ]);
        
        return [
            'success' => true,
            'data' => $enrichmentData
        ];
    }
}
```

**Note**: This controller will be automatically discovered by the APIRouteRegistry since it extends `ApiControllerBase` and includes the required `registerRoutes()` method. The routes will be registered during framework bootstrap and made available to the Router for frontend consumption.

### Phase 4: Frontend Components (Days 7-9)

#### Step 4.1: TMDB Movie Selection Dialog
**File**: `gravitycar-frontend/src/components/movies/TMDBMovieSelector.tsx`
```typescript
import React, { useState } from 'react';
import { Modal } from '../ui/Modal';

interface TMDBMovie {
  tmdb_id: number;
  title: string;
  release_year: number;
  poster_url: string;
  overview: string;
  obscurity_score: number;
}

interface TMDBMovieSelectorProps {
  isOpen: boolean;
  onClose: () => void;
  onSelect: (movie: TMDBMovie) => void;
  movies: TMDBMovie[];
  title: string;
}

export const TMDBMovieSelector: React.FC<TMDBMovieSelectorProps> = ({
  isOpen,
  onClose,
  onSelect,
  movies,
  title
}) => {
  const [selectedMovie, setSelectedMovie] = useState<TMDBMovie | null>(null);
  
  const handleSelect = () => {
    if (selectedMovie) {
      onSelect(selectedMovie);
    }
  };
  
  return (
    <Modal isOpen={isOpen} onClose={onClose} title={`Select Match for "${title}"`}>
      <div className="space-y-4 max-h-96 overflow-y-auto">
        {movies.map((movie) => (
          <div
            key={movie.tmdb_id}
            className={`border rounded-lg p-4 cursor-pointer transition-colors ${
              selectedMovie?.tmdb_id === movie.tmdb_id
                ? 'border-blue-500 bg-blue-50'
                : 'border-gray-200 hover:border-gray-300'
            }`}
            onClick={() => setSelectedMovie(movie)}
          >
            <div className="flex space-x-4">
              {movie.poster_url && (
                <img
                  src={movie.poster_url}
                  alt={movie.title}
                  className="w-16 h-24 object-cover rounded"
                />
              )}
              <div className="flex-1">
                <h3 className="font-semibold text-lg">{movie.title}</h3>
                <p className="text-gray-600">
                  Year: {movie.release_year} | Obscurity: {movie.obscurity_score}/5
                </p>
                <p className="text-sm text-gray-500 mt-2 line-clamp-3">
                  {movie.overview}
                </p>
              </div>
            </div>
          </div>
        ))}
      </div>
      
      <div className="flex justify-between mt-6">
        <button
          onClick={onClose}
          className="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50"
        >
          Skip TMDB Match
        </button>
        <button
          onClick={handleSelect}
          disabled={!selectedMovie}
          className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
        >
          Select This Movie
        </button>
      </div>
    </Modal>
  );
};
```

#### Step 4.2: Enhanced Movie Creation Form
**File**: `gravitycar-frontend/src/components/movies/MovieCreateForm.tsx`
```typescript
import React, { useState, useEffect } from 'react';
import { TMDBMovieSelector } from './TMDBMovieSelector';
import { useApi } from '../../hooks/useApi';

export const MovieCreateForm = ({ onSave, onCancel }) => {
  const [formData, setFormData] = useState({
    name: '',
    synopsis: '',
    poster_url: '',
    trailer_url: '',
    obscurity_score: null,
    tmdb_id: null
  });
  
  const [tmdbState, setTmdbState] = useState({
    isSearching: false,
    showSelector: false,
    searchResults: [],
    matchType: null
  });
  
  const api = useApi();
  
  // Debounced TMDB search on title change
  useEffect(() => {
    if (formData.name.length >= 3) {
      const timer = setTimeout(() => {
        searchTMDB(formData.name);
      }, 500);
      
      return () => clearTimeout(timer);
    }
  }, [formData.name]);
  
  const searchTMDB = async (title: string) => {
    setTmdbState(prev => ({ ...prev, isSearching: true }));
    
    try {
      const response = await api.get(`/movies/tmdb/search?title=${encodeURIComponent(title)}`);
      const { exact_match, partial_matches, match_type } = response.data;
      
      if (match_type === 'exact') {
        // Auto-apply exact match
        applyTMDBData(exact_match);
      } else if (match_type === 'multiple') {
        // Show selection dialog
        setTmdbState({
          isSearching: false,
          showSelector: true,
          searchResults: partial_matches,
          matchType: 'multiple'
        });
      }
      // For 'none', do nothing - allow manual entry
      
    } catch (error) {
      console.error('TMDB search failed:', error);
    } finally {
      setTmdbState(prev => ({ ...prev, isSearching: false }));
    }
  };
  
  const applyTMDBData = async (tmdbMovie) => {
    try {
      const enrichmentResponse = await api.get(`/movies/tmdb/enrich/${tmdbMovie.tmdb_id}`);
      const enrichmentData = enrichmentResponse.data;
      
      setFormData(prev => ({
        ...prev,
        ...enrichmentData,
        // Keep user-entered title
        name: prev.name
      }));
      
      setTmdbState(prev => ({ ...prev, showSelector: false }));
    } catch (error) {
      console.error('TMDB enrichment failed:', error);
    }
  };
  
  const handleSubmit = async (e) => {
    e.preventDefault();
    await onSave(formData);
  };
  
  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div>
        <label className="block text-sm font-medium text-gray-700">
          Movie Title *
        </label>
        <div className="relative">
          <input
            type="text"
            value={formData.name}
            onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
            className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
            required
          />
          {tmdbState.isSearching && (
            <div className="absolute right-3 top-3">
              <div className="animate-spin h-4 w-4 border-2 border-blue-500 border-t-transparent rounded-full"></div>
            </div>
          )}
        </div>
        {formData.tmdb_id && (
          <p className="text-sm text-green-600 mt-1">
            ✓ Matched with TMDB (ID: {formData.tmdb_id})
          </p>
        )}
      </div>
      
      {/* Synopsis */}
      <div>
        <label className="block text-sm font-medium text-gray-700">Synopsis</label>
        <textarea
          value={formData.synopsis}
          onChange={(e) => setFormData(prev => ({ ...prev, synopsis: e.target.value }))}
          className="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2"
          rows={4}
          readOnly={!!formData.tmdb_id}
        />
      </div>
      
      {/* Poster Preview */}
      {formData.poster_url && (
        <div>
          <label className="block text-sm font-medium text-gray-700">Poster Preview</label>
          <img
            src={formData.poster_url}
            alt="Movie poster"
            className="mt-2 w-32 h-48 object-cover rounded border"
          />
        </div>
      )}
      
      {/* Trailer Preview */}
      {formData.trailer_url && (
        <div>
          <label className="block text-sm font-medium text-gray-700">Trailer</label>
          <div className="mt-2">
            <a
              href={formData.trailer_url}
              target="_blank"
              rel="noopener noreferrer"
              className="text-blue-600 hover:text-blue-800"
            >
              View Trailer on YouTube
            </a>
          </div>
        </div>
      )}
      
      {/* Form actions */}
      <div className="flex justify-end space-x-3 pt-4">
        <button
          type="button"
          onClick={onCancel}
          className="px-4 py-2 text-gray-700 border border-gray-300 rounded hover:bg-gray-50"
        >
          Cancel
        </button>
        <button
          type="submit"
          className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
        >
          Create Movie
        </button>
      </div>
      
      {/* TMDB Movie Selector Modal */}
      <TMDBMovieSelector
        isOpen={tmdbState.showSelector}
        onClose={() => setTmdbState(prev => ({ ...prev, showSelector: false }))}
        onSelect={applyTMDBData}
        movies={tmdbState.searchResults}
        title={formData.name}
      />
    </form>
  );
};
```

#### Step 4.3: VideoEmbed React Component
**File**: `gravitycar-frontend/src/components/fields/VideoEmbed.tsx`
```typescript
import React, { useState } from 'react';

interface VideoEmbedProps {
  value: string;
  onChange: (value: string) => void;
  width?: number;
  height?: number;
  showControls?: boolean;
  readOnly?: boolean;
}

export const VideoEmbed: React.FC<VideoEmbedProps> = ({
  value,
  onChange,
  width = 560,
  height = 315,
  showControls = true,
  readOnly = false
}) => {
  const [showPreview, setShowPreview] = useState(false);
  
  const getEmbedUrl = (url: string): string | null => {
    if (!url) return null;
    
    // YouTube
    const youtubeMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/);
    if (youtubeMatch) {
      return `https://www.youtube.com/embed/${youtubeMatch[1]}`;
    }
    
    // Vimeo
    const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
    if (vimeoMatch) {
      return `https://player.vimeo.com/video/${vimeoMatch[1]}`;
    }
    
    return null;
  };
  
  const embedUrl = getEmbedUrl(value);
  
  return (
    <div className="space-y-3">
      {!readOnly && (
        <input
          type="url"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder="Enter YouTube or Vimeo URL"
          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      )}
      
      {value && embedUrl && (
        <div>
          {showPreview ? (
            <div className="relative">
              <iframe
                src={embedUrl}
                width={width}
                height={height}
                frameBorder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowFullScreen
                className="rounded"
              />
              <button
                onClick={() => setShowPreview(false)}
                className="absolute top-2 right-2 bg-black bg-opacity-50 text-white p-1 rounded text-sm"
              >
                Hide
              </button>
            </div>
          ) : (
            <div className="flex items-center space-x-3">
              <div className="flex-1 p-3 bg-gray-50 rounded border">
                <p className="text-sm text-gray-600">Video URL: {value}</p>
              </div>
              <button
                onClick={() => setShowPreview(true)}
                className="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
              >
                Preview
              </button>
            </div>
          )}
        </div>
      )}
      
      {value && !embedUrl && (
        <p className="text-sm text-red-600">
          Invalid video URL. Please enter a valid YouTube or Vimeo URL.
        </p>
      )}
    </div>
  );
};
```

### Phase 5: Metadata Updates and UI Enhancements (Days 10-11)

#### Step 5.1: Updated Movies Metadata
**Note**: This step is already covered in Phase 1, Step 1.1. The metadata structure shown there includes all the necessary UI configuration for enhanced list views with thumbnails and proper field organization.

#### Step 5.2: Enhanced List View with Thumbnails
**File**: `gravitycar-frontend/src/components/movies/MovieListView.tsx`
```typescript
import React from 'react';

interface MovieListViewProps {
  movies: Movie[];
  onEdit: (movie: Movie) => void;
  onDelete: (movie: Movie) => void;
  onViewQuotes: (movie: Movie) => void;
}

export const MovieListView: React.FC<MovieListViewProps> = ({
  movies,
  onEdit,
  onDelete,
  onViewQuotes
}) => {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      {movies.map((movie) => (
        <div
          key={movie.id}
          className="bg-white rounded-lg shadow border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow"
        >
          {/* Movie Poster Thumbnail */}
          <div className="w-full h-64 bg-gray-200 flex items-center justify-center">
            {movie.poster_url ? (
              <img
                src={movie.poster_url}
                alt={movie.name}
                className="w-full h-full object-cover"
                loading="lazy"
                onError={(e) => {
                  const target = e.target as HTMLImageElement;
                  target.src = '/api/placeholder-movie-poster.svg';
                }}
              />
            ) : (
              <div className="text-gray-400 text-center p-4">
                <svg className="w-12 h-12 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" />
                </svg>
                <span className="text-sm">No Poster</span>
              </div>
            )}
          </div>
          
          {/* Movie Info */}
          <div className="p-4">
            <h3 className="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">
              {movie.name}
            </h3>
            
            <div className="flex justify-between items-center text-sm text-gray-600 mb-3">
              {movie.release_year && (
                <span>{movie.release_year}</span>
              )}
              {movie.obscurity_score && (
                <span className="flex items-center">
                  <span className="mr-1">Obscurity:</span>
                  <div className="flex">
                    {[1, 2, 3, 4, 5].map((level) => (
                      <div
                        key={level}
                        className={`w-2 h-2 rounded-full mr-1 ${
                          level <= movie.obscurity_score ? 'bg-orange-400' : 'bg-gray-200'
                        }`}
                      />
                    ))}
                  </div>
                </span>
              )}
            </div>
            
            {movie.synopsis && (
              <p className="text-gray-600 text-sm mb-4 line-clamp-3">
                {movie.synopsis}
              </p>
            )}
            
            {/* Action Buttons */}
            <div className="flex justify-between items-center pt-3 border-t border-gray-200">
              <button
                onClick={() => onViewQuotes(movie)}
                className="text-green-600 hover:text-green-700 text-sm font-medium"
              >
                View Quotes
              </button>
              <div className="flex space-x-2">
                <button
                  onClick={() => onEdit(movie)}
                  className="text-blue-600 hover:text-blue-700 text-sm font-medium"
                >
                  Edit
                </button>
                <button
                  onClick={() => onDelete(movie)}
                  className="text-red-600 hover:text-red-700 text-sm font-medium"
                >
                  Delete
                </button>
              </div>
            </div>
            
            {/* TMDB Badge */}
            {movie.tmdb_id && (
              <div className="mt-2 pt-2 border-t border-gray-100">
                <span className="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                  TMDB #{movie.tmdb_id}
                </span>
              </div>
            )}
          </div>
        </div>
      ))}
    </div>
  );
};
```

### Phase 6: Testing and Documentation (Days 12-13)

#### Step 6.1: Unit Tests
**File**: `Tests/Unit/Services/MovieTMDBIntegrationServiceTest.php`
```php
<?php
namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Gravitycar\Services\MovieTMDBIntegrationService;
use Gravitycar\Core\ServiceLocator;

class MovieTMDBIntegrationServiceTest extends TestCase {
    private MovieTMDBIntegrationService $service;
    
    protected function setUp(): void {
        ServiceLocator::initialize();
        $this->service = new MovieTMDBIntegrationService();
    }
    
    public function testSearchMovieExactMatch(): void {
        $result = $this->service->searchMovie('The Matrix');
        
        $this->assertEquals('exact', $result['match_type']);
        $this->assertNotNull($result['exact_match']);
        $this->assertEquals('The Matrix', $result['exact_match']['title']);
    }
    
    public function testSearchMovieMultipleMatches(): void {
        $result = $this->service->searchMovie('Matrix');
        
        $this->assertEquals('multiple', $result['match_type']);
        $this->assertIsArray($result['partial_matches']);
        $this->assertGreaterThan(1, count($result['partial_matches']));
    }
    
    public function testEnrichMovieData(): void {
        $enrichmentData = $this->service->enrichMovieData(603); // The Matrix
        
        $this->assertArrayHasKey('tmdb_id', $enrichmentData);
        $this->assertArrayHasKey('synopsis', $enrichmentData);
        $this->assertArrayHasKey('poster_url', $enrichmentData);
        $this->assertArrayHasKey('trailer_url', $enrichmentData);
        $this->assertArrayHasKey('obscurity_score', $enrichmentData);
        
        $this->assertEquals(603, $enrichmentData['tmdb_id']);
        $this->assertIsString($enrichmentData['synopsis']);
        $this->assertNotEmpty($enrichmentData['poster_url']);
    }
}
```

#### Step 6.2: Integration Tests
**File**: `Tests/Integration/MoviesTMDBIntegrationTest.php`
```php
<?php
namespace Tests\Integration;

use Tests\Integration\IntegrationTestCase;
use Gravitycar\Models\Movies\Movies;

class MoviesTMDBIntegrationTest extends IntegrationTestCase {
    
    public function testCreateMovieWithTMDBMatch(): void {
        $movie = new Movies();
        
        // Search for exact match
        $searchResult = $movie->searchTMDBMovies('The Matrix');
        $this->assertEquals('exact', $searchResult['match_type']);
        
        // Apply TMDB data
        $tmdbMovie = $searchResult['exact_match'];
        $movie->enrichFromTMDB($tmdbMovie['tmdb_id']);
        
        // Set title and save
        $movie->setFieldValue('name', 'The Matrix');
        $result = $movie->save();
        
        $this->assertTrue($result);
        $this->assertNotNull($movie->getFieldValue('synopsis'));
        $this->assertNotNull($movie->getFieldValue('poster_url'));
        $this->assertNotNull($movie->getFieldValue('trailer_url'));
        $this->assertEquals(603, $movie->getFieldValue('tmdb_id'));
    }
    
    public function testTitleBecomesReadOnlyAfterSave(): void {
        $movie = new Movies();
        $movie->setFieldValue('name', 'Test Movie');
        $movie->save();
        
        $nameField = $movie->getField('name');
        $this->assertTrue($nameField->isReadOnly());
    }
}
```

### Phase 7: Deployment and Schema Update (Day 14)

#### Step 7.1: Final Setup and Verification
**Command**: Run setup script to ensure all changes are applied
```bash
php setup.php
```

**Verification Steps**:
1. Check that new columns exist in movies table:
   ```sql
   DESCRIBE movies;
   ```
2. Verify metadata cache includes new fields
3. Test API endpoints for TMDB integration
4. Verify frontend components render correctly
5. Run full test suite to ensure no regressions

#### Step 7.2: Documentation Updates
Update the following documentation:
- `docs/TMDB_API_Guide.md` (already exists)
- `docs/Fields/VideoField.md` (new)
- `docs/models/Movies.md` (update with new fields)
- API documentation for new endpoints

**Note**: The framework handles all database schema changes automatically through the metadata system and setup.php script. No manual SQL execution is required.

## Risk Assessment and Mitigation

### Technical Risks
1. **TMDB API Rate Limits**: Implement caching and request throttling
2. **Image Loading Performance**: Use lazy loading and thumbnail optimization
3. **Browser Compatibility**: Test video embedding across browsers
4. **Data Validation**: Comprehensive validation for all TMDB data
5. **Schema Changes**: Framework handles schema via metadata + setup.php

### Mitigation Strategies
1. **Graceful Degradation**: System works without TMDB integration if API fails
2. **Caching Strategy**: Cache TMDB results for 24 hours to reduce API calls
3. **Manual Fallback**: Allow users to manually enter data if TMDB fails
4. **Validation**: Sanitize all external data before storage
5. **Framework Integration**: Use metadata-driven approach, no manual SQL

## Success Criteria
1. ✅ Users can create movies with automatic TMDB lookup
2. ✅ Multiple match selection dialog works correctly
3. ✅ Movie data is automatically populated from TMDB
4. ✅ Poster images display correctly with thumbnails in list view
5. ✅ Video trailers embed properly
6. ✅ Movie titles become read-only after creation
7. ✅ System handles TMDB API failures gracefully
8. ✅ Performance remains acceptable with image loading

## Timeline Summary
- **Phase 1**: Metadata updates, FieldBase enhancements, and new field types (2 days)
- **Phase 2**: TMDB integration service (2 days)  
- **Phase 3**: Backend API enhancements (2 days)
- **Phase 4**: Frontend components (3 days)
- **Phase 5**: UI enhancements (2 days)
- **Phase 6**: Testing and documentation (2 days)
- **Phase 7**: Schema deployment via setup.php (1 day)

**Total Estimated Timeline**: 14 days

**Key Framework Integration Points**:
- Database schema managed via metadata + `php setup.php`
- New VideoField follows existing field patterns
- Enhanced FieldBase with setReadOnly() method for dynamic readonly control
- TMDB integration uses ServiceLocator pattern
- React components follow established UI patterns

## Future Enhancements
1. **Bulk TMDB Import**: Import multiple movies from TMDB
2. **Cast and Crew**: Expand to include actors and directors
3. **Genre Management**: Create genre taxonomy linked to TMDB
4. **Advanced Filtering**: Filter by obscurity score, year, genre
5. **Watchlist Features**: User-specific movie lists and ratings
