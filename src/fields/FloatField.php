<?php
namespace Gravitycar\Fields;

use Gravitycar\Core\FieldBase;
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

    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
        $this->label = $metadata['label'] ?? $metadata['name'] ?? '';
        $this->required = $metadata['required'] ?? false;
        $this->defaultValue = $metadata['defaultValue'] ?? null;
        $this->minValue = $metadata['minValue'] ?? null;
        $this->maxValue = $metadata['maxValue'] ?? null;
        $this->allowNegative = $metadata['allowNegative'] ?? true;
        $this->precision = $metadata['precision'] ?? 2;
        $this->step = $metadata['step'] ?? 0.01;
        $this->placeholder = $metadata['placeholder'] ?? 'Enter a decimal number';
        $this->showSpinners = $metadata['showSpinners'] ?? true;
        $this->formatDisplay = $metadata['formatDisplay'] ?? false;
    }
}
