<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * FloatField: Input field for decimal number values with precision control.
 */
class FloatField extends FieldBase {
    protected string $type = 'Float';
    protected string $label = '';
    protected bool $required = false;
    protected $defaultValue = null;
    protected $minValue = null;
    protected $maxValue = null;
    protected bool $allowNegative = true;
    protected int $precision = 2;
    protected float $step = 0.01;
    protected string $placeholder = 'Enter a decimal number';
    protected bool $showSpinners = true;
    protected bool $formatDisplay = false;
    
    /** @var array Numeric operators for float fields */
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
