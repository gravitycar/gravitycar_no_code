<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
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
        // ingestMetadata() in parent constructor now handles all property assignments

        // Special handling for options loading after properties are set
        $this->loadOptions();
    }

    protected function loadOptions(): void {
        // Handle static options or dynamic options
        if (isset($this->metadata['options'])) {
            $this->options = $this->metadata['options'];
        } elseif ($this->className && $this->methodName && class_exists($this->className) && method_exists($this->className, $this->methodName)) {
            $this->options = call_user_func([$this->className, $this->methodName]);
        } else {
            $this->options = [];
        }
    }

    public function getOptions(): array {
        return $this->options;
    }
}
