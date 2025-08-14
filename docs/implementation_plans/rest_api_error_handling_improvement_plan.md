# REST API Error Handling Improvement Implementation Plan

## 1. Feature Overview

The current Gravitycar Framework has only one exception class (`GCException`) for all error handling. This limitation makes it difficult to provide appropriate HTTP response codes (404, 412, 422, etc.) for different types of API errors. This feature will expand the exception handling system to provide precise## 11. Future Enhancementses and improve the REST API's compliance with HTTP standards.

The goal is to create a comprehensive exception hierarchy that enables API controllers to throw specific exceptions that automatically map to the correct HTTP status codes, providing better error responses for frontend applications and API consumers.

## 2. Requirements

### 2.1 Functional Requirements

**FR1: HTTP Status Code Mapping**
- Support for all relevant HTTP error status codes (400-599)
- Automatic mapping from exception types to HTTP status codes
- Preserve existing GCException functionality for backward compatibility

**FR2: API-Specific Exception Classes**
- Create API-specific exception classes that inherit from GCException
- Exceptions should only be thrown from API controller classes
- Support for validation errors, not found errors, conflict errors, etc.

**FR3: Enhanced Error Context**
- Maintain existing context array functionality from GCException
- Add HTTP status code information to error responses
- Support for error details specific to HTTP API scenarios
- **Validation Error Aggregation**: Utilize existing ModelBase->getValidationErrors() method to collect all field validation errors into a single response for UnprocessableEntityException (422 status)

**FR4: RestApiHandler Integration**
- Update RestApiHandler to recognize new exception types
- Proper HTTP status code setting based on exception type
- Consistent JSON error response format

**FR5: Backward Compatibility**
- Existing GCException usage should continue to work
- No breaking changes to existing API controller interfaces
- Gradual migration path for existing controllers

### 2.2 Non-Functional Requirements

**NFR1: Performance**
- Exception handling should not add significant overhead
- Logging performance should remain the same

**NFR2: Maintainability**
- Clear exception hierarchy and naming conventions
- Easy to add new HTTP status codes in the future
- Comprehensive documentation and examples

**NFR3: Testing**
- Full unit test coverage for new exception classes
- Integration tests for API error handling scenarios
- Test coverage for HTTP status code mapping

## 3. Design

### 3.1 Architecture Overview

We will implement **Approach 1: Multiple API Exception Classes** for the following reasons:

1. **Type Safety**: Each exception type is explicitly defined
2. **Semantic Clarity**: Exception names clearly indicate the error type
3. **IDE Support**: Better autocomplete and type hints
4. **Extensibility**: Easy to add new exception types with specific behaviors
5. **Documentation**: Self-documenting code through exception names

### 3.2 Exception Hierarchy

```
Exception
└── GCException (existing - base framework exception)
    └── APIException (new - base API exception)
        ├── ClientErrorException (4xx errors)
        │   ├── BadRequestException (400)
        │   ├── UnauthorizedException (401)
        │   ├── ForbiddenException (403)
        │   ├── NotFoundException (404)
        │   ├── MethodNotAllowedException (405)
        │   ├── ConflictException (409)
        │   ├── PreconditionFailedException (412)
        │   ├── UnprocessableEntityException (422)
        │   └── TooManyRequestsException (429)
        └── ServerErrorException (5xx errors)
            ├── InternalServerErrorException (500)
            ├── NotImplementedException (501)
            ├── BadGatewayException (502)
            └── ServiceUnavailableException (503)
```

### 3.3 Class Design

#### Base APIException Class
```php
abstract class APIException extends GCException
{
    protected int $httpStatusCode;
    
    public function __construct(
        string $message, 
        array $context = [], 
        ?Exception $previous = null
    );
    
    public function getHttpStatusCode(): int;
    abstract public function getDefaultMessage(): string;
}
```

#### Specific Exception Classes
Each specific exception class will:
- Define its HTTP status code
- Provide a default error message
- Allow custom messages and context
- Inherit logging functionality from GCException

### 3.4 RestApiHandler Integration

The `RestApiHandler::handleError()` method will be updated to:
1. Check if the exception is an instance of `APIException`
2. Use the exception's HTTP status code if available
3. Fall back to existing logic for non-API exceptions
4. Include HTTP status code in error response

### 3.5 Usage Patterns

#### In API Controllers
```php
// Instead of:
throw new GCException('User not found', ['id' => $id]);

// Use:
throw new NotFoundException('User not found', ['id' => $id]);

// For validation errors with field-level details:
$validationErrors = $model->getValidationErrors();
if (!empty($validationErrors)) {
    throw new UnprocessableEntityException(
        'Validation failed', 
        ['validation_errors' => $validationErrors]
    );
}
```

#### Error Response Format
```json
{
    "success": false,
    "status": 422,
    "error": {
        "message": "Validation failed",
        "type": "Unprocessable Entity",
        "code": 422,
        "context": {
            "validation_errors": {
                "email": ["Email format is invalid"],
                "username": ["Username must be at least 3 characters", "Username contains invalid characters"]
            }
        }
    },
    "timestamp": "2025-08-13T10:30:00+00:00"
}
```

## 4. Implementation Steps

### 4.1 Phase 1: Core Exception Classes (Week 1)

**Step 1.1: Create Base APIException Class**
- Create `src/Exceptions/APIException.php`
- Implement abstract base class with HTTP status code support
- Add getHttpStatusCode() and abstract getDefaultMessage() methods
- Ensure proper inheritance from GCException

**Step 1.2: Create Client Error Exception Classes**
- Create `src/Exceptions/ClientErrorException.php` (base for 4xx)
- Create specific classes: BadRequestException, NotFoundException, etc.
- Each class defines its HTTP status code and default message
- Implement constructor with proper parameter handling

**Step 1.3: Create Server Error Exception Classes**
- Create `src/Exceptions/ServerErrorException.php` (base for 5xx)
- Create specific classes: InternalServerErrorException, etc.
- Follow same pattern as client error exceptions

**Step 1.4: Critical Bug Fix for Field Validation**
- Fix FieldBase->setValue() method to validate BEFORE setting the value
- Ensure invalid values are not stored when validation fails
- Maintain backward compatibility for setValueFromTrustedSource() method
- Update field validation to properly integrate with error aggregation

### 4.2 Phase 2: RestApiHandler Integration (Week 1)

**Step 2.1: Update Autoloading**
- Ensure all new exception classes are properly autoloaded
- Verify namespace structure follows framework conventions

**Step 2.2: Update RestApiHandler Error Handling**
- Modify `RestApiHandler::handleError()` method
- Add logic to detect APIException instances
- Extract HTTP status code from exception
- Update error response structure to include proper status codes

**Step 2.3: Maintain Backward Compatibility**
- Ensure existing GCException handling still works
- Default to 500 status code for non-API exceptions
- Preserve existing error response format structure

### 4.3 Phase 3: ModelBaseAPIController Integration (Week 2)

**Step 3.1: Update Validation Error Handling**
- Replace GCException with appropriate API exceptions in validation methods
- Use BadRequestException for invalid parameters
- Use UnprocessableEntityException for validation failures with aggregated field errors
- **Critical Bug Fix**: Fix FieldBase->setValue() method to only set values AFTER validation passes
- Utilize existing ModelBase->getValidationErrors() method to aggregate all field validation errors for 422 responses

**Step 3.2: Update CRUD Operation Error Handling**
- Use NotFoundException for missing records
- Use ConflictException for constraint violations
- Use InternalServerErrorException for database errors

**Step 3.3: Update Relationship Operation Error Handling**
- Use appropriate exceptions for relationship operation failures
- Use NotFoundException for missing related records
- Use BadRequestException for invalid relationship parameters

### 4.4 Phase 4: Testing and Documentation (Week 2)

**Step 4.1: Unit Tests**
- Create comprehensive tests for each exception class
- Test HTTP status code mapping
- Test error message formatting
- Test context handling

**Step 4.2: Integration Tests**
- Test RestApiHandler error response generation
- Test end-to-end API error scenarios
- Verify proper HTTP status codes in responses

**Step 4.3: Documentation**
- Update API documentation with new error codes
- Create developer guide for using new exceptions
- Document migration path from existing GCException usage

### 4.5 Phase 5: Migration and Cleanup (Week 3)

**Step 5.1: Gradual Migration**
- Update existing API controllers to use new exceptions
- Provide migration guide for custom controllers
- Maintain backward compatibility throughout

**Step 5.2: Error Response Enhancement**
- Add error categorization to responses
- Include suggested actions for common errors
- Enhance error context with relevant debugging information

## 5. Testing Strategy

### 5.1 Unit Testing

**Exception Class Tests**
- Test each exception class constructor
- Verify HTTP status code values
- Test default message functionality
- Test context preservation from GCException

**RestApiHandler Tests**
- Test error handling for each exception type
- Verify correct HTTP status code setting
- Test error response JSON structure
- Test backward compatibility with GCException

### 5.2 Integration Testing

**API Error Scenarios**
- Test 404 responses for missing resources
- Test 400 responses for invalid requests
- Test 422 responses for validation failures with proper field-level error aggregation
- Test 500 responses for server errors

**End-to-End Testing**
- Test complete request/response cycle for error scenarios
- Verify correct HTTP headers and status codes
- Test error responses in different API endpoints

### 5.3 Performance Testing

**Exception Overhead**
- Measure exception creation and handling performance
- Compare with existing GCException performance
- Ensure no significant performance regression

## 6. Documentation

### 6.1 API Documentation Updates

**Error Response Documentation**
- Document all possible HTTP status codes
- Provide examples of error responses
- Explain error context structure

**Exception Usage Guide**
- When to use each exception type
- Best practices for error handling in controllers
- Migration guide from GCException

### 6.2 Developer Documentation

**Exception Hierarchy Reference**
- Complete list of available exception classes
- HTTP status code mapping table
- Code examples for common scenarios

**Integration Guide**
- How to handle exceptions in custom controllers
- Error response customization options
- Testing error scenarios

## 7. Risks and Mitigations

### 7.1 Backward Compatibility Risk

**Risk**: Breaking existing code that catches GCException
**Mitigation**: 
- All new exceptions inherit from GCException
- Existing catch blocks will continue to work
- Provide gradual migration path

### 7.2 Performance Impact Risk

**Risk**: Additional exception classes might impact performance
**Mitigation**:
- Lightweight exception implementations
- Lazy loading of exception classes

### 7.3 Complexity Risk

**Risk**: Too many exception classes might confuse developers
**Mitigation**:
- Clear naming conventions
- Comprehensive documentation
- IDE support through proper type hints
- Examples and usage guides

### 7.4 HTTP Standard Compliance Risk

**Risk**: Incorrect mapping of exceptions to HTTP status codes
**Mitigation**:
- Follow RFC 7231 HTTP status code specifications
- Review mappings with team
- Include status code justification in documentation

## 8. Success Criteria

### 8.1 Functional Success Criteria

1. **Complete Exception Hierarchy**: All major HTTP 4xx and 5xx status codes have corresponding exception classes
2. **Proper Status Code Mapping**: API responses return correct HTTP status codes based on exception types
3. **Backward Compatibility**: Existing code continues to work without modifications
4. **Enhanced Error Responses**: API error responses include proper status codes, messages, and context

### 8.2 Technical Success Criteria

1. **Test Coverage**: >95% code coverage for new exception classes and error handling logic
2. **Documentation**: Complete documentation for all new exception classes and usage patterns
3. **Integration**: Seamless integration with existing RestApiHandler and API controllers

### 8.3 User Experience Success Criteria

1. **Developer Experience**: Clear exception types make error handling more intuitive
2. **API Consumer Experience**: Proper HTTP status codes improve client-side error handling
3. **Debugging Experience**: Enhanced error context makes troubleshooting easier

## 9. Timeline

- **Week 1**: Core exception classes and RestApiHandler integration
- **Week 2**: ModelBaseAPIController integration and testing
- **Week 3**: Migration, documentation, and final testing

Total estimated time: **3 weeks**

## 10. Dependencies

### 10.1 Framework Dependencies
- Existing GCException class and logging functionality
- RestApiHandler error handling mechanism
- ServiceLocator pattern for accessing logger

### 10.2 External Dependencies
- PSR-4 autoloading for new exception classes
- PHPUnit for testing new functionality
- Existing Monolog logging configuration

## Areas Addressed in This Implementation

### ✅ Validation Error Aggregation
The framework already has a robust validation error aggregation system in place:

- **FieldBase->validate()**: Each field validates its value and calls `registerValidationError()` for any failures
- **FieldBase->registerValidationError()**: Adds error messages to the field's `validationErrors` array
- **ModelBase->getValidationErrors()**: Collects validation errors from all fields and returns them organized by field name
- **Integration**: The new `UnprocessableEntityException` will utilize this existing system to provide comprehensive field-level validation error details in 422 responses

### 🐛 Critical Bug Fix Required
**FieldBase setValue Bug**: The current `FieldBase->setValue()` method has a critical flaw where it sets the value FIRST and then validates, meaning invalid values get stored even when validation fails. This will be fixed to validate BEFORE setting the value.

---

### 11.1 Advanced Error Handling
- Error aggregation for multiple validation failures
- Rate limiting exception handling
- Custom error pages for different HTTP status codes

### 11.2 Monitoring and Analytics
- Error rate monitoring by exception type
- Performance metrics for error handling
- Integration with external error tracking services

### 11.3 Developer Tools
- Exception handling IDE plugins
- Error response testing utilities
- Automated HTTP status code validation tools

---

---

## Areas Addressed in This Implementation

### ✅ Validation Error Aggregation
The framework already has a robust validation error aggregation system in place:

- **FieldBase->validate()**: Each field validates its value and calls `registerValidationError()` for any failures
- **FieldBase->registerValidationError()**: Adds error messages to the field's `validationErrors` array
- **ModelBase->getValidationErrors()**: Collects validation errors from all fields and returns them organized by field name
- **Integration**: The new `UnprocessableEntityException` will utilize this existing system to provide comprehensive field-level validation error details in 422 responses

### 🐛 Critical Bug Fix Required
**FieldBase setValue Bug**: The current `FieldBase->setValue()` method has a critical flaw where it sets the value FIRST and then validates, meaning invalid values get stored even when validation fails. This will be fixed to validate BEFORE setting the value.

This implementation plan provides a comprehensive approach to improving REST API error handling in the Gravitycar Framework while maintaining backward compatibility and following HTTP standards.
