<?php
namespace Gravitycar\Api;

use Gravitycar\Api\FormatSpecificRequestParser;

/**
 * Structured Request Parser for filter[field][operator]=value format
 * 
 * Example: 
 * GET /Users?startRow=0&endRow=100&filter[role][equals]=admin&filter[age][between]=18,65&sort[0][field]=created_at&sort[0][direction]=desc
 */
class StructuredRequestParser extends FormatSpecificRequestParser {
    
    /**
     * Parse request data into standardized format
     */
    public function parse(array $requestData): array {
        $this->logger->debug('Parsing structured request format', [
            'format' => 'structured',
            'request_keys' => array_keys($requestData)
        ]);
        
        return $this->parseToUnified($requestData);
    }
    
    /**
     * Detect if this parser can handle the given request data
     */
    public function canHandle(array $requestData): bool {
        // Look for structured filter format: filter[field][operator]
        if (isset($requestData['filter']) && is_array($requestData['filter'])) {
            foreach ($requestData['filter'] as $fieldName => $fieldData) {
                if (is_array($fieldData)) {
                    // Check if it has operator-style keys
                    foreach (array_keys($fieldData) as $key) {
                        if ($this->isValidOperator($key)) {
                            return true;
                        }
                    }
                }
            }
        }
        
        // Look for structured sort format: sort[0][field]
        if (isset($requestData['sort']) && is_array($requestData['sort'])) {
            foreach ($requestData['sort'] as $sortData) {
                if (is_array($sortData) && isset($sortData['field'])) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get the format name for this parser
     */
    public function getFormatName(): string {
        return 'structured';
    }
    
    /**
     * Parse structured format into unified parameter structure
     */
    public function parseToUnified(array $requestData): array {
        $parsed = [
            'pagination' => $this->parsePagination($requestData),
            'filters' => $this->parseFilters($requestData),
            'sorting' => $this->parseSorting($requestData),
            'search' => $this->parseSearch($requestData),
            'responseFormat' => 'structured'
        ];
        
        return $parsed;
    }
    
    /**
     * Parse pagination parameters for structured format
     */
    protected function parsePagination(array $requestData): array {
        // Support both startRow/endRow and page/pageSize patterns
        if (isset($requestData['startRow']) && isset($requestData['endRow'])) {
            $startRow = (int) $requestData['startRow'];
            $endRow = (int) $requestData['endRow'];
            $pageSize = $endRow - $startRow;
            $page = $pageSize > 0 ? floor($startRow / $pageSize) + 1 : 1;
            
            return [
                'page' => max(1, $page),
                'pageSize' => $this->constrainPageSize($pageSize),
                'offset' => $startRow
            ];
        }
        
        // Fallback to standard page/pageSize
        $page = max(1, (int) ($requestData['page'] ?? 1));
        $pageSize = $this->constrainPageSize((int) ($requestData['pageSize'] ?? 20));
        
        return [
            'page' => $page,
            'pageSize' => $pageSize,
            'offset' => ($page - 1) * $pageSize
        ];
    }
    
    /**
     * Parse filter parameters: filter[field][operator]=value
     */
    protected function parseFilters(array $requestData): array {
        $filters = [];
        
        if (!isset($requestData['filter']) || !is_array($requestData['filter'])) {
            return $filters;
        }
        
        foreach ($requestData['filter'] as $fieldName => $fieldFilters) {
            $fieldName = $this->sanitizeFieldName($fieldName);
            if (!$fieldName) continue;
            
            if (!is_array($fieldFilters)) {
                // Simple filter[field]=value format - assume equals
                $filters[] = $this->createFilterStructure($fieldName, 'equals', $fieldFilters);
                continue;
            }
            
            foreach ($fieldFilters as $operator => $value) {
                if ($this->isValidOperator($operator)) {
                    // Handle comma-separated values for 'in', 'notIn', 'between'
                    if (in_array($operator, ['in', 'notIn', 'between']) && is_string($value)) {
                        $value = array_map('trim', explode(',', $value));
                    }
                    
                    $filters[] = $this->createFilterStructure($fieldName, $operator, $value);
                }
            }
        }
        
        return $filters;
    }
    
    /**
     * Parse sorting parameters: sort[0][field]=created_at&sort[0][direction]=desc
     */
    protected function parseSorting(array $requestData): array {
        $sorting = [];
        
        if (!isset($requestData['sort']) || !is_array($requestData['sort'])) {
            return $sorting;
        }
        
        // Handle indexed sort array: sort[0][field], sort[0][direction]
        foreach ($requestData['sort'] as $index => $sortData) {
            if (!is_array($sortData)) continue;
            
            $field = $this->sanitizeFieldName($sortData['field'] ?? '');
            $direction = strtolower($sortData['direction'] ?? 'asc');
            
            if ($field && in_array($direction, ['asc', 'desc'])) {
                $sorting[] = [
                    'field' => $field,
                    'direction' => $direction,
                    'priority' => (int) $index
                ];
            }
        }
        
        // Sort by priority to maintain order
        usort($sorting, function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
        
        return $sorting;
    }
    
    /**
     * Parse search parameters
     */
    protected function parseSearch(array $requestData): array {
        $search = [];
        
        // Global search term
        if (!empty($requestData['search'])) {
            $search['term'] = trim($requestData['search']);
        }
        
        // Search fields specification
        if (!empty($requestData['searchFields'])) {
            if (is_string($requestData['searchFields'])) {
                $search['fields'] = array_map('trim', explode(',', $requestData['searchFields']));
            } elseif (is_array($requestData['searchFields'])) {
                $search['fields'] = $requestData['searchFields'];
            }
        }
        
        return $search;
    }
    
    /**
     * Check if operator is valid
     */
    protected function isValidOperator(string $operator): bool {
        $validOperators = [
            'equals', 'notEquals', 'contains', 'startsWith', 'endsWith',
            'in', 'notIn', 'greaterThan', 'greaterThanOrEqual', 
            'lessThan', 'lessThanOrEqual', 'between',
            'isNull', 'isNotNull', 'overlap', 'containsAll', 'containsNone'
        ];
        
        return in_array($operator, $validOperators, true);
    }
}
