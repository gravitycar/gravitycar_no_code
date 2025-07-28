<?php
namespace Gravitycar\Relationships;

use Gravitycar\Relationships\RelationshipBase;
use Monolog\Logger;

/**
 * OneToOneRelationship: Represents a one-to-one relationship between two models.
 */
class OneToOneRelationship extends RelationshipBase {
    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
    }

    /**
     * Validate the relationship structure
     */
    public function validate(): bool {
        if (empty($this->metadata['model_a']) || empty($this->metadata['model_b'])) {
            $this->logger->error("OneToOne relationship missing model_a or model_b");
            return false;
        }

        return parent::validate();
    }
}
