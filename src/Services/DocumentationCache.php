<?php
namespace Gravitycar\Services;

use Gravitycar\Core\Config;
use Psr\Log\LoggerInterface;

/**
 * DocumentationCache: Simple file-based caching for generated documentation
 * 
 * Migrated to Pure Dependency Injection - all dependencies explicitly injected
 */
class DocumentationCache {
    private Config $config;
    private LoggerInterface $logger;
    private string $cacheDir;
    
    /**
     * Constructor with explicit dependency injection
     * 
     * @param LoggerInterface $logger Logger for operation logging
     * @param Config $config Configuration access
     */
    public function __construct(
        LoggerInterface $logger,
        Config $config
    ) {
        $this->logger = $logger;
        $this->config = $config;
        $this->cacheDir = $this->config->get('documentation.cache_directory', 'cache/documentation/');
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Get cached OpenAPI specification
     */
    public function getCachedOpenAPISpec(): ?array {
        if (!$this->config->get('documentation.cache_enabled', true)) {
            return null;
        }
        
        $cacheFile = $this->cacheDir . 'openapi_spec.php';
        if (file_exists($cacheFile)) {
            $data = include $cacheFile;
            if (is_array($data) && $this->isCacheValid($data)) {
                return $data;
            }
        }
        return null;
    }
    
    /**
     * Cache OpenAPI specification
     */
    public function cacheOpenAPISpec(array $spec): void {
        if (!$this->config->get('documentation.cache_enabled', true)) {
            return;
        }
        
        $spec['timestamp'] = date('c');
        $cacheFile = $this->cacheDir . 'openapi_spec.php';
        $content = '<?php return ' . var_export($spec, true) . ';';
        
        if (file_put_contents($cacheFile, $content) !== false) {
            if ($this->config->get('documentation.log_cache_operations', false)) {
                $this->logger->info("OpenAPI specification cached", ['file' => $cacheFile]);
            }
        } else {
            $this->logger->warning("Failed to cache OpenAPI specification", ['file' => $cacheFile]);
        }
    }
    
    /**
     * Get cached model metadata
     */
    public function getCachedModelMetadata(string $modelName): ?array {
        if (!$this->config->get('documentation.cache_enabled', true)) {
            return null;
        }
        
        $cacheFile = $this->cacheDir . "model_{$modelName}.php";
        if (file_exists($cacheFile)) {
            $data = include $cacheFile;
            if (is_array($data) && $this->isCacheValid($data)) {
                return $data;
            }
        }
        return null;
    }
    
    /**
     * Cache model metadata
     */
    public function cacheModelMetadata(string $modelName, array $metadata): void {
        if (!$this->config->get('documentation.cache_enabled', true)) {
            return;
        }
        
        $metadata['timestamp'] = date('c');
        $cacheFile = $this->cacheDir . "model_{$modelName}.php";
        $content = '<?php return ' . var_export($metadata, true) . ';';
        
        if (file_put_contents($cacheFile, $content) !== false) {
            if ($this->config->get('documentation.log_cache_operations', false)) {
                $this->logger->info("Model metadata cached", ['model' => $modelName, 'file' => $cacheFile]);
            }
        } else {
            $this->logger->warning("Failed to cache model metadata", ['model' => $modelName, 'file' => $cacheFile]);
        }
    }
    
    /**
     * Get cached models list
     */
    public function getCachedModelsList(): ?array {
        if (!$this->config->get('documentation.cache_enabled', true)) {
            return null;
        }
        
        $cacheFile = $this->cacheDir . 'models_list.php';
        if (file_exists($cacheFile)) {
            $data = include $cacheFile;
            if (is_array($data) && $this->isCacheValid($data)) {
                return $data;
            }
        }
        return null;
    }
    
    /**
     * Cache models list
     */
    public function cacheModelsList(array $modelsList): void {
        if (!$this->config->get('documentation.cache_enabled', true)) {
            return;
        }
        
        $modelsList['timestamp'] = date('c');
        $cacheFile = $this->cacheDir . 'models_list.php';
        $content = '<?php return ' . var_export($modelsList, true) . ';';
        
        if (file_put_contents($cacheFile, $content) !== false) {
            if ($this->config->get('documentation.log_cache_operations', false)) {
                $this->logger->info("Models list cached", ['file' => $cacheFile]);
            }
        } else {
            $this->logger->warning("Failed to cache models list", ['file' => $cacheFile]);
        }
    }
    
    /**
     * Get cached field types
     */
    public function getCachedFieldTypes(): ?array {
        if (!$this->config->get('documentation.cache_enabled', true)) {
            return null;
        }
        
        $cacheFile = $this->cacheDir . 'field_types.php';
        if (file_exists($cacheFile)) {
            $data = include $cacheFile;
            if (is_array($data) && $this->isCacheValid($data)) {
                return $data;
            }
        }
        return null;
    }
    
    /**
     * Cache field types
     */
    public function cacheFieldTypes(array $fieldTypes): void {
        if (!$this->config->get('documentation.cache_enabled', true)) {
            return;
        }
        
        $fieldTypes['timestamp'] = date('c');
        $cacheFile = $this->cacheDir . 'field_types.php';
        $content = '<?php return ' . var_export($fieldTypes, true) . ';';
        
        if (file_put_contents($cacheFile, $content) !== false) {
            if ($this->config->get('documentation.log_cache_operations', false)) {
                $this->logger->info("Field types cached", ['file' => $cacheFile]);
            }
        } else {
            $this->logger->warning("Failed to cache field types", ['file' => $cacheFile]);
        }
    }
    
    /**
     * Clear all documentation cache
     */
    public function clearCache(): void {
        $files = glob($this->cacheDir . '*.php');
        $clearedCount = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $clearedCount++;
            }
        }
        
        $this->logger->info("Documentation cache cleared", [
            'cleared_files' => $clearedCount,
            'directory' => $this->cacheDir
        ]);
    }
    
    /**
     * Clear cache for specific model
     */
    public function clearModelCache(string $modelName): void {
        $cacheFile = $this->cacheDir . "model_{$modelName}.php";
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            $this->logger->info("Model cache cleared", ['model' => $modelName]);
        }
    }
    /**
     * Check if cached data is still valid based on configured TTL
     */
    private function isCacheValid(array $cachedData): bool {
        if (!isset($cachedData['timestamp'])) {
            return false;
        }
        
        $cacheTime = strtotime($cachedData['timestamp']);
        $ttl = $this->config->get('documentation.cache_ttl_seconds', 3600);
        
        return (time() - $cacheTime) < $ttl;
    }
}
