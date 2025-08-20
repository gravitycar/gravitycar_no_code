<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * DateTimeField: Input field for date and time values, with timezone conversion.
 */
class DateTimeField extends FieldBase {
    protected string $type = 'DateTime';
    protected string $label = '';
    protected bool $required = false;
    protected int $maxLength = 19;
    protected string $reactComponent = 'DateTimePicker';
    
    /** @var array DateTime comparison operators */
    protected array $operators = [
        'equals', 'notEquals', 'greaterThan', 'greaterThanOrEqual', 
        'lessThan', 'lessThanOrEqual', 'between', 'in', 'notIn', 
        'isNull', 'isNotNull'
    ];

    public function __construct(array $metadata) {
        parent::__construct($metadata);
        // ingestMetadata() in parent constructor now handles all property assignments
    }
}
