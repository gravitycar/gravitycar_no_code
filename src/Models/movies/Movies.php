<?php
namespace Gravitycar\Models\movies;

use Gravitycar\Models\ModelBase;
use Monolog\Logger;

/**
 * Movies model class for Gravitycar framework.
 */
class Movies extends ModelBase {
    public function __construct(Logger $logger) {
        parent::__construct($logger);
    }
}
