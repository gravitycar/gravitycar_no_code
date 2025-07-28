<?php
namespace Gravitycar\Database;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\GCException;
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

    public function __construct(Logger $logger, array $dbParams) {
        $this->logger = $logger;
        $this->dbParams = $dbParams;
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
     * Find records by criteria
     */
    public function find(string $modelClass, array $criteria = [], array $orderBy = [], int $limit = null, int $offset = null): array {
        try {
            $conn = $this->getConnection();
            $queryBuilder = $conn->createQueryBuilder();

            // Create a temporary model instance to get table name
            $tempModel = ServiceLocator::get($modelClass);
            $tableName = $tempModel->getTableName();

            $queryBuilder->select('*')->from($tableName);

            // Add WHERE conditions
            foreach ($criteria as $field => $value) {
                if (is_array($value)) {
                    $queryBuilder->andWhere("$field IN (:$field)");
                    $queryBuilder->setParameter($field, $value);
                } else {
                    $queryBuilder->andWhere("$field = :$field");
                    $queryBuilder->setParameter($field, $value);
                }
            }

            // Add ORDER BY
            foreach ($orderBy as $field => $direction) {
                $queryBuilder->orderBy($field, $direction);
            }

            // Add LIMIT and OFFSET
            if ($limit !== null) {
                $queryBuilder->setMaxResults($limit);
            }
            if ($offset !== null) {
                $queryBuilder->setFirstResult($offset);
            }

            $result = $queryBuilder->executeQuery();
            $rows = $result->fetchAllAssociative();

            // Convert rows to model instances
            $models = [];
            foreach ($rows as $row) {
                $model = ServiceLocator::get($modelClass);
                $this->populateModelFromRow($model, $row);
                $models[] = $model;
            }

            $this->logger->debug('Models found', [
                'model_class' => $modelClass,
                'criteria' => $criteria,
                'count' => count($models)
            ]);

            return $models;

        } catch (\Exception $e) {
            $this->logger->error('Failed to find models', [
                'model_class' => $modelClass,
                'criteria' => $criteria,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Database find operation failed: ' . $e->getMessage(), [], 0, $e);
        }
    }

    /**
     * Find a single record by ID
     */
    public function findById(string $modelClass, $id) {
        $results = $this->find($modelClass, ['id' => $id], [], 1);
        return empty($results) ? null : $results[0];
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
     * Populate a model from a database row
     */
    protected function populateModelFromRow($model, array $row): void {
        foreach ($row as $fieldName => $value) {
            if ($model->hasField($fieldName)) {
                $model->set($fieldName, $value);
            }
        }
    }
}
