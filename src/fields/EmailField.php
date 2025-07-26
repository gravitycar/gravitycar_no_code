<?php
namespace Gravitycar\Fields;

use Gravitycar\Core\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * EmailField: Input field for email addresses with format validation.
 */
class EmailField extends FieldBase {
    protected string $type = 'Email';
    protected string $label = '';
    protected bool $required = false;
    protected int $maxLength = 254;
    protected string $placeholder = 'Enter email address';
    protected bool $normalize = true;

    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
        $this->label = $metadata['label'] ?? $metadata['name'] ?? '';
        $this->required = $metadata['required'] ?? false;
        $this->maxLength = $metadata['maxLength'] ?? 254;
        $this->placeholder = $metadata['placeholder'] ?? 'Enter email address';
        $this->normalize = $metadata['normalize'] ?? true;
    }

    public function setValue($value): void {
        if ($this->normalize && is_string($value)) {
            $value = strtolower($value);
        }
        parent::setValue($value);
    }
}
