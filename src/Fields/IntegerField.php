<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * IntegerField: Input field for integer values with range validation.
 */
class IntegerField extends FieldBase {
    protected string $type = 'Integer';
    protected string $label = '';
    protected bool $required = false;
    protected $defaultValue = null;
    protected $minValue = null;
    protected $maxValue = null;
    protected bool $allowNegative = true;
    protected int $step = 1;
    protected string $placeholder = 'Enter a number';
    protected bool $showSpinners = true;
    protected string $reactComponent = 'NumberInput';
    
    /** @var array Numeric operators for integer fields */
    protected array $operators = [
        'equals', 'notEquals', 'greaterThan', 'greaterThanOrEqual', 
        'lessThan', 'lessThanOrEqual', 'between', 'in', 'notIn', 
        'isNull', 'isNotNull'
    ];

    public function __construct(array $metadata) {
        parent::__construct($metadata);
        // ingestMetadata() in parent constructor now handles all property assignments
    }

    /**
     * Generate OpenAPI schema for integer field
     */
    public function generateOpenAPISchema(): array {
        $schema = [
            'type' => 'integer'
        ];
        
        if (isset($this->metadata['minValue'])) {
            $schema['minimum'] = $this->metadata['minValue'];
        }
        
        if (isset($this->metadata['maxValue'])) {
            $schema['maximum'] = $this->metadata['maxValue'];
        }
        
        if (isset($this->metadata['description'])) {
            $schema['description'] = $this->metadata['description'];
        }
        
        if (isset($this->metadata['example'])) {
            $schema['example'] = $this->metadata['example'];
        }
        
        return $schema;
    }
}
