<?php
namespace Gravitycar\Fields;

use Gravitycar\Core\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * EnumField: Dropdown field for selecting a single value from a predefined set of options.
 */
class EnumField extends FieldBase {
    protected string $type = 'Enum';
    protected string $label = '';
    protected bool $required = false;
    protected int $maxLength = 255;
    protected string $className = '';
    protected string $methodName = '';
    protected array $options = [];

    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
        $this->label = $metadata['label'] ?? $metadata['name'] ?? '';
        $this->required = $metadata['required'] ?? false;
        $this->maxLength = $metadata['maxLength'] ?? 255;
        $this->className = $metadata['optionsClass'] ?? '';
        $this->methodName = $metadata['optionsMethod'] ?? '';

        // Handle static options or dynamic options
        if (isset($metadata['options'])) {
            $this->options = $metadata['options'];
        } else {
            $this->loadOptions();
        }
    }

    protected function loadOptions(): void {
        if ($this->className && $this->methodName && class_exists($this->className) && method_exists($this->className, $this->methodName)) {
            $this->options = call_user_func([$this->className, $this->methodName]);
        } else {
            $this->options = [];
        }
    }

    public function getOptions(): array {
        return $this->options;
    }
}
