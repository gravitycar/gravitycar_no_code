<?php
namespace Gravitycar\Contracts;

use Doctrine\DBAL\Connection;
use Gravitycar\Models\ModelBase;

/**
 * Database Connector interface for dependency injection.
 * Defines the contract for database operations in Gravitycar.
 */
interface DatabaseConnectorInterface
{
    /**
     * Get the Doctrine DBAL connection
     */
    public function getConnection(): Connection;
    
    /**
     * Find a record by ID
     */
    public function findById(ModelBase $model, string $id): ?array;
    
    /**
     * Find multiple records matching criteria
     */
    public function findWhere(ModelBase $model, array $criteria = [], int $limit = 0): array;
    
    /**
     * Find records with flexible criteria and parameters
     */
    public function find(ModelBase $model, array $criteria = [], array $fields = [], array $parameters = []): array;
    
    /**
     * Create a new record
     */
    public function create(ModelBase $model): bool;
    
    /**
     * Update an existing record
     */
    public function update(ModelBase $model): bool;
    
    /**
     * Delete a record (soft delete)
     */
    public function delete(ModelBase $model): bool;
    
    /**
     * Soft delete a record by updating deleted_at timestamp
     */
    public function softDelete(ModelBase $model): bool;
    
    /**
     * Hard delete a record (permanently remove from database)
     */
    public function hardDelete(ModelBase $model): bool;
    
    /**
     * Get table name for a model
     */
    public function getTableName(ModelBase $model): string;
    
    /**
     * Execute raw SQL query
     */
    public function executeQuery(string $sql, array $params = []): array;
    
    /**
     * Check if connection is healthy
     */
    public function isHealthy(): bool;
}
