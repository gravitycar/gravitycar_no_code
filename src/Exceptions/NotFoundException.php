<?php
namespace Gravitycar\Exceptions;

/**
 * 404 Not Found Exception
 * The requested resource could not be found.
 */
class NotFoundException extends ClientErrorException {
    protected int $httpStatusCode = 404;

    public function getDefaultMessage(): string {
        return 'Resource not found';
    }
}
