<?php
namespace Gravitycar\Relationships;

use Monolog\Logger;
use Gravitycar\Exceptions\GCException;

/**
 * Abstract base class for all relationships in Gravitycar.
 * Handles relationship metadata, validation, and logging.
 */
abstract class RelationshipBase {
    /** @var string */
    protected string $name;
    /** @var string */
    protected string $type;
    /** @var array */
    protected array $fields = [];
    /** @var array */
    protected array $metadata;
    /** @var Logger */
    protected Logger $logger;

    public function __construct(array $metadata, Logger $logger) {
        if (empty($metadata['name'])) {
            throw new GCException('Relationship metadata missing name', $logger);
        }
        $this->name = $metadata['name'];
        $this->type = $metadata['type'] ?? 'N_M';
        $this->metadata = $metadata;
        $this->logger = $logger;
        $this->fields = $metadata['fields'] ?? [];
    }

    public function getName(): string {
        return $this->name;
    }

    public function getType(): string {
        return $this->type;
    }

    public function getFields(): array {
        return $this->fields;
    }

    public function validate(): bool {
        foreach ($this->fields as $fieldName => $fieldMeta) {
            if (empty($fieldMeta['name'])) {
                $this->logger->error("Relationship field $fieldName missing name");
                return false;
            }
        }
        return true;
    }
}
