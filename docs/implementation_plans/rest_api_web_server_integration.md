# REST API Web Server Integration Implementation Plan

## Feature Overview
This plan outlines the implementation of a web server integration for the Gravitycar Framework's Router system. The goal is to create a REST API endpoint that can receive web traffic, bootstrap the Gravitycar application, and route requests through the existing Router class to return JSON responses.

The implementation will support REST API requests with the pattern:
- `GET <domain>/Users` → List all users
- `PUT <domain>/Movies/abc-123` → Update movie with ID abc-123
- `POST <domain>/Books` → Create a new book
- `DELETE <domain>/Articles/xyz-789` → Delete article with ID xyz-789

## Requirements

### Functional Requirements
1. **Single Entry Point**: Create `rest_api.php` in the root directory as the main REST API endpoint
2. **URL Rewriting**: Configure Apache mod_rewrite to redirect `/Users`, `/Movies/*` patterns to `rest_api.php`
3. **Route Preservation**: Ensure the original route is available to the PHP process
4. **Framework Bootstrap**: Initialize Gravitycar application with full bootstrap
5. **Router Integration**: Delegate request routing to the existing Router class
6. **JSON Response**: Return proper JSON responses with appropriate HTTP status codes
7. **Error Handling**: Graceful error handling with proper HTTP error responses
8. **HTTP Method Support**: Support GET, POST, PUT, DELETE, PATCH methods
9. **Query Parameters**: Preserve and pass through URL query parameters
10. **Content Type Handling**: Process JSON request bodies for POST/PUT/PATCH requests

### Non-Functional Requirements
1. **Performance**: Minimal bootstrap overhead for API requests
2. **Security**: Basic input validation and sanitization
3. **Logging**: Request/response logging for debugging
4. **CORS Support**: Optional CORS headers for browser-based clients
5. **Content Negotiation**: Support for JSON content type
6. **Error Response Consistency**: Standardized error response format

## Design

### Architecture Overview
```
Web Server (Apache) 
    ↓ (mod_rewrite)
rest_api.php
    ↓ (bootstrap)
Gravitycar Framework
    ↓ (routing)
Router Class
    ↓ (controller execution)
ModelBaseAPIController
    ↓ (JSON response)
Client
```

### Component Interactions
1. **Apache mod_rewrite** captures `/#<route>` patterns and redirects to `rest_api.php`
2. **rest_api.php** extracts the original route, bootstraps Gravitycar, and calls the Router
3. **Router** processes the request using existing `route()` method
4. **ModelBaseAPIController** handles the business logic and returns structured data
5. **rest_api.php** formats the response as JSON with appropriate HTTP headers

### File Structure
```
/mnt/g/projects/gravitycar_no_code/
├── rest_api.php                    # Main REST API entry point (lightweight)
├── .htaccess                       # Apache rewrite rules
├── src/Api/RestApiHandler.php      # REST API handler class
└── (uses existing Gravitycar bootstrap)
```

## RestApiHandler Class

### Overview
The `RestApiHandler` class (located in `src/Api/RestApiHandler.php`) is the core component responsible for processing REST API requests. It provides a clean separation between the web entry point (`rest_api.php`) and the API processing logic.

### Class Properties

#### Private Properties
- `?Logger $logger` - Application logger instance for request/response logging
- `?Router $router` - API router instance for request routing and delegation
- `?Gravitycar $app` - Main Gravitycar application instance for framework access

### Class Methods

#### Public Methods

**`handleRequest(): void`**
- Main entry point for processing REST API requests
- Orchestrates the complete request lifecycle:
  1. Bootstrap the Gravitycar application
  2. Extract request information from HTTP environment
  3. Route the request through the Router class
  4. Send ReactJS-friendly JSON response
- Provides comprehensive error handling for different exception types

#### Private Methods

**`bootstrapApplication(): void`**
- Initializes the complete Gravitycar framework
- Sets up core services (Config, Logger, Database)
- Initializes metadata engine for model introspection
- Creates Router instance for API request handling
- Throws `GCException` if bootstrap fails

**`extractRequestInfo(): array`**
- Parses incoming HTTP requests to extract:
  - HTTP method (GET, POST, PUT, DELETE, PATCH)
  - Request path from URL
  - Query parameters from URL
  - Request body for POST/PUT/PATCH (JSON or form data)
- Returns array with keys: `method`, `path`, `additionalParams`, `originalPath`
- Throws `GCException` for invalid API paths

**`routeRequest(array $requestInfo): array`**
- Delegates request to the Router class
- Handles route matching and parameter extraction
- Manages controller instantiation and method execution
- Processes model operations through ModelBaseAPIController
- Returns formatted result data

**`sendJsonResponse(array $result): void`**
- Formats and sends ReactJS-friendly JSON responses
- Sets appropriate HTTP headers (CORS, content-type)
- Handles OPTIONS preflight requests
- Adds metadata (count for arrays, timestamps)
- Uses ISO 8601 timestamp format

**`handleError(Throwable $e, string $errorType): void`**
- Provides consistent error handling and response formatting
- Maps exceptions to appropriate HTTP status codes
- Logs detailed error information for debugging
- Returns ReactJS-friendly error responses
- Includes context information when available

### Response Format

#### Success Response Structure
```json
{
  "success": true,
  "status": 200,
  "data": [...],
  "count": 5,
  "timestamp": "2025-08-13T19:01:19+00:00"
}
```

#### Error Response Structure
```json
{
  "success": false,
  "status": 400,
  "error": {
    "message": "Error description",
    "type": "Error Type",
    "code": 0,
    "context": {...}
  },
  "timestamp": "2025-08-13T19:01:19+00:00"
}
```

### Integration Points

#### Framework Integration
- Uses `Gravitycar` class for application bootstrap
- Integrates with `ServiceLocator` for dependency access
- Delegates routing to existing `Router` class
- Leverages `ModelBaseAPIController` for data operations

#### HTTP Integration
- Processes `$_SERVER` variables for request information
- Handles `$_GET`, `$_POST`, and `php://input` for parameters
- Sets appropriate HTTP headers for browser compatibility
- Provides CORS support for frontend integration

### Error Handling Strategy

#### Exception Mapping
- `GCException` → HTTP 400 (Bad Request)
- `Exception` → HTTP 500 (Internal Server Error)
- `Throwable` → HTTP 500 (Fatal Error)

#### Logging Strategy
- Request/response logging through Gravitycar Logger
- Detailed error logging with stack traces
- Fallback to `error_log()` if logger unavailable

## Implementation Steps

### Step 1: Create Apache Rewrite Rules
Create `.htaccess` file in the root directory with mod_rewrite rules to:
- Capture requests matching `/Users`, `/Movies/*` etc. patterns
- Redirect to `rest_api.php` while preserving the original route
- Set environment variables for the original route and method
- Handle both simple routes (`/Users`) and complex routes (`/Users/123`)

### Step 2: Create REST API Entry Point
Create `rest_api.php` with the following functionality:
- Simple entry point that instantiates `RestApiHandler`
- Delegates all processing to the handler class
- Minimal code for easy maintenance

Create `src/Api/RestApiHandler.php` with the following functionality:
- Extract the original route from environment variables or request parameters
- Parse HTTP method and request body
- Bootstrap Gravitycar framework with minimal services
- Create Request object from parsed data
- Call Router's `route()` method
- Format response as JSON with appropriate headers
- Handle errors gracefully with proper HTTP status codes

### Step 3: Bootstrap Integration
Integrate full Gravitycar bootstrap in `rest_api.php`:
- Use complete Gravitycar bootstrap process for full functionality
- Ensure all services are available (Database, Metadata, Logging, etc.)
- Maintain consistency with existing setup script bootstrap
- Handle bootstrap errors appropriately

### Step 4: Enhance Router for Web Context
Modify the Router class to:
- Support direct route invocation (already implemented)
- Ensure proper HTTP status code handling
- Validate that responses are properly formatted

### Step 5: Response Formatting
Implement ReactJS-friendly response formatting:
- Success responses: `{"success": true, "status": 200, "data": {...}, "count": N, "timestamp": "..."}`
- Error responses: `{"success": false, "status": 500, "error": {"message": "...", "code": "..."}, "timestamp": "..."}`
- Generic HTTP status codes for now (specific mapping out of scope)
- Consistent JSON headers (Content-Type: application/json)

### Step 6: Testing and Validation
Create comprehensive tests for:
- URL rewriting functionality
- REST API entry point
- Various HTTP methods and routes
- Error handling scenarios
- Performance benchmarks

## Testing Strategy

### Unit Tests
- `RestApiBootstrapTest.php`: Test lightweight bootstrap functionality
- `RestApiEntryPointTest.php`: Test main entry point logic
- Router integration tests (already exist)

### Integration Tests
- End-to-end API request tests
- Apache rewrite rule validation
- Error handling scenarios
- HTTP method and status code validation

### Manual Testing
- Browser-based testing of various routes
- cURL command testing for different HTTP methods
- Performance testing with multiple concurrent requests
- Error scenario validation

## Documentation

### User Guide
- REST API endpoint documentation
- Supported routes and methods
- Request/response format examples
- Error code reference

### Developer Guide
- Apache configuration requirements
- Deployment instructions
- Troubleshooting guide
- Performance optimization tips

### API Documentation
- Route documentation (can leverage existing ModelBaseAPIController docs)
- HTTP method specifications
- Query parameter documentation
- Response format specifications

## Risks and Mitigations

### Risk 1: Apache Configuration Dependencies
**Risk**: Solution requires Apache with mod_rewrite enabled
**Mitigation**: 
- Provide alternative solutions for Nginx
- Document Apache configuration requirements clearly
- Create fallback for environments without mod_rewrite

### Risk 2: Performance Impact
**Risk**: Full framework bootstrap may be too slow for API requests
**Mitigation**:
- Create lightweight bootstrap option
- Implement caching for metadata and routes
- Profile and optimize critical paths

### Risk 3: Security Vulnerabilities
**Risk**: Direct web access to PHP files could expose security issues
**Mitigation**:
- Implement input validation and sanitization
- Add rate limiting capabilities
- Follow security best practices for web APIs

### Risk 4: Backward Compatibility
**Risk**: Changes might break existing functionality
**Mitigation**:
- Keep existing Router interface unchanged
- Maintain existing test coverage
- Implement feature flags if needed

### Risk 5: URL Pattern Conflicts
**Risk**: API URL patterns might conflict with frontend routing
**Mitigation**:
- Use clean REST patterns (`/Users`, `/Movies/*`) that don't conflict with ReactJS routing
- Document URL patterns clearly
- API routes use different namespace than frontend routes

## Implementation Dependencies

### Framework Components
- Existing Router class (✅ Complete)
- ModelBaseAPIController (✅ Complete)
- Request class (✅ Complete)
- APIRouteRegistry (✅ Complete)
- Gravitycar bootstrap system (✅ Complete)

### External Dependencies
- Apache web server with mod_rewrite
- PHP 7.4+ with JSON extension
- Existing Gravitycar configuration

### Configuration Requirements
- Database connection (for data operations)
- Logging configuration
- Error handling configuration
- Optional CORS configuration

## Clarification Questions Checklist

Before proceeding with implementation, here are the questions to address:

- [x] **1. URL Pattern**: ✅ **RESOLVED** - Use `/Users` pattern (no hash). This works perfectly with ReactJS as it avoids hash routing conflicts and uses clean REST URLs.

- [x] **2. Apache Configuration**: ✅ **RESOLVED** - Apache only. We will require Apache web server with mod_rewrite enabled. No need to support other web servers at this time.

- [x] **3. Bootstrap Optimization**: ✅ **RESOLVED** - Use full Gravitycar bootstrap. This ensures all services and functionality are available for API requests.

- [x] **4. Response Format**: ✅ **RESOLVED** - Enhanced ReactJS-friendly format that wraps current responses with success indicators and standardized error handling.

- [x] **5. Error Handling**: ✅ **RESOLVED** - API errors return JSON responses consistent with ReactJS-friendly format. Specific HTTP status code mapping (404, etc.) is out of scope - focus is on getting framework responding to live traffic.

## Success Criteria

### Functional Success
- [ ] REST API responds to `GET /Users` with JSON user list
- [ ] REST API handles `POST /Users` to create new users
- [ ] REST API processes `PUT /Users/123` to update specific users
- [ ] REST API responds to `DELETE /Users/123` to delete users
- [ ] Error responses return consistent JSON format
- [ ] Query parameters are properly processed

### Performance Success
- [ ] API responses under 200ms for simple GET requests
- [ ] Memory usage under 32MB for typical requests
- [ ] No significant impact on existing functionality

### Quality Success
- [ ] 95%+ test coverage for new components
- [ ] All existing tests continue to pass
- [ ] Code follows framework coding standards
- [ ] Documentation is complete and accurate

## Future Enhancements

### Phase 2 Considerations
- Authentication and authorization middleware
- Rate limiting and throttling
- API versioning support
- Swagger/OpenAPI documentation generation
- Caching layer for read operations
- WebSocket support for real-time features

### Scalability Considerations
- Load balancer compatibility
- CDN integration for static responses
- Database connection pooling
- Response caching strategies
- Horizontal scaling support
