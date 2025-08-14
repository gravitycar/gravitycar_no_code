<?php
namespace Gravitycar\Exceptions;

/**
 * Base class for 4xx client error exceptions.
 * These errors indicate that the client has made an error in the request.
 */
abstract class ClientErrorException extends APIException {
    
    /**
     * Get error category for client errors
     */
    public function getErrorCategory(): string {
        return 'Client Error';
    }
}
