<?php
namespace Gravitycar\Fields;

use Gravitycar\Core\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * MultiEnumField: Multi-select field for multiple values from predefined options.
 */
class MultiEnumField extends FieldBase {
    protected string $type = 'MultiEnum';
    protected string $label = '';
    protected bool $required = false;
    protected int $maxLength = 16000;
    protected string $className = '';
    protected string $methodName = '';
    protected int $maxSelections = 0;
    protected int $minSelections = 0;
    protected array $options = [];

    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
        $this->label = $metadata['label'] ?? $metadata['name'] ?? '';
        $this->required = $metadata['required'] ?? false;
        $this->maxLength = $metadata['maxLength'] ?? 16000;
        $this->className = $metadata['optionsClass'] ?? '';
        $this->methodName = $metadata['optionsMethod'] ?? '';
        $this->maxSelections = $metadata['maxSelections'] ?? 0;
        $this->minSelections = $metadata['minSelections'] ?? 0;

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
