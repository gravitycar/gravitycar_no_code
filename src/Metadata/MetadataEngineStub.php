<?php
namespace Gravitycar\Metadata;

use Monolog\Logger;
use Exception;

/**
 * MetadataEngineStub provides empty metadata when the main MetadataEngine fails.
 * Prevents application crashes while indicating metadata loading issues.
 */
class MetadataEngineStub extends MetadataEngine {
    private Exception $originalError;

    public function __construct(Logger $logger, Exception $error) {
        $this->logger = $logger;
        $this->originalError = $error;
        $this->modelsDirPath = 'src/models';
        $this->relationshipsDirPath = 'src/relationships';
        $this->cacheDirPath = 'cache/';
        $this->metadataCache = [];

        $this->logger->error('MetadataEngineStub active - metadata operations will return empty results');
        $this->logger->error('Original metadata error: ' . $error->getMessage());
    }

    public function loadAllMetadata(): array {
        $this->logger->warning('loadAllMetadata() called on MetadataEngineStub - returning empty metadata');
        return [
            'models' => [],
            'relationships' => []
        ];
    }

    public function getCachedMetadata(): array {
        $this->logger->warning('getCachedMetadata() called on MetadataEngineStub - returning empty metadata');
        return [
            'models' => [],
            'relationships' => []
        ];
    }

    protected function scanAndLoadMetadata(string $dirPath): array {
        $this->logger->warning("scanAndLoadMetadata() called on MetadataEngineStub for path: $dirPath");
        return [];
    }

    protected function validateMetadata(array $metadata): void {
        $this->logger->info('validateMetadata() called on MetadataEngineStub - no validation performed');
    }

    protected function cacheMetadata(array $metadata): void {
        $this->logger->warning('cacheMetadata() called on MetadataEngineStub - no caching performed');
    }

    public function getOriginalError(): Exception {
        return $this->originalError;
    }

    public function isStub(): bool {
        return true;
    }
}
