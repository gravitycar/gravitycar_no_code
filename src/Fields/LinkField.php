<?php
namespace Gravitycar\Fields;

use Monolog\Logger;

/**
 * LinkField: URL input field that stores and validates http/https links.
 *
 * Stores a URL string (max 256 characters by default). When a value is
 * provided, it is validated by LinkURLValidation, which ensures the scheme
 * is http or https. Empty values are accepted when the field is not required.
 *
 * The $target property controls the HTML <a> target attribute rendered by the
 * LinkInput React component. It defaults to '_blank' and can be overridden
 * per-model in the metadata file. It is automatically serialised into the
 * metadata array by FieldBase::syncPropertiesToMetadata() so the frontend
 * receives it as field.target.
 *
 * Auto-discovered by MetadataEngine via src/Fields/ scan.
 * No manual registration required.
 */
class LinkField extends FieldBase
{
    /** @var string Field type identifier used by MetadataEngine */
    protected string $type = 'Link';

    /** @var string React component name for rendering this field type */
    protected string $reactComponent = 'LinkInput';

    /** @var string Display label (overridden per-model in metadata) */
    protected string $label = '';

    /** @var bool Whether the field must have a value */
    protected bool $required = false;

    /** @var bool Whether the field may store null in the database */
    protected bool $nullable = true;

    /** @var int Maximum URL length in characters */
    protected int $maxLength = 256;

    /** @var string Placeholder text shown in the URL input */
    protected string $placeholder = 'https://...';

    /**
     * Controls the HTML <a> target attribute in the LinkInput React component.
     * Override per-model by setting 'target' in the field's metadata array.
     *
     * @var string
     */
    protected string $target = '_blank';

    /** @var array Supported filter operators for link fields */
    protected array $operators = ['equals', 'notEquals', 'isNull', 'isNotNull'];

    /**
     * Default validation rules applied to every LinkField instance.
     * The 'LinkURL' string is resolved to a LinkURLValidation instance
     * by FieldBase::setUpValidationRules() via ValidationRuleFactory.
     *
     * @var array
     */
    protected array $validationRules = ['LinkURL'];

    /**
     * @param array       $metadata Field metadata; keys map to class properties
     * @param Logger|null $logger   Optional Monolog logger instance
     */
    public function __construct(array $metadata, ?Logger $logger = null)
    {
        parent::__construct($metadata, $logger);
    }

    /**
     * Generate an OpenAPI schema fragment for this field.
     * Reports the field as a URI-format string with the configured max length.
     *
     * @return array OpenAPI schema array
     */
    public function generateOpenAPISchema(): array
    {
        return [
            'type'      => 'string',
            'format'    => 'uri',
            'maxLength' => $this->metadata['maxLength'] ?? $this->maxLength,
        ];
    }
}
