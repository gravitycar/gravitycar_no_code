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
    
    /** @var array Text field specific operators */
    protected array $operators = [
        'equals', 'notEquals', 'contains', 'startsWith', 'endsWith', 
        'in', 'notIn', 'isNull', 'isNotNull'
    ];

    public function __construct(array $metadata) {
        parent::__construct($metadata);
        // ingestMetadata() in parent constructor now handles all property assignments
    }
}
