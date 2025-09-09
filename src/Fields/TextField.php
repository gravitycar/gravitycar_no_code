<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
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
    protected string $reactComponent = 'TextInput';
    
    /** @var array Text field specific operators */
    protected array $operators = [
        'equals', 'notEquals', 'contains', 'startsWith', 'endsWith', 
        'in', 'notIn', 'isNull', 'isNotNull'
    ];

    public function __construct(array $metadata, ?Logger $logger = null) {
        parent::__construct($metadata, $logger);
        // ingestMetadata() in parent constructor now handles all property assignments
    }

    /**
     * Generate OpenAPI schema for text field
     */
    public function generateOpenAPISchema(): array {
        $schema = [
            'type' => 'string'
        ];
        
        if (isset($this->metadata['maxLength'])) {
            $schema['maxLength'] = $this->metadata['maxLength'];
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
