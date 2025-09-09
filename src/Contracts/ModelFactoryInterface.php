<?php
namespace Gravitycar\Contracts;

use Gravitycar\Models\ModelBase;

/**
 * ModelFactoryInterface for Gravitycar framework.
 * Defines the contract for model instantiation and retrieval.
 */
interface ModelFactoryInterface
{
    /**
     * Create a new, empty model instance from model name
     */
    public function new(string $modelName): ModelBase;
    
    /**
     * Retrieve and populate a model instance from database by ID
     */
    public function retrieve(string $modelName, string $id): ?ModelBase;
    
    /**
     * Create a new model instance populated with data
     */
    public function createNew(string $modelName, array $data = []): ModelBase;
    
    /**
     * Find existing model or create new one
     */
    public function findOrNew(string $modelName, string $id): ModelBase;
    
    /**
     * Create and save model in one call
     */
    public function create(string $modelName, array $data): ModelBase;
    
    /**
     * Update existing record
     */
    public function update(string $modelName, string $id, array $data): ?ModelBase;
}
