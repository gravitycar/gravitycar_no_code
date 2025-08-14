<?php
namespace Gravitycar\Exceptions;

/**
 * 401 Unauthorized Exception
 * Authentication is required and has failed or has not been provided.
 */
class UnauthorizedException extends ClientErrorException {
    protected int $httpStatusCode = 401;

    public function getDefaultMessage(): string {
        return 'Authentication required - please provide valid credentials';
    }
}
