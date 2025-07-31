<?php
namespace Gravitycar\ComponentGenerator;

use Monolog\Logger;
use Gravitycar\Exceptions\GCException;

/**
 * Abstract base class for all React component generators in Gravitycar.
 * Handles metadata, logging, and dynamic component generation.
 */
abstract class ComponentGeneratorBase {
    /** @var string */
    protected string $name;
    /** @var array */
    protected array $metadata;
    /** @var Logger */
    protected Logger $logger;

    public function __construct(array $metadata, Logger $logger) {
        if (empty($metadata['name'])) {
            throw new GCException('Component generator metadata missing name',
                ['metadata' => $metadata]);
        }
        $this->name = $metadata['name'];
        $this->metadata = $metadata;
        $this->logger = $logger;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getMetadata(): array {
        return $this->metadata;
    }

    /**
     * Generate the React component code for this field type
     */
    abstract public function generateComponent(): string;

    /**
     * Generate form component for create/edit views
     */
    public function generateFormComponent(): string {
        return $this->generateComponent();
    }

    /**
     * Generate list view component for display in tables
     */
    public function generateListViewComponent(): string {
        return $this->generateComponent();
    }
}
