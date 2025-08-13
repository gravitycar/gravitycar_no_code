<?php
namespace Gravitycar\Schema;

use Gravitycar\Core\Config;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Relationships\RelationshipBase;
use Gravitycar\Metadata\CoreFieldsMetadata;
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
    /** @var CoreFieldsMetadata */
    protected CoreFieldsMetadata $coreFieldsMetadata;

    public function __construct() {
        $this->logger = ServiceLocator::getLogger();
        $this->dbConnector = ServiceLocator::getDatabaseConnector();
        $this->coreFieldsMetadata = ServiceLocator::getCoreFieldsMetadata();
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
     * Create relationship table from relationship instance
     */
    public function createRelationshipTable(RelationshipBase $relationship): void {
        $connection = $this->dbConnector->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $fromSchema = $schemaManager->introspectSchema();
        $toSchema = clone $fromSchema;

        $tableName = $relationship->getTableName();
        $relationshipMeta = $relationship->getRelationshipMetadata();

        if (!$toSchema->hasTable($tableName)) {
            $table = $toSchema->createTable($tableName);
            $this->createRelationshipTableStructure($table, $relationship);

            $this->logger->info("Created relationship table", [
                'table_name' => $tableName,
                'relationship_name' => $relationship->getName(),
                'relationship_type' => $relationship->getType()
            ]);
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
     * Generate or update a table for a relationship
     */
    protected function generateRelationshipTable(Schema $schema, string $relationshipName, array $relationshipMeta): void {
        $tableName = $this->generateRelationshipTableName($relationshipMeta);

        if ($schema->hasTable($tableName)) {
            $table = $schema->getTable($tableName);
            $this->updateRelationshipTable($table, $relationshipMeta);
        } else {
            $table = $schema->createTable($tableName);
            $this->createRelationshipTableFromMeta($table, $relationshipMeta);
        }

        $this->logger->info("Generated/updated relationship table: $relationshipName -> $tableName");
    }

    /**
     * Generate relationship table name from metadata
     */
    protected function generateRelationshipTableName(array $relationshipMeta): string {
        $type = $relationshipMeta['type'];

        switch ($type) {
            case 'OneToOne':
                $modelA = strtolower($relationshipMeta['modelA']);
                $modelB = strtolower($relationshipMeta['modelB']);
                $tableName = "rel_1_{$modelA}_1_{$modelB}";
                break;

            case 'OneToMany':
                $modelOne = strtolower($relationshipMeta['modelOne']);
                $modelMany = strtolower($relationshipMeta['modelMany']);
                $tableName = "rel_1_{$modelOne}_M_{$modelMany}";
                break;

            case 'ManyToMany':
                $modelA = strtolower($relationshipMeta['modelA']);
                $modelB = strtolower($relationshipMeta['modelB']);
                $tableName = "rel_N_{$modelA}_M_{$modelB}";
                break;

            default:
                throw new GCException("Unknown relationship type: {$type}");
        }

        // Ensure table name doesn't exceed database limits
        if (strlen($tableName) > 64) {
            $tableName = substr($tableName, 0, 64);
        }

        return $tableName;
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
     * Create a relationship table structure from RelationshipBase instance
     */
    protected function createRelationshipTableStructure(Table $table, RelationshipBase $relationship): void {
        // Add core model fields (id, created_at, updated_at, etc.)
        $this->addCoreModelFields($table);

        // Add relationship-specific ID fields based on type
        $relationshipMeta = $relationship->getRelationshipMetadata();
        $this->addRelationshipIdFields($table, $relationshipMeta);

        // Add any additional fields specified in metadata
        $additionalFields = $relationshipMeta['additionalFields'] ?? [];
        foreach ($additionalFields as $fieldName => $fieldMeta) {
            $this->addColumnFromFieldMeta($table, $fieldName, $fieldMeta);
        }

        // Set primary key
        $table->setPrimaryKey(['id']);

        // Add indexes for relationship fields
        $this->addRelationshipIndexes($table, $relationshipMeta);
    }

    /**
     * Create relationship table from metadata (used by generateRelationshipTable)
     */
    protected function createRelationshipTableFromMeta(Table $table, array $relationshipMeta): void {
        // Add core model fields
        $this->addCoreModelFields($table);

        // Add relationship-specific ID fields
        $this->addRelationshipIdFields($table, $relationshipMeta);

        // Add any additional fields
        $additionalFields = $relationshipMeta['additionalFields'] ?? [];
        foreach ($additionalFields as $fieldName => $fieldMeta) {
            $this->addColumnFromFieldMeta($table, $fieldName, $fieldMeta);
        }

        // Set primary key
        $table->setPrimaryKey(['id']);

        // Add indexes
        $this->addRelationshipIndexes($table, $relationshipMeta);
    }

    /**
     * Add core model fields to a table (id, timestamps, etc.)
     */
    protected function addCoreModelFields(Table $table): void {
        try {
            $coreFields = $this->coreFieldsMetadata->getStandardCoreFields();

            foreach ($coreFields as $fieldName => $fieldMeta) {
                $this->addColumnFromFieldMeta($table, $fieldName, $fieldMeta);
            }

            $this->logger->debug('Added core model fields to table', [
                'table_name' => $table->getName(),
                'core_fields_count' => count($coreFields)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to add core model fields to table', [
                'table_name' => $table->getName(),
                'error' => $e->getMessage()
            ]);

            // Fallback to hardcoded core fields if CoreFieldsMetadata fails
            $this->addFallbackCoreModelFields($table);
        }
    }

    /**
     * Fallback method to add core model fields if CoreFieldsMetadata fails
     */
    protected function addFallbackCoreModelFields(Table $table): void {
        $this->logger->warning('Using fallback core model fields', [
            'table_name' => $table->getName()
        ]);

        // ID field
        $table->addColumn('id', Types::STRING, [
            'length' => 36,
            'notnull' => true,
            'comment' => 'Primary key UUID'
        ]);

        // Timestamp fields
        $table->addColumn('created_at', Types::DATETIME_MUTABLE, [
            'notnull' => true,
            'default' => 'CURRENT_TIMESTAMP'
        ]);

        $table->addColumn('updated_at', Types::DATETIME_MUTABLE, [
            'notnull' => true,
            'default' => 'CURRENT_TIMESTAMP'
        ]);

        // Soft delete fields
        $table->addColumn('deleted_at', Types::DATETIME_MUTABLE, [
            'notnull' => false,
            'default' => null
        ]);

        // User tracking fields
        $table->addColumn('created_by', Types::STRING, [
            'length' => 36,
            'notnull' => false,
            'default' => null
        ]);

        $table->addColumn('updated_by', Types::STRING, [
            'length' => 36,
            'notnull' => false,
            'default' => null
        ]);

        $table->addColumn('deleted_by', Types::STRING, [
            'length' => 36,
            'notnull' => false,
            'default' => null
        ]);
    }

    /**
     * Add relationship-specific ID fields based on relationship type
     */
    protected function addRelationshipIdFields(Table $table, array $relationshipMeta): void {
        $type = $relationshipMeta['type'];

        switch ($type) {
            case 'OneToOne':
                $modelA = strtolower($relationshipMeta['modelA']);
                $modelB = strtolower($relationshipMeta['modelB']);

                $table->addColumn("{$modelA}_id", Types::STRING, [
                    'length' => 36,
                    'notnull' => true,
                    'comment' => "Foreign key to {$relationshipMeta['modelA']} table"
                ]);

                $table->addColumn("{$modelB}_id", Types::STRING, [
                    'length' => 36,
                    'notnull' => true,
                    'comment' => "Foreign key to {$relationshipMeta['modelB']} table"
                ]);
                break;

            case 'OneToMany':
                $modelOne = strtolower($relationshipMeta['modelOne']);
                $modelMany = strtolower($relationshipMeta['modelMany']);

                $table->addColumn("one_{$modelOne}_id", Types::STRING, [
                    'length' => 36,
                    'notnull' => true,
                    'comment' => "Foreign key to {$relationshipMeta['modelOne']} table (one side)"
                ]);

                $table->addColumn("many_{$modelMany}_id", Types::STRING, [
                    'length' => 36,
                    'notnull' => true,
                    'comment' => "Foreign key to {$relationshipMeta['modelMany']} table (many side)"
                ]);
                break;

            case 'ManyToMany':
                $modelA = strtolower($relationshipMeta['modelA']);
                $modelB = strtolower($relationshipMeta['modelB']);

                $table->addColumn("{$modelA}_id", Types::STRING, [
                    'length' => 36,
                    'notnull' => true,
                    'comment' => "Foreign key to {$relationshipMeta['modelA']} table"
                ]);

                $table->addColumn("{$modelB}_id", Types::STRING, [
                    'length' => 36,
                    'notnull' => true,
                    'comment' => "Foreign key to {$relationshipMeta['modelB']} table"
                ]);
                break;
        }
    }

    /**
     * Add indexes for relationship tables
     */
    protected function addRelationshipIndexes(Table $table, array $relationshipMeta): void {
        $type = $relationshipMeta['type'];

        switch ($type) {
            case 'OneToOne':
                $modelA = strtolower($relationshipMeta['modelA']);
                $modelB = strtolower($relationshipMeta['modelB']);

                // Unique constraint for OneToOne
                $table->addUniqueIndex(["{$modelA}_id", "{$modelB}_id"], "uniq_{$modelA}_{$modelB}");

                // Individual indexes for lookups
                $table->addIndex(["{$modelA}_id"], "idx_{$modelA}_id");
                $table->addIndex(["{$modelB}_id"], "idx_{$modelB}_id");
                break;

            case 'OneToMany':
                $modelOne = strtolower($relationshipMeta['modelOne']);
                $modelMany = strtolower($relationshipMeta['modelMany']);

                // Compound index for relationship lookups
                $table->addIndex(["one_{$modelOne}_id", "many_{$modelMany}_id"], "idx_one_many_lookup");

                // Individual indexes
                $table->addIndex(["one_{$modelOne}_id"], "idx_one_{$modelOne}_id");
                $table->addIndex(["many_{$modelMany}_id"], "idx_many_{$modelMany}_id");
                break;

            case 'ManyToMany':
                $modelA = strtolower($relationshipMeta['modelA']);
                $modelB = strtolower($relationshipMeta['modelB']);

                // Compound unique index to prevent duplicate relationships
                $table->addUniqueIndex(["{$modelA}_id", "{$modelB}_id"], "uniq_{$modelA}_{$modelB}");

                // Individual indexes for lookups
                $table->addIndex(["{$modelA}_id"], "idx_{$modelA}_id");
                $table->addIndex(["{$modelB}_id"], "idx_{$modelB}_id");
                break;
        }

        // Add index for soft delete queries
        $table->addIndex(['deleted_at'], 'idx_deleted_at');
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
     * Update an existing relationship table
     */
    protected function updateRelationshipTable(Table $table, array $relationshipMeta): void {
        // For now, just log that we're updating - full update logic can be added later
        $this->logger->info("Updating existing relationship table", [
            'table_name' => $table->getName(),
            'relationship_type' => $relationshipMeta['type']
        ]);

        // Add any missing additional fields
        $additionalFields = $relationshipMeta['additionalFields'] ?? [];
        foreach ($additionalFields as $fieldName => $fieldMeta) {
            if (!$table->hasColumn($fieldName)) {
                $this->addColumnFromFieldMeta($table, $fieldName, $fieldMeta);
            }
        }
    }

    /**
     * Add a column to a table based on field metadata
     */
    protected function addColumnFromFieldMeta(Table $table, string $fieldName, array $fieldMeta): void {
        $type = $this->getDoctrineTypeFromFieldType($fieldMeta['type'] ?? 'TextField');
        $options = $this->getColumnOptionsFromFieldMeta($fieldMeta);

        $table->addColumn($fieldName, $type, $options);
    }

    /**
     * Update a column in a table based on field metadata
     */
    protected function updateColumnFromFieldMeta(Table $table, string $fieldName, array $fieldMeta): void {
        // For now, just log that we're updating - full update logic can be added later
        $this->logger->debug("Column '$fieldName' already exists, skipping update");
    }

    /**
     * Convert field type to Doctrine DBAL type
     */
    protected function getDoctrineTypeFromFieldType(string $fieldType): string {
        $typeMap = [
            'TextField' => Types::STRING,
            'BigTextField' => Types::TEXT,
            'IntegerField' => Types::INTEGER,
            'FloatField' => Types::FLOAT,
            'BooleanField' => Types::BOOLEAN,
            'DateField' => Types::DATE_MUTABLE,
            'DateTimeField' => Types::DATETIME_MUTABLE,
            'EmailField' => Types::STRING,
            'PasswordField' => Types::STRING,
            'IDField' => Types::STRING,
            'ImageField' => Types::STRING,
            'Enum' => Types::STRING,
            'MultiEnum' => Types::JSON,
            'RadioButtonSetField' => Types::STRING,
            'RelatedRecord' => Types::STRING,
        ];

        return $typeMap[$fieldType] ?? Types::STRING;
    }

    /**
     * Get column options from field metadata
     */
    protected function getColumnOptionsFromFieldMeta(array $fieldMeta): array {
        $options = [];

        // Set nullable based on required field
        $options['notnull'] = $fieldMeta['required'] ?? false;

        // Set default value if specified
        if (isset($fieldMeta['default'])) {
            $options['default'] = $fieldMeta['default'];
        }

        // Set length for string fields
        if (isset($fieldMeta['maxLength'])) {
            $options['length'] = $fieldMeta['maxLength'];
        } elseif ($fieldMeta['type'] === 'TextField') {
            $options['length'] = 255; // Default length for text fields
        } elseif ($fieldMeta['type'] === 'EmailField') {
            $options['length'] = 255;
        } elseif ($fieldMeta['type'] === 'IDField') {
            $options['length'] = 36; // UUID length
        } elseif ($fieldMeta['type'] === 'ImageField') {
            $options['length'] = 500; // Path length
        }

        // Add comment if label is provided
        if (isset($fieldMeta['label'])) {
            $options['comment'] = $fieldMeta['label'];
        }

        return $options;
    }

    /**
     * Execute schema changes
     */
    protected function executeSchemaChanges(Schema $fromSchema, Schema $toSchema): void {
        $connection = $this->dbConnector->getConnection();
        $platform = $connection->getDatabasePlatform();
        $queries = $fromSchema->getMigrateToSql($toSchema, $platform);

        foreach ($queries as $query) {
            try {
                $connection->executeStatement($query);
                $this->logger->debug("Executed schema query: $query");
            } catch (\Exception $e) {
                $this->logger->error("Failed to execute schema query", [
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);
                throw new GCException("Schema update failed: " . $e->getMessage(), [], 0, $e);
            }
        }

        if (!empty($queries)) {
            $this->logger->info("Schema update completed", [
                'queries_executed' => count($queries)
            ]);
        }
    }
}
