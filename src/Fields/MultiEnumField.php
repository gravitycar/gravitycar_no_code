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
    protected string $optionsClass = '';
    protected string $optionsMethod = '';
    protected int $maxSelections = 0;
    protected int $minSelections = 0;
    protected array $options = [];
    protected string $reactComponent = 'MultiSelect';
    
    /** @var array Array-specific operators for multi-value fields */
    protected array $operators = [
        'equals', 'notEquals', 'overlap', 'containsAll', 'containsNone', 
        'in', 'notIn', 'isNull', 'isNotNull'
    ];

    public function __construct(array $metadata, ?Logger $logger = null) {
        parent::__construct($metadata, $logger);
        // ingestMetadata() in parent constructor now handles all property assignments

        // Special handling for options loading after properties are set
        $this->loadOptions();
    }

    protected function loadOptions(): void {
        // Handle static options first - but only if they contain actual options
        if (isset($this->metadata['options']) && is_array($this->metadata['options']) && !empty($this->metadata['options'])) {
            $this->options = $this->metadata['options'];
            return;
        }

        // Load options from external class method if specified
        if ($this->optionsClass && $this->optionsMethod) {
            try {
                if (class_exists($this->optionsClass) && method_exists($this->optionsClass, $this->optionsMethod)) {
                    $this->options = call_user_func([$this->optionsClass, $this->optionsMethod]);
                } else {
                    error_log("MultiEnumField: Unable to load options from {$this->optionsClass}::{$this->optionsMethod} - class or method not found");
                    $this->options = [];
                }
            } catch (\Exception $e) {
                error_log("MultiEnumField: Error loading options from {$this->optionsClass}::{$this->optionsMethod} - " . $e->getMessage());
                $this->options = [];
            }
        } else {
            $this->options = [];
        }
    }

    public function getOptions(): array {
        return $this->options;
    }
}
