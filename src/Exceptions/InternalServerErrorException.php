<?php
namespace Gravitycar\Exceptions;

/**
 * 500 Internal Server Error Exception
 * A generic error message for unexpected server errors.
 */
class InternalServerErrorException extends ServerErrorException {
    protected int $httpStatusCode = 500;

    public function getDefaultMessage(): string {
        return 'Internal server error - an unexpected error occurred';
    }
}
