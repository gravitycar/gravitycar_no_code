<?php

namespace Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Gravitycar\Exceptions\ParameterValidationException;
use Gravitycar\Exceptions\BadRequestException;

class ParameterValidationExceptionTest extends TestCase
{
    public function testExtendssBadRequestException(): void
    {
        $exception = new ParameterValidationException();
        
        $this->assertInstanceOf(BadRequestException::class, $exception);
    }

    public function testConstructorWithDefaults(): void
    {
        $exception = new ParameterValidationException();
        
        $this->assertEquals('Parameter validation failed', $exception->getMessage());
        $this->assertEquals([], $exception->getErrors());
        $this->assertEquals([], $exception->getSuggestions());
        $this->assertFalse($exception->hasErrors());
        $this->assertEquals(0, $exception->getErrorCount());
    }

    public function testConstructorWithCustomMessage(): void
    {
        $message = 'Custom validation message';
        $exception = new ParameterValidationException($message);
        
        $this->assertEquals($message, $exception->getMessage());
    }

    public function testConstructorWithErrors(): void
    {
        $errors = [
            ['field' => 'name', 'error' => 'Required', 'value' => null],
            ['field' => 'email', 'error' => 'Invalid format', 'value' => 'invalid-email']
        ];
        
        $exception = new ParameterValidationException('Validation failed', $errors);
        
        $this->assertEquals($errors, $exception->getErrors());
        $this->assertTrue($exception->hasErrors());
        $this->assertEquals(2, $exception->getErrorCount());
    }

    public function testConstructorWithSuggestions(): void
    {
        $suggestions = ['Check field format', 'Ensure required fields are provided'];
        
        $exception = new ParameterValidationException('Validation failed', [], $suggestions);
        
        $this->assertEquals($suggestions, $exception->getSuggestions());
    }

    public function testConstructorSetsContext(): void
    {
        $errors = [['field' => 'test', 'error' => 'Test error', 'value' => 'test']];
        $suggestions = ['Test suggestion'];
        
        $exception = new ParameterValidationException('Test', $errors, $suggestions);
        $context = $exception->getContext();
        
        $this->assertEquals($errors, $context['validation_errors']);
        $this->assertEquals($suggestions, $context['suggestions']);
        $this->assertEquals(1, $context['error_count']);
    }

    public function testAddError(): void
    {
        $exception = new ParameterValidationException();
        
        $exception->addError('username', 'Username is required');
        
        $errors = $exception->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals('username', $errors[0]['field']);
        $this->assertEquals('Username is required', $errors[0]['error']);
        $this->assertNull($errors[0]['value']);
        $this->assertArrayHasKey('timestamp', $errors[0]);
    }

    public function testAddErrorWithValue(): void
    {
        $exception = new ParameterValidationException();
        
        $exception->addError('email', 'Invalid email format', 'invalid@');
        
        $errors = $exception->getErrors();
        $this->assertEquals('invalid@', $errors[0]['value']);
    }

    public function testAddMultipleErrors(): void
    {
        $exception = new ParameterValidationException();
        
        $exception->addError('field1', 'Error 1', 'value1');
        $exception->addError('field2', 'Error 2', 'value2');
        $exception->addError('field3', 'Error 3');
        
        $this->assertEquals(3, $exception->getErrorCount());
        $this->assertTrue($exception->hasErrors());
        
        $errors = $exception->getErrors();
        $this->assertCount(3, $errors);
        $this->assertEquals('field1', $errors[0]['field']);
        $this->assertEquals('field2', $errors[1]['field']);
        $this->assertEquals('field3', $errors[2]['field']);
    }

    public function testAddErrorUpdatesContext(): void
    {
        $exception = new ParameterValidationException();
        
        $exception->addError('test_field', 'Test error');
        
        $context = $exception->getContext();
        $this->assertCount(1, $context['validation_errors']);
        $this->assertEquals(1, $context['error_count']);
    }

    public function testAddSuggestion(): void
    {
        $exception = new ParameterValidationException();
        
        $exception->addSuggestion('Try using a different format');
        
        $suggestions = $exception->getSuggestions();
        $this->assertCount(1, $suggestions);
        $this->assertEquals('Try using a different format', $suggestions[0]);
    }

    public function testAddMultipleSuggestions(): void
    {
        $exception = new ParameterValidationException();
        
        $exception->addSuggestion('Suggestion 1');
        $exception->addSuggestion('Suggestion 2');
        $exception->addSuggestion('Suggestion 3');
        
        $suggestions = $exception->getSuggestions();
        $this->assertCount(3, $suggestions);
        $this->assertEquals('Suggestion 1', $suggestions[0]);
        $this->assertEquals('Suggestion 2', $suggestions[1]);
        $this->assertEquals('Suggestion 3', $suggestions[2]);
    }

    public function testAddSuggestionUpdatesContext(): void
    {
        $exception = new ParameterValidationException();
        
        $exception->addSuggestion('Test suggestion');
        
        $context = $exception->getContext();
        $this->assertCount(1, $context['suggestions']);
        $this->assertEquals('Test suggestion', $context['suggestions'][0]);
    }

    public function testHasErrorsWithNoErrors(): void
    {
        $exception = new ParameterValidationException();
        
        $this->assertFalse($exception->hasErrors());
    }

    public function testHasErrorsWithErrors(): void
    {
        $exception = new ParameterValidationException();
        $exception->addError('field', 'error');
        
        $this->assertTrue($exception->hasErrors());
    }

    public function testGetErrorCountUpdatesAfterAddingErrors(): void
    {
        $exception = new ParameterValidationException();
        
        $this->assertEquals(0, $exception->getErrorCount());
        
        $exception->addError('field1', 'error1');
        $this->assertEquals(1, $exception->getErrorCount());
        
        $exception->addError('field2', 'error2');
        $this->assertEquals(2, $exception->getErrorCount());
    }

    public function testGetApiResponseWithMinimalData(): void
    {
        $exception = new ParameterValidationException('Test message');
        
        $response = $exception->getApiResponse();
        
        $this->assertFalse($response['success']);
        $this->assertEquals('Test message', $response['error']);
        $this->assertEquals(400, $response['code']); // BadRequestException default code
        $this->assertEquals([], $response['validation_errors']);
        $this->assertEquals([], $response['suggestions']);
        $this->assertEquals(0, $response['error_count']);
        $this->assertArrayHasKey('timestamp', $response);
    }

    public function testGetApiResponseWithFullData(): void
    {
        $exception = new ParameterValidationException('Full validation failed');
        $exception->addError('name', 'Name is required', null);
        $exception->addError('email', 'Invalid email', 'bad@email');
        $exception->addSuggestion('Check the field formats');
        $exception->addSuggestion('Ensure all required fields are provided');
        
        $response = $exception->getApiResponse();
        
        $this->assertFalse($response['success']);
        $this->assertEquals('Full validation failed', $response['error']);
        $this->assertCount(2, $response['validation_errors']);
        $this->assertCount(2, $response['suggestions']);
        $this->assertEquals(2, $response['error_count']);
        
        // Check error structure
        $this->assertEquals('name', $response['validation_errors'][0]['field']);
        $this->assertEquals('Name is required', $response['validation_errors'][0]['error']);
        $this->assertNull($response['validation_errors'][0]['value']);
        
        $this->assertEquals('email', $response['validation_errors'][1]['field']);
        $this->assertEquals('Invalid email', $response['validation_errors'][1]['error']);
        $this->assertEquals('bad@email', $response['validation_errors'][1]['value']);
        
        // Check suggestions
        $this->assertEquals('Check the field formats', $response['suggestions'][0]);
        $this->assertEquals('Ensure all required fields are provided', $response['suggestions'][1]);
    }

    public function testTimestampInErrors(): void
    {
        $exception = new ParameterValidationException();
        
        $beforeTime = time(); // Use Unix timestamp for more reliable comparison
        $exception->addError('test', 'Test error');
        $afterTime = time();
        
        $errors = $exception->getErrors();
        $errorTimestamp = strtotime($errors[0]['timestamp']);
        
        $this->assertGreaterThanOrEqual($beforeTime, $errorTimestamp);
        $this->assertLessThanOrEqual($afterTime + 1, $errorTimestamp); // Allow 1 second tolerance
    }

    public function testTimestampInApiResponse(): void
    {
        $exception = new ParameterValidationException('Test');
        
        $beforeTime = time(); // Use Unix timestamp for more reliable comparison
        $response = $exception->getApiResponse();
        $afterTime = time();
        
        $responseTimestamp = strtotime($response['timestamp']);
        
        $this->assertGreaterThanOrEqual($beforeTime, $responseTimestamp);
        $this->assertLessThanOrEqual($afterTime + 1, $responseTimestamp); // Allow 1 second tolerance
    }

    public function testComplexValidationScenario(): void
    {
        $exception = new ParameterValidationException('Complex validation scenario');
        
        // Add multiple field errors
        $exception->addError('user.name', 'Name cannot be empty', '');
        $exception->addError('user.email', 'Email format is invalid', 'not-an-email');
        $exception->addError('user.age', 'Age must be between 18 and 120', 150);
        $exception->addError('user.password', 'Password too weak', 'pass');
        
        // Add helpful suggestions
        $exception->addSuggestion('Ensure name has at least 2 characters');
        $exception->addSuggestion('Use a valid email format like user@domain.com');
        $exception->addSuggestion('Age should be a realistic value');
        $exception->addSuggestion('Password should be at least 8 characters with mixed case');
        
        // Verify comprehensive structure
        $this->assertEquals(4, $exception->getErrorCount());
        $this->assertTrue($exception->hasErrors());
        
        $errors = $exception->getErrors();
        $suggestions = $exception->getSuggestions();
        
        $this->assertCount(4, $errors);
        $this->assertCount(4, $suggestions);
        
        // Verify API response structure
        $response = $exception->getApiResponse();
        $this->assertEquals(4, $response['error_count']);
        $this->assertCount(4, $response['validation_errors']);
        $this->assertCount(4, $response['suggestions']);
        
        // Verify specific error content
        $this->assertEquals('user.name', $errors[0]['field']);
        $this->assertEquals('', $errors[0]['value']);
        
        $this->assertEquals('user.age', $errors[2]['field']);
        $this->assertEquals(150, $errors[2]['value']);
    }
}
