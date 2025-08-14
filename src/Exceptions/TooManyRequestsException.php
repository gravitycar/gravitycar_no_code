<?php
namespace Gravitycar\Exceptions;

/**
 * 429 Too Many Requests Exception
 * The user has sent too many requests in a given amount of time.
 */
class TooManyRequestsException extends ClientErrorException {
    protected int $httpStatusCode = 429;

    public function getDefaultMessage(): string {
        return 'Too many requests - please try again later';
    }
}
