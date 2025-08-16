<?php
namespace Gravitycar\Metadata;

use Gravitycar\Exceptions\GCException;
use Gravitycar\Metadata\CoreFieldsMetadata;
use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;

/**
 * MetadataEngine: Centralized singleton for loading, validating, and caching metadata for models and relationships.
 * Provides lazy-loading pattern to eliminate repeated file I/O during model/relationship instantiation.
 */
class MetadataEngine {
    /** @var MetadataEngine|null Singleton instance */
    private static ?MetadataEngine $instance = null;
    
    /** @var string */
    protected string $modelsDirPath = 'src/Models';
    /** @var string */
    protected string $relationshipsDirPath = 'src/Relationships';
    /** @var string */
    protected string $cacheDirPath = 'cache/';
    /** @var Logger|null */
    protected ?Logger $logger = null;
    /** @var array */
    protected array $metadataCache = [];
    /** @var array */
    protected array $coreFieldsCache = [];
    /** @var CoreFieldsMetadata|null */
    protected ?CoreFieldsMetadata $coreFieldsMetadata = null;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        // Initialize with default values to avoid circular dependency
        // Services will be injected later when needed
        $this->modelsDirPath = 'src/Models';
        $this->relationshipsDirPath = 'src/Relationships';
        $this->cacheDirPath = 'cache/';
        $this->coreFieldsMetadata = new CoreFieldsMetadata();
        $this->metadataCache = $this->getCachedMetadata();
    }

    /**
     * Initialize services (called lazily to avoid circular dependency)
     */
    private function initializeServices(): void {
        if ($this->logger === null) {
            $this->logger = ServiceLocator::getLogger();
            $config = ServiceLocator::getConfig();
            $this->modelsDirPath = $config->get('metadata.models_dir_path', 'src/Models');
            $this->relationshipsDirPath = $config->get('metadata.relationships_dir_path', 'src/Relationships');
            $this->cacheDirPath = $config->get('metadata.cache_dir_path', 'cache/');
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): MetadataEngine {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reset singleton instance (useful for testing)
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
        $this->initializeServices(); // Ensure services are initialized
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
        $this->initializeServices(); // Ensure services are initialized
        
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
        $this->initializeServices(); // Ensure services are initialized
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
        $metadata = [
            'models' => $models,
            'relationships' => $relationships,
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
                        $metadata[$className] = $data;
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
}
