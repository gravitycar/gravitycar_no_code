<?php
namespace Gravitycar\Fields;

use Gravitycar\Core\FieldBase;
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

    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
        $this->label = $metadata['label'] ?? $metadata['name'] ?? '';
        $this->required = $metadata['required'] ?? false;
        $this->maxLength = $metadata['maxLength'] ?? 10;
    }
}
