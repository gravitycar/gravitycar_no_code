<?php
namespace Gravitycar\Exceptions;

/**
 * 403 Forbidden Exception
 * The request is valid but the server is refusing to respond to it.
 */
class ForbiddenException extends ClientErrorException {
    protected int $httpStatusCode = 403;

    public function getDefaultMessage(): string {
        return 'Access forbidden - you do not have permission to access this resource';
    }
}
