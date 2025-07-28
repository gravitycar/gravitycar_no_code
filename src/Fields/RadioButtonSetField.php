<?php
namespace Gravitycar\Fields;

use Gravitycar\Core\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * RadioButtonSetField: Field for selecting a single value from options displayed as radio buttons.
 */
class RadioButtonSetField extends FieldBase {
    protected string $type = 'RadioButtonSet';
    protected string $label = '';
    protected bool $required = false;
    protected string $className = '';
    protected string $methodName = '';
    protected $defaultValue = null;
    protected string $layout = 'vertical';
    protected bool $allowClear = false;
    protected string $clearLabel = 'None';
    protected array $options = [];

    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
        $this->label = $metadata['label'] ?? $metadata['name'] ?? '';
        $this->required = $metadata['required'] ?? false;
        $this->className = $metadata['optionsClass'] ?? '';
        $this->methodName = $metadata['optionsMethod'] ?? '';
        $this->defaultValue = $metadata['defaultValue'] ?? null;
        $this->layout = $metadata['layout'] ?? 'vertical';
        $this->allowClear = $metadata['allowClear'] ?? false;
        $this->clearLabel = $metadata['clearLabel'] ?? 'None';

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
