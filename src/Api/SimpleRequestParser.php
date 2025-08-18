<?php

namespace Gravitycar\Api;

/**
 * Simple format parser for basic field=value parameters
 * 
 * Handles the most basic query parameter format used by default.
 * This is the fallback parser when no other format is detected.
 */
class SimpleRequestParser extends FormatSpecificRequestParser
{
    /**
     * Parse simple format request data
     * 
     * @param array $requestData Raw request data
     * @return array Standardized parsed parameters
     */
    public function parse(array $requestData): array
    {
        $this->logger->debug('Parsing simple format request', [
            'param_count' => count($requestData)
        ]);
        
        $parsed = [
            'pagination' => $this->parsePagination($requestData),
            'sorting' => $this->parseSorting($requestData),
            'filters' => $this->parseFilters($requestData),
            'search' => $this->parseSearch($requestData),
            'responseFormat' => $this->getFormatName()
        ];
        
        $this->logger->debug('Simple format parsing completed', [
            'filters_count' => count($parsed['filters']),
            'sorts_count' => count($parsed['sorting']),
            'has_search' => !empty($parsed['search']['term'])
        ]);
        
        return $parsed;
    }
    
    /**
     * Simple format can handle any request (fallback parser)
     * 
     * @param array $requestData Raw request data
     * @return bool Always true (fallback parser)
     */
    public function canHandle(array $requestData): bool
    {
        return true; // Fallback parser handles everything
    }
    
    /**
     * Get format name
     * 
     * @return string Format name
     */
    public function getFormatName(): string
    {
        return 'simple';
    }
    
    /**
     * Parse pagination parameters
     * 
     * @param array $requestData Request data
     * @return array Pagination structure
     */
    protected function parsePagination(array $requestData): array
    {
        $page = (int)($requestData['page'] ?? self::DEFAULT_PAGE);
        $pageSize = (int)($requestData['pageSize'] ?? $requestData['per_page'] ?? self::DEFAULT_PAGE_SIZE);
        
        return $this->createPaginationStructure($page, $pageSize);
    }
    
    /**
     * Parse sorting parameters
     * 
     * @param array $requestData Request data
     * @return array Array of sort structures
     */
    protected function parseSorting(array $requestData): array
    {
        $sorting = [];
        
        // Handle sortBy/sortOrder pattern (TanStack Query style)
        if (isset($requestData['sortBy'])) {
            $field = $requestData['sortBy'];
            $direction = $requestData['sortOrder'] ?? 'asc';
            $sorting[] = $this->createSortStructure($field, $direction);
        }
        
        // Handle sort parameter (comma-separated field:direction)
        if (isset($requestData['sort'])) {
            $sortPairs = explode(',', $requestData['sort']);
            foreach ($sortPairs as $sortPair) {
                $parts = explode(':', trim($sortPair));
                $field = trim($parts[0]);
                $direction = isset($parts[1]) ? trim($parts[1]) : 'asc';
                
                if (!empty($field)) {
                    $sorting[] = $this->createSortStructure($field, $direction);
                }
            }
        }
        
        return $sorting;
    }
    
    /**
     * Parse filter parameters
     * 
     * @param array $requestData Request data
     * @return array Array of filter structures
     */
    protected function parseFilters(array $requestData): array
    {
        $filters = [];
        
        // Reserved parameter names that are not filters
        $reservedParams = [
            'page', 'pageSize', 'per_page', 'offset', 'limit',
            'sortBy', 'sortOrder', 'sort',
            'search', 'search_fields', 'q',
            'include_total', 'include_available_filters',
            'responseFormat', 'format'
        ];
        
        foreach ($requestData as $key => $value) {
            // Skip reserved parameters
            if (in_array($key, $reservedParams)) {
                continue;
            }
            
            // Skip empty values
            if ($value === '' || $value === null) {
                continue;
            }
            
            // Simple field=value filter (equals operator)
            $filters[] = $this->createFilterStructure($key, 'equals', $value);
        }
        
        return $filters;
    }
    
    /**
     * Parse search parameters
     * 
     * @param array $requestData Request data
     * @return array Search structure
     */
    protected function parseSearch(array $requestData): array
    {
        $searchTerm = $requestData['search'] ?? $requestData['q'] ?? '';
        $searchFields = [];
        
        // Parse search_fields parameter
        if (isset($requestData['search_fields'])) {
            $searchFields = explode(',', $requestData['search_fields']);
            $searchFields = array_map('trim', $searchFields);
            $searchFields = array_filter($searchFields); // Remove empty values
        }
        
        return [
            'term' => trim($searchTerm),
            'fields' => $searchFields,
            'operator' => 'contains' // Default search operator
        ];
    }
}
