<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
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
    protected string $optionsClass = '';
    protected string $optionsMethod = '';
    protected array $options = [];
    protected string $reactComponent = 'Select';
    
    /** @var array Enum-specific operators for single value selection */
    protected array $operators = ['equals', 'notEquals', 'in', 'notIn', 'isNull', 'isNotNull'];

    public function __construct(array $metadata) {
        parent::__construct($metadata);
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
                    error_log("EnumField: Unable to load options from {$this->optionsClass}::{$this->optionsMethod} - class or method not found");
                    $this->options = [];
                }
            } catch (\Exception $e) {
                error_log("EnumField: Error loading options from {$this->optionsClass}::{$this->optionsMethod} - " . $e->getMessage());
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
