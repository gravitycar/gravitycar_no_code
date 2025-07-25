<?php

namespace Gravitycar\Core;

use Gravitycar\Core\GCException;
use Gravitycar\Factories\FieldFactory;

/**
 * Base class for all models in the Gravitycar framework
 *
 * This class provides the common functionality for models including
 * field management, metadata processing, validation, and database operations.
 */
abstract class ModelBase
{
    protected string $tableName = '';
    protected string $primaryKey = 'id';
    protected array $fields = [];
    protected array $metadata = [];
    protected array $validationErrors = [];
    protected bool $isLoaded = false;
    protected bool $hasChanges = false;
    protected ?DatabaseConnector $db = null;

    public function __construct(array $data = [])
    {
        $this->loadMetadata();
        $this->ingestMetadata();

        if (!empty($data)) {
            $this->loadFromArray($data);
        }
    }

    protected function loadMetadata(): void
    {
        $className = get_class($this);
        $modelName = basename(str_replace('\\', '/', $className));
        $metadataPath = $this->getMetadataPath($modelName);

        if (file_exists($metadataPath)) {
            $this->metadata = include $metadataPath;
        } else {
            throw new GCException("Metadata file not found for model: {$modelName}");
        }
    }

    protected function getMetadataPath(string $modelName): string
    {
        // Override in child classes or use convention
        return __DIR__ . "/../../metadata/models/{$modelName}.php";
    }

    protected function ingestMetadata(): void
    {
        if (isset($this->metadata['table_name'])) {
            $this->tableName = $this->metadata['table_name'];
        }

        if (isset($this->metadata['primary_key'])) {
            $this->primaryKey = $this->metadata['primary_key'];
        }

        if (isset($this->metadata['fields']) && is_array($this->metadata['fields'])) {
            $fieldFactory = new FieldFactory();

            foreach ($this->metadata['fields'] as $fieldName => $fieldDefinition) {
                $fieldDefinition['name'] = $fieldName;
                $field = $fieldFactory->createField($fieldDefinition);
                $this->fields[$fieldName] = $field;
            }
        }
    }

    public function get(string $fieldName): mixed
    {
        if (!isset($this->fields[$fieldName])) {
            throw new GCException("Field '{$fieldName}' does not exist in model");
        }

        return $this->fields[$fieldName]->get($fieldName);
    }

    public function set(string $fieldName, mixed $value): void
    {
        if (!isset($this->fields[$fieldName])) {
            throw new GCException("Field '{$fieldName}' does not exist in model");
        }

        $this->fields[$fieldName]->set($fieldName, $value, $this);
        $this->hasChanges = true;
    }

    public function loadFromArray(array $data): void
    {
        foreach ($data as $fieldName => $value) {
            if (isset($this->fields[$fieldName])) {
                $this->fields[$fieldName]->setValueFromDB($value);
            }
        }
        $this->isLoaded = true;
        $this->hasChanges = false;
    }

    public function toArray(): array
    {
        $data = [];
        foreach ($this->fields as $fieldName => $field) {
            if ($field->isDbField()) {
                $data[$fieldName] = $field->getValueForApi();
            }
        }
        return $data;
    }

    public function validate(): bool
    {
        $this->validationErrors = [];

        foreach ($this->fields as $field) {
            if (!empty($field->getValidationErrors())) {
                $this->validationErrors[$field->getName()] = $field->getValidationErrors();
            }
        }

        return empty($this->validationErrors);
    }

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $db = $this->getDatabase();

        if ($this->isLoaded && !empty($this->get($this->primaryKey))) {
            return $this->update($db);
        } else {
            return $this->insert($db);
        }
    }

    protected function insert(DatabaseConnector $db): bool
    {
        $data = [];
        foreach ($this->fields as $field) {
            if ($field->isDbField() && !$field->isPrimaryKey()) {
                $data[$field->getName()] = $field->get($field->getName());
            }
        }

        $result = $db->insert($this->tableName, $data);

        if ($result && $db->getLastInsertId()) {
            $this->set($this->primaryKey, $db->getLastInsertId());
            $this->isLoaded = true;
            $this->hasChanges = false;
        }

        return $result;
    }

    protected function update(DatabaseConnector $db): bool
    {
        $data = [];
        $changedFields = [];

        foreach ($this->fields as $field) {
            if ($field->isDbField() && $field->hasChanged() && !$field->isPrimaryKey()) {
                $data[$field->getName()] = $field->get($field->getName());
                $changedFields[] = $field->getName();
            }
        }

        if (empty($data)) {
            return true; // No changes to save
        }

        $primaryKeyValue = $this->get($this->primaryKey);
        $result = $db->update($this->tableName, $data, [$this->primaryKey => $primaryKeyValue]);

        if ($result) {
            $this->hasChanges = false;
        }

        return $result;
    }

    public function delete(): bool
    {
        if (!$this->isLoaded || empty($this->get($this->primaryKey))) {
            throw new GCException("Cannot delete model that hasn't been loaded from database");
        }

        $db = $this->getDatabase();
        $primaryKeyValue = $this->get($this->primaryKey);

        return $db->delete($this->tableName, [$this->primaryKey => $primaryKeyValue]);
    }

    public static function find(mixed $id): ?static
    {
        $instance = new static();
        $db = $instance->getDatabase();

        $data = $db->select($instance->tableName, ['*'], [$instance->primaryKey => $id]);

        if (!empty($data)) {
            $instance->loadFromArray($data[0]);
            return $instance;
        }

        return null;
    }

    public static function findAll(array $conditions = [], int $limit = null, int $offset = null): array
    {
        $instance = new static();
        $db = $instance->getDatabase();

        $data = $db->select($instance->tableName, ['*'], $conditions, $limit, $offset);
        $results = [];

        foreach ($data as $row) {
            $model = new static();
            $model->loadFromArray($row);
            $results[] = $model;
        }

        return $results;
    }

    public function addValidationError(string $fieldName, array $errors): void
    {
        if (!isset($this->validationErrors[$fieldName])) {
            $this->validationErrors[$fieldName] = [];
        }
        $this->validationErrors[$fieldName] = array_merge($this->validationErrors[$fieldName], $errors);
    }

    protected function getDatabase(): DatabaseConnector
    {
        if ($this->db === null) {
            $this->db = DatabaseConnector::getInstance();
        }
        return $this->db;
    }

    // Getter methods
    public function getTableName(): string { return $this->tableName; }
    public function getPrimaryKey(): string { return $this->primaryKey; }
    public function getFields(): array { return $this->fields; }
    public function getMetadata(): array { return $this->metadata; }
    public function getValidationErrors(): array { return $this->validationErrors; }
    public function isLoaded(): bool { return $this->isLoaded; }
    public function hasChanges(): bool { return $this->hasChanges; }
    public function getField(string $fieldName): ?FieldsBase
    {
        return $this->fields[$fieldName] ?? null;
    }
}
