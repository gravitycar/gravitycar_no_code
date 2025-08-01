<?php
namespace Gravitycar\Models\movie_quotes;

use Gravitycar\Models\ModelBase;
use Monolog\Logger;

/**
 * MovieQuotes model class for Gravitycar framework.
 */
class MovieQuotes extends ModelBase {
    public function __construct(Logger $logger) {
        parent::__construct($logger);
    }
}
