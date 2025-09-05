# Movies TMDB Refresh Implementation

## Overview
This implementation adds automatic TMDB data refresh functionality to the Movies model in the Gravitycar Framework. The functionality includes both automatic refresh during updates and a manual refresh API endpoint.

## Files Modified

### 1. Movies Model (`src/Models/movies/Movies.php`)
- **Added `update()` method override**: Automatically refreshes TMDB data when a movie is updated if it has a TMDB ID
- **Added `refreshFromTMDB()` method**: Public method to manually refresh TMDB data for a movie
- **Enhanced error handling**: Logs warnings if TMDB refresh fails but doesn't block the update operation

#### Key Features:
- Automatic TMDB refresh on every movie update
- Graceful error handling - update succeeds even if TMDB refresh fails
- Logs TMDB refresh failures for debugging
- Public method for manual TMDB refresh

### 2. TMDB API Controller (`src/Api/TMDBController.php`)
- **Added refresh route**: `POST /movies/{movie_id}/tmdb/refresh`
- **Added `refresh()` method**: API endpoint to manually refresh TMDB data for a specific movie

#### API Endpoint Details:
- **URL**: `POST /movies/{movie_id}/tmdb/refresh`
- **Parameters**: Movie ID in the URL path
- **Response**: Success message with updated movie data or error details
- **Error Handling**: Returns 400 status for errors with descriptive messages

### 3. Cache Management
- Updated API routes cache to include the new refresh endpoint
- Removed duplicate TMDBController file to avoid conflicts

## Usage Examples

### 1. Automatic Refresh During Update
```php
$movie = ServiceLocator::createModel('\\Gravitycar\\Models\\movies\\Movies');
$movie->findById('some-movie-id');
$movie->set('some_field', 'new_value');
$movie->update(); // TMDB data is automatically refreshed if movie has tmdb_id
```

### 2. Manual API Refresh
```bash
curl -X POST http://localhost:8081/movies/0e32547c-be01-4dea-bd90-52c97f44f5d8/tmdb/refresh
```

**Response:**
```json
{
  "success": true,
  "message": "Movie data refreshed from TMDB successfully",
  "data": {
    "movie_id": "0e32547c-be01-4dea-bd90-52c97f44f5d8",
    "tmdb_id": 1726,
    "updated_fields": {
      "synopsis": "After being held captive in an Afghan cave...",
      "poster_url": "https://image.tmdb.org/t/p/w500/78lPtwv72eTNqFW9COBYI0dWDJa.jpg",
      "trailer_url": null,
      "obscurity_score": 80.2196,
      "release_year": 2008
    }
  }
}
```

### 3. Manual Method Call
```php
$movie = ServiceLocator::createModel('\\Gravitycar\\Models\\movies\\Movies');
$movie->findById('some-movie-id');
$movie->refreshFromTMDB(); // Uses existing tmdb_id
// or
$movie->refreshFromTMDB(1726); // Uses specific TMDB ID
$movie->update(); // Save the refreshed data
```

## Updated Fields from TMDB
The refresh functionality updates the following fields:
- `synopsis` - Movie overview/description
- `poster_url` - Movie poster image URL
- `trailer_url` - YouTube trailer URL (if available)
- `obscurity_score` - Calculated based on TMDB popularity
- `release_year` - Year the movie was released

## Error Handling
- **No TMDB ID**: Returns error if movie doesn't have a TMDB ID to refresh from
- **Movie Not Found**: Returns 404-style error if movie ID doesn't exist
- **TMDB API Failures**: Logs warnings but doesn't fail the operation
- **Save Failures**: Returns error if updated movie data cannot be saved

## Technical Implementation Details

### Route Registration
The refresh endpoint is registered through the TMDBController's `registerRoutes()` method:
```php
[
    'method' => 'POST',
    'path' => '/movies/?/tmdb/refresh',
    'apiClass' => '\\Gravitycar\\Api\\TMDBController',
    'apiMethod' => 'refresh',
    'parameterNames' => ['movieId']
]
```

### Service Integration
- Uses existing `MovieTMDBIntegrationService` for TMDB API calls
- Uses `ServiceLocator` for dependency injection
- Uses framework's standard logging for error tracking
- Follows framework patterns for error handling and response formatting

## Testing
The implementation has been tested with:
- Iron Man movie (TMDB ID: 1726) - ✅ Successful refresh
- API endpoint responds correctly with updated data
- Automatic refresh during movie updates works as expected
- Error handling works for missing movies and missing TMDB IDs

## Future Enhancements
- Add UI button in the frontend for manual refresh
- Add batch refresh capability for multiple movies
- Add option to refresh only specific fields
- Add configuration to enable/disable automatic refresh on updates
