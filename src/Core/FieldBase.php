<?php
namespace Gravitycar\Core;

use Monolog\Logger;
use Gravitycar\Exceptions\GCException;

/**
 * Abstract base class for all field types in Gravitycar.
 * Handles value, validation, metadata, and logging.
 */
abstract class FieldBase {
    /** @var string */
    protected string $name;
    /** @var mixed */
    protected $value;
    /** @var mixed */
    protected $originalValue;
    /** @var array */
    protected array $metadata;
    /** @var array */
    protected array $validationRules = [];
    /** @var Logger */
    protected Logger $logger;

    public function __construct(array $metadata, Logger $logger) {
        if (empty($metadata['name'])) {
            throw new GCException('Field metadata missing name',
                ['metadata' => $metadata]);
        }
        $this->name = $metadata['name'];
        $this->metadata = $metadata;
        $this->logger = $logger;
        $this->value = $metadata['defaultValue'] ?? null;
        $this->originalValue = $this->value;
        $this->validationRules = $metadata['validationRules'] ?? [];
    }

    public function getName(): string {
        return $this->name;
    }

    public function getValue() {
        return $this->value;
    }

    public function setValue($value): void {
        $this->originalValue = $this->value;
        $this->value = $value;
        $this->validate();
    }

    public function validate(): bool {
        foreach ($this->validationRules as $ruleName) {
            $ruleClass = "\\Gravitycar\\Validation\\" . $ruleName . "Validation";
            if (!class_exists($ruleClass)) {
                $this->logger->warning("Validation rule class $ruleClass does not exist for field {$this->name}");
                continue;
            }
            $rule = new $ruleClass($this->logger);
            if (!$rule->validate($this->value)) {
                $this->logger->error("Validation failed for field {$this->name} with rule $ruleName");
                return false;
            }
        }
        return true;
    }
}
