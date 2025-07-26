<?php
namespace Gravitycar\Fields;

use Gravitycar\Core\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * TextField: Basic input field for text data.
 */
class TextField extends FieldBase {
    protected string $type = 'Text';
    protected string $label = '';
    protected bool $required = false;
    protected int $maxLength = 255;

    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
        $this->label = $metadata['label'] ?? $metadata['name'] ?? '';
        $this->required = $metadata['required'] ?? false;
        $this->maxLength = $metadata['maxLength'] ?? 255;
    }
}
