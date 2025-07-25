<?php

namespace Gravitycar\Models;

use Gravitycar\Core\ModelBase;

/**
 * User model for the Gravitycar framework
 *
 * Handles user authentication and basic user information.
 */
class User extends ModelBase
{
    protected function getMetadataPath(string $modelName): string
    {
        return __DIR__ . "/../../metadata/models/users.php";
    }
}
