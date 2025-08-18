<?php

namespace Gravitycar\Api;

/**
 * MUI DataGrid format parser for Material-UI DataGrid server-side data source
 * 
 * Handles MUI DataGrid specific JSON parameters like filterModel and sortModel.
 */
class MuiDataGridRequestParser extends FormatSpecificRequestParser
{
    /**
     * Parse MUI DataGrid format request data
     * 
     * @param array $requestData Raw request data
     * @return array Standardized parsed parameters
     */
    public function parse(array $requestData): array
    {
        $this->logger->debug('Parsing MUI DataGrid format request', [
            'has_filterModel' => isset($requestData['filterModel']),
            'has_sortModel' => isset($requestData['sortModel']),
            'page' => $requestData['page'] ?? null
        ]);
        
        $parsed = [
            'pagination' => $this->parsePagination($requestData),
            'sorting' => $this->parseSorting($requestData),
            'filters' => $this->parseFilters($requestData),
            'search' => $this->parseSearch($requestData),
            'responseFormat' => $this->getFormatName()
        ];
        
        $this->logger->debug('MUI DataGrid format parsing completed', [
            'filters_count' => count($parsed['filters']),
            'sorts_count' => count($parsed['sorting']),
            'page' => $parsed['pagination']['page']
        ]);
        
        return $parsed;
    }
    
    /**
     * Detect MUI DataGrid format by presence of filterModel or sortModel
     * 
     * @param array $requestData Raw request data
     * @return bool True if MUI DataGrid format detected
     */
    public function canHandle(array $requestData): bool
    {
        return isset($requestData['filterModel']) || isset($requestData['sortModel']);
    }
    
    /**
     * Get format name
     * 
     * @return string Format name
     */
    public function getFormatName(): string
    {
        return 'mui-datagrid';
    }
    
    /**
     * Parse MUI DataGrid pagination (0-based page)
     * 
     * @param array $requestData Request data
     * @return array Pagination structure
     */
    protected function parsePagination(array $requestData): array
    {
        // MUI DataGrid uses 0-based page numbers
        $page = (int)($requestData['page'] ?? 0) + 1; // Convert to 1-based
        $pageSize = (int)($requestData['pageSize'] ?? self::DEFAULT_PAGE_SIZE);
        
        return $this->createPaginationStructure($page, $pageSize);
    }
    
    /**
     * Parse MUI DataGrid sorting (JSON sortModel)
     * 
     * @param array $requestData Request data
     * @return array Array of sort structures
     */
    protected function parseSorting(array $requestData): array
    {
        $sorting = [];
        
        if (isset($requestData['sortModel'])) {
            $sortModel = $this->parseJsonParameter($requestData['sortModel'], []);
            
            foreach ($sortModel as $sort) {
                if (isset($sort['field']) && isset($sort['sort'])) {
                    $sorting[] = $this->createSortStructure($sort['field'], $sort['sort']);
                }
            }
        }
        
        return $sorting;
    }
    
    /**
     * Parse MUI DataGrid filter format (JSON filterModel)
     * 
     * @param array $requestData Request data
     * @return array Array of filter structures
     */
    protected function parseFilters(array $requestData): array
    {
        $filters = [];
        
        if (isset($requestData['filterModel'])) {
            $filterModel = $this->parseJsonParameter($requestData['filterModel'], []);
            
            // MUI DataGrid filterModel structure:
            // {
            //   "items": [
            //     {"field": "name", "operator": "contains", "value": "john"},
            //     {"field": "age", "operator": ">=", "value": 18}
            //   ],
            //   "logicOperator": "and"
            // }
            
            if (isset($filterModel['items']) && is_array($filterModel['items'])) {
                foreach ($filterModel['items'] as $item) {
                    if (isset($item['field']) && isset($item['operator']) && isset($item['value'])) {
                        $operator = $this->mapMuiOperator($item['operator']);
                        $filters[] = $this->createFilterStructure($item['field'], $operator, $item['value']);
                    }
                }
            } else {
                // Alternative format: direct field => value mapping
                foreach ($filterModel as $field => $value) {
                    if (is_string($field) && !empty($value)) {
                        if (is_array($value)) {
                            // Handle complex operators like {"gte": 18, "lte": 65}
                            foreach ($value as $op => $val) {
                                $operator = $this->mapMuiOperator($op);
                                $filters[] = $this->createFilterStructure($field, $operator, $val);
                            }
                        } else {
                            // Simple field => value mapping (equals)
                            $filters[] = $this->createFilterStructure($field, 'equals', $value);
                        }
                    }
                }
            }
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
        
        return [
            'term' => trim($searchTerm),
            'fields' => [],
            'operator' => 'contains'
        ];
    }
    
    /**
     * Map MUI DataGrid operators to our standard operators
     * 
     * @param string $muiOperator MUI DataGrid operator
     * @return string Standard operator name
     */
    protected function mapMuiOperator(string $muiOperator): string
    {
        $operatorMap = [
            // String operators
            'contains' => 'contains',
            'equals' => 'equals',
            'startsWith' => 'startsWith',
            'endsWith' => 'endsWith',
            'isEmpty' => 'isNull',
            'isNotEmpty' => 'isNotNull',
            'isAnyOf' => 'in',
            
            // Number operators
            '=' => 'equals',
            '!=' => 'notEquals',
            '>' => 'greaterThan',
            '>=' => 'greaterThanOrEqual',
            'gte' => 'greaterThanOrEqual',
            '<' => 'lessThan',
            '<=' => 'lessThanOrEqual',
            'lte' => 'lessThanOrEqual',
            
            // Date operators
            'is' => 'equals',
            'not' => 'notEquals',
            'after' => 'greaterThan',
            'onOrAfter' => 'greaterThanOrEqual',
            'before' => 'lessThan',
            'onOrBefore' => 'lessThanOrEqual',
            
            // Boolean operators
            'true' => 'equals',
            'false' => 'equals'
        ];
        
        return $operatorMap[$muiOperator] ?? 'equals';
    }
}
