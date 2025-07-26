<?php
namespace Gravitycar\Fields;

use Gravitycar\Core\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * BigTextField: Input field for large text data.
 */
class BigTextField extends FieldBase {
    protected string $type = 'BigText';
    protected string $label = '';
    protected bool $required = false;
    protected int $maxLength = 16000;

    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
        $this->label = $metadata['label'] ?? $metadata['name'] ?? '';
        $this->required = $metadata['required'] ?? false;
        $this->maxLength = $metadata['maxLength'] ?? 16000;
    }
}
