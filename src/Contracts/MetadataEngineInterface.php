<?php
namespace Gravitycar\Contracts;

/**
 * MetadataEngineInterface for Gravitycar framework.
 * Defines the contract for metadata loading and caching.
 */
interface MetadataEngineInterface
{
    /**
     * Get model metadata by name
     */
    public function getModelMetadata(string $modelName): array;
    
    /**
     * Get all loaded metadata
     */
    public function getAllMetadata(): array;
    
    /**
     * Check if metadata is loaded
     */
    public function isLoaded(): bool;
    
    /**
     * Force reload metadata
     */
    public function reloadMetadata(): void;
    
    /**
     * Get relationship metadata by name
     */
    public function getRelationshipMetadata(string $relationshipName): array;
    
    /**
     * Build relationship metadata file path
     */
    public function buildRelationshipMetadataPath(string $relationshipName): string;
    
    /**
     * Resolve model name from class name
     */
    public function resolveModelName(string $className): string;
    
    /**
     * Build model metadata file path
     */
    public function buildModelMetadataPath(string $modelName): string;
}
