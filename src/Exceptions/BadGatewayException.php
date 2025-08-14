<?php
namespace Gravitycar\Exceptions;

/**
 * 502 Bad Gateway Exception
 * The server received an invalid response from an upstream server.
 */
class BadGatewayException extends ServerErrorException {
    protected int $httpStatusCode = 502;

    public function getDefaultMessage(): string {
        return 'Bad gateway - invalid response from upstream server';
    }
}
