<?php
namespace Gravitycar\Database;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Fields\FieldBase;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Models\ModelBase;
use Monolog\Logger;

/**
 * DatabaseConnector provides DBAL connection and utility methods for Gravitycar.
 */
class DatabaseConnector {
    /** @var array */
    protected array $dbParams;
    /** @var Logger */
    protected Logger $logger;
    /** @var Connection|null */
    protected ?Connection $connection = null;
    /** @var int */
    protected int $joinCounter = 0;

    public function __construct() {
        $this->logger = ServiceLocator::getLogger();
        $config = ServiceLocator::getConfig();
        $this->dbParams = $config->get('database') ?? [];
    }

    /**
     * Get Doctrine DBAL connection
     */
    public function getConnection(): Connection {
        if ($this->connection === null) {
            try {
                $this->connection = DriverManager::getConnection($this->dbParams);
            } catch (\Exception $e) {
                throw new GCException('Database connection failed: ' . $e->getMessage(),
                    ['db_params' => $this->dbParams, 'error' => $e->getMessage()], 0, $e);
            }
        }
        return $this->connection;
    }

    /**
     * Test database connection
     */
    public function testConnection(): bool {
        try {
            $conn = $this->getConnection();
            $conn->connect();
            return $conn->isConnected();
        } catch (\Exception $e) {
            $this->logger->error('Database connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a table exists
     */
    public function tableExists(string $tableName): bool {
        try {
            $conn = $this->getConnection();
            $schemaManager = method_exists($conn, 'createSchemaManager') ? $conn->createSchemaManager() : $conn->getSchemaManager();
            return $schemaManager->tablesExist([$tableName]);
        } catch (\Exception $e) {
            $this->logger->error('Table existence check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new database if it does not exist
     */
    public function createDatabaseIfNotExists(): bool {
        $dbName = $this->dbParams['dbname'] ?? null;
        if (!$dbName) {
            throw new GCException('Database name not specified in config',
                ['db_params' => $this->dbParams]);
        }
        try {
            $params = $this->dbParams;
            unset($params['dbname']);
            $conn = DriverManager::getConnection($params);
            $conn->executeStatement("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->logger->info("Database '$dbName' created or already exists.");
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Database creation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a database exists
     */
    public function databaseExists(string $databaseName): bool {
        try {
            // Create connection without specifying a database
            $params = $this->dbParams;
            unset($params['dbname']);
            $conn = DriverManager::getConnection($params);

            // Query information_schema to check if database exists
            $sql = "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :dbname";
            $result = $conn->executeQuery($sql, ['dbname' => $databaseName]);
            $exists = $result->fetchOne() !== false;

            $this->logger->debug('Database existence check completed', [
                'database_name' => $databaseName,
                'exists' => $exists
            ]);

            return $exists;

        } catch (\Exception $e) {
            $this->logger->error('Database existence check failed', [
                'database_name' => $databaseName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Drop a database if it exists
     */
    public function dropDatabase(string $databaseName): bool {
        try {
            // Create connection without specifying a database
            $params = $this->dbParams;
            unset($params['dbname']);
            $conn = DriverManager::getConnection($params);

            // Drop the database
            $sql = "DROP DATABASE IF EXISTS `$databaseName`";
            $conn->executeStatement($sql);

            $this->logger->info('Database dropped successfully', [
                'database_name' => $databaseName
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Database drop failed', [
                'database_name' => $databaseName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create a new record in the database for a model
     */
    public function create($model): bool {
        try {
            $conn = $this->getConnection();
            $queryBuilder = $conn->createQueryBuilder();

            $tableName = $model->getTableName();
            $data = $this->extractDBFieldData($model);

            if (empty($data)) {
                throw new GCException('No database fields to insert', [
                    'model_class' => get_class($model),
                    'table_name' => $tableName
                ]);
            }

            $queryBuilder
                ->insert($tableName);

            // Set values and parameters using the correct format for DBAL
            foreach ($data as $field => $value) {
                $queryBuilder->setValue($field, ":$field");
                $queryBuilder->setParameter($field, $value);
            }

            $result = $queryBuilder->executeStatement();

            // Set the ID if it was auto-generated
            if ($result && $model->hasField('id') && !$model->get('id')) {
                $lastInsertId = $conn->lastInsertId();
                $model->set('id', $lastInsertId);
            }

            $this->logger->info('Model created successfully', [
                'model_class' => get_class($model),
                'table_name' => $tableName,
                'id' => $model->get('id')
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create model', [
                'model_class' => get_class($model),
                'error' => $e->getMessage()
            ]);
            throw new GCException('Database create operation failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Update an existing record in the database for a model
     */
    public function update($model): bool {
        try {
            $conn = $this->getConnection();
            $queryBuilder = $conn->createQueryBuilder();

            $tableName = $model->getTableName();
            $data = $this->extractDBFieldData($model, true); // Include nulls for updates (needed for restore)
            $id = $model->get('id');

            if (!$id) {
                throw new GCException('Cannot update model without ID', [
                    'model_class' => get_class($model)
                ]);
            }

            // Remove ID from data since we don't want to update it
            unset($data['id']);

            if (empty($data)) {
                throw new GCException('No database fields to update', [
                    'model_class' => get_class($model),
                    'table_name' => $tableName
                ]);
            }

            $queryBuilder->update($tableName);

            foreach ($data as $field => $value) {
                $queryBuilder->set($field, ":$field");
                $queryBuilder->setParameter($field, $value);
            }

            $queryBuilder->where('id = :id');
            $queryBuilder->setParameter('id', $id);

            $result = $queryBuilder->executeStatement();

            $this->logger->info('Model updated successfully', [
                'model_class' => get_class($model),
                'table_name' => $tableName,
                'id' => $id
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to update model', [
                'model_class' => get_class($model),
                'error' => $e->getMessage()
            ]);
            throw new GCException('Database update operation failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Delete a record from the database for a model
     */
    public function delete($model): bool {
        try {
            $conn = $this->getConnection();
            $queryBuilder = $conn->createQueryBuilder();

            $tableName = $model->getTableName();
            $id = $model->get('id');

            if (!$id) {
                throw new GCException('Cannot delete model without ID', [
                    'model_class' => get_class($model)
                ]);
            }

            $queryBuilder
                ->delete($tableName)
                ->where('id = :id');

            $queryBuilder->setParameter('id', $id);
            $result = $queryBuilder->executeStatement();

            $this->logger->info('Model deleted successfully', [
                'model_class' => get_class($model),
                'table_name' => $tableName,
                'id' => $id
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete model', [
                'model_class' => get_class($model),
                'error' => $e->getMessage()
            ]);
            throw new GCException('Database delete operation failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Soft delete a record by updating deleted_at and deleted_by fields
     */
    public function softDelete($model): bool {
        try {
            $conn = $this->getConnection();
            $queryBuilder = $conn->createQueryBuilder();

            $tableName = $model->getTableName();
            $id = $model->get('id');

            if (!$id) {
                throw new GCException('Cannot soft delete model without ID', [
                    'model_class' => get_class($model)
                ]);
            }

            // Build the update data for soft delete fields
            $data = [];
            if ($model->hasField('deleted_at') && $model->get('deleted_at')) {
                $data['deleted_at'] = $model->get('deleted_at');
            }
            if ($model->hasField('deleted_by') && $model->get('deleted_by')) {
                $data['deleted_by'] = $model->get('deleted_by');
            }

            if (empty($data)) {
                throw new GCException('No soft delete fields to update', [
                    'model_class' => get_class($model),
                    'table_name' => $tableName
                ]);
            }

            $queryBuilder->update($tableName);

            foreach ($data as $field => $value) {
                $queryBuilder->set($field, ":$field");
                $queryBuilder->setParameter($field, $value);
            }

            $queryBuilder->where('id = :id');
            $queryBuilder->setParameter('id', $id);

            $result = $queryBuilder->executeStatement();

            $this->logger->info('Model soft deleted successfully', [
                'model_class' => get_class($model),
                'table_name' => $tableName,
                'id' => $id
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to soft delete model', [
                'model_class' => get_class($model),
                'error' => $e->getMessage()
            ]);
            throw new GCException('Database soft delete operation failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Hard delete a record (permanently removes from database)
     */
    public function hardDelete($model): bool {
        try {
            $conn = $this->getConnection();
            $queryBuilder = $conn->createQueryBuilder();

            $tableName = $model->getTableName();
            $id = $model->get('id');

            if (!$id) {
                throw new GCException('Cannot hard delete model without ID', [
                    'model_class' => get_class($model)
                ]);
            }

            $queryBuilder
                ->delete($tableName)
                ->where('id = :id');

            $queryBuilder->setParameter('id', $id);
            $result = $queryBuilder->executeStatement();

            $this->logger->info('Model hard deleted successfully', [
                'model_class' => get_class($model),
                'table_name' => $tableName,
                'id' => $id
            ]);

            return $result > 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to hard delete model', [
                'model_class' => get_class($model),
                'error' => $e->getMessage()
            ]);
            throw new GCException('Database hard delete operation failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Find records by criteria using an existing model instance
     */
    public function find(ModelBase $model, array $criteria = [], array $fields = [], array $parameters = []): array {
        try {
            $conn = $this->getConnection();
            $queryBuilder = $conn->createQueryBuilder();

            // Use the existing model instance directly - NO expensive ServiceLocator::get() call
            $tableName = $model->getTableName();
            $mainAlias = $model->getAlias();
            $modelFields = $model->getFields();

            // Start with main table using model's alias
            $queryBuilder->from($tableName, $mainAlias);

            // Build SELECT clause and handle field selection
            $this->buildSelectClause($queryBuilder, $model, $modelFields, $fields);

            // Apply WHERE conditions
            $this->applyCriteria($queryBuilder, $criteria, $mainAlias, $modelFields);

            // Apply query parameters (ORDER BY, LIMIT, OFFSET)
            $this->applyQueryParameters($queryBuilder, $parameters, $mainAlias, $modelFields);

            // Execute query and return raw rows
            $result = $queryBuilder->executeQuery();
            $rows = $result->fetchAllAssociative();

            $this->logger->debug('Database find operation completed', [
                'model_class' => get_class($model),
                'criteria' => $criteria,
                'selected_fields' => $fields,
                'main_alias' => $mainAlias,
                'row_count' => count($rows),
                'joins_applied' => $this->joinCounter
            ]);

            return $rows;

        } catch (\Exception $e) {
            $this->logger->error('Failed to find models', [
                'model_class' => get_class($model),
                'criteria' => $criteria,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Database find operation failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Find a single record by ID using an existing model instance
     */
    public function findById(ModelBase $model, $id): ?array {
        $results = $this->find($model, ['id' => $id], [], ['limit' => 1]);
        return empty($results) ? null : $results[0];
    }

    /**
     * Get count of records matching criteria
     */
    public function getCount(ModelBase $model, string $fieldName, $value, bool $includeDeleted = false): int {
        try {
            $conn = $this->getConnection();
            $queryBuilder = $conn->createQueryBuilder();

            $tableName = $model->getTableName();

            $queryBuilder
                ->select('COUNT(*) as count')
                ->from($tableName)
                ->where($fieldName . ' = :value')
                ->setParameter('value', $value);

            // Add soft delete filter unless including deleted records
            if (!$includeDeleted && $model->hasField('deleted_at')) {
                $queryBuilder->andWhere('deleted_at IS NULL');
            }

            $result = $queryBuilder->executeQuery();
            $count = $result->fetchOne();

            $this->logger->debug('Database count operation completed', [
                'model_class' => get_class($model),
                'table_name' => $tableName,
                'field_name' => $fieldName,
                'field_value' => $value,
                'include_deleted' => $includeDeleted,
                'count' => $count
            ]);

            return (int) $count;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get count from database', [
                'model_class' => get_class($model),
                'field_name' => $fieldName,
                'field_value' => $value,
                'include_deleted' => $includeDeleted,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Database count operation failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Extract database field data from a model
     */
    protected function extractDBFieldData($model, bool $includeNulls = false): array {
        $data = [];
        foreach ($model->getFields() as $fieldName => $field) {
            // Check if this field should be stored in the database using the new utility method
            if (!$field->isDBField()) {
                continue;
            }

            $value = $model->get($fieldName);
            // Include the field if it has a value, or if we're including nulls (like for restore operations)
            if ($value !== null || $includeNulls) {
                $data[$fieldName] = $value;
            }
        }
        return $data;
    }

    /**
     * Handle RelatedRecord field joins and select clauses
     */
    private function handleRelatedRecordField(
        \Doctrine\DBAL\Query\QueryBuilder $queryBuilder,
        $mainModel,
        \Gravitycar\Fields\RelatedRecordField $field
    ): array {
        $selectFields = [];
        $fieldName = $field->getName();
        $mainAlias = $mainModel->getAlias();

        try {
            // Get the related model instance
            $relatedModel = $field->getRelatedModelInstance();

        } catch (\Exception $e) {
            // If we can't get the related model, just select the foreign key
            $this->logger->warning("Failed to get related model for RelatedRecord field {$fieldName}: " . $e->getMessage());
            $selectFields[] = "{$mainAlias}.{$fieldName}";
            return $selectFields;
        }

        $joinAlias = "rel_{$this->joinCounter}";
        $this->joinCounter++;

        try {
            // Add LEFT JOIN for related table
            $queryBuilder->leftJoin(
                $mainAlias,
                $relatedModel->getTableName(),
                $joinAlias,
                "{$mainAlias}.{$fieldName} = {$joinAlias}.id"
            );

            // Add the foreign key field
            $selectFields[] = "{$mainAlias}.{$fieldName}";

            // Add concatenated display name from the related model
            $concatDisplayName = $this->concatDisplayName($relatedModel, $field);
            $selectFields[] = "{$concatDisplayName} as " . $field->getDisplayFieldName();

            $this->logger->debug("Added RelatedRecord join", [
                'field_name' => $fieldName,
                'main_alias' => $mainAlias,
                'related_model' => get_class($relatedModel),
                'related_table' => $relatedModel->getTableName(),
                'display_columns' => $relatedModel->getDisplayColumns(),
                'join_alias' => $joinAlias
            ]);

        } catch (\Exception $e) {
            $this->logger->warning("Failed to create join for RelatedRecord field {$fieldName}: " . $e->getMessage());
            // Fall back to just selecting the foreign key
            $selectFields[] = "{$mainAlias}.{$fieldName}";
        }

        return $selectFields;
    }

    /**
     * Create SQL CONCAT() function call for related model display columns
     */
    private function concatDisplayName($relatedModel, \Gravitycar\Fields\RelatedRecordField $field): string {
        try {
            $displayColumns = $relatedModel->getDisplayColumns();
            $fieldName = $field->getName();

            // Filter display columns to only include database fields
            $dbDisplayColumns = [];
            foreach ($displayColumns as $column) {
                if ($relatedModel->hasField($column)) {
                    $relatedField = $relatedModel->getFields()[$column];
                    if ($relatedField->isDBField()) {
                        $dbDisplayColumns[] = $column;
                    } else {
                        $this->logger->debug("Skipping non-database display column", [
                            'column' => $column,
                            'field_type' => get_class($relatedField),
                            'related_model' => get_class($relatedModel),
                            'parent_field' => $fieldName
                        ]);
                    }
                } else {
                    $this->logger->warning("Display column '$column' does not exist in related model", [
                        'column' => $column,
                        'related_model' => get_class($relatedModel),
                        'parent_field' => $fieldName
                    ]);
                }
            }

            if (empty($dbDisplayColumns)) {
                // Fallback: try to find 'name' field if it's a database field
                if ($relatedModel->hasField('name')) {
                    $nameField = $relatedModel->getFields()['name'];
                    if ($nameField->isDBField()) {
                        return "COALESCE(rel_{$this->joinCounter}.name, '')";
                    }
                }
                
                // Last resort: use 'id' field which should always exist and be a database field
                $this->logger->warning("No valid database display columns found, falling back to id field", [
                    'related_model' => get_class($relatedModel),
                    'parent_field' => $fieldName,
                    'original_display_columns' => $displayColumns
                ]);
                return "COALESCE(rel_{$this->joinCounter}.id, '')";
            }

            if (count($dbDisplayColumns) === 1) {
                // Single column - use COALESCE to handle NULL values
                return "COALESCE(rel_{$this->joinCounter}.{$dbDisplayColumns[0]}, '')";
            }

            // Multiple columns - use CONCAT with COALESCE to handle NULL values
            $concatParts = [];
            foreach ($dbDisplayColumns as $column) {
                $concatParts[] = "COALESCE(rel_{$this->joinCounter}.{$column}, '')";
            }

            // Join with spaces between columns, using CONCAT_WS to handle empty strings
            return "CONCAT_WS(' ', " . implode(", ", $concatParts) . ")";

        } catch (\Exception $e) {
            $this->logger->warning("Failed to create CONCAT for RelatedRecord field {$field->getName()}: " . $e->getMessage());
            // Fallback to simple id field which should always be available and be a database field
            return "COALESCE(rel_{$this->joinCounter}.id, '')";
        }
    }

    /**
     * Check if a record exists in a table where a specific field has a specific value
     * Used for validation to ensure field values exist in their target tables
     */
    public function recordExists(FieldBase $field, $value): bool {
        // Skip validation for null or empty values
        if ($value === null || $value === '') {
            return true;
        }

        // Field must have a table name set
        $tableName = $field->getTableName();
        if (empty($tableName)) {
            throw new GCException('Field must have a table name set to check record existence', [
                'field_name' => $field->getName(),
                'field_type' => get_class($field),
                'value' => $value
            ]);
        }

        try {
            // Check if value exists in the field's table
            $conn = $this->getConnection();
            $queryBuilder = $conn->createQueryBuilder();

            $queryBuilder
                ->select('COUNT(*) as count')
                ->from($tableName)
                ->where($field->getName() . ' = :value')
                ->setParameter('value', $value);

            $result = $queryBuilder->executeQuery();
            $count = $result->fetchOne();

            $exists = $count > 0;

            $this->logger->debug('Field value existence check completed', [
                'field_name' => $field->getName(),
                'field_type' => get_class($field),
                'table_name' => $tableName,
                'field_value' => $value,
                'exists' => $exists
            ]);

            return $exists;

        } catch (\Exception $e) {
            $this->logger->error('Failed to check record existence for field validation', [
                'field_name' => $field->getName(),
                'field_type' => get_class($field),
                'table_name' => $tableName,
                'field_value' => $value,
                'error' => $e->getMessage()
            ]);

            // Re-throw as GCException for consistent error handling
            throw new GCException('Record existence check failed: ' . $e->getMessage(), [
                'field_name' => $field->getName(),
                'value' => $value
            ], 0, $e);
        }
    }

    /**
     * Build SELECT clause for the query
     */
    protected function buildSelectClause(
        \Doctrine\DBAL\Query\QueryBuilder $queryBuilder,
        $tempModel,
        $modelFields,
        $fields
    ): void {
        $mainAlias = $tempModel->getAlias();

        // Determine which fields to select
        $fieldsToSelect = empty($fields) ? array_keys($modelFields) : $fields;

        $selectFields = [];

        foreach ($fieldsToSelect as $fieldName) {
            $field = $modelFields[$fieldName] ?? null;

            if (!$field) {
                $this->logger->warning("Field {$fieldName} not found in model {$tempModel->getName()}");
                continue;
            }

            if ($field instanceof \Gravitycar\Fields\RelatedRecordField) {
                // Handle RelatedRecord field with JOIN
                $relatedFields = $this->handleRelatedRecordField($queryBuilder, $tempModel, $field);
                $selectFields = array_merge($selectFields, $relatedFields);
            } else {
                // Only add database fields to SELECT clause
                if ($field->isDBField()) {
                    $selectFields[] = "{$mainAlias}.{$fieldName}";
                } else {
                    $this->logger->debug("Skipping non-database field from SELECT clause", [
                        'field_name' => $fieldName,
                        'field_type' => get_class($field),
                        'model' => get_class($tempModel)
                    ]);
                }
            }
        }

        // Apply SELECT clause
        $queryBuilder->select(implode(', ', $selectFields));
    }

    /**
     * Apply WHERE criteria to the query
     */
    protected function applyCriteria(
        \Doctrine\DBAL\Query\QueryBuilder $queryBuilder,
        array $criteria,
        string $mainAlias,
        array $modelFields = []
    ): void {
        // Add WHERE conditions using main table alias
        foreach ($criteria as $field => $value) {
            // Validate that the field is a database field if modelFields are provided
            if (!empty($modelFields) && isset($modelFields[$field])) {
                if (!$modelFields[$field]->isDBField()) {
                    $this->logger->warning("Skipping non-database field from WHERE criteria", [
                        'field_name' => $field,
                        'field_type' => get_class($modelFields[$field])
                    ]);
                    continue;
                }
            }

            if (is_array($value)) {
                $queryBuilder->andWhere("{$mainAlias}.{$field} IN (:{$field})");
                $queryBuilder->setParameter($field, $value);
            } elseif ($value === null) {
                $queryBuilder->andWhere("{$mainAlias}.{$field} IS NULL");
            } elseif (is_string($value) && $value === '__NOT_NULL__') {
                // Special marker for IS NOT NULL conditions
                $queryBuilder->andWhere("{$mainAlias}.{$field} IS NOT NULL");
            } else {
                $queryBuilder->andWhere("{$mainAlias}.{$field} = :{$field}");
                $queryBuilder->setParameter($field, $value);
            }
        }
    }

    /**
     * Apply query parameters like ORDER BY, LIMIT, OFFSET
     */
    protected function applyQueryParameters(
        \Doctrine\DBAL\Query\QueryBuilder $queryBuilder,
        array $parameters,
        string $mainAlias,
        array $modelFields = []
    ): void {
        $orderBy = $parameters['orderBy'] ?? [];
        $limit = $parameters['limit'] ?? null;
        $offset = $parameters['offset'] ?? null;

        // Add ORDER BY using main table alias
        foreach ($orderBy as $field => $direction) {
            // Validate that the field is a database field if modelFields are provided
            if (!empty($modelFields) && isset($modelFields[$field])) {
                if (!$modelFields[$field]->isDBField()) {
                    $this->logger->warning("Skipping non-database field from ORDER BY", [
                        'field_name' => $field,
                        'field_type' => get_class($modelFields[$field])
                    ]);
                    continue;
                }
            }
            $queryBuilder->orderBy("{$mainAlias}.{$field}", $direction);
        }

        // Add LIMIT and OFFSET
        if ($limit !== null) {
            $queryBuilder->setMaxResults($limit);
        }
        if ($offset !== null) {
            $queryBuilder->setFirstResult($offset);
        }
    }

    /**
     * Bulk soft delete records by field value
     * Updates deleted_at and deleted_by fields for all records where fieldName equals fieldValue
     */
    public function bulkSoftDeleteByFieldValue(
        ModelBase $model,
        string $fieldName,
        $fieldValue,
        ?string $currentUserId = null
    ): int {
        try {
            $criteria = [
                $fieldName => $fieldValue,
                'deleted_at' => null  // Only soft delete non-deleted records
            ];
            
            $currentDateTime = date('Y-m-d H:i:s');
            $fieldValues = [
                'deleted_at' => $currentDateTime,
                'deleted_by' => $currentUserId
            ];

            return $this->bulkUpdateByCriteriaWithFieldValues($model, $criteria, $fieldValues);

        } catch (\Exception $e) {
            $this->logger->error('Failed to bulk soft delete by field value', [
                'model_class' => get_class($model),
                'table_name' => $model->getTableName(),
                'field_name' => $fieldName,
                'field_value' => $fieldValue,
                'deleted_by' => $currentUserId,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Bulk soft delete by field value failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Bulk update records by criteria with specified field values
     * Updates specified fields for all records matching the given criteria
     */
    public function bulkUpdateByCriteriaWithFieldValues(
        ModelBase $model,
        array $criteria,
        array $fieldValues
    ): int {
        try {
            $conn = $this->getConnection();
            $queryBuilder = $conn->createQueryBuilder();

            $tableName = $model->getTableName();
            $modelFields = $model->getFields();

            $queryBuilder->update($tableName);

            // Set field values - only if the field exists and is a DB field
            foreach ($fieldValues as $fieldName => $value) {
                $field = $modelFields[$fieldName] ?? null;
                if ($field && $field->isDBField()) {
                    $queryBuilder->set($fieldName, ":{$fieldName}_value");
                    $queryBuilder->setParameter("{$fieldName}_value", $value);
                }
            }

            // Apply criteria using existing applyCriteria method logic
            foreach ($criteria as $field => $value) {
                // Validate that the field is a database field
                $fieldObj = $modelFields[$field] ?? null;
                if ($fieldObj && !$fieldObj->isDBField()) {
                    $this->logger->warning("Skipping non-database field from WHERE criteria", [
                        'field_name' => $field,
                        'field_type' => get_class($fieldObj),
                        'operation' => 'bulkUpdateByCriteriaWithFieldValues'
                    ]);
                    continue;
                }

                if (is_array($value)) {
                    $queryBuilder->andWhere("{$field} IN (:{$field}_criteria)");
                    $queryBuilder->setParameter("{$field}_criteria", $value);
                } elseif ($value === null) {
                    $queryBuilder->andWhere("{$field} IS NULL");
                } elseif (is_string($value) && $value === '__NOT_NULL__') {
                    $queryBuilder->andWhere("{$field} IS NOT NULL");
                } else {
                    $queryBuilder->andWhere("{$field} = :{$field}_criteria");
                    $queryBuilder->setParameter("{$field}_criteria", $value);
                }
            }

            $result = $queryBuilder->executeStatement();

            $this->logger->info('Bulk update by criteria with field values completed', [
                'model_class' => get_class($model),
                'table_name' => $tableName,
                'criteria' => $criteria,
                'field_values' => $fieldValues,
                'records_updated' => $result
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to bulk update by criteria with field values', [
                'model_class' => get_class($model),
                'criteria' => $criteria,
                'field_values' => $fieldValues,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Bulk update by criteria with field values failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Bulk soft delete records by criteria
     * Updates deleted_at and deleted_by fields for all records matching the given criteria
     */
    public function bulkSoftDeleteByCriteria(
        ModelBase $model,
        array $criteria,
        ?string $currentUserId = null
    ): int {
        try {
            $currentDateTime = date('Y-m-d H:i:s');
            $fieldValues = [
                'deleted_at' => $currentDateTime,
                'deleted_by' => $currentUserId
            ];

            // Add condition to only soft delete non-deleted records
            $criteria['deleted_at'] = null;

            return $this->bulkUpdateByCriteriaWithFieldValues($model, $criteria, $fieldValues);

        } catch (\Exception $e) {
            $this->logger->error('Failed to bulk soft delete by criteria', [
                'model_class' => get_class($model),
                'table_name' => $model->getTableName(),
                'criteria' => $criteria,
                'deleted_by' => $currentUserId,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Bulk soft delete by criteria failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Bulk restore soft-deleted records by criteria
     * Restores deleted_at and deleted_by fields for all soft-deleted records matching the given criteria
     */
    public function bulkRestoreByCriteria(
        ModelBase $model,
        array $criteria
    ): int {
        try {
            $fieldValues = [
                'deleted_at' => null,
                'deleted_by' => null
            ];

            // Add condition to only restore deleted records
            $criteria['deleted_at'] = '__NOT_NULL__';

            return $this->bulkUpdateByCriteriaWithFieldValues($model, $criteria, $fieldValues);

        } catch (\Exception $e) {
            $this->logger->error('Failed to bulk restore by criteria', [
                'model_class' => get_class($model),
                'criteria' => $criteria,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Bulk restore by criteria failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

}
