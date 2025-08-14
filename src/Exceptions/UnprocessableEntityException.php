<?php
namespace Gravitycar\Exceptions;

/**
 * 422 Unprocessable Entity Exception
 * The request was well-formed but was unable to be followed due to semantic errors.
 * Typically used for validation failures.
 */
class UnprocessableEntityException extends ClientErrorException {
    protected int $httpStatusCode = 422;

    public function getDefaultMessage(): string {
        return 'Unprocessable entity - the request contains validation errors';
    }

    /**
     * Create exception with validation errors from ModelBase
     * 
     * @param array $validationErrors Validation errors from ModelBase->getValidationErrors()
     * @param string $message Custom message (optional)
     * @return static
     */
    public static function withValidationErrors(array $validationErrors, string $message = ''): static {
        return new static($message ?: 'Validation failed', [
            'validation_errors' => $validationErrors
        ]);
    }
}
