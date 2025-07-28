<?php
namespace Gravitycar\Schema;

use Gravitycar\Core\Config;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Exceptions\GCException;
use Monolog\Logger;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Types;

/**
 * SchemaGenerator: Converts metadata to MySQL DDL statements and manages schema updates.
 */
class SchemaGenerator {
    /** @var Config */
    protected Config $config;
    /** @var DatabaseConnector */
    protected DatabaseConnector $dbConnector;
    /** @var Logger */
    protected Logger $logger;

    public function __construct(Logger $logger, DatabaseConnector $dbConnector) {
        $this->logger = $logger;
        $this->dbConnector = $dbConnector;
    }

    /**
     * Create the database if it does not exist (for installation)
     */
    public function createDatabaseIfNotExists(): bool {
        return $this->dbConnector->createDatabaseIfNotExists();
    }

    /**
     * Generate or update schema for all models and relationships
     */
    public function generateSchema(array $metadata): void {
        $connection = $this->dbConnector->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $fromSchema = $schemaManager->introspectSchema();
        $toSchema = clone $fromSchema;

        // Generate tables for models
        foreach ($metadata['models'] ?? [] as $modelName => $modelMeta) {
            $this->generateModelTable($toSchema, $modelName, $modelMeta);
        }

        // Generate tables for relationships
        foreach ($metadata['relationships'] ?? [] as $relName => $relMeta) {
            $this->generateRelationshipTable($toSchema, $relName, $relMeta);
        }

        // Execute the schema changes
        $this->executeSchemaChanges($fromSchema, $toSchema);
    }

    /**
     * Generate or update a table for a model
     */
    protected function generateModelTable(Schema $schema, string $modelName, array $modelMeta): void {
        // Skip table generation for non-database models
        if (isset($modelMeta['nonDb']) && $modelMeta['nonDb'] === true) {
            $this->logger->info("Skipping table generation for non-DB model: $modelName");
            return;
        }

        // Also skip if table name is empty string (alternative approach)
        $tableName = $modelMeta['table'] ?? strtolower($modelName);
        if (empty($tableName)) {
            $this->logger->info("Skipping table generation for model with empty table name: $modelName");
            return;
        }

        $tableName = $modelMeta['table'] ?? strtolower($modelName);

        if ($schema->hasTable($tableName)) {
            $table = $schema->getTable($tableName);
            $this->updateModelTable($table, $modelMeta);
        } else {
            $table = $schema->createTable($tableName);
            $this->createModelTable($table, $modelMeta);
        }

        $this->logger->info("Generated/updated table for model: $modelName -> $tableName");
    }

    /**
     * Create a new table for a model
     */
    protected function createModelTable(Table $table, array $modelMeta): void {
        $fields = $modelMeta['fields'] ?? [];

        foreach ($fields as $fieldName => $fieldMeta) {
            // Skip non-database fields
            if (isset($fieldMeta['isDBField']) && $fieldMeta['isDBField'] === false) {
                continue;
            }
            if (isset($fieldMeta['nonDb']) && $fieldMeta['nonDb'] === true) {
                continue;
            }

            $this->addColumnFromFieldMeta($table, $fieldName, $fieldMeta);
        }

        // Add primary key if ID field exists
        if (isset($fields['id'])) {
            $table->setPrimaryKey(['id']);
        }
    }

    /**
     * Update an existing table for a model
     */
    protected function updateModelTable(Table $table, array $modelMeta): void {
        $fields = $modelMeta['fields'] ?? [];
        $existingColumns = array_keys($table->getColumns());

        foreach ($fields as $fieldName => $fieldMeta) {
            // Skip non-database fields
            if (isset($fieldMeta['isDBField']) && $fieldMeta['isDBField'] === false) {
                continue;
            }
            if (isset($fieldMeta['nonDb']) && $fieldMeta['nonDb'] === true) {
                continue;
            }

            if (!$table->hasColumn($fieldName)) {
                $this->addColumnFromFieldMeta($table, $fieldName, $fieldMeta);
            } else {
                $this->updateColumnFromFieldMeta($table, $fieldName, $fieldMeta);
            }
        }

        // Remove columns that are no longer in metadata (except core fields)
        $coreFields = ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'];
        foreach ($existingColumns as $columnName) {
            if (!isset($fields[$columnName]) && !in_array($columnName, $coreFields)) {
                $this->logger->warning("Column '$columnName' exists in database but not in metadata - consider removing it manually");
            }
        }
    }

    /**
     * Add a column to a table based on field metadata
     */
    protected function addColumnFromFieldMeta(Table $table, string $fieldName, array $fieldMeta): void {
        $type = $this->getDoctrineTypeFromFieldType($fieldMeta['type'] ?? 'Text');
        $options = $this->getColumnOptionsFromFieldMeta($fieldMeta);

        $table->addColumn($fieldName, $type, $options);

        // Add indexes if needed
        if (isset($fieldMeta['unique']) && $fieldMeta['unique']) {
            $table->addUniqueIndex([$fieldName], $fieldName . '_unique');
        }
    }

    /**
     * Update a column in a table based on field metadata
     */
    protected function updateColumnFromFieldMeta(Table $table, string $fieldName, array $fieldMeta): void {
        $column = $table->getColumn($fieldName);
        $type = $this->getDoctrineTypeFromFieldType($fieldMeta['type'] ?? 'Text');
        $options = $this->getColumnOptionsFromFieldMeta($fieldMeta);

        // Update column type and options
        $column->setType(\Doctrine\DBAL\Types\Type::getType($type));
        foreach ($options as $option => $value) {
            $column->setOption($option, $value);
        }
    }

    /**
     * Convert field type to Doctrine DBAL type
     */
    protected function getDoctrineTypeFromFieldType(string $fieldType): string {
        $typeMap = [
            'ID' => Types::GUID,
            'Text' => Types::STRING,
            'BigText' => Types::TEXT,
            'Email' => Types::STRING,
            'Password' => Types::STRING,
            'Integer' => Types::INTEGER,
            'Float' => Types::FLOAT,
            'Boolean' => Types::BOOLEAN,
            'DateTime' => Types::DATETIME_MUTABLE,
            'Date' => Types::DATE_MUTABLE,
            'Enum' => Types::STRING,
            'MultiEnum' => Types::TEXT,
            'RadioButtonSet' => Types::STRING,
            'Image' => Types::STRING,
            'RelatedRecord' => Types::GUID,
        ];

        return $typeMap[$fieldType] ?? Types::STRING;
    }

    /**
     * Get column options from field metadata
     */
    protected function getColumnOptionsFromFieldMeta(array $fieldMeta): array {
        $options = [];

        // Set nullable based on required field
        $options['notnull'] = isset($fieldMeta['required']) && $fieldMeta['required'];

        // Set length for string fields
        if (isset($fieldMeta['maxLength'])) {
            $options['length'] = $fieldMeta['maxLength'];
        } else {
            // Default lengths by field type
            $type = $fieldMeta['type'] ?? 'Text';
            switch ($type) {
                case 'Text':
                case 'Email':
                case 'Password':
                case 'Enum':
                case 'RadioButtonSet':
                    $options['length'] = 255;
                    break;
                case 'ID':
                case 'RelatedRecord':
                    $options['length'] = 36; // UUID length
                    break;
                case 'Image':
                    $options['length'] = 500;
                    break;
            }
        }

        // Set default value
        if (isset($fieldMeta['defaultValue'])) {
            $options['default'] = $fieldMeta['defaultValue'];
        }

        // Set precision for float fields
        if (($fieldMeta['type'] ?? '') === 'Float' && isset($fieldMeta['precision'])) {
            $options['precision'] = $fieldMeta['precision'];
            $options['scale'] = $fieldMeta['precision'];
        }

        return $options;
    }

    /**
     * Generate or update a table for a relationship
     */
    protected function generateRelationshipTable(Schema $schema, string $relName, array $relMeta): void {
        // Only create tables for many-to-many relationships
        if (($relMeta['type'] ?? 'N_M') !== 'N_M') {
            return;
        }

        $tableName = $relMeta['table'] ?? $relName;

        if ($schema->hasTable($tableName)) {
            $table = $schema->getTable($tableName);
            $this->updateRelationshipTable($table, $relMeta);
        } else {
            $table = $schema->createTable($tableName);
            $this->createRelationshipTable($table, $relMeta);
        }

        $this->logger->info("Generated/updated relationship table: $relName -> $tableName");
    }

    /**
     * Create a new table for a relationship
     */
    protected function createRelationshipTable(Table $table, array $relMeta): void {
        // Add standard relationship fields
        $table->addColumn('id', Types::GUID, ['length' => 36, 'notnull' => true]);
        $table->addColumn('model_a_id', Types::GUID, ['length' => 36, 'notnull' => true]);
        $table->addColumn('model_b_id', Types::GUID, ['length' => 36, 'notnull' => true]);

        // Add audit fields
        $table->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('updated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('deleted_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('created_by', Types::GUID, ['length' => 36, 'notnull' => false]);
        $table->addColumn('updated_by', Types::GUID, ['length' => 36, 'notnull' => false]);
        $table->addColumn('deleted_by', Types::GUID, ['length' => 36, 'notnull' => false]);

        // Add custom fields from metadata
        $fields = $relMeta['fields'] ?? [];
        foreach ($fields as $fieldName => $fieldMeta) {
            if (!in_array($fieldName, ['id', 'model_a_id', 'model_b_id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'])) {
                $this->addColumnFromFieldMeta($table, $fieldName, $fieldMeta);
            }
        }

        // Set primary key and indexes
        $table->setPrimaryKey(['id']);
        $table->addIndex(['model_a_id'], 'idx_model_a');
        $table->addIndex(['model_b_id'], 'idx_model_b');
        $table->addUniqueIndex(['model_a_id', 'model_b_id'], 'unique_relationship');
    }

    /**
     * Update an existing relationship table
     */
    protected function updateRelationshipTable(Table $table, array $relMeta): void {
        $fields = $relMeta['fields'] ?? [];
        $standardFields = ['id', 'model_a_id', 'model_b_id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'];

        foreach ($fields as $fieldName => $fieldMeta) {
            if (!in_array($fieldName, $standardFields)) {
                if (!$table->hasColumn($fieldName)) {
                    $this->addColumnFromFieldMeta($table, $fieldName, $fieldMeta);
                } else {
                    $this->updateColumnFromFieldMeta($table, $fieldName, $fieldMeta);
                }
            }
        }
    }

    /**
     * Execute schema changes by comparing from and to schemas
     */
    protected function executeSchemaChanges(Schema $fromSchema, Schema $toSchema): void {
        $connection = $this->dbConnector->getConnection();
        $platform = $connection->getDatabasePlatform();

        $comparator = new \Doctrine\DBAL\Schema\Comparator();
        $schemaDiff = $comparator->compareSchemas($fromSchema, $toSchema);

        $sqlStatements = $schemaDiff->toSql($platform);

        if (empty($sqlStatements)) {
            $this->logger->info("No schema changes needed");
            return;
        }

        $this->logger->info("Executing " . count($sqlStatements) . " schema changes");

        foreach ($sqlStatements as $sql) {
            try {
                $connection->executeStatement($sql);
                $this->logger->info("Executed: " . $sql);
            } catch (\Exception $e) {
                $this->logger->error("Failed to execute: " . $sql . " - Error: " . $e->getMessage());
                throw new GCException("Schema generation failed: " . $e->getMessage(),
                    ['sql' => $sql, 'error' => $e->getMessage()], 0, $e);
            }
        }

        $this->logger->info("Schema generation completed successfully");
    }
}
