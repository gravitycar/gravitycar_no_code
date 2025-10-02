<?php

namespace Gravitycar\Exceptions;

/**
 * NavigationBuilderException
 * 
 * Specialized exception for navigation-related errors including:
 * - Navigation configuration loading failures
 * - Role-based navigation building errors  
 * - Navigation cache operations
 * - Custom page validation issues
 * - Navigation section processing errors
 */
class NavigationBuilderException extends GCException
{
    // Inherits all functionality from GCException
    // No additional methods needed - just provides specific exception type for navigation operations
}