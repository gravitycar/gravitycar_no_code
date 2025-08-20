<?php
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * ImageField: Field for storing and displaying image file paths or URLs.
 */
class ImageField extends FieldBase {
    protected string $type = 'Image';
    protected string $label = '';
    protected bool $required = false;
    protected int $maxLength = 500;
    protected $width = null;
    protected $height = null;
    protected string $altText = '';
    protected bool $allowLocal = true;
    protected bool $allowRemote = true;
    protected string $placeholder = 'Enter image path or URL';
    protected string $reactComponent = 'ImageUpload';
    
    /** @var array Image fields have very limited filtering capabilities */
    protected array $operators = ['equals', 'notEquals', 'isNull', 'isNotNull'];

    public function __construct(array $metadata) {
        parent::__construct($metadata);
        // ingestMetadata() in parent constructor now handles all property assignments
    }
}
