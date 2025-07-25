<?php

namespace Gravitycar\Database;

use Gravitycar\Core\GCException;
use Gravitycar\Database\DatabaseConnector;

/**
 * Schema Generator for creating and updating database tables
 *
 * This class handles the dynamic creation and modification of database
 * tables based on model metadata definitions.
 */
class SchemaGenerator
{
    private DatabaseConnector $db;
    private array $createdTables = [];

    public function __construct(DatabaseConnector $db = null)
    {
        $this->db = $db ?? DatabaseConnector::getInstance();
    }

    public function generateSchemaFromMetadata(array $metadata, string $tableName): bool
    {
        if (!isset($metadata['fields']) || !is_array($metadata['fields'])) {
            throw new GCException("Invalid metadata: fields array is required");
        }

        if ($this->db->tableExists($tableName)) {
            return $this->updateTableSchema($tableName, $metadata['fields']);
        } else {
            return $this->createTable($tableName, $metadata['fields']);
        }
    }

    private function createTable(string $tableName, array $fields): bool
    {
        $sql = "CREATE TABLE `{$tableName}` (";
        $columnDefinitions = [];
        $indexes = [];

        foreach ($fields as $fieldName => $fieldConfig) {
            $columnDef = $this->buildColumnDefinition($fieldName, $fieldConfig);
            $columnDefinitions[] = $columnDef;

            // Handle indexes
            if (isset($fieldConfig['isIndexed']) && $fieldConfig['isIndexed']) {
                $indexes[] = "INDEX `idx_{$tableName}_{$fieldName}` (`{$fieldName}`)";
            }

            if (isset($fieldConfig['unique']) && $fieldConfig['unique']) {
                $indexes[] = "UNIQUE INDEX `unq_{$tableName}_{$fieldName}` (`{$fieldName}`)";
            }
        }

        $sql .= implode(', ', array_merge($columnDefinitions, $indexes));
        $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $result = $this->db->execute($sql);
            if ($result) {
                $this->createdTables[] = $tableName;
            }
            return $result;
        } catch (GCException $e) {
            throw new GCException("Failed to create table {$tableName}: " . $e->getMessage());
        }
    }

    private function updateTableSchema(string $tableName, array $fields): bool
    {
        // Get current table schema
        $currentSchema = $this->db->getTableSchema($tableName);
        $currentColumns = [];

        foreach ($currentSchema as $column) {
            $currentColumns[$column['Field']] = $column;
        }

        $alterations = [];

        // Check for new columns or modified columns
        foreach ($fields as $fieldName => $fieldConfig) {
            if (!isset($currentColumns[$fieldName])) {
                // New column
                $columnDef = $this->buildColumnDefinition($fieldName, $fieldConfig);
                $alterations[] = "ADD COLUMN {$columnDef}";
            } else {
                // Check if column needs modification
                $newColumnDef = $this->buildColumnDefinition($fieldName, $fieldConfig);
                $alterations[] = "MODIFY COLUMN {$newColumnDef}";
            }
        }

        if (!empty($alterations)) {
            $sql = "ALTER TABLE `{$tableName}` " . implode(', ', $alterations);
            return $this->db->execute($sql);
        }

        return true;
    }

    private function buildColumnDefinition(string $fieldName, array $fieldConfig): string
    {
        $databaseType = $fieldConfig['databaseType'] ?? 'VARCHAR(255)';
        $definition = "`{$fieldName}` {$databaseType}";

        // Handle nullable
        if (isset($fieldConfig['required']) && $fieldConfig['required']) {
            $definition .= " NOT NULL";
        } else {
            $definition .= " NULL";
        }

        // Handle default value
        if (isset($fieldConfig['defaultValue']) && $fieldConfig['defaultValue'] !== null) {
            $defaultValue = is_string($fieldConfig['defaultValue'])
                ? "'{$fieldConfig['defaultValue']}'"
                : $fieldConfig['defaultValue'];
            $definition .= " DEFAULT {$defaultValue}";
        }

        return $definition;
    }

    public function dropTable(string $tableName): bool
    {
        $sql = "DROP TABLE IF EXISTS `{$tableName}`";
        return $this->db->execute($sql);
    }

    public function getCreatedTables(): array
    {
        return $this->createdTables;
    }

    public function generateAllTablesFromDirectory(string $metadataPath): array
    {
        $results = [];

        if (!is_dir($metadataPath)) {
            throw new GCException("Metadata directory not found: {$metadataPath}");
        }

        $files = glob($metadataPath . '/*.php');

        foreach ($files as $file) {
            $modelName = basename($file, '.php');
            $metadata = include $file;

            if (isset($metadata['table_name'])) {
                $tableName = $metadata['table_name'];
            } else {
                $tableName = strtolower($modelName);
            }

            try {
                $result = $this->generateSchemaFromMetadata($metadata, $tableName);
                $results[$modelName] = [
                    'success' => $result,
                    'table_name' => $tableName
                ];
            } catch (GCException $e) {
                $results[$modelName] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}
