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

    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
        // ingestMetadata() in parent constructor now handles all property assignments
    }
}
