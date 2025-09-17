# Unit Test Refactoring Plan - Developer D: External Services & Integration

## Overview
This plan focuses on fixing unit tests for external service integrations and miscellaneous tests that don't fit into the core infrastructure, API controller, or model factory categories.

## Assigned Test Categories

### 1. TMDB API Service Tests (Medium Priority)
**Files to Modify:** `Tests/Unit/TMDBApiServiceTest.php`
**Issue:** Tests are making actual HTTP calls to TMDB API instead of using mocks
**Failing Tests:** 2 tests (tests 96-97 from error output)

#### Current Issue
**Error:** `Gravitycar\Exceptions\GCException: Failed to connect to TMDB API`

The tests are attempting real API calls instead of using mocked HTTP responses.

#### Fix Strategy
1. Mock HTTP client instead of making real API calls
2. Create fake API responses for test scenarios
3. Test error handling with mocked failures

**Example Fix Pattern:**
```php
protected function setUp(): void {
    $this->config = $this->createMock(Config::class);
    $this->logger = $this->createMock(Logger::class);
    
    // Mock TMDB API key configuration
    $this->config->method('get')->willReturnMap([
        ['open_imdb_api_key', null, 'test_api_key'],
        ['tmdb.base_url', null, 'https://api.themoviedb.org/3/']
    ]);
    
    $this->tmdbService = new TMDBApiService($this->config, $this->logger);
    
    // Mock HTTP client responses
    $this->setupHttpMocks();
}

private function setupHttpMocks(): void {
    // Create mock HTTP responses for different scenarios
    $searchResponse = [
        'results' => [
            [
                'id' => 12345,
                'title' => 'Test Movie',
                'release_date' => '2023-01-01',
                'overview' => 'Test movie overview'
            ]
        ]
    ];
    
    // Mock successful search response
    $this->mockHttpResponse(200, $searchResponse);
}

private function mockHttpResponse(int $statusCode, array $data): void {
    // Implementation depends on how TMDBApiService makes HTTP calls
    // May need to mock curl functions or HTTP client library
}
```

#### Specific Test Fixes Needed

**testSearchMoviesWithValidQuery:**
- Mock HTTP response for movie search
- Verify API request parameters
- Test response parsing

**testGetMovieDetailsWithValidId:**
- Mock HTTP response for movie details
- Test ID validation
- Test error handling for invalid IDs

### 2. Service Integration Tests (Low Priority)
**Files to Monitor:** Tests that may be affected by external service changes

#### Strategy
1. Identify any other tests that make external HTTP calls
2. Ensure all external services are properly mocked
3. Create integration test categories for actual API testing (separate from unit tests)

### 3. Miscellaneous Cleanup (Low Priority)
**Files to Review:** Any remaining failing tests not covered by other developers

#### Tasks
1. Monitor for any additional test failures after other developers complete their work
2. Fix any edge cases or outlier tests
3. Ensure test suite reaches 100% pass rate

## Implementation Order
1. **TMDB API Service Tests** - Critical for movie functionality
2. **Review Integration Test Architecture** - Prevent future external dependency issues
3. **Final Cleanup** - Address any remaining issues

## Testing Strategy

### TMDB API Tests
```bash
# Test TMDB service specifically
vendor/bin/phpunit Tests/Unit/TMDBApiServiceTest.php

# Test all services
vendor/bin/phpunit Tests/Unit/ --filter "Service"
```

### Integration Testing Strategy
Create a clear separation between unit tests (no external calls) and integration tests:

```bash
# Unit tests (all mocked)
vendor/bin/phpunit Tests/Unit/

# Integration tests (real API calls) - separate suite
vendor/bin/phpunit Tests/Integration/
```

## HTTP Mocking Patterns

### Option 1: Mock HTTP Client Library
If TMDBApiService uses a specific HTTP client:

```php
protected function setUp(): void {
    $this->httpClient = $this->createMock(HttpClientInterface::class);
    $this->config = $this->createMock(Config::class);
    $this->logger = $this->createMock(Logger::class);
    
    $this->tmdbService = new TMDBApiService($this->config, $this->logger);
    
    // Inject mocked HTTP client
    $reflection = new ReflectionClass($this->tmdbService);
    $property = $reflection->getProperty('httpClient');
    $property->setAccessible(true);
    $property->setValue($this->tmdbService, $this->httpClient);
}
```

### Option 2: Mock curl Functions
If TMDBApiService uses curl directly:

```php
// May need to use runkit extension or similar to mock curl_* functions
// Or refactor TMDBApiService to use dependency injection for HTTP client
```

### Option 3: Refactor for Testability
**Recommended Long-term Solution:**

```php
// Update TMDBApiService constructor to accept HTTP client
public function __construct(
    Config $config, 
    Logger $logger,
    ?HttpClientInterface $httpClient = null
) {
    $this->config = $config;
    $this->logger = $logger;
    $this->httpClient = $httpClient ?? new HttpClient();
}
```

## External Service Test Data

### Sample TMDB API Responses
Create realistic test data:

```php
class TMDBTestData {
    public static function getMovieSearchResponse(): array {
        return [
            'page' => 1,
            'results' => [
                [
                    'adult' => false,
                    'backdrop_path' => '/path/to/backdrop.jpg',
                    'genre_ids' => [28, 12, 16],
                    'id' => 123456,
                    'original_language' => 'en',
                    'original_title' => 'Test Movie',
                    'overview' => 'A test movie for unit testing.',
                    'popularity' => 1234.567,
                    'poster_path' => '/path/to/poster.jpg',
                    'release_date' => '2023-01-01',
                    'title' => 'Test Movie',
                    'video' => false,
                    'vote_average' => 7.5,
                    'vote_count' => 1000
                ]
            ],
            'total_pages' => 1,
            'total_results' => 1
        ];
    }
    
    public static function getMovieDetailsResponse(): array {
        return [
            'adult' => false,
            'backdrop_path' => '/path/to/backdrop.jpg',
            'budget' => 10000000,
            'genres' => [
                ['id' => 28, 'name' => 'Action'],
                ['id' => 12, 'name' => 'Adventure']
            ],
            'id' => 123456,
            'original_title' => 'Test Movie',
            'overview' => 'A test movie for unit testing.',
            'poster_path' => '/path/to/poster.jpg',
            'release_date' => '2023-01-01',
            'revenue' => 50000000,
            'runtime' => 120,
            'title' => 'Test Movie',
            'vote_average' => 7.5,
            'vote_count' => 1000
        ];
    }
    
    public static function getErrorResponse(): array {
        return [
            'success' => false,
            'status_code' => 34,
            'status_message' => 'The resource you requested could not be found.'
        ];
    }
}
```

## Error Handling Test Cases

### Network Error Scenarios
```php
public function testHandleNetworkTimeout(): void {
    $this->httpClient->method('get')
        ->willThrowException(new \Exception('Connection timeout'));
    
    $this->expectException(GCException::class);
    $this->expectExceptionMessage('Failed to connect to TMDB API');
    
    $this->tmdbService->searchMovies('test query');
}

public function testHandleInvalidApiKey(): void {
    $this->httpClient->method('get')
        ->willReturn($this->createResponse(401, TMDBTestData::getErrorResponse()));
    
    $this->expectException(GCException::class);
    $this->expectExceptionMessage('Invalid API key');
    
    $this->tmdbService->searchMovies('test query');
}
```

## Expected Impact
- **Fixes 2+ failing tests** (TMDB API service tests)
- **Establishes proper external service testing patterns**
- **Prevents future external dependency issues in unit tests**
- **Creates foundation for proper integration testing**

## Dependencies
- **No dependencies** on other developer work
- **Can be completed independently**
- **May inform best practices** for other external service tests

## Coordination Notes
- **Share HTTP mocking patterns** with other developers
- **Document external service testing standards** for future development
- **Consider refactoring external services** for better testability

## Estimated Time
- TMDB API Service Tests: 3-4 hours (HTTP mocking setup, test data creation)
- Integration Test Architecture Review: 1-2 hours
- Documentation and Standards: 1-2 hours
- Final Cleanup: 1-2 hours (contingency for remaining issues)
- **Total: 6-10 hours**

## Success Criteria
- All TMDB API service tests pass (2 tests)
- No external HTTP calls in unit test suite
- Clear separation between unit tests and integration tests
- Documented patterns for testing external services
- All unit tests pass (1108/1108)

## Long-term Recommendations
1. **Refactor external services** to use dependency injection for HTTP clients
2. **Create integration test suite** for actual API testing
3. **Implement circuit breaker patterns** for external service failures
4. **Add monitoring** for external service availability
5. **Cache external API responses** in development/testing environments