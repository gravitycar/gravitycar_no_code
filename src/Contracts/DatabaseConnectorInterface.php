<?php
namespace Gravitycar\Contracts;

use Doctrine\DBAL\Connection;
use Gravitycar\Models\ModelBase;
use Gravitycar\Fields\FieldBase;

/**
 * Database Connector interface for dependency injection.
 * Defines the contract for database operations in Gravitycar.
 * 
 * This interface includes ALL public methods from DatabaseConnector 
 * to ensure complete compatibility with pure dependency injection.
 */
interface DatabaseConnectorInterface
{
    // =====================================
    // Connection Management
    // =====================================
    
    /**
     * Get the Doctrine DBAL connection
     */
    public function getConnection(): Connection;
    
    /**
     * Reset the connection (useful after database creation)
     */
    public function resetConnection(): void;
    
    /**
     * Test database connection
     */
    public function testConnection(): bool;
    
    /**
     * Check if connection is healthy
     */
    public function isHealthy(): bool;
    
    // =====================================
    // Database Management
    // =====================================
    
    /**
     * Check if a table exists
     */
    public function tableExists(string $tableName): bool;
    
    /**
     * Create database if it doesn't exist
     */
    public function createDatabaseIfNotExists(): bool;
    
    /**
     * Check if a database exists
     */
    public function databaseExists(string $databaseName): bool;
    
    /**
     * Drop a database if it exists
     */
    public function dropDatabase(string $databaseName): bool;
    
    // =====================================
    // CRUD Operations
    // =====================================
    
    /**
     * Create a new record
     */
    public function create($model): bool;
    
    /**
     * Update an existing record
     */
    public function update($model): bool;
    
    /**
     * Delete a record (soft delete)
     */
    public function delete($model): bool;
    
    /**
     * Soft delete a record by updating deleted_at timestamp
     */
    public function softDelete($model): bool;
    
    /**
     * Hard delete a record (permanently remove from database)
     */
    public function hardDelete($model): bool;

    /**
     * Truncate a table efficiently while preserving structure
     * @param ModelBase $model The model whose table should be truncated
     */
    public function truncate(\Gravitycar\Models\ModelBase $model): void;
    
    // =====================================
    // Query Operations
    // =====================================
    
    /**
     * Find records with flexible criteria and parameters
     */
    public function find($model, array $criteria = [], array $fields = [], array $parameters = []): array;
    
    /**
     * Find a record by ID
     */
    public function findById($model, $id): ?array;
    
    /**
     * Find multiple records matching criteria
     */
    public function findWhere(ModelBase $model, array $criteria = [], int $limit = 0): array;
    
    /**
     * Get table name for a model
     */
    public function getTableName(ModelBase $model): string;
    
    /**
     * Execute raw SQL query
     */
    public function executeQuery(string $sql, array $params = []): array;
    
    // =====================================
    // Random Record Operations
    // =====================================
    
    /**
     * Get a random record matching criteria
     */
    public function getRandomRecord($model, array $criteria = [], array $fields = ['id'], array $parameters = []): ?string;
    
    /**
     * Get a random record using validated filters
     */
    public function getRandomRecordWithValidatedFilters($model, array $validatedFilters = []): ?string;
    
    // =====================================
    // Record Existence Validation
    // =====================================
    
    /**
     * Check if a record exists in a table where a specific field has a specific value
     */
    public function recordExists(FieldBase $field, $value): bool;
    
    /**
     * Check if a record exists excluding a specific ID (for unique validation during updates)
     */
    public function recordExistsExcludingId(FieldBase $field, $value, $excludeId = null): bool;
    
    // =====================================
    // Enhanced Query Methods (React Compatible)
    // =====================================
    
    /**
     * Find records with React-compatible parameter handling
     * 
     * @param ModelBase $model The model instance
     * @param array $validatedParams Pre-validated parameters from Router
     * @param bool $includeDeleted Whether to include soft-deleted records
     * @return array Array of records
     */
    public function findWithReactParams(
        ModelBase $model,
        array $validatedParams = [],
        bool $includeDeleted = false
    ): array;

    /**
     * Get count of records matching validated criteria
     * 
     * @param ModelBase $model The model instance
     * @param array $validatedParams Pre-validated parameters from Router
     * @param bool $includeDeleted Whether to include soft-deleted records
     * @return int Count of matching records
     */
    public function getCountWithValidatedCriteria(
        ModelBase $model,
        array $validatedParams = [],
        bool $includeDeleted = false
    ): int;
    
    // =====================================
    // Bulk Operations
    // =====================================
    
    /**
     * Bulk soft delete records by field value
     */
    public function bulkSoftDeleteByFieldValue(
        ModelBase $model,
        string $fieldName,
        $fieldValue,
        ?string $currentUserId = null
    ): int;
    
    /**
     * Bulk update records by criteria with specified field values
     */
    public function bulkUpdateByCriteriaWithFieldValues(
        ModelBase $model,
        array $criteria,
        array $fieldValues
    ): int;
    
    /**
     * Bulk soft delete records by criteria
     */
    public function bulkSoftDeleteByCriteria(
        ModelBase $model,
        array $criteria,
        ?string $currentUserId = null
    ): int;
    
    /**
     * Bulk restore soft-deleted records by criteria
     */
    public function bulkRestoreByCriteria(
        ModelBase $model,
        array $criteria
    ): int;
}
