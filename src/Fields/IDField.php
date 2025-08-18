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
    
    /** @var array ID fields have limited operators for security and performance */
    protected array $operators = ['equals', 'notEquals', 'in', 'notIn', 'isNull', 'isNotNull'];

    public function __construct(array $metadata) {
        parent::__construct($metadata);
        // ingestMetadata() in parent constructor now handles all property assignments
    }
}
