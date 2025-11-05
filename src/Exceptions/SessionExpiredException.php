<?php

namespace Gravitycar\Exceptions;

/**
 * SessionExpiredException
 * Thrown when user session expires due to inactivity
 */
class SessionExpiredException extends UnauthorizedException
{
    public function __construct(
        string $message = 'Session expired',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        $context['code'] = 'SESSION_EXPIRED';
        parent::__construct($message, $context, $previous);
    }
}
