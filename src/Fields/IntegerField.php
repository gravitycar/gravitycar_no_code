<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * IntegerField: Input field for integer values with range validation.
 */
class IntegerField extends FieldBase {
    protected string $type = 'Integer';
    protected string $label = '';
    protected bool $required = false;
    protected $defaultValue = null;
    protected $minValue = null;
    protected $maxValue = null;
    protected bool $allowNegative = true;
    protected int $step = 1;
    protected string $placeholder = 'Enter a number';
    protected bool $showSpinners = true;

    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
        // ingestMetadata() in parent constructor now handles all property assignments
    }
}
