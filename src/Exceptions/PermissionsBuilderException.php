<?php

namespace Gravitycar\Exceptions;

/**
 * PermissionsBuilderException
 * 
 * Specialized exception for permission building operations.
 * Provides context-aware error messages for permission-related failures.
 */
class PermissionsBuilderException extends GCException
{
    // Standard constructor inheritance - no static factory methods needed
    // Use: throw new PermissionsBuilderException($message, $context);
}