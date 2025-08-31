# TMDB API Service Guide

## Overview

The `TMDBApiService` provides a comprehensive interface for interacting with The Movie Database (TMDB) API. This service enables searching for movies and retrieving detailed movie information with consistent data formatting and error handling.

## Features

- **Movie Search**: Search for movies using partial titles
- **Movie Details**: Retrieve comprehensive movie information
- **Obscurity Scoring**: Calculate relative obscurity scores (1-5 scale)
- **Image URLs**: Generate properly formatted image URLs for posters and backdrops
- **Trailer Links**: Extract YouTube trailer URLs
- **Data Normalization**: Consistent output format across all methods

## Configuration

### Environment Variables

Add the following to your `.env` file:

```env
TMDB_API_KEY=your_api_key_here
TMDB_API_READ_ACCESS_TOKEN=your_read_access_token_here
```

### Config.php

The service automatically reads configuration from `config.php`:

```php
'tmdb' => [
    'api_key' => $_ENV['TMDB_API_KEY'] ?? null,
    'read_access_token' => $_ENV['TMDB_API_READ_ACCESS_TOKEN'] ?? null,
    'base_url' => 'https://api.themoviedb.org/3',
    'image_base_url' => 'https://image.tmdb.org/t/p'
],
```

## Usage

### Basic Initialization

```php
use Gravitycar\Services\TMDBApiService;
use Gravitycar\Core\ServiceLocator;

// Initialize service locator
ServiceLocator::initialize();

// Create TMDB service instance
$tmdbService = new TMDBApiService();
```

### Movie Search

Search for movies using partial titles:

```php
try {
    $results = $tmdbService->searchMovies('Matrix');
    
    foreach ($results as $movie) {
        echo "Title: {$movie['title']} ({$movie['release_year']})\n";
        echo "TMDB ID: {$movie['tmdb_id']}\n";
        echo "Obscurity Score: {$movie['obscurity_score']}/5\n";
        echo "Poster: {$movie['poster_url']}\n";
    }
} catch (GCException $e) {
    echo "Error: " . $e->getMessage();
}
```

### Movie Details

Get comprehensive information about a specific movie:

```php
try {
    $details = $tmdbService->getMovieDetails(603); // The Matrix
    
    echo "Title: {$details['title']}\n";
    echo "Year: {$details['release_year']}\n";
    echo "Overview: {$details['overview']}\n";
    echo "Trailer: {$details['trailer_url']}\n";
    echo "Obscurity: {$details['obscurity_score']}/5\n";
    echo "Genres: " . implode(', ', $details['genres']) . "\n";
} catch (GCException $e) {
    echo "Error: " . $e->getMessage();
}
```

## Data Structure

### Search Results

Each search result contains:

```php
[
    'tmdb_id' => 603,                           // TMDB movie ID
    'title' => 'The Matrix',                    // Movie title
    'release_year' => 1999,                     // Release year
    'poster_url' => 'https://image.tmdb...',    // Poster image URL
    'overview' => 'Set in the 22nd century...', // Truncated plot synopsis
    'popularity' => 18.5118,                    // TMDB popularity score
    'obscurity_score' => 2,                     // Calculated obscurity (1-5)
    'vote_average' => 8.231,                    // Average rating
    'vote_count' => 26725                       // Number of votes
]
```

### Movie Details

Detailed movie information includes:

```php
[
    'tmdb_id' => 603,
    'title' => 'The Matrix',
    'release_year' => 1999,
    'overview' => 'Set in the 22nd century...',
    'poster_url' => 'https://image.tmdb.org/t/p/w500/...',
    'backdrop_url' => 'https://image.tmdb.org/t/p/w1280/...',
    'trailer_url' => 'https://www.youtube.com/watch?v=...',
    'popularity' => 18.5118,
    'obscurity_score' => 2,
    'vote_average' => 8.231,
    'vote_count' => 26725,
    'runtime' => 136,                           // Runtime in minutes
    'genres' => ['Action', 'Science Fiction'],  // Genre names
    'imdb_id' => 'tt0133093',                  // IMDB ID
    'tagline' => 'Believe the unbelievable.',  // Movie tagline
    'release_date' => '1999-03-31',            // Full release date
    'budget' => 63000000,                      // Production budget
    'revenue' => 463517383                     // Box office revenue
]
```

## Obscurity Scoring

The service calculates obscurity scores based on TMDB popularity:

- **1** (Very well known): Popularity ≥ 50
- **2** (Well known): Popularity 20-49
- **3** (Moderately known): Popularity 10-19
- **4** (Somewhat obscure): Popularity 5-9
- **5** (Very obscure): Popularity < 5

## Image Handling

### Available Poster Sizes
- `w92`, `w154`, `w185`, `w342`, `w500`, `w780`, `original`

### Available Backdrop Sizes
- `w300`, `w780`, `w1280`, `original`

### Usage

```php
// Get available sizes
$posterSizes = $tmdbService->getPosterSizes();
$backdropSizes = $tmdbService->getBackdropSizes();

// Images are automatically formatted to w500 for posters and w1280 for backdrops
```

## Trailer Detection

The service prioritizes trailers in this order:
1. Official YouTube trailers
2. Any YouTube trailers
3. Any YouTube videos

## Error Handling

The service throws `GCException` for:
- Missing API keys
- Empty search queries
- API connection failures
- Invalid JSON responses
- TMDB API errors

## Best Practices

1. **Error Handling**: Always wrap API calls in try-catch blocks
2. **Caching**: Consider implementing caching for frequently accessed data
3. **Rate Limiting**: Be mindful of TMDB API rate limits
4. **Image Optimization**: Choose appropriate image sizes for your use case
5. **Logging**: The service automatically logs API requests and errors

## Testing

Run the included unit tests:

```bash
./vendor/bin/phpunit Tests/Unit/TMDBApiServiceTest.php
```

Run the demo script:

```bash
php tmdb_demo.php
```

## API Limitations

- **Rate Limiting**: TMDB API has rate limits (40 requests per 10 seconds)
- **Adult Content**: Adult content is filtered out by default
- **Language**: Defaults to English; internationalization not implemented
- **Regions**: No region-specific filtering implemented

## Dependencies

- **TMDB API Key**: Required for all operations
- **ServiceLocator**: For configuration and logging
- **cURL/stream_context**: For HTTP requests
- **JSON extension**: For response parsing

## Security Considerations

- API keys are loaded from environment variables
- No sensitive data is logged
- User-agent string identifies the application
- Timeout protection prevents hanging requests

## Future Enhancements

Potential improvements:
- Caching layer implementation
- Multi-language support
- Region-specific content
- Advanced search filters
- Bulk operations
- WebP image format support
