<?php
namespace Gravitycar\Metadata;

use Gravitycar\Exceptions\GCException;
use Monolog\Logger;

/**
 * MetadataEngine: Loads, validates, and caches metadata for models and relationships.
 */
class MetadataEngine {
    /** @var string */
    protected string $modelsDirPath = 'src/models';
    /** @var string */
    protected string $relationshipsDirPath = 'src/relationships';
    /** @var string */
    protected string $cacheDirPath = 'cache/';
    /** @var Logger */
    protected Logger $logger;
    /** @var array */
    protected array $metadataCache = [];

    public function __construct() {
        $this->logger = new Logger(static::class);
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
