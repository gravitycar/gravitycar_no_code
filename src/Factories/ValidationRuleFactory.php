<?php
namespace Gravitycar\Factories;

use Gravitycar\Validation\ValidationRuleBase;
use Monolog\Logger;
use Gravitycar\Exceptions\GCException;

/**
 * Factory for creating validation rule instances based on metadata.
 * Discovers available validation rule types and instantiates them dynamically.
 */
class ValidationRuleFactory {
    /** @var Logger */
    protected Logger $logger;
    /** @var array */
    protected array $availableValidationRules = [];

    public function __construct(Logger $logger) {
        $this->logger = $logger;
        $this->discoverValidationRules();
    }

    /**
     * Scan src/validation directory for available validation rule types
     */
    protected function discoverValidationRules(): void {
        $validationDir = __DIR__ . '/../validation';
        if (!is_dir($validationDir)) {
            $this->logger->warning("Validation directory not found: $validationDir");
            return;
        }
        $files = scandir($validationDir);
        foreach ($files as $file) {
            if (preg_match('/^(.*)Validation\.php$/', $file, $matches)) {
                $type = $matches[1];
                $this->availableValidationRules[$type] = "Gravitycar\\Validation\\{$type}Validation";
            }
        }
    }

    /**
     * Create a validation rule instance from name
     */
    public function createValidationRule(string $ruleName): ValidationRuleBase {
        $className = $this->availableValidationRules[$ruleName] ?? null;
        if (!$className || !class_exists($className)) {
            throw new GCException("Validation rule class not found for rule: $ruleName",
                ['rule_name' => $ruleName, 'expected_class' => $className]);
        }
        return new $className($this->logger);
    }

    /**
     * Get all available validation rule types
     */
    public function getAvailableValidationRules(): array {
        return array_keys($this->availableValidationRules);
    }
}
