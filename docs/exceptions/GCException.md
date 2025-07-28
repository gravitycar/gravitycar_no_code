# GCException Class

## Purpose
Provides the base exception class for all Gravitycar framework-specific exceptions, ensuring consistent error handling throughout the application.

## Location
- **Base Class**: `src/exceptions/GCException.php`
- **Exception Implementations**: `src/exceptions/` directory

## Core Principles
- All framework exceptions should extend the base Exception class
- All exceptions should be logged using the Monolog library
- Every entry point in the framework should handle exceptions and return meaningful error responses
- Provide meaningful error messages for debugging and user feedback

## Exception Types
Define custom exceptions for specific error scenarios:
- Database connection errors
- Metadata validation errors
- Field validation errors
- Authentication/authorization errors
- Configuration errors
- Schema generation errors

## Implementation Requirements
- **Logging Integration**: All exceptions must be logged with appropriate log levels
- **Error Context**: Include relevant context information (user ID, model name, field name, etc.)
- **User-Friendly Messages**: Provide both technical details for developers and user-friendly messages for end users
- **Error Codes**: Use consistent error codes for programmatic error handling

## Key Features
- **Automatic Logging**: Exceptions are automatically logged when thrown
- **Context Preservation**: Maintains error context for debugging
- **Graceful Degradation**: Allows application to continue functioning when possible
- **API Error Responses**: Formats exceptions for consistent API error responses

## Exception Categories
- **ValidationException**: Field and model validation errors
- **DatabaseException**: Database connection and query errors
- **MetadataException**: Metadata loading and parsing errors
- **AuthenticationException**: User authentication and authorization errors
- **ConfigurationException**: Configuration file and setup errors

## Error Response Format
Exceptions should generate consistent error responses:
- HTTP status code
- Error message
- Error code
- Context information (when appropriate)
- Timestamp
- Request ID (for tracking)

## Implementation Notes
- Use appropriate log levels (ERROR, WARNING, INFO) based on exception severity
- Include stack traces in development environments
- Sanitize sensitive information in production error messages
- Provide recovery suggestions when possible
