<?php
namespace Gravitycar\Factories;

use Gravitycar\Validation\ValidationRuleBase;
use Gravitycar\Core\ContainerConfig;
use Gravitycar\Contracts\MetadataEngineInterface;
use Monolog\Logger;
use Gravitycar\Exceptions\GCException;

/**
 * Factory for creating validation rule instances based on cached metadata.
 * Uses pure dependency injection and cached validation rule definitions.
 */
class ValidationRuleFactory {
    /**
     * Constructor with pure dependency injection
     */
    public function __construct(
        private Logger $logger,
        private MetadataEngineInterface $metadataEngine
    ) {
        // All dependencies explicitly injected
        // No ServiceLocator calls
        // No filesystem operations
        // Ready for immediate use
    }

    /**
     * Create a validation rule instance from name using cached metadata
     */
    public function createValidationRule(string $ruleName): ValidationRuleBase {
        // Get class name from cached metadata
        $rules = $this->metadataEngine->getValidationRuleDefinitions();
        $ruleData = $rules[$ruleName] ?? null;
        
        if (!$ruleData || !isset($ruleData['class'])) {
            throw new GCException("Validation rule not found: $ruleName", [
                'rule_name' => $ruleName,
                'available_rules' => array_keys($rules)
            ]);
        }
        
        $className = $ruleData['class'];
        
        if (!class_exists($className)) {
            throw new GCException("Validation rule class does not exist: $className", [
                'rule_name' => $ruleName,
                'class_name' => $className
            ]);
        }

        // Use container for creation (pure DI approach)
        return ContainerConfig::getContainer()->newInstance($className);
    }

    /**
     * Get all available validation rule types from cached metadata
     */
    public function getAvailableValidationRules(): array {
        $rules = $this->metadataEngine->getValidationRuleDefinitions();
        return array_keys($rules);
    }
}
