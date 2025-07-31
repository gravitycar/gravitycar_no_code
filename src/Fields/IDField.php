<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * IDField: Field for unique record identifiers (UUID).
 */
class IDField extends FieldBase {
    protected string $type = 'ID';
    protected string $label = 'ID';
    protected bool $required = true;
    protected bool $unique = true;
    protected bool $readOnly = true;

    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
        // ingestMetadata() in parent constructor now handles all property assignments
    }
}
