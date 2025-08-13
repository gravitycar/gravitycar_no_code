<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * BooleanField: Input field for true/false values.
 */
class BooleanField extends FieldBase {
    protected string $type = 'Boolean';
    protected string $label = '';
    protected bool $required = false;
    protected $defaultValue = null;
    protected string $trueLabel = 'Yes';
    protected string $falseLabel = 'No';
    protected string $displayAs = 'checkbox';

    public function __construct(array $metadata) {
        parent::__construct($metadata);
        // ingestMetadata() in parent constructor now handles all property assignments
    }
}
