<?php

namespace Gravitycar\Api;

/**
 * AG-Grid format parser for AG-Grid server-side data source
 * 
 * Handles AG-Grid specific parameters like startRow/endRow, complex filter models,
 * and AG-Grid sorting format.
 */
class AgGridRequestParser extends FormatSpecificRequestParser
{
    /**
     * Parse AG-Grid format request data
     * 
     * @param array $requestData Raw request data
     * @return array Standardized parsed parameters
     */
    public function parse(array $requestData): array
    {
        $this->logger->debug('Parsing AG-Grid format request', [
            'has_startRow' => isset($requestData['startRow']),
            'has_endRow' => isset($requestData['endRow']),
            'has_filters' => isset($requestData['filters'])
        ]);
        
        $parsed = [
            'pagination' => $this->parsePagination($requestData),
            'sorting' => $this->parseSorting($requestData),
            'filters' => $this->parseFilters($requestData),
            'search' => $this->parseSearch($requestData),
            'responseFormat' => $this->getFormatName()
        ];
        
        $this->logger->debug('AG-Grid format parsing completed', [
            'filters_count' => count($parsed['filters']),
            'sorts_count' => count($parsed['sorting']),
            'start_row' => $requestData['startRow'] ?? null,
            'end_row' => $requestData['endRow'] ?? null
        ]);
        
        return $parsed;
    }
    
    /**
     * Detect AG-Grid format by presence of startRow/endRow
     * 
     * @param array $requestData Raw request data
     * @return bool True if AG-Grid format detected
     */
    public function canHandle(array $requestData): bool
    {
        return isset($requestData['startRow']) && isset($requestData['endRow']);
    }
    
    /**
     * Get format name
     * 
     * @return string Format name
     */
    public function getFormatName(): string
    {
        return 'ag-grid';
    }
    
    /**
     * Parse AG-Grid pagination (startRow/endRow)
     * 
     * @param array $requestData Request data
     * @return array Pagination structure
     */
    protected function parsePagination(array $requestData): array
    {
        $startRow = (int)($requestData['startRow'] ?? 0);
        $endRow = (int)($requestData['endRow'] ?? self::DEFAULT_PAGE_SIZE);
        
        // Calculate page size and page number
        $pageSize = $endRow - $startRow;
        $pageSize = $this->constrainPageSize($pageSize);
        
        // Calculate page number (1-based)
        $page = $pageSize > 0 ? floor($startRow / $pageSize) + 1 : 1;
        
        return [
            'page' => $page,
            'pageSize' => $pageSize,
            'offset' => $startRow,
            'limit' => $pageSize,
            'startRow' => $startRow,
            'endRow' => $endRow
        ];
    }
    
    /**
     * Parse AG-Grid sorting format
     * 
     * @param array $requestData Request data
     * @return array Array of sort structures
     */
    protected function parseSorting(array $requestData): array
    {
        $sorting = [];
        
        // AG-Grid sends sort as: sort[0][colId], sort[0][sort], sort[1][colId], etc.
        foreach ($requestData as $key => $value) {
            if (preg_match('/^sort\[(\d+)\]\[colId\]$/', $key, $matches)) {
                $index = (int)$matches[1];
                $field = $value;
                $directionKey = "sort[{$index}][sort]";
                $direction = $requestData[$directionKey] ?? 'asc';
                
                if (!empty($field)) {
                    $sorting[] = [
                        'field' => $this->sanitizeFieldName($field),
                        'direction' => $this->parseSortDirection($direction),
                        'priority' => $index
                    ];
                }
            }
        }
        
        // Sort by priority to maintain order
        usort($sorting, function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
        
        // Remove priority from final result
        return array_map(function($sort) {
            unset($sort['priority']);
            return $sort;
        }, $sorting);
    }
    
    /**
     * Parse AG-Grid filter format
     * 
     * @param array $requestData Request data
     * @return array Array of filter structures
     */
    protected function parseFilters(array $requestData): array
    {
        $filters = [];
        
        // AG-Grid sends filters as: filters[fieldName][type], filters[fieldName][filter]
        $filterFields = [];
        
        foreach ($requestData as $key => $value) {
            if (preg_match('/^filters\[([^\]]+)\]\[type\]$/', $key, $matches)) {
                $fieldName = $matches[1];
                $filterType = $value;
                $filterValueKey = "filters[{$fieldName}][filter]";
                $filterValue = $requestData[$filterValueKey] ?? null;
                
                if (!empty($filterValue)) {
                    $operator = $this->mapAgGridFilterType($filterType);
                    $filters[] = $this->createFilterStructure($fieldName, $operator, $filterValue);
                }
            }
        }
        
        return $filters;
    }
    
    /**
     * Parse search parameters (AG-Grid doesn't have built-in global search)
     * 
     * @param array $requestData Request data
     * @return array Search structure
     */
    protected function parseSearch(array $requestData): array
    {
        // AG-Grid doesn't have standard global search, but check for common patterns
        $searchTerm = $requestData['globalFilter'] ?? $requestData['search'] ?? '';
        
        return [
            'term' => trim($searchTerm),
            'fields' => [],
            'operator' => 'contains'
        ];
    }
    
    /**
     * Map AG-Grid filter types to our standard operators
     * 
     * @param string $agGridType AG-Grid filter type
     * @return string Standard operator name
     */
    protected function mapAgGridFilterType(string $agGridType): string
    {
        $typeMap = [
            'equals' => 'equals',
            'notEqual' => 'notEquals',
            'contains' => 'contains',
            'notContains' => 'notContains',
            'startsWith' => 'startsWith',
            'endsWith' => 'endsWith',
            'lessThan' => 'lessThan',
            'lessThanOrEqual' => 'lessThanOrEqual',
            'greaterThan' => 'greaterThan',
            'greaterThanOrEqual' => 'greaterThanOrEqual',
            'inRange' => 'between',
            'empty' => 'isNull',
            'notEmpty' => 'isNotNull',
            'blank' => 'isNull',
            'notBlank' => 'isNotNull'
        ];
        
        return $typeMap[$agGridType] ?? 'equals';
    }
}
