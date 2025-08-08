<?php
namespace Gravitycar\Metadata;

use Gravitycar\Exceptions\GCException;
use Gravitycar\Metadata\CoreFieldsMetadata;
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
    /** @var Logger */
    protected Logger $logger;
    /** @var array */
    protected array $metadataCache = [];
    /** @var array */
    protected array $modelMetadataCache = [];
    /** @var array */
    protected array $relationshipMetadataCache = [];
    /** @var array */
    protected array $coreFieldsCache = [];
    /** @var array */
    protected array $currentlyBuilding = [];
    /** @var CoreFieldsMetadata|null */
    protected ?CoreFieldsMetadata $coreFieldsMetadata = null;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct(Logger $logger, string $modelsDirPath = 'src/Models', string $relationshipsDirPath = 'src/Relationships', string $cacheDirPath = 'cache/') {
        $this->logger = $logger;
        $this->modelsDirPath = $modelsDirPath;
        $this->relationshipsDirPath = $relationshipsDirPath;
        $this->cacheDirPath = $cacheDirPath;
        $this->coreFieldsMetadata = new CoreFieldsMetadata($logger);
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(Logger $logger = null, string $modelsDirPath = 'src/Models', string $relationshipsDirPath = 'src/Relationships', string $cacheDirPath = 'cache/'): MetadataEngine {
        if (self::$instance === null) {
            if ($logger === null) {
                throw new GCException("Logger is required for first initialization of MetadataEngine");
            }
            self::$instance = new self($logger, $modelsDirPath, $relationshipsDirPath, $cacheDirPath);
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
     * Lazy load model metadata for a specific model
     */
    public function getModelMetadata(string $modelName): array {
        $resolvedName = $this->resolveModelName($modelName);
        
        // Check if already cached
        if (isset($this->modelMetadataCache[$resolvedName])) {
            return $this->modelMetadataCache[$resolvedName];
        }

        // Prevent circular dependencies
        if (isset($this->currentlyBuilding[$resolvedName])) {
            throw new GCException("Circular dependency detected while loading metadata for model: {$resolvedName}");
        }

        $this->currentlyBuilding[$resolvedName] = true;

        try {
            $metadataPath = $this->buildModelMetadataPath($resolvedName);
            
            if (!file_exists($metadataPath)) {
                $this->logger->warning("Model metadata file not found: {$metadataPath}");
                $metadata = [];
            } else {
                $metadata = include $metadataPath;
                if (!is_array($metadata)) {
                    throw new GCException("Invalid metadata format in file: {$metadataPath}");
                }
            }

            // Include core fields metadata
            $coreFields = $this->getCoreFieldsMetadata();
            if (!empty($coreFields)) {
                $metadata = array_merge($coreFields, $metadata);
            }

            $this->modelMetadataCache[$resolvedName] = $metadata;
            unset($this->currentlyBuilding[$resolvedName]);

            $this->logger->info("Model metadata loaded and cached", [
                'model' => $resolvedName,
                'fields_count' => count($metadata['fields'] ?? []),
                'relationships_count' => count($metadata['relationships'] ?? [])
            ]);

            return $metadata;

        } catch (\Exception $e) {
            unset($this->currentlyBuilding[$resolvedName]);
            $this->logger->error("Failed to load model metadata", [
                'model' => $resolvedName,
                'error' => $e->getMessage()
            ]);
            throw new GCException("Failed to load metadata for model {$resolvedName}: " . $e->getMessage(), [
                'model' => $resolvedName,
                'error' => $e->getMessage()
            ], 0, $e);
        }
    }

    /**
     * Lazy load relationship metadata for a specific relationship
     */
    public function getRelationshipMetadata(string $relationshipName): array {
        // Check if already cached
        if (isset($this->relationshipMetadataCache[$relationshipName])) {
            return $this->relationshipMetadataCache[$relationshipName];
        }

        // Prevent circular dependencies
        if (isset($this->currentlyBuilding["rel_{$relationshipName}"])) {
            throw new GCException("Circular dependency detected while loading metadata for relationship: {$relationshipName}");
        }

        $this->currentlyBuilding["rel_{$relationshipName}"] = true;

        try {
            $metadataPath = $this->buildRelationshipMetadataPath($relationshipName);
            
            if (!file_exists($metadataPath)) {
                $this->logger->warning("Relationship metadata file not found: {$metadataPath}");
                $metadata = [];
            } else {
                $metadata = include $metadataPath;
                if (!is_array($metadata)) {
                    throw new GCException("Invalid metadata format in file: {$metadataPath}");
                }
            }

            $this->relationshipMetadataCache[$relationshipName] = $metadata;
            unset($this->currentlyBuilding["rel_{$relationshipName}"]);

            $this->logger->info("Relationship metadata loaded and cached", [
                'relationship' => $relationshipName,
                'fields_count' => count($metadata['fields'] ?? [])
            ]);

            return $metadata;

        } catch (\Exception $e) {
            unset($this->currentlyBuilding["rel_{$relationshipName}"]);
            $this->logger->error("Failed to load relationship metadata", [
                'relationship' => $relationshipName,
                'error' => $e->getMessage()
            ]);
            throw new GCException("Failed to load metadata for relationship {$relationshipName}: " . $e->getMessage(), [
                'relationship' => $relationshipName,
                'error' => $e->getMessage()
            ], 0, $e);
        }
    }

    /**
     * Get core fields metadata (cached)
     */
    public function getCoreFieldsMetadata(): array {
        if (empty($this->coreFieldsCache)) {
            if ($this->coreFieldsMetadata === null) {
                $this->coreFieldsMetadata = new CoreFieldsMetadata($this->logger);
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
        unset($this->modelMetadataCache[$resolvedName]);
        
        // Clear from relationship cache  
        unset($this->relationshipMetadataCache[$entityName]);
        
        $this->logger->info("Cache cleared for entity: {$entityName}");
    }

    /**
     * Clear all metadata caches
     */
    public function clearAllCaches(): void {
        $this->metadataCache = [];
        $this->modelMetadataCache = [];
        $this->relationshipMetadataCache = [];
        $this->coreFieldsCache = [];
        $this->currentlyBuilding = [];
        
        $this->logger->info("All metadata caches cleared");
    }

    /**
     * Scan, load, and validate all metadata files
     */
    public function loadAllMetadata(): array {
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
                    $name = $matches[1];
                    $filePath = $subDir . DIRECTORY_SEPARATOR . $file;
                    $data = include $filePath;
                    if (is_array($data)) {
                        $metadata[$name] = $data;
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
