<?php
namespace Tests\Unit\Exceptions;

use PHPUnit\Framework\TestCase;
use Gravitycar\Exceptions\APIException;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\BadRequestException;
use Gravitycar\Exceptions\UnprocessableEntityException;
use Gravitycar\Exceptions\InternalServerErrorException;

/**
 * Unit tests for API Exception classes
 */
class APIExceptionTest extends TestCase {

    public function testNotFoundExceptionHasCorrectStatusCode(): void {
        $exception = new NotFoundException();
        $this->assertEquals(404, $exception->getHttpStatusCode());
        $this->assertEquals(404, $exception->getCode());
    }

    public function testNotFoundExceptionUsesDefaultMessage(): void {
        $exception = new NotFoundException();
        $this->assertEquals('Resource not found', $exception->getMessage());
    }

    public function testNotFoundExceptionUsesCustomMessage(): void {
        $exception = new NotFoundException('Custom not found message');
        $this->assertEquals('Custom not found message', $exception->getMessage());
    }

    public function testBadRequestExceptionHasCorrectStatusCode(): void {
        $exception = new BadRequestException();
        $this->assertEquals(400, $exception->getHttpStatusCode());
        $this->assertEquals(400, $exception->getCode());
    }

    public function testBadRequestExceptionUsesDefaultMessage(): void {
        $exception = new BadRequestException();
        $this->assertEquals('Bad request - the request contains invalid parameters or syntax', $exception->getMessage());
    }

    public function testUnprocessableEntityExceptionHasCorrectStatusCode(): void {
        $exception = new UnprocessableEntityException();
        $this->assertEquals(422, $exception->getHttpStatusCode());
        $this->assertEquals(422, $exception->getCode());
    }

    public function testUnprocessableEntityExceptionWithValidationErrors(): void {
        $validationErrors = [
            'email' => ['Email format is invalid'],
            'username' => ['Username must be at least 3 characters']
        ];
        
        $exception = UnprocessableEntityException::withValidationErrors($validationErrors);
        
        $this->assertEquals(422, $exception->getHttpStatusCode());
        $this->assertEquals('Validation failed', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertArrayHasKey('validation_errors', $context);
        $this->assertEquals($validationErrors, $context['validation_errors']);
    }

    public function testInternalServerErrorExceptionHasCorrectStatusCode(): void {
        $exception = new InternalServerErrorException();
        $this->assertEquals(500, $exception->getHttpStatusCode());
        $this->assertEquals(500, $exception->getCode());
    }

    public function testAPIExceptionGetErrorType(): void {
        $notFoundException = new NotFoundException();
        $this->assertEquals(' Not Found', $notFoundException->getErrorType());
        
        $badRequestException = new BadRequestException();
        $this->assertEquals(' Bad Request', $badRequestException->getErrorType());
    }

    public function testAPIExceptionWithContext(): void {
        $context = ['user_id' => 123, 'additional_info' => 'test'];
        $exception = new NotFoundException('User not found', $context);
        
        $this->assertEquals($context, $exception->getContext());
    }

    public function testAPIExceptionInheritanceFromGCException(): void {
        $exception = new NotFoundException();
        $this->assertInstanceOf(\Gravitycar\Exceptions\GCException::class, $exception);
    }

    public function testAPIExceptionWithPreviousException(): void {
        $previousException = new \Exception('Previous error');
        $exception = new InternalServerErrorException('Server error', [], $previousException);
        
        $this->assertSame($previousException, $exception->getPrevious());
    }
}
