<?php
namespace Gravitycar\Fields;

use Gravitycar\Core\FieldBase;
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

    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
        $this->label = $metadata['label'] ?? $metadata['name'] ?? '';
        $this->required = $metadata['required'] ?? false;
        $this->defaultValue = $metadata['defaultValue'] ?? null;
        $this->trueLabel = $metadata['trueLabel'] ?? 'Yes';
        $this->falseLabel = $metadata['falseLabel'] ?? 'No';
        $this->displayAs = $metadata['displayAs'] ?? 'checkbox';
    }
}
