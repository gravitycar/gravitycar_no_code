<?php
namespace Gravitycar\Metadata;

use Gravitycar\Exceptions\GCException;
use Gravitycar\Metadata\CoreFieldsMetadata;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\LoggerInterface;
use Gravitycar\Core\Config;
use Monolog\Logger;

/**
 * MetadataEngine: Centralized for loading, validating, and caching metadata for models and relationships.
 * Provides lazy-loading pattern to eliminate repeated file I/O during model/relationship instantiation.
 * Now uses proper DI instead of singleton pattern.
 */
class MetadataEngine implements MetadataEngineInterface {
    /** @var MetadataEngine|null Singleton instance (deprecated - kept for backward compatibility) */
    private static ?MetadataEngine $instance = null;
    
    /** @var string */
    protected string $modelsDirPath = 'src/Models';
    /** @var string */
    protected string $relationshipsDirPath = 'src/Relationships';
    /** @var string */
    protected string $fieldsDirPath = 'src/Fields';
    /** @var string */
    protected string $cacheDirPath = 'cache/';
    /** @var LoggerInterface|Logger */
    protected $logger;
    /** @var Config */
    protected Config $config;
    /** @var array */
    protected array $metadataCache = [];
    /** @var array */
    protected array $coreFieldsCache = [];
    /** @var CoreFieldsMetadata */
    protected CoreFieldsMetadata $coreFieldsMetadata;

    /**
     * Constructor with proper dependency injection
     */
    public function __construct($logger, Config $config, ?CoreFieldsMetadata $coreFieldsMetadata = null) {
        $this->logger = $logger;
        $this->config = $config;
        $this->coreFieldsMetadata = $coreFieldsMetadata ?? new CoreFieldsMetadata();
        
        // Initialize paths from config
        $this->modelsDirPath = $config->get('metadata.models_dir_path', 'src/Models');
        $this->relationshipsDirPath = $config->get('metadata.relationships_dir_path', 'src/Relationships');
        $this->fieldsDirPath = $config->get('metadata.fields_dir_path', 'src/Fields');
        $this->cacheDirPath = $config->get('metadata.cache_dir_path', 'cache/');
        
        // Load cached metadata
        $this->metadataCache = $this->getCachedMetadata();
    }

    /**
     * Private constructor for legacy singleton pattern (deprecated)
     * @deprecated Use dependency injection instead
     */
    private function constructSingleton() {
        // Initialize with default values to avoid circular dependency
        // Services will be injected later when needed
        $this->modelsDirPath = 'src/Models';
        $this->relationshipsDirPath = 'src/Relationships';
        $this->fieldsDirPath = 'src/Fields';
        $this->cacheDirPath = 'cache/';
        $this->coreFieldsMetadata = new CoreFieldsMetadata();
        $this->metadataCache = $this->getCachedMetadata();
    }

    /**
     * Create a field instance for getting React component information
     */
    private function createFieldInstance(string $fieldClassName, array $metadata = []): object {
        return new $fieldClassName($metadata);
    }

    /**
     * Get singleton instance (deprecated - use DI instead)
     * @deprecated Use dependency injection instead of singleton pattern
     */
    public static function getInstance(): MetadataEngine {
        if (self::$instance === null) {
            // Create instance using ServiceLocator for backward compatibility
            $logger = ServiceLocator::getLogger();
            $config = ServiceLocator::getConfig();
            self::$instance = new self($logger, $config);
        }
        return self::$instance;
    }

    /**
     * Reset singleton instance (useful for testing)
     * @deprecated Use dependency injection instead of singleton pattern
     */
    public static function reset(): void {
        self::$instance = null;
    }

    /**
     * Build model metadata file path
     */
    public function buildModelMetadataPath(string $modelName): string {
        $modelNameLc = strtolower($this->resolveModelName($modelName));
        return "{$this->modelsDirPath}/{$modelNameLc}/{$modelNameLc}_metadata.php";
    }

    /**
     * Build relationship metadata file path
     */
    public function buildRelationshipMetadataPath(string $relationshipName): string {
        $relationshipNameLc = strtolower($relationshipName);
        return "{$this->relationshipsDirPath}/{$relationshipNameLc}/{$relationshipNameLc}_metadata.php";
    }

    /**
     * Resolve model name from class name or simple name
     */
    public function resolveModelName(string $modelName): string {
        // If it's a fully qualified class name, get the base name
        if (strpos($modelName, '\\') !== false) {
            $modelName = basename(str_replace('\\', '/', $modelName));
        }
        return $modelName;
    }

    /**
     * Get model metadata for a specific model (case-sensitive)
     * Throws exception if exact model name is not found in cache
     */
    public function getModelMetadata(string $modelName): array {
        $resolvedName = $this->resolveModelName($modelName);
        
        // Check if already cached - must be exact match (case-sensitive)
        if (isset($this->metadataCache['models'][$resolvedName])) {
            return $this->metadataCache['models'][$resolvedName];
        }

        // If not found in cache, throw exception - no fallback to file system
        $this->logger->warning("Model metadata not found in cache", [
            'requested' => $resolvedName,
            'available_models' => array_keys($this->metadataCache['models'] ?? [])
        ]);
        
        throw new GCException("Model metadata not found for '{$resolvedName}'", [
            'model' => $resolvedName,
            'available_models' => array_keys($this->metadataCache['models'] ?? [])
        ]);
    }

    /**
     * Get relationship metadata for a specific relationship (case-sensitive)
     * Throws exception if exact relationship name is not found in cache
     */
    public function getRelationshipMetadata(string $relationshipName): array {
        // Check if already cached - must be exact match (case-sensitive)
        if (isset($this->metadataCache['relationships'][$relationshipName])) {
            return $this->metadataCache['relationships'][$relationshipName];
        }

        // If not found in cache, throw exception - no fallback to file system
        $this->logger->warning("Relationship metadata not found in cache", [
            'requested' => $relationshipName,
            'available_relationships' => array_keys($this->metadataCache['relationships'] ?? [])
        ]);
        
        throw new GCException("Relationship metadata not found for '{$relationshipName}'", [
            'relationship' => $relationshipName,
            'available_relationships' => array_keys($this->metadataCache['relationships'] ?? [])
        ]);
    }

    /**
     * Get core fields metadata (cached)
     */
    public function getCoreFieldsMetadata(): array {
        if (empty($this->coreFieldsCache)) {
            if ($this->coreFieldsMetadata === null) {
                $this->coreFieldsMetadata = new CoreFieldsMetadata();
            }
            $this->coreFieldsCache = $this->coreFieldsMetadata->getStandardCoreFields();
        }
        return $this->coreFieldsCache;
    }

    /**
     * Clear cache for specific entity
     */
    public function clearCacheForEntity(string $entityName): void {
        $resolvedName = $this->resolveModelName($entityName);
        
        // Clear from model cache
        unset($this->metadataCache['models'][$resolvedName]);
        
        // Clear from relationship cache  
        unset($this->metadataCache['relationships'][$entityName]);
        
        $this->logger->info("Cache cleared for entity: {$entityName}");
    }

    /**
     * Clear all metadata caches
     */
    public function clearAllCaches(): void {
        $this->metadataCache = [];
        $this->coreFieldsCache = [];
        
        $this->logger->info("All metadata caches cleared");
    }

    /**
     * Scan, load, and validate all metadata files
     * Uses cached metadata if available to improve performance
     */
    public function loadAllMetadata(): array {
        // Check for existing cache first to avoid unnecessary file I/O
        $cachedMetadata = $this->getCachedMetadata();
        if (!empty($cachedMetadata)) {
            $this->logger->debug("Using cached metadata", [
                'models_count' => count($cachedMetadata['models'] ?? []),
                'relationships_count' => count($cachedMetadata['relationships'] ?? [])
            ]);
            return $cachedMetadata;
        }

        // Cache not available or empty, rebuild from files
        $this->logger->info("Rebuilding metadata cache from files");
        $models = $this->scanAndLoadMetadata($this->modelsDirPath);
        $relationships = $this->scanAndLoadMetadata($this->relationshipsDirPath);
        $fieldTypes = $this->scanAndLoadFieldTypes();
        $metadata = [
            'models' => $models,
            'relationships' => $relationships,
            'field_types' => $fieldTypes,
        ];
        $this->validateMetadata($metadata);
        $this->cacheMetadata($metadata);
        return $metadata;
    }

    /**
     * Scan a directory for metadata files and load them
     */
    protected function scanAndLoadMetadata(string $dirPath): array {
        $metadata = [];
        if (!is_dir($dirPath)) {
            $this->logger->warning("Metadata directory not found: $dirPath");
            return $metadata;
        }

        // Get core fields once and reuse for all entities
        $coreFields = $this->getCoreFieldsMetadata();

        $dirs = scandir($dirPath);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') continue;

            $subDir = $dirPath . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($subDir)) continue;

            $files = scandir($subDir);
            foreach ($files as $file) {
                if (preg_match('/^(.*)_metadata\.php$/', $file, $matches)) {
                    $filePath = $subDir . DIRECTORY_SEPARATOR . $file;
                    $data = include $filePath;
                    if (is_array($data)) {
                        // Use the actual class name from metadata instead of filename
                        $className = $data['name'] ?? $matches[1];
                        
                        // Merge core fields with entity-specific fields
                        // Entity-specific fields override core fields
                        if (isset($data['fields']) && is_array($data['fields'])) {
                            $data['fields'] = array_merge($coreFields, $data['fields']);
                        } else {
                            // If no entity-specific fields, use only core fields
                            $data['fields'] = $coreFields;
                        }
                        
                        // Resolve external options for fields that specify optionsClass/optionsMethod
                        $this->resolveExternalFieldOptions($data['fields']);
                        
                        $metadata[$className] = $data;
                        
                        $this->logger->debug("Merged core fields with entity metadata", [
                            'entity' => $className,
                            'core_fields_count' => count($coreFields),
                            'entity_fields_count' => count($data['fields']) - count($coreFields),
                            'total_fields_count' => count($data['fields'])
                        ]);
                    } else {
                        $this->logger->warning("Invalid metadata format in file: $filePath");
                    }
                }
            }
        }
        return $metadata;
    }

    /**
     * Validate loaded metadata for consistency and correctness
     */
    protected function validateMetadata(array $metadata): void {
        // TODO: Implement detailed validation logic
        $this->logger->info("Validating metadata files");
    }

    /**
     * Cache metadata for performance
     */
    protected function cacheMetadata(array $metadata): void {
        if (!is_dir($this->cacheDirPath)) {
            mkdir($this->cacheDirPath, 0755, true);
        }

        $cacheFile = $this->cacheDirPath . 'metadata_cache.php';
        $content = '<?php return ' . var_export($metadata, true) . ';';
        if (file_put_contents($cacheFile, $content) === false) {
            $this->logger->warning("Failed to write metadata cache file: $cacheFile");
        } else {
            $this->logger->info("Metadata cache written: $cacheFile");
        }
        $this->metadataCache = $metadata;
    }

    /**
     * Resolve external options for fields that specify optionsClass and optionsMethod
     */
    protected function resolveExternalFieldOptions(array &$fields): void {
        foreach ($fields as $fieldName => &$fieldData) {
            // Only process fields that have optionsClass and optionsMethod but no existing options
            if (isset($fieldData['optionsClass']) && isset($fieldData['optionsMethod'])) {
                $optionsClass = $fieldData['optionsClass'];
                $optionsMethod = $fieldData['optionsMethod'];
                
                // Skip if options already exist and are not empty
                if (isset($fieldData['options']) && !empty($fieldData['options'])) {
                    continue;
                }
                
                try {
                    // Load options from external class
                    if (class_exists($optionsClass) && method_exists($optionsClass, $optionsMethod)) {
                        $options = call_user_func([$optionsClass, $optionsMethod]);
                        if (is_array($options) && !empty($options)) {
                            $fieldData['options'] = $options;
                            $this->logger->debug("Resolved external options for field", [
                                'field' => $fieldName,
                                'class' => $optionsClass,
                                'method' => $optionsMethod,
                                'options_count' => count($options)
                            ]);
                        } else {
                            $this->logger->warning("External options method returned empty or invalid data", [
                                'field' => $fieldName,
                                'class' => $optionsClass,
                                'method' => $optionsMethod
                            ]);
                            $fieldData['options'] = [];
                        }
                    } else {
                        $this->logger->error("External options class or method not found", [
                            'field' => $fieldName,
                            'class' => $optionsClass,
                            'method' => $optionsMethod,
                            'class_exists' => class_exists($optionsClass),
                            'method_exists' => method_exists($optionsClass, $optionsMethod)
                        ]);
                        $fieldData['options'] = [];
                    }
                } catch (\Exception $e) {
                    $this->logger->error("Error resolving external options for field", [
                        'field' => $fieldName,
                        'class' => $optionsClass,
                        'method' => $optionsMethod,
                        'error' => $e->getMessage()
                    ]);
                    $fieldData['options'] = [];
                }
            }
        }
    }

    /**
     * Get cached metadata
     */
    public function getCachedMetadata(): array {
        if (!empty($this->metadataCache)) {
            return $this->metadataCache;
        }

        $cacheFile = $this->cacheDirPath . 'metadata_cache.php';
        if (file_exists($cacheFile)) {
            $data = include $cacheFile;
            if (is_array($data)) {
                $this->metadataCache = $data;
                return $data;
            }
        }
        return [];
    }

    /**
     * Get all available model names from cache
     */
    public function getAvailableModels(): array {
        $cachedMetadata = $this->getCachedMetadata();
        return array_keys($cachedMetadata['models'] ?? []);
    }
    
    /**
     * Get model summary information for API discovery
     */
    public function getModelSummaries(): array {
        $cachedMetadata = $this->getCachedMetadata();
        $summaries = [];
        
        foreach ($cachedMetadata['models'] ?? [] as $modelName => $modelData) {
            $summaries[$modelName] = [
                'name' => $modelName,
                'table' => $modelData['table'] ?? strtolower($modelName),
                'description' => $modelData['description'] ?? "Model for {$modelName}",
                'fieldCount' => count($modelData['fields'] ?? []),
                'relationshipCount' => count($modelData['relationships'] ?? [])
            ];
        }
        
        return $summaries;
    }
    
    /**
     * Get all relationships across all models
     */
    public function getAllRelationships(): array {
        $cachedMetadata = $this->getCachedMetadata();
        $allRelationships = [];
        
        // Collect relationships from models
        foreach ($cachedMetadata['models'] ?? [] as $modelName => $modelData) {
            if (isset($modelData['relationships'])) {
                foreach ($modelData['relationships'] as $relationshipName => $relationshipData) {
                    // Ensure relationship data is an array
                    if (!is_array($relationshipData)) {
                        continue;
                    }
                    
                    $allRelationships["{$modelName}.{$relationshipName}"] = array_merge(
                        $relationshipData,
                        ['source_model' => $modelName]
                    );
                }
            }
        }
        
        // Add standalone relationships
        foreach ($cachedMetadata['relationships'] ?? [] as $relationshipName => $relationshipData) {
            $allRelationships[$relationshipName] = $relationshipData;
        }
        
        return $allRelationships;
    }
    
    /**
     * Get field type definitions from cache (dynamically discovered)
     */
    public function getFieldTypeDefinitions(): array {
        $cachedMetadata = $this->getCachedMetadata();
        return $cachedMetadata['field_types'] ?? [];
    }
    
    /**
     * Check if model exists in cache
     */
    public function modelExists(string $modelName): bool {
        $cachedMetadata = $this->getCachedMetadata();
        return isset($cachedMetadata['models'][$modelName]);
    }

    /**
     * Scan and discover all FieldBase subclasses dynamically with React metadata
     */
    protected function scanAndLoadFieldTypes(): array {
        $fieldTypes = [];
        
        if (!is_dir($this->fieldsDirPath)) {
            $this->logger->warning("Fields directory not found: {$this->fieldsDirPath}");
            return $fieldTypes;
        }
        
        $files = glob($this->fieldsDirPath . '/*.php');
        foreach ($files as $filePath) {
            $fileName = basename($filePath, '.php');
            
            // Skip base classes and interfaces
            if (in_array($fileName, ['FieldBase', 'FieldInterface'])) {
                continue;
            }
            
            try {
                $className = "Gravitycar\\Fields\\{$fileName}";
                
                if (!class_exists($className)) {
                    continue;
                }
                
                $reflection = new \ReflectionClass($className);
                
                // Skip abstract classes and interfaces
                if ($reflection->isAbstract() || $reflection->isInterface()) {
                    continue;
                }
                
                // Check if it's a FieldBase subclass
                if (!$reflection->isSubclassOf('Gravitycar\\Fields\\FieldBase')) {
                    continue;
                }
                
                $fieldType = $this->extractFieldTypeFromClassName($fileName);
                
                $fieldTypeData = [
                    'type' => $fieldType,
                    'class' => $className,
                    'description' => $this->getStaticProperty($reflection, 'description', 
                        $this->generateDescriptionFromClassName($fileName)),
                    'react_component' => $this->getReactComponentForFieldType($fieldType),
                    'validation_rules' => $this->getSupportedValidationRulesForFieldType($fieldType),
                    'operators' => $this->getFieldOperators($reflection, $className)
                ];
                
                $fieldTypes[$fieldType] = $fieldTypeData;
                
            } catch (\Exception $e) {
                $this->logger->warning("Error processing field type {$fileName}: " . $e->getMessage());
            }
        }
        
        return $fieldTypes;
    }

    /**
     * Extract field type from class name for FieldFactory
     */
    private function extractFieldTypeFromClassName(string $className): string {
        // Convert "TextField" to "Text", "EmailField" to "Email", etc.
        return str_replace('Field', '', $className);
    }

    /**
     * Safely get static property value with fallback
     */
    private function getStaticProperty(\ReflectionClass $reflection, string $propertyName, $fallback) {
        if ($reflection->hasProperty($propertyName) && $reflection->getProperty($propertyName)->isStatic()) {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            return $property->getValue();
        }
        return $fallback;
    }

    /**
     * Generate fallback description from class name
     */
    private function generateDescriptionFromClassName(string $className): string {
        // Convert "TextField" to "Text field"
        $words = preg_split('/(?=[A-Z])/', $className, -1, PREG_SPLIT_NO_EMPTY);
        return implode(' ', array_map('strtolower', $words));
    }

    /**
     * Get React component mapping for field type using FieldFactory pattern
     */
    private function getReactComponentForFieldType(string $fieldType): string {
        try {
            // Build field class name from field type
            $fieldClassName = "Gravitycar\\Fields\\{$fieldType}Field";
            
            // Create field instance using internal method
            $fieldInstance = $this->createFieldInstance($fieldClassName, []);
            
            // Get React component from field instance
            return $fieldInstance->getReactComponent();
            
        } catch (\Exception $e) {
            // Fallback to TextInput if field creation fails
            if ($this->logger) {
                $this->logger->warning("Failed to get React component for field type '{$fieldType}': " . $e->getMessage());
            }
            return 'TextInput';
        }
    }

    /**
     * Get supported validation rules for a field type (what it CAN support, not what's configured)
     */
    private function getSupportedValidationRulesForFieldType(string $fieldType): array {
        // Get all available validation rules from validation directory
        $supportedRules = [];
        $validationDir = 'src/Validation';
        
        if (!is_dir($validationDir)) {
            return $supportedRules;
        }
        
        $files = glob($validationDir . '/*Validation.php');
        foreach ($files as $filePath) {
            $fileName = basename($filePath, '.php');
            $className = "Gravitycar\\Validation\\{$fileName}";
            
            if (class_exists($className)) {
                try {
                    $reflection = new \ReflectionClass($className);
                    if (!$reflection->isAbstract() && 
                        $reflection->isSubclassOf('Gravitycar\\Validation\\ValidationRuleBase')) {
                        
                        $ruleName = $this->extractRuleNameFromClass($fileName);
                        $ruleInstance = new $className();
                        
                        $supportedRules[] = [
                            'name' => $ruleName,
                            'class' => $className,
                            'description' => $this->getValidationRuleDescription($ruleInstance),
                            'javascript_validation' => $this->getJavaScriptValidation($ruleInstance)
                        ];
                    }
                } catch (\Exception $e) {
                    $this->logger->warning("Error processing validation rule {$fileName}: " . $e->getMessage());
                }
            }
        }
        
        return $supportedRules;
    }

    /**
     * Extract rule name from validation rule class name
     */
    private function extractRuleNameFromClass(string $className): string {
        // Extract the class name without namespace and "Validation" suffix
        $shortClassName = str_replace('Validation', '', $className);
        return $shortClassName;
    }

    /**
     * Get human-readable description from validation rule instance
     */
    private function getValidationRuleDescription(\Gravitycar\Validation\ValidationRuleBase $ruleInstance): string {
        $reflection = new \ReflectionClass($ruleInstance);
        
        // Try to get static description property
        $description = $this->getStaticProperty($reflection, 'description', '');
        
        if (empty($description)) {
            // Fallback to error message or generate from class name
            $description = $ruleInstance->getErrorMessage();
            if (empty($description)) {
                $className = $reflection->getShortName();
                $description = $this->generateDescriptionFromClassName($className);
            }
        }
        
        return $description;
    }

    /**
     * Get JavaScript validation from rule instance
     */
    private function getJavaScriptValidation(\Gravitycar\Validation\ValidationRuleBase $ruleInstance): string {
        if (method_exists($ruleInstance, 'getJavascriptValidation')) {
            return $ruleInstance->getJavascriptValidation();
        }
        return '';
    }

    /**
     * Get field operators from reflection or default
     */
    private function getFieldOperators(\ReflectionClass $reflection, string $className): array {
        $defaultOperators = ['equals', 'notEquals', 'isNull', 'isNotNull'];
        
        try {
            if ($reflection->hasProperty('operators')) {
                $property = $reflection->getProperty('operators');
                $property->setAccessible(true);
                
                // Create temporary instance to get operators
                $instance = $reflection->newInstanceWithoutConstructor();
                return $property->getValue($instance) ?? $defaultOperators;
            }
        } catch (\Exception $e) {
            $this->logger->debug("Could not get operators for {$className}: " . $e->getMessage());
        }
        
        return $defaultOperators;
    }

    /**
     * Extract validation rules that a specific field instance actually has configured
     */
    private function getFieldValidationRules($fieldInstance): array {
        $rules = [];
        
        try {
            // Introspect the field instance's actual validation rules
            $reflection = new \ReflectionClass($fieldInstance);
            $validationRulesProperty = $reflection->getProperty('validationRules');
            $validationRulesProperty->setAccessible(true);
            $validationRules = $validationRulesProperty->getValue($fieldInstance);
            
            foreach ($validationRules as $ruleInstance) {
                if ($ruleInstance instanceof \Gravitycar\Validation\ValidationRuleBase) {
                    $rules[] = [
                        'name' => $this->extractRuleNameFromClass(get_class($ruleInstance)),
                        'class' => get_class($ruleInstance),
                        'description' => $this->getValidationRuleDescription($ruleInstance),
                        'javascript_validation' => $this->getJavaScriptValidation($ruleInstance)
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning("Error extracting validation rules from field instance: " . $e->getMessage());
        }
        
        return $rules;
    }

    /**
     * Get all loaded metadata (implementation of MetadataEngineInterface)
     */
    public function getAllMetadata(): array {
        if (empty($this->metadataCache)) {
            $this->metadataCache = $this->loadAllMetadata();
        }
        return $this->metadataCache;
    }

    /**
     * Check if metadata is loaded (implementation of MetadataEngineInterface)
     */
    public function isLoaded(): bool {
        return !empty($this->metadataCache);
    }

    /**
     * Check if metadata is loaded for a model (implementation of MetadataEngineInterface)
     */
    public function isMetadataLoaded(string $modelName): bool {
        $resolvedName = $this->resolveModelName($modelName);
        return isset($this->metadataCache['models'][$resolvedName]);
    }

    /**
     * Reload metadata from disk (implementation of MetadataEngineInterface)
     */
    public function reloadMetadata(): void {
        $this->metadataCache = [];
        $this->metadataCache = $this->loadAllMetadata();
    }
}
