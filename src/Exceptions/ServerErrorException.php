<?php
namespace Gravitycar\Exceptions;

/**
 * Base class for 5xx server error exceptions.
 * These errors indicate that the server has made an error or is incapable of performing the request.
 */
abstract class ServerErrorException extends APIException {
    
    /**
     * Get error category for server errors
     */
    public function getErrorCategory(): string {
        return 'Server Error';
    }
}
