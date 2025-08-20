<?php

namespace Gravitycar\Metadata;

use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;

/**
 * Provides centralized metadata definitions for core fields used across all models.
 * This eliminates duplication and ensures consistency of core field definitions.
 *
 * This service is designed to be dependency-injected and easily mockable for testing.
 */
class CoreFieldsMetadata
{
    /**
     * Cache for compiled core fields per model to avoid repeated computation
     */
    private array $coreFieldsCache = [];

    /**
     * Registry for model-specific core fields
     */
    private array $modelSpecificCoreFields = [];

    /**
     * Cached standard core fields to avoid repeated file loading
     */
    private ?array $standardCoreFields = null;

    private Logger $logger;
    private string $templatePath;

    public function __construct(?string $templatePath = null, ?Logger $logger = null)
    {
        $this->logger = $logger ?? ServiceLocator::getLogger();
        $this->templatePath = $templatePath ?? __DIR__ . '/templates/core_fields_metadata.php';
    }

    /**
     * Get the standard core fields that all models should have
     */
    public function getStandardCoreFields(): array
    {
        // Load once and cache in instance variable for performance
        if ($this->standardCoreFields === null) {
            if (!file_exists($this->templatePath)) {
                $this->logger->error("Core fields metadata template not found", [
                    'template_path' => $this->templatePath
                ]);
                throw new \RuntimeException("Core fields metadata template not found at: {$this->templatePath}");
            }

            $templateResult = include $this->templatePath;

            if (!is_array($templateResult)) {
                $this->logger->error("Core fields metadata template returned invalid data", [
                    'template_path' => $this->templatePath,
                    'returned_type' => gettype($templateResult)
                ]);
                throw new \RuntimeException("Core fields metadata template must return an array");
            }

            $this->standardCoreFields = $templateResult;

            $this->logger->debug('Loaded standard core fields from template', [
                'template_path' => $this->templatePath,
                'fields_count' => count($this->standardCoreFields),
                'field_names' => array_keys($this->standardCoreFields)
            ]);
        }

        return $this->standardCoreFields;
    }

    /**
     * Register additional core fields for a specific model class
     *
     * @param string $modelClass The fully qualified model class name
     * @param array $additionalCoreFields Array of field metadata
     */
    public function registerModelSpecificCoreFields(string $modelClass, array $additionalCoreFields): void
    {
        $this->modelSpecificCoreFields[$modelClass] = $additionalCoreFields;

        // Clear cache for this model to force regeneration
        unset($this->coreFieldsCache[$modelClass]);

        $this->logger->debug('Registered model-specific core fields', [
            'model_class' => $modelClass,
            'additional_fields' => array_keys($additionalCoreFields)
        ]);
    }

    /**
     * Get model-specific core fields for a given model class
     * This includes inheritance - subclasses inherit parent core fields
     */
    public function getModelSpecificCoreFields(string $modelClass): array
    {
        $modelSpecificFields = [];

        // First, check if we have fields registered directly for this model class
        if (isset($this->modelSpecificCoreFields[$modelClass])) {
            $modelSpecificFields = array_merge($modelSpecificFields, $this->modelSpecificCoreFields[$modelClass]);
        }

        // If the class actually exists, also check inheritance chain
        if (class_exists($modelClass)) {
            // Build inheritance chain to collect core fields from all parent classes
            $classHierarchy = $this->getClassHierarchy($modelClass);

            foreach ($classHierarchy as $class) {
                // Skip the current class since we already processed it above
                if ($class !== $modelClass && isset($this->modelSpecificCoreFields[$class])) {
                    $modelSpecificFields = array_merge($modelSpecificFields, $this->modelSpecificCoreFields[$class]);
                }
            }
        }

        return $modelSpecificFields;
    }

    /**
     * Get all core fields for a specific model (standard + model-specific)
     * Results are cached for performance
     */
    public function getAllCoreFieldsForModel(string $modelClass): array
    {
        // Check cache first
        if (isset($this->coreFieldsCache[$modelClass])) {
            return $this->coreFieldsCache[$modelClass];
        }

        // Combine standard and model-specific core fields
        $standardFields = $this->getStandardCoreFields();
        $modelSpecificFields = $this->getModelSpecificCoreFields($modelClass);

        // Model-specific fields can override standard fields
        $allCoreFields = array_merge($standardFields, $modelSpecificFields);

        // Cache the result
        $this->coreFieldsCache[$modelClass] = $allCoreFields;

        $this->logger->debug('Generated core fields for model', [
            'model_class' => $modelClass,
            'standard_fields_count' => count($standardFields),
            'model_specific_fields_count' => count($modelSpecificFields),
            'total_fields_count' => count($allCoreFields)
        ]);

        return $allCoreFields;
    }

    /**
     * Check if a given field name is considered a core field
     */
    public function isCoreField(string $fieldName, string $modelClass = null): bool
    {
        // Check standard core fields first
        $standardFields = $this->getStandardCoreFields();
        if (isset($standardFields[$fieldName])) {
            return true;
        }

        // If model class provided, check model-specific core fields
        if ($modelClass !== null) {
            $modelSpecificFields = $this->getModelSpecificCoreFields($modelClass);
            if (isset($modelSpecificFields[$fieldName])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get names of all core fields for a model
     */
    public function getCoreFieldNames(string $modelClass): array
    {
        $coreFields = $this->getAllCoreFieldsForModel($modelClass);
        return array_keys($coreFields);
    }

    /**
     * Clear all cached core fields (useful for testing or when definitions change)
     */
    public function clearCache(): void
    {
        $this->coreFieldsCache = [];
        $this->standardCoreFields = null;

        $this->logger->debug('Cleared all core fields cache');
    }

    /**
     * Clear cache for a specific model
     */
    public function clearCacheForModel(string $modelClass): void
    {
        unset($this->coreFieldsCache[$modelClass]);

        $this->logger->debug('Cleared core fields cache for model', [
            'model_class' => $modelClass
        ]);
    }

    /**
     * Get the class hierarchy for inheritance-aware core field resolution
     */
    private function getClassHierarchy(string $modelClass): array
    {
        $hierarchy = [];
        $currentClass = $modelClass;

        while ($currentClass !== false && class_exists($currentClass)) {
            $hierarchy[] = $currentClass;
            $currentClass = get_parent_class($currentClass);
        }

        // Reverse to process from base class to specific class
        return array_reverse($hierarchy);
    }

    /**
     * Validate core field metadata structure
     */
    public function validateCoreFieldMetadata(array $fieldMetadata): bool
    {
        $requiredKeys = ['name', 'type', 'label', 'isDBField'];

        foreach ($requiredKeys as $key) {
            if (!isset($fieldMetadata[$key])) {
                $this->logger->warning('Core field metadata validation failed', [
                    'missing_key' => $key,
                    'field_metadata' => $fieldMetadata
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Get core field metadata with model-specific overrides applied
     * This allows models to customize core field properties while maintaining the structure
     */
    public function getCoreFieldWithOverrides(string $fieldName, string $modelClass, array $overrides = []): ?array
    {
        $coreFields = $this->getAllCoreFieldsForModel($modelClass);

        if (!isset($coreFields[$fieldName])) {
            $this->logger->warning('Core field not found for override', [
                'field_name' => $fieldName,
                'model_class' => $modelClass,
                'available_fields' => array_keys($coreFields)
            ]);
            return null;
        }

        $fieldMetadata = $coreFields[$fieldName];

        // Apply overrides (but don't allow changing the core field name or type)
        $protectedKeys = ['name', 'type'];
        foreach ($overrides as $key => $value) {
            if (!in_array($key, $protectedKeys)) {
                $fieldMetadata[$key] = $value;
            } else {
                $this->logger->warning('Attempted to override protected core field property', [
                    'field_name' => $fieldName,
                    'protected_key' => $key,
                    'attempted_value' => $value
                ]);
            }
        }

        $this->logger->debug('Applied core field overrides', [
            'field_name' => $fieldName,
            'model_class' => $modelClass,
            'overrides_applied' => array_keys(array_diff_key($overrides, array_flip($protectedKeys)))
        ]);

        return $fieldMetadata;
    }

    /**
     * Set a custom template path (useful for testing)
     */
    public function setTemplatePath(string $templatePath): void
    {
        $this->templatePath = $templatePath;
        $this->standardCoreFields = null; // Clear cache to force reload

        $this->logger->debug('Updated core fields template path', [
            'new_template_path' => $templatePath
        ]);
    }
}
