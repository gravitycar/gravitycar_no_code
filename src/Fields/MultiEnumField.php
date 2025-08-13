<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
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

    public function __construct(array $metadata) {
        parent::__construct($metadata);
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
