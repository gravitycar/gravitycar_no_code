<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * BigTextField: Input field for large text data.
 */
class BigTextField extends FieldBase {
    protected string $type = 'BigText';
    protected string $label = '';
    protected bool $required = false;
    protected int $maxLength = 16000;

    public function __construct(array $metadata) {
        parent::__construct($metadata);
        // ingestMetadata() in parent constructor now handles all property assignments
    }
}
