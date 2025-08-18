<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * EmailField: Input field for email addresses with format validation.
 */
class EmailField extends FieldBase {
    protected string $type = 'Email';
    protected string $label = '';
    protected bool $required = false;
    protected int $maxLength = 254;
    protected string $placeholder = 'Enter email address';
    protected bool $normalize = true;
    
    /** @var array Email-specific operators (text-like but more restricted) */
    protected array $operators = [
        'equals', 'notEquals', 'contains', 'startsWith', 'endsWith', 
        'in', 'notIn', 'isNull', 'isNotNull'
    ];

    public function __construct(array $metadata) {
        parent::__construct($metadata);
        // ingestMetadata() in parent constructor now handles all property assignments
    }

    public function setValue($value): void {
        if ($this->normalize && is_string($value)) {
            $value = strtolower($value);
        }
        parent::setValue($value);
    }
}
