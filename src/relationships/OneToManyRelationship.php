<?php
namespace Gravitycar\Relationships;

use Gravitycar\Relationships\RelationshipBase;
use Monolog\Logger;

/**
 * OneToManyRelationship: Represents a one-to-many relationship between two models.
 */
class OneToManyRelationship extends RelationshipBase {
    public function __construct(array $metadata, Logger $logger) {
        parent::__construct($metadata, $logger);
    }

    /**
     * Validate the relationship structure
     */
    public function validate(): bool {
        // Ensure we have model_a and model_b defined
        if (empty($this->metadata['model_a']) || empty($this->metadata['model_b'])) {
            $this->logger->error("OneToMany relationship missing model_a or model_b");
            return false;
        }
        
        return parent::validate();
    }
}