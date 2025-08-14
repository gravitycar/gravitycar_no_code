<?php
namespace Gravitycar\Exceptions;

/**
 * 503 Service Unavailable Exception
 * The server is currently unavailable (overloaded or down for maintenance).
 */
class ServiceUnavailableException extends ServerErrorException {
    protected int $httpStatusCode = 503;

    public function getDefaultMessage(): string {
        return 'Service unavailable - the server is temporarily unable to handle the request';
    }
}
