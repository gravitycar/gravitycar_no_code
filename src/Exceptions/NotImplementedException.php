<?php
namespace Gravitycar\Exceptions;

/**
 * 501 Not Implemented Exception
 * The server does not support the functionality required to fulfill the request.
 */
class NotImplementedException extends ServerErrorException {
    protected int $httpStatusCode = 501;

    public function getDefaultMessage(): string {
        return 'Not implemented - this functionality is not yet available';
    }
}
