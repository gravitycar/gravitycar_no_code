<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * DateField: Input field for date values with timezone conversion.
 */
class DateField extends FieldBase {
    protected string $type = 'Date';
    protected string $label = '';
    protected bool $required = false;
    protected int $maxLength = 10;
    protected string $reactComponent = 'DatePicker';
    
    /** @var array Date comparison operators */
    protected array $operators = [
        'equals', 'notEquals', 'greaterThan', 'greaterThanOrEqual', 
        'lessThan', 'lessThanOrEqual', 'between', 'in', 'notIn', 
        'isNull', 'isNotNull'
    ];

    public function __construct(array $metadata, ?Logger $logger = null) {
        parent::__construct($metadata, $logger);
        // ingestMetadata() in parent constructor now handles all property assignments
    }
}
