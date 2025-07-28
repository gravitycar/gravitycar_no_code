<?php
namespace Gravitycar\Core;

use Attribute;

/**
 * Service attribute for marking classes as injectable services
 */
#[Attribute]
class Service {
    public function __construct(
        public string $name = '',
        public bool $singleton = false,
        public array $tags = []
    ) {}
}

/**
 * Inject attribute for marking constructor parameters for specific service injection
 */
#[Attribute]
class Inject {
    public function __construct(
        public string $service = ''
    ) {}
}
