<?php
namespace Gravitycar\Fields;

use Gravitycar\Core\FieldBase;
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

    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
        $this->label = $metadata['label'] ?? $metadata['name'] ?? '';
        $this->required = $metadata['required'] ?? false;
        $this->defaultValue = $metadata['defaultValue'] ?? null;
        $this->minValue = $metadata['minValue'] ?? null;
        $this->maxValue = $metadata['maxValue'] ?? null;
        $this->allowNegative = $metadata['allowNegative'] ?? true;
        $this->step = $metadata['step'] ?? 1;
        $this->placeholder = $metadata['placeholder'] ?? 'Enter a number';
    }
}
