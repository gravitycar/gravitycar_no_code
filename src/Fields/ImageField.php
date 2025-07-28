<?php
namespace Gravitycar\Fields;

use Gravitycar\Core\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * ImageField: Field for storing and displaying image file paths or URLs.
 */
class ImageField extends FieldBase {
    protected string $type = 'Image';
    protected string $label = '';
    protected bool $required = false;
    protected int $maxLength = 500;
    protected $width = null;
    protected $height = null;
    protected string $altText = '';
    protected bool $allowLocal = true;
    protected bool $allowRemote = true;
    protected string $placeholder = 'Enter image path or URL';

    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
        $this->label = $metadata['label'] ?? $metadata['name'] ?? '';
        $this->required = $metadata['required'] ?? false;
        $this->maxLength = $metadata['maxLength'] ?? 500;
        $this->width = $metadata['width'] ?? null;
        $this->height = $metadata['height'] ?? null;
        $this->altText = $metadata['altText'] ?? '';
        $this->allowLocal = $metadata['allowLocal'] ?? true;
        $this->allowRemote = $metadata['allowRemote'] ?? true;
        $this->placeholder = $metadata['placeholder'] ?? 'Enter image path or URL';
    }
}
