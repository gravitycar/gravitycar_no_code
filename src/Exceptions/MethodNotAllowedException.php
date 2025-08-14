<?php
namespace Gravitycar\Exceptions;

/**
 * 405 Method Not Allowed Exception
 * The request method is not supported for the requested resource.
 */
class MethodNotAllowedException extends ClientErrorException {
    protected int $httpStatusCode = 405;

    public function getDefaultMessage(): string {
        return 'Method not allowed for this resource';
    }
}
