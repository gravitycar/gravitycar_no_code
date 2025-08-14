<?php
namespace Gravitycar\Exceptions;

/**
 * 412 Precondition Failed Exception
 * One or more conditions given in the request header fields evaluated to false.
 */
class PreconditionFailedException extends ClientErrorException {
    protected int $httpStatusCode = 412;

    public function getDefaultMessage(): string {
        return 'Precondition failed - one or more request conditions were not met';
    }
}
