<?php

namespace Gravitycar\Api;

use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;

/**
 * Abstract base class for format-specific request parsers
 * 
 * Provides common functionality for parsing different React library query parameter formats
 */
abstract class FormatSpecificRequestParser 
{
    protected Logger $logger;
    protected const MAX_PAGE_SIZE = 1000;
    protected const DEFAULT_PAGE_SIZE = 20;
    protected const DEFAULT_PAGE = 1;
    
    public function __construct()
    {
        $this->logger = ServiceLocator::getLogger();
    }
    
    /**
     * Parse request data into standardized format
     * 
     * @param array $requestData Raw request data
     * @return array Standardized parsed parameters
     */
    abstract public function parse(array $requestData): array;
    
    /**
     * Detect if this parser can handle the given request data
     * 
     * @param array $requestData Raw request data
     * @return bool True if this parser can handle the request
     */
    abstract public function canHandle(array $requestData): bool;
    
    /**
     * Get the format name for this parser
     * 
     * @return string Format name (e.g., 'ag-grid', 'mui', 'simple')
     */
    abstract public function getFormatName(): string;
    
    /**
     * Sanitize field name to prevent injection attacks
     * 
     * @param string $fieldName Raw field name
     * @return string Sanitized field name
     */
    protected function sanitizeFieldName(string $fieldName): string
    {
        // Allow alphanumeric, underscore, and dot for relationship fields
        return preg_replace('/[^a-zA-Z0-9_.]/', '', $fieldName);
    }
    
    /**
     * Validate and constrain page size
     * 
     * @param int $pageSize Raw page size
     * @return int Constrained page size
     */
    protected function constrainPageSize(int $pageSize): int
    {
        if ($pageSize <= 0) {
            return self::DEFAULT_PAGE_SIZE;
        }
        
        if ($pageSize > self::MAX_PAGE_SIZE) {
            $this->logger->warning('Page size exceeds maximum, constraining', [
                'requested_size' => $pageSize,
                'max_size' => self::MAX_PAGE_SIZE
            ]);
            return self::MAX_PAGE_SIZE;
        }
        
        return $pageSize;
    }
    
    /**
     * Validate and constrain page number
     * 
     * @param int $page Raw page number
     * @return int Constrained page number (1-based)
     */
    protected function constrainPageNumber(int $page): int
    {
        return max(1, $page);
    }
    
    /**
     * Parse sort direction
     * 
     * @param string $direction Raw sort direction
     * @return string Validated sort direction ('asc' or 'desc')
     */
    protected function parseSortDirection(string $direction): string
    {
        $direction = strtolower(trim($direction));
        return in_array($direction, ['asc', 'desc']) ? $direction : 'asc';
    }
    
    /**
     * Create standardized pagination structure
     * 
     * @param int $page Page number (1-based)
     * @param int $pageSize Items per page
     * @param int|null $offset Optional offset override
     * @return array Standardized pagination structure
     */
    protected function createPaginationStructure(int $page, int $pageSize, ?int $offset = null): array
    {
        $page = $this->constrainPageNumber($page);
        $pageSize = $this->constrainPageSize($pageSize);
        $calculatedOffset = ($page - 1) * $pageSize;
        
        return [
            'page' => $page,
            'pageSize' => $pageSize,
            'offset' => $offset ?? $calculatedOffset,
            'limit' => $pageSize
        ];
    }
    
    /**
     * Create standardized filter structure
     * 
     * @param string $field Field name
     * @param string $operator Filter operator
     * @param mixed $value Filter value
     * @return array Standardized filter structure
     */
    protected function createFilterStructure(string $field, string $operator, $value): array
    {
        return [
            'field' => $this->sanitizeFieldName($field),
            'operator' => $operator,
            'value' => $value
        ];
    }
    
    /**
     * Create standardized sort structure
     * 
     * @param string $field Field name
     * @param string $direction Sort direction
     * @return array Standardized sort structure
     */
    protected function createSortStructure(string $field, string $direction): array
    {
        return [
            'field' => $this->sanitizeFieldName($field),
            'direction' => $this->parseSortDirection($direction)
        ];
    }
    
    /**
     * Parse JSON parameter safely
     * 
     * @param string $jsonString JSON string to parse
     * @param array $fallback Fallback value if parsing fails
     * @return array Parsed JSON or fallback
     */
    protected function parseJsonParameter(string $jsonString, array $fallback = []): array
    {
        try {
            $decoded = json_decode($jsonString, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to parse JSON parameter', [
                'json_string' => substr($jsonString, 0, 200),
                'error' => $e->getMessage()
            ]);
        }
        
        return $fallback;
    }
}
