<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * PasswordField: Secure input field for passwords and sensitive data.
 */
class PasswordField extends FieldBase {
    protected string $type = 'Password';
    protected string $label = '';
    protected bool $required = true;
    protected int $maxLength = 100;
    protected int $minLength = 8;
    protected bool $showButton = true;
    protected string $placeholder = 'Enter password';
    protected bool $hashOnSave = true;

    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
        // ingestMetadata() in parent constructor now handles all property assignments
    }
}
