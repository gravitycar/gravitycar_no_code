<?php

namespace Gravitycar\Api;

use Gravitycar\Core\ServiceLocator;
use Gravitycar\Models\ModelBase;
use Gravitycar\Fields\FieldBase;
use Monolog\Logger;

/**
 * Filter Criteria Management with Field-Based Operator Validation
 * 
 * Validates filters against model fields and their allowed operators
 * Provides type-appropriate validation and SQL generation
 */
class FilterCriteria
{
    private Logger $logger;
    
    public function __construct()
    {
        $this->logger = ServiceLocator::getLogger();
    }
    
    /**
     * Validate filters against model fields and their allowed operators
     * 
     * @param array $filters Parsed filter array from request
     * @param ModelBase $model Model instance to validate against
     * @return array Validated filters that passed all checks
     */
    public function validateAndFilterForModel(array $filters, ModelBase $model): array
    {
        $validatedFilters = [];
        $modelFields = $model->getFields();
        $modelName = get_class($model);
        
        $this->logger->debug('Starting filter validation for model', [
            'model' => $modelName,
            'filter_count' => count($filters),
            'model_field_count' => count($modelFields)
        ]);
        
        foreach ($filters as $filter) {
            if (!isset($filter['field']) || !isset($filter['operator'])) {
                $this->logger->warning('Invalid filter structure, skipping', [
                    'filter' => $filter
                ]);
                continue;
            }
            
            $fieldName = $filter['field'];
            $operator = $filter['operator'];
            $value = $filter['value'] ?? null;
            
            // Check if field exists on model
            if (!isset($modelFields[$fieldName])) {
                $this->logger->warning('Filter field does not exist on model, skipping', [
                    'field' => $fieldName,
                    'model' => $modelName,
                    'available_fields' => array_keys($modelFields)
                ]);
                continue;
            }
            
            $field = $modelFields[$fieldName];
            
            // Check if field is a database field
            if (!$field->isDBField()) {
                $this->logger->warning('Filter field is not a database field, skipping', [
                    'field' => $fieldName,
                    'field_type' => get_class($field)
                ]);
                continue;
            }
            
            // Check if operator is supported by this field
            if (!$field->supportsOperator($operator)) {
                $this->logger->warning('Operator not supported by field type, skipping', [
                    'field' => $fieldName,
                    'operator' => $operator,
                    'field_type' => get_class($field),
                    'supported_operators' => $field->getSupportedOperators()
                ]);
                continue;
            }
            
            // Validate value against field type
            $validatedValue = $this->validateValueForField($value, $operator, $field);
            if ($validatedValue === null && !in_array($operator, ['isNull', 'isNotNull'])) {
                $this->logger->warning('Value validation failed for field, skipping', [
                    'field' => $fieldName,
                    'operator' => $operator,
                    'value' => $value,
                    'field_type' => get_class($field)
                ]);
                continue;
            }
            
            // Filter passed all validation checks
            $validatedFilters[] = [
                'field' => $fieldName,
                'operator' => $operator,
                'value' => $validatedValue,
                'fieldType' => get_class($field)
            ];
            
            $this->logger->debug('Filter validated successfully', [
                'field' => $fieldName,
                'operator' => $operator,
                'field_type' => get_class($field)
            ]);
        }
        
        $this->logger->info('Filter validation completed', [
            'model' => $modelName,
            'input_filter_count' => count($filters),
            'validated_filter_count' => count($validatedFilters)
        ]);
        
        return $validatedFilters;
    }
    
    /**
     * Get all filterable fields for a model with their supported operators
     * 
     * @param ModelBase $model Model instance
     * @return array Filterable fields with operator information
     */
    public function getSupportedFilters(ModelBase $model): array
    {
        $supportedFilters = [];
        $modelFields = $model->getFields();
        
        foreach ($modelFields as $fieldName => $field) {
            // Only include database fields
            if (!$field->isDBField()) {
                continue;
            }
            
            $supportedFilters[$fieldName] = [
                'fieldType' => get_class($field),
                'operators' => $field->getSupportedOperators(),
                'operatorDescriptions' => $field->getOperatorDescriptions(),
                'fieldDescription' => $this->getFieldDescription($field)
            ];
        }
        
        return $supportedFilters;
    }
    
    /**
     * Validate value against field type and operator requirements
     * 
     * @param mixed $value Value to validate
     * @param string $operator Operator being used
     * @param FieldBase $field Field instance for validation
     * @return mixed|null Validated value or null if validation fails
     */
    protected function validateValueForField($value, string $operator, FieldBase $field)
    {
        // Null operators don't need value validation
        if (in_array($operator, ['isNull', 'isNotNull'])) {
            return null;
        }
        
        $fieldType = get_class($field);
        
        try {
            switch ($fieldType) {
                case 'Gravitycar\\Fields\\IntegerField':
                case 'Gravitycar\\Fields\\IDField':
                    return $this->validateIntegerValue($value, $operator);
                    
                case 'Gravitycar\\Fields\\FloatField':
                    return $this->validateFloatValue($value, $operator);
                    
                case 'Gravitycar\\Fields\\BooleanField':
                    return $this->validateBooleanValue($value, $operator);
                    
                case 'Gravitycar\\Fields\\DateField':
                case 'Gravitycar\\Fields\\DateTimeField':
                    return $this->validateDateValue($value, $operator);
                    
                case 'Gravitycar\\Fields\\EnumField':
                case 'Gravitycar\\Fields\\RadioButtonSetField':
                    return $this->validateEnumValue($value, $operator, $field);
                    
                case 'Gravitycar\\Fields\\MultiEnumField':
                    return $this->validateMultiEnumValue($value, $operator, $field);
                    
                case 'Gravitycar\\Fields\\TextField':
                case 'Gravitycar\\Fields\\BigTextField':
                case 'Gravitycar\\Fields\\EmailField':
                case 'Gravitycar\\Fields\\PasswordField':
                case 'Gravitycar\\Fields\\ImageField':
                default:
                    return $this->validateStringValue($value, $operator);
            }
        } catch (\Exception $e) {
            $this->logger->error('Value validation exception', [
                'field_type' => $fieldType,
                'operator' => $operator,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Validate integer values
     */
    protected function validateIntegerValue($value, string $operator)
    {
        if (in_array($operator, ['in', 'notIn', 'between'])) {
            if (!is_array($value)) {
                return null;
            }
            return array_map('intval', array_filter($value, 'is_numeric'));
        }
        
        return is_numeric($value) ? (int) $value : null;
    }
    
    /**
     * Validate float values
     */
    protected function validateFloatValue($value, string $operator)
    {
        if (in_array($operator, ['in', 'notIn', 'between'])) {
            if (!is_array($value)) {
                return null;
            }
            return array_map('floatval', array_filter($value, 'is_numeric'));
        }
        
        return is_numeric($value) ? (float) $value : null;
    }
    
    /**
     * Validate boolean values
     */
    protected function validateBooleanValue($value, string $operator)
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['true', '1', 'yes', 'on'])) {
                return true;
            }
            if (in_array($value, ['false', '0', 'no', 'off'])) {
                return false;
            }
        }
        
        return is_numeric($value) ? (bool) $value : null;
    }
    
    /**
     * Validate date values
     */
    protected function validateDateValue($value, string $operator)
    {
        if (in_array($operator, ['in', 'notIn', 'between'])) {
            if (!is_array($value)) {
                return null;
            }
            return array_filter(array_map([$this, 'validateSingleDateValue'], $value));
        }
        
        return $this->validateSingleDateValue($value);
    }
    
    /**
     * Validate single date value
     */
    protected function validateSingleDateValue($value)
    {
        if (is_string($value)) {
            $timestamp = strtotime($value);
            return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
        }
        
        return null;
    }
    
    /**
     * Validate enum values against allowed options
     */
    protected function validateEnumValue($value, string $operator, FieldBase $field)
    {
        $options = $field->getMetadataValue('options', []);
        
        if (in_array($operator, ['in', 'notIn'])) {
            if (!is_array($value)) {
                return null;
            }
            
            $validValues = [];
            foreach ($value as $val) {
                if (isset($options[$val]) || in_array($val, $options)) {
                    $validValues[] = $val;
                }
            }
            return empty($validValues) ? null : $validValues;
        }
        
        // Single value validation
        return (isset($options[$value]) || in_array($value, $options)) ? $value : null;
    }
    
    /**
     * Validate multi-enum values
     */
    protected function validateMultiEnumValue($value, string $operator, FieldBase $field)
    {
        $options = $field->getMetadataValue('options', []);
        
        if (in_array($operator, ['overlap', 'containsAll', 'containsNone'])) {
            if (!is_array($value)) {
                return null;
            }
            
            $validValues = [];
            foreach ($value as $val) {
                if (isset($options[$val]) || in_array($val, $options)) {
                    $validValues[] = $val;
                }
            }
            return empty($validValues) ? null : $validValues;
        }
        
        return $this->validateEnumValue($value, $operator, $field);
    }
    
    /**
     * Validate string values
     */
    protected function validateStringValue($value, string $operator)
    {
        if (in_array($operator, ['in', 'notIn'])) {
            if (!is_array($value)) {
                return null;
            }
            return array_map('strval', $value);
        }
        
        return is_scalar($value) ? (string) $value : null;
    }
    
    /**
     * Get human-readable description for a field type
     */
    protected function getFieldDescription(FieldBase $field): string
    {
        $fieldType = get_class($field);
        $shortName = substr($fieldType, strrpos($fieldType, '\\') + 1);
        
        $descriptions = [
            'TextField' => 'Text input field',
            'BigTextField' => 'Large text area field',
            'IntegerField' => 'Numeric integer field',
            'FloatField' => 'Decimal number field',
            'BooleanField' => 'True/false checkbox field',
            'DateField' => 'Date selection field',
            'DateTimeField' => 'Date and time selection field',
            'EmailField' => 'Email address field',
            'PasswordField' => 'Password input field',
            'EnumField' => 'Single selection dropdown',
            'MultiEnumField' => 'Multiple selection field',
            'RadioButtonSetField' => 'Radio button group',
            'IDField' => 'Unique identifier field',
            'ImageField' => 'Image file path field',
            'RelatedRecordField' => 'Foreign key relationship field'
        ];
        
        return $descriptions[$shortName] ?? 'Custom field type';
    }
}
