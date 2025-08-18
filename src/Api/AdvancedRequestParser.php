<?php
namespace Gravitycar\Api;

use Gravitycar\Api\FormatSpecificRequestParser;

/**
 * Advanced Request Parser for comprehensive query parameter format
 * 
 * Example: 
 * GET /Users?page=1&per_page=20&search=john&search_fields=first_name,last_name,email&filter[role]=admin&filter[age][gte]=18&filter[age][lte]=65&filter[status][in]=active,pending&sort=created_at:desc,name:asc&include_total=true&include_available_filters=true
 */
class AdvancedRequestParser extends FormatSpecificRequestParser {
    
    /**
     * Parse request data into standardized format
     */
    public function parse(array $requestData): array {
        $this->logger->debug('Parsing advanced request format', [
            'format' => 'advanced',
            'request_keys' => array_keys($requestData)
        ]);
        
        return $this->parseToUnified($requestData);
    }
    
    /**
     * Detect if this parser can handle the given request data
     */
    public function canHandle(array $requestData): bool {
        // Look for advanced-specific parameters
        $advancedParams = [
            'per_page', 'search_fields', 'include_total', 
            'include_available_filters', 'include_metadata'
        ];
        
        foreach ($advancedParams as $param) {
            if (isset($requestData[$param])) {
                return true;
            }
        }
        
        // Look for sort parameter with colon syntax: sort=field:direction,field2:direction
        if (isset($requestData['sort']) && is_string($requestData['sort'])) {
            if (strpos($requestData['sort'], ':') !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get the format name for this parser
     */
    public function getFormatName(): string {
        return 'advanced';
    }
    
    /**
     * Parse advanced format into unified parameter structure
     */
    public function parseToUnified(array $requestData): array {
        $parsed = [
            'pagination' => $this->parsePagination($requestData),
            'filters' => $this->parseFilters($requestData),
            'sorting' => $this->parseSorting($requestData),
            'search' => $this->parseSearch($requestData),
            'responseFormat' => 'advanced',
            'options' => $this->parseOptions($requestData)
        ];
        
        return $parsed;
    }
    
    /**
     * Parse pagination parameters for advanced format
     */
    protected function parsePagination(array $requestData): array {
        // Support both 'per_page' and 'pageSize'
        $pageSize = $this->constrainPageSize((int) ($requestData['per_page'] ?? $requestData['pageSize'] ?? 20));
        $page = max(1, (int) ($requestData['page'] ?? 1));
        
        return [
            'page' => $page,
            'pageSize' => $pageSize,
            'offset' => ($page - 1) * $pageSize
        ];
    }
    
    /**
     * Parse filter parameters: filter[field] or filter[field][operator]
     */
    protected function parseFilters(array $requestData): array {
        $filters = [];
        
        if (!isset($requestData['filter']) || !is_array($requestData['filter'])) {
            return $filters;
        }
        
        foreach ($requestData['filter'] as $fieldName => $fieldData) {
            $fieldName = $this->sanitizeFieldName($fieldName);
            if (!$fieldName) continue;
            
            if (is_array($fieldData)) {
                // Advanced operator format: filter[field][operator]=value
                foreach ($fieldData as $operator => $value) {
                    if ($this->isValidOperator($operator)) {
                        // Handle comma-separated values for certain operators
                        if (in_array($operator, ['in', 'notIn', 'between']) && is_string($value)) {
                            $value = array_map('trim', explode(',', $value));
                        }
                        
                        $filters[] = $this->createFilterStructure($fieldName, $operator, $value);
                    }
                }
            } else {
                // Simple format: filter[field]=value (assume equals)
                $filters[] = $this->createFilterStructure($fieldName, 'equals', $fieldData);
            }
        }
        
        return $filters;
    }
    
    /**
     * Parse sorting parameters: sort=field:direction,field2:direction
     */
    protected function parseSorting(array $requestData): array {
        $sorting = [];
        
        if (empty($requestData['sort'])) {
            return $sorting;
        }
        
        $sortString = $requestData['sort'];
        if (!is_string($sortString)) {
            return $sorting;
        }
        
        // Parse comma-separated sort fields: "created_at:desc,name:asc"
        $sortPairs = array_map('trim', explode(',', $sortString));
        $priority = 0;
        
        foreach ($sortPairs as $sortPair) {
            if (strpos($sortPair, ':') !== false) {
                list($field, $direction) = explode(':', $sortPair, 2);
                $field = $this->sanitizeFieldName(trim($field));
                $direction = strtolower(trim($direction));
                
                if ($field && in_array($direction, ['asc', 'desc'])) {
                    $sorting[] = [
                        'field' => $field,
                        'direction' => $direction,
                        'priority' => $priority++
                    ];
                }
            } else {
                // Default to ascending if no direction specified
                $field = $this->sanitizeFieldName(trim($sortPair));
                if ($field) {
                    $sorting[] = [
                        'field' => $field,
                        'direction' => 'asc',
                        'priority' => $priority++
                    ];
                }
            }
        }
        
        return $sorting;
    }
    
    /**
     * Parse search parameters with advanced features
     */
    protected function parseSearch(array $requestData): array {
        $search = [];
        
        // Global search term
        if (!empty($requestData['search'])) {
            $search['term'] = trim($requestData['search']);
        }
        
        // Search fields specification
        if (!empty($requestData['search_fields'])) {
            if (is_string($requestData['search_fields'])) {
                $search['fields'] = array_map('trim', explode(',', $requestData['search_fields']));
            } elseif (is_array($requestData['search_fields'])) {
                $search['fields'] = $requestData['search_fields'];
            }
        }
        
        // Search operator (contains, startsWith, etc.)
        if (!empty($requestData['search_operator'])) {
            $search['operator'] = $requestData['search_operator'];
        }
        
        return $search;
    }
    
    /**
     * Parse additional options for advanced format
     */
    protected function parseOptions(array $requestData): array {
        $options = [];
        
        // Include total count in response
        if (isset($requestData['include_total'])) {
            $options['includeTotal'] = $this->parseBooleanParam($requestData['include_total']);
        }
        
        // Include available filters in response
        if (isset($requestData['include_available_filters'])) {
            $options['includeAvailableFilters'] = $this->parseBooleanParam($requestData['include_available_filters']);
        }
        
        // Include model metadata in response
        if (isset($requestData['include_metadata'])) {
            $options['includeMetadata'] = $this->parseBooleanParam($requestData['include_metadata']);
        }
        
        // Include related data
        if (!empty($requestData['include'])) {
            if (is_string($requestData['include'])) {
                $options['include'] = array_map('trim', explode(',', $requestData['include']));
            } elseif (is_array($requestData['include'])) {
                $options['include'] = $requestData['include'];
            }
        }
        
        return $options;
    }
    
    /**
     * Parse boolean parameter from string values
     */
    protected function parseBooleanParam($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['true', '1', 'yes', 'on'], true);
        }
        
        return (bool) $value;
    }
    
    /**
     * Check if operator is valid
     */
    protected function isValidOperator(string $operator): bool {
        $validOperators = [
            'equals', 'notEquals', 'contains', 'startsWith', 'endsWith',
            'in', 'notIn', 'greaterThan', 'greaterThanOrEqual', 
            'lessThan', 'lessThanOrEqual', 'between',
            'isNull', 'isNotNull', 'overlap', 'containsAll', 'containsNone',
            // Shorthand operators
            'eq', 'ne', 'gt', 'gte', 'lt', 'lte'
        ];
        
        return in_array($operator, $validOperators, true);
    }
}
