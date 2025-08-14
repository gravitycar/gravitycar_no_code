<?php
namespace Gravitycar\Exceptions;

/**
 * 409 Conflict Exception
 * The request could not be completed due to a conflict with the current state of the resource.
 */
class ConflictException extends ClientErrorException {
    protected int $httpStatusCode = 409;

    public function getDefaultMessage(): string {
        return 'Conflict - the request conflicts with the current state of the resource';
    }
}
