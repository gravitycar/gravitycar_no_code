<?php

namespace Gravitycar\Factories;

use Gravitycar\Core\FieldsBase;
use Gravitycar\Core\GCException;
use Gravitycar\Core\ModelBase;

/**
 * Field Factory for creating field instances based on field type definitions
 *
 * This factory is responsible for creating the appropriate field class instances
 * based on the field type specified in metadata files.
 */
class FieldFactory
{
    private ?array $fieldDefinition = null;
    private array $fieldTypeMap = [];
    private string $defaultFieldType = 'TextField';
    private ?string $fieldClass = null;
    private ?FieldsBase $field = null;
    private ?ModelBase $model = null;
    private $logger = null;

    public function __construct(array $fieldDefinition = [], ModelBase $model = null)
    {
        $this->fieldDefinition = $fieldDefinition;
        $this->model = $model;
        $this->initializeFieldTypeMap();
    }

    private function initializeFieldTypeMap(): void
    {
        $this->fieldTypeMap = [
            'Text' => 'Gravitycar\\Fields\\TextField',
            'BigText' => 'Gravitycar\\Fields\\BigTextField',
            'Email' => 'Gravitycar\\Fields\\EmailField',
            'Password' => 'Gravitycar\\Fields\\PasswordField',
            'Integer' => 'Gravitycar\\Fields\\IntegerField',
            'Float' => 'Gravitycar\\Fields\\FloatField',
            'Boolean' => 'Gravitycar\\Fields\\BooleanField',
            'Date' => 'Gravitycar\\Fields\\DateField',
            'DateTime' => 'Gravitycar\\Fields\\DateTimeField',
            'Enum' => 'Gravitycar\\Fields\\EnumField',
            'MultiEnum' => 'Gravitycar\\Fields\\MultiEnumField',
            'Image' => 'Gravitycar\\Fields\\ImageField',
            'RelatedRecord' => 'Gravitycar\\Fields\\RelatedRecordField',
            'RadioButtonSet' => 'Gravitycar\\Fields\\RadioButtonSetField',
            'ID' => 'Gravitycar\\Fields\\IDField'
        ];
    }

    public function createField(): FieldsBase
    {
        if (empty($this->fieldDefinition)) {
            throw new GCException("Field definition is required to create a field");
        }

        $fieldType = $this->fieldDefinition['type'] ?? $this->defaultFieldType;

        if (isset($this->fieldTypeMap[$fieldType])) {
            $this->fieldClass = $this->fieldTypeMap[$fieldType];
        } else {
            $this->fieldClass = $this->fieldTypeMap[$this->defaultFieldType];
            if ($this->logger) {
                $modelName = $this->model ? get_class($this->model) : 'Unknown';
                $fieldName = $this->fieldDefinition['name'] ?? 'Unknown';
                error_log("Unknown field type '{$fieldType}' for field '{$fieldName}' in model '{$modelName}'. Using default type '{$this->defaultFieldType}'.");
            }
        }

        if (!class_exists($this->fieldClass)) {
            throw new GCException("Field class does not exist: {$this->fieldClass}");
        }

        $this->field = new $this->fieldClass($this->fieldDefinition);
        return $this->field;
    }

    public function getFieldDefinition(): array
    {
        return $this->fieldDefinition ?? [];
    }

    public function getModel(): ModelBase
    {
        return $this->model;
    }

    public function registerFieldType(string $typeName, string $className): void
    {
        if (!class_exists($className)) {
            throw new GCException("Field class does not exist: {$className}");
        }

        $this->fieldTypeMap[$typeName] = $className;
    }

    public function setLogger($logger): void
    {
        $this->logger = $logger;
    }
}
