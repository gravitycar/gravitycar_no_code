<?php
namespace Gravitycar\Exceptions;

/**
 * 400 Bad Request Exception
 * The request cannot be fulfilled due to bad syntax or invalid parameters.
 */
class BadRequestException extends ClientErrorException {
    protected int $httpStatusCode = 400;

    public function getDefaultMessage(): string {
        return 'Bad request - the request contains invalid parameters or syntax';
    }
}
