<?php

namespace Gravitycar\Api;

use Gravitycar\Core\ServiceLocator;
use Gravitycar\Models\ModelBase;
use Gravitycar\Fields\FieldBase;
use Monolog\Logger;

/**
 * Search Engine for Multi-Field Search Functionality
 * 
 * Provides validated search capabilities across multiple model fields
 * with field-type appropriate search methods
 */
class SearchEngine
{
    private Logger $logger;
    private array $searchOperators = [
        'contains', 'startsWith', 'endsWith', 'equals', 'fullText'
    ];
    
    public function __construct()
    {
        $this->logger = ServiceLocator::getLogger();
    }
    
    /**
     * Validate search parameters against model fields
     * 
     * @param array $searchParams Parsed search parameters from request
     * @param ModelBase $model Model instance to validate against
     * @return array Validated search parameters
     */
    public function validateSearchForModel(array $searchParams, ModelBase $model): array
    {
        $validatedSearch = [];
        $modelFields = $model->getFields();
        $modelName = get_class($model);
        
        // Validate search term
        if (isset($searchParams['term']) && !empty(trim($searchParams['term']))) {
            $validatedSearch['term'] = trim($searchParams['term']);
        } else {
            $this->logger->debug('No search term provided or term is empty');
            return []; // No search if no term
        }
        
        // Validate and filter search fields
        $requestedFields = $searchParams['fields'] ?? [];
        if (empty($requestedFields)) {
            // Use ModelBase's getSearchableFieldsList() method which respects displayColumns
            $requestedFields = $model->getSearchableFieldsList();
        }
        
        $validatedFields = [];
        foreach ($requestedFields as $fieldName) {
            // Check if field exists on model
            if (!isset($modelFields[$fieldName])) {
                $this->logger->warning('Search field does not exist on model, skipping', [
                    'field' => $fieldName,
                    'model' => $modelName
                ]);
                continue;
            }
            
            $field = $modelFields[$fieldName];
            
            // Check if field is a database field
            if (!$field->isDBField()) {
                $this->logger->warning('Search field is not a database field, skipping', [
                    'field' => $fieldName,
                    'field_type' => get_class($field)
                ]);
                continue;
            }
            
            // Check if field is searchable (not password fields, etc.)
            if (!$this->isFieldSearchable($field)) {
                $this->logger->warning('Field type is not searchable, skipping', [
                    'field' => $fieldName,
                    'field_type' => get_class($field)
                ]);
                continue;
            }
            
            $validatedFields[] = $fieldName;
            
            $this->logger->debug('Search field validated', [
                'field' => $fieldName,
                'field_type' => get_class($field)
            ]);
        }
        
        if (empty($validatedFields)) {
            $this->logger->warning('No valid search fields found', [
                'model' => $modelName,
                'requested_fields' => $requestedFields
            ]);
            return []; // No search if no valid fields
        }
        
        $validatedSearch['fields'] = $validatedFields;
        
        // Validate search operator if provided
        if (isset($searchParams['operator'])) {
            $operator = $searchParams['operator'];
            if (in_array($operator, $this->searchOperators)) {
                $validatedSearch['operator'] = $operator;
            } else {
                $this->logger->warning('Invalid search operator, using default', [
                    'operator' => $operator,
                    'valid_operators' => $this->searchOperators
                ]);
                $validatedSearch['operator'] = 'contains'; // Default
            }
        } else {
            $validatedSearch['operator'] = 'contains'; // Default
        }
        
        $this->logger->info('Search validation completed', [
            'model' => $modelName,
            'search_term' => $validatedSearch['term'],
            'search_fields' => $validatedSearch['fields'],
            'search_operator' => $validatedSearch['operator']
        ]);
        
        return $validatedSearch;
    }
    
    /**
     * Get all searchable fields for a model with their search configuration
     * 
     * @param ModelBase $model Model instance
     * @return array Searchable fields information
     */
    public function getSearchableFields(ModelBase $model): array
    {
        $searchableFields = [];
        $modelFields = $model->getFields();
        
        foreach ($modelFields as $fieldName => $field) {
            if (!$field->isDBField() || !$this->isFieldSearchable($field)) {
                continue;
            }
            
            $searchableFields[$fieldName] = [
                'fieldType' => get_class($field),
                'searchOperators' => $this->getSearchOperatorsForField($field),
                'fieldDescription' => $this->getFieldDescription($field),
                'isDefaultSearchable' => $this->isDefaultSearchable($field)
            ];
        }
        
        return $searchableFields;
    }
    
    /**
     * Get default searchable fields for a model
     * These are fields that are reasonable to search by default
     */
    protected function getDefaultSearchableFields(ModelBase $model): array
    {
        $defaultFields = [];
        $modelFields = $model->getFields();
        
        foreach ($modelFields as $fieldName => $field) {
            if ($this->isDefaultSearchable($field)) {
                $defaultFields[] = $fieldName;
            }
        }
        
        // If no default searchable fields, try to find some reasonable ones
        if (empty($defaultFields)) {
            foreach ($modelFields as $fieldName => $field) {
                if ($this->isFieldSearchable($field) && $field->isDBField()) {
                    $defaultFields[] = $fieldName;
                    // Limit to first few fields to avoid performance issues
                    if (count($defaultFields) >= 3) {
                        break;
                    }
                }
            }
        }
        
        $this->logger->debug('Determined default searchable fields', [
            'model' => get_class($model),
            'default_fields' => $defaultFields
        ]);
        
        return $defaultFields;
    }
    
    /**
     * Check if a field type is searchable
     */
    protected function isFieldSearchable(FieldBase $field): bool
    {
        $fieldType = get_class($field);
        
        // Never search password fields
        if ($fieldType === 'Gravitycar\\Fields\\PasswordField') {
            return false;
        }
        
        // Image fields are generally not useful for text search
        if ($fieldType === 'Gravitycar\\Fields\\ImageField') {
            return false;
        }
        
        // Large text fields might be searchable but should be opt-in
        if ($fieldType === 'Gravitycar\\Fields\\BigTextField') {
            // Check if explicitly marked as searchable
            return $field->getMetadataValue('isSearchable', false);
        }
        
        // Text fields are generally searchable
        if (in_array($fieldType, [
            'Gravitycar\\Fields\\TextField',
            'Gravitycar\\Fields\\EmailField'
        ])) {
            return true;
        }
        
        // Enum fields can be searched for exact matches
        if (in_array($fieldType, [
            'Gravitycar\\Fields\\EnumField',
            'Gravitycar\\Fields\\RadioButtonSetField'
        ])) {
            return true;
        }
        
        // Other field types can be searched if explicitly enabled
        return $field->getMetadataValue('isSearchable', false);
    }
    
    /**
     * Check if field should be included in default search
     */
    protected function isDefaultSearchable(FieldBase $field): bool
    {
        if (!$this->isFieldSearchable($field)) {
            return false;
        }
        
        // Check if explicitly marked for default search
        if ($field->getMetadataValue('isDefaultSearchable', null) !== null) {
            return $field->getMetadataValue('isDefaultSearchable', false);
        }
        
        $fieldType = get_class($field);
        
        // Common "name-like" field names that are good for default search
        $fieldName = $field->getName();
        $defaultSearchableNames = [
            'name', 'title', 'label', 'description', 'email',
            'first_name', 'last_name', 'username', 'display_name'
        ];
        
        if (in_array($fieldName, $defaultSearchableNames)) {
            return true;
        }
        
        // Text fields with names containing common searchable terms
        if ($fieldType === 'Gravitycar\\Fields\\TextField') {
            $searchablePatterns = ['name', 'title', 'label', 'code', 'identifier'];
            foreach ($searchablePatterns as $pattern) {
                if (strpos(strtolower($fieldName), $pattern) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get search operators appropriate for a field type
     */
    protected function getSearchOperatorsForField(FieldBase $field): array
    {
        $fieldType = get_class($field);
        
        switch ($fieldType) {
            case 'Gravitycar\\Fields\\TextField':
            case 'Gravitycar\\Fields\\EmailField':
            case 'Gravitycar\\Fields\\BigTextField':
                return ['contains', 'startsWith', 'endsWith', 'equals'];
                
            case 'Gravitycar\\Fields\\EnumField':
            case 'Gravitycar\\Fields\\RadioButtonSetField':
                return ['equals'];
                
            case 'Gravitycar\\Fields\\IntegerField':
            case 'Gravitycar\\Fields\\FloatField':
            case 'Gravitycar\\Fields\\IDField':
                return ['equals'];
                
            default:
                return ['contains', 'equals'];
        }
    }
    
    /**
     * Parse search term into components for advanced search features
     */
    public function parseSearchTerm(string $term): array
    {
        $parsedTerm = [
            'original' => $term,
            'cleaned' => trim($term),
            'words' => [],
            'quoted_phrases' => [],
            'operators' => []
        ];
        
        // Extract quoted phrases first
        preg_match_all('/"([^"]*)"/', $term, $quotedMatches);
        if (!empty($quotedMatches[1])) {
            $parsedTerm['quoted_phrases'] = $quotedMatches[1];
            // Remove quoted phrases from term for word extraction
            $term = preg_replace('/"[^"]*"/', '', $term);
        }
        
        // Extract individual words
        $words = preg_split('/\s+/', trim($term), -1, PREG_SPLIT_NO_EMPTY);
        $parsedTerm['words'] = array_filter($words, function($word) {
            return strlen($word) > 1; // Filter out single character words
        });
        
        $this->logger->debug('Parsed search term', $parsedTerm);
        
        return $parsedTerm;
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
            'EmailField' => 'Email address field',
            'EnumField' => 'Single selection dropdown',
            'RadioButtonSetField' => 'Radio button group',
            'IntegerField' => 'Numeric field',
            'FloatField' => 'Decimal field',
            'IDField' => 'Identifier field'
        ];
        
        return $descriptions[$shortName] ?? 'Custom field type';
    }
}
