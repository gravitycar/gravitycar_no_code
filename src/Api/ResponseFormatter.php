<?php

namespace Gravitycar\Api;

use Psr\Log\LoggerInterface;
use Gravitycar\Core\ServiceLocator;

/**
 * ResponseFormatter - Formats API responses for different React libraries and data fetching patterns
 * 
 * This class provides format-specific response structures optimized for various React data 
 * fetching libraries including TanStack Query, SWR, AG-Grid, MUI DataGrid, and infinite scroll.
 */
class ResponseFormatter
{
    private LoggerInterface $logger;
    
    public function __construct()
    {
        $this->logger = ServiceLocator::getLogger();
    }
    
    /**
     * Main formatting entry point - routes to format-specific methods
     */
    public function format(array $data, array $meta, string $format): array
    {
        $startTime = microtime(true);
        
        $response = match ($format) {
            'ag-grid' => $this->formatForAgGrid($data, $meta),
            'mui', 'mui-datagrid' => $this->formatForMuiDataGrid($data, $meta),
            'tanstack-query', 'react-query' => $this->formatForTanStackQuery($data, $meta),
            'swr' => $this->formatForSWR($data, $meta),
            'infinite-scroll' => $this->formatForInfiniteScroll($data, $meta),
            'cursor' => $this->formatForCursorPagination($data, $meta),
            default => $this->formatStandard($data, $meta)
        };
        
        $formatTime = round((microtime(true) - $startTime) * 1000, 2);
        
        $this->logger->info("Response formatted", [
            'format' => $format,
            'record_count' => count($data),
            'format_time_ms' => $formatTime,
            'includes_metadata' => !empty($meta)
        ]);
        
        return $response;
    }
    
    /**
     * AG-Grid server-side data source format
     * Optimized for AG-Grid's infinite scroll and server-side filtering
     */
    private function formatForAgGrid(array $data, array $meta): array
    {
        $pagination = $meta['pagination'] ?? [];
        
        // AG-Grid expects lastRow to be set when we reach the end
        $lastRow = null;
        if (isset($pagination['hasNextPage']) && !$pagination['hasNextPage']) {
            $lastRow = ($pagination['offset'] ?? 0) + count($data);
        }
        
        $response = [
            'success' => true,
            'data' => $data,
            'lastRow' => $lastRow  // Always include lastRow (null means more data available)
        ];
        
        // Include additional metadata for debugging if available
        if (isset($meta['debug']) && $meta['debug']) {
            $response['meta'] = [
                'pagination' => $pagination,
                'filters' => $meta['filters'] ?? [],
                'sorting' => $meta['sorting'] ?? [],
                'search' => $meta['search'] ?? []
            ];
        }
        
        return $response;
    }
    
    /**
     * MUI DataGrid server-side format
     * Optimized for Material-UI DataGrid server-side operations
     */
    private function formatForMuiDataGrid(array $data, array $meta): array
    {
        $pagination = $meta['pagination'] ?? [];
        
        return [
            'success' => true,
            'data' => $data,
            'rowCount' => $pagination['total'] ?? 0,
            'meta' => [
                'page' => $pagination['page'] ?? 0, // MUI uses 0-based pages
                'pageSize' => $pagination['pageSize'] ?? 25,
                'total' => $pagination['total'] ?? 0,
                'hasNextPage' => $pagination['hasNextPage'] ?? false,
                'hasPreviousPage' => $pagination['hasPreviousPage'] ?? false
            ],
            'filters' => $meta['filters'] ?? [],
            'sorting' => $meta['sorting'] ?? []
        ];
    }
    
    /**
     * TanStack Query (React Query) format
     * Optimized for TanStack Query with comprehensive metadata
     */
    private function formatForTanStackQuery(array $data, array $meta): array
    {
        return [
            'success' => true,
            'data' => $data,
            'meta' => $this->buildComprehensiveMeta($meta),
            'links' => $this->buildPaginationLinks($meta['pagination'] ?? []),
            'timestamp' => date('c') // ISO 8601 timestamp for cache management
        ];
    }
    
    /**
     * SWR format
     * Similar to TanStack Query but optimized for SWR's caching patterns
     */
    private function formatForSWR(array $data, array $meta): array
    {
        return [
            'success' => true,
            'data' => $data,
            'meta' => $this->buildComprehensiveMeta($meta),
            'pagination' => $this->buildSWRPagination($meta['pagination'] ?? []),
            'cache_key' => $this->generateCacheKey($meta),
            'timestamp' => time()
        ];
    }
    
    /**
     * Infinite scroll format
     * Optimized for cursor-based infinite scroll implementations
     */
    private function formatForInfiniteScroll(array $data, array $meta): array
    {
        $pagination = $meta['pagination'] ?? [];
        
        return [
            'success' => true,
            'data' => $data,
            'pagination' => [
                'hasNextPage' => $pagination['hasNextPage'] ?? false,
                'nextCursor' => $pagination['nextCursor'] ?? null,
                'pageSize' => $pagination['pageSize'] ?? count($data)
            ],
            'meta' => [
                'total' => $pagination['total'] ?? null, // Optional for infinite scroll
                'filters' => $meta['filters'] ?? [],
                'search' => $meta['search'] ?? []
            ]
        ];
    }
    
    /**
     * Cursor-based pagination format
     * For high-performance cursor pagination with encoded cursors
     */
    private function formatForCursorPagination(array $data, array $meta): array
    {
        $pagination = $meta['pagination'] ?? [];
        
        return [
            'success' => true,
            'data' => $data,
            'pageInfo' => [
                'hasNextPage' => $pagination['hasNextPage'] ?? false,
                'hasPreviousPage' => $pagination['hasPreviousPage'] ?? false,
                'startCursor' => $pagination['startCursor'] ?? null,
                'endCursor' => $pagination['endCursor'] ?? null
            ],
            'meta' => [
                'total' => $pagination['total'] ?? null,
                'filters' => $meta['filters'] ?? [],
                'sorting' => $meta['sorting'] ?? []
            ]
        ];
    }
    
    /**
     * Standard format (default)
     * Generic response format compatible with most React applications
     */
    private function formatStandard(array $data, array $meta): array
    {
        return [
            'success' => true,
            'data' => $data,
            'meta' => $this->buildComprehensiveMeta($meta),
            'pagination' => $this->buildStandardPagination($meta['pagination'] ?? [])
        ];
    }
    
    /**
     * Build comprehensive metadata for advanced formats
     */
    private function buildComprehensiveMeta(array $meta): array
    {
        $comprehensive = [
            'pagination' => $this->buildStandardPagination($meta['pagination'] ?? []),
            'filters' => [
                'applied' => $meta['filters']['applied'] ?? [],
                'available' => $meta['filters']['available'] ?? []
            ],
            'sorting' => [
                'applied' => $meta['sorting']['applied'] ?? [],
                'available' => $meta['sorting']['available'] ?? []
            ],
            'search' => [
                'applied' => $meta['search']['applied'] ?? [],
                'available_fields' => $meta['search']['available_fields'] ?? []
            ]
        ];
        
        // Include optional metadata
        if (isset($meta['query_time'])) {
            $comprehensive['performance'] = [
                'query_time_ms' => $meta['query_time'],
                'total_records' => $meta['pagination']['total'] ?? 0
            ];
        }
        
        return $comprehensive;
    }
    
    /**
     * Build standard pagination metadata
     */
    private function buildStandardPagination(array $pagination): array
    {
        return [
            'page' => $pagination['page'] ?? 1,
            'pageSize' => $pagination['pageSize'] ?? 20,
            'total' => $pagination['total'] ?? 0,
            'pageCount' => $pagination['pageCount'] ?? 0,
            'hasNextPage' => $pagination['hasNextPage'] ?? false,
            'hasPreviousPage' => $pagination['hasPreviousPage'] ?? false,
            'offset' => $pagination['offset'] ?? 0,
            'limit' => $pagination['limit'] ?? 20
        ];
    }
    
    /**
     * Build SWR-specific pagination metadata
     */
    private function buildSWRPagination(array $pagination): array
    {
        return [
            'current' => $pagination['page'] ?? 1,
            'size' => $pagination['pageSize'] ?? 20,
            'total' => $pagination['total'] ?? 0,
            'pages' => $pagination['pageCount'] ?? 0,
            'hasMore' => $pagination['hasNextPage'] ?? false
        ];
    }
    
    /**
     * Build pagination links for REST API navigation
     */
    private function buildPaginationLinks(array $pagination): array
    {
        $links = [];
        $page = $pagination['page'] ?? 1;
        $pageCount = $pagination['pageCount'] ?? 0;
        
        if ($page > 1) {
            $links['first'] = $this->buildPageUrl(1, $pagination);
            $links['prev'] = $this->buildPageUrl($page - 1, $pagination);
        }
        
        if ($page < $pageCount) {
            $links['next'] = $this->buildPageUrl($page + 1, $pagination);
            $links['last'] = $this->buildPageUrl($pageCount, $pagination);
        }
        
        $links['self'] = $this->buildPageUrl($page, $pagination);
        
        return $links;
    }
    
    /**
     * Build URL for pagination link (placeholder - would use actual request URL)
     */
    private function buildPageUrl(int $page, array $pagination): string
    {
        // In real implementation, this would build actual URLs based on current request
        $pageSize = $pagination['pageSize'] ?? 20;
        return "?page={$page}&pageSize={$pageSize}";
    }
    
    /**
     * Generate cache key for response caching
     */
    private function generateCacheKey(array $meta): string
    {
        $keyData = [
            'pagination' => $meta['pagination'] ?? [],
            'filters' => $meta['filters']['applied'] ?? [],
            'sorting' => $meta['sorting']['applied'] ?? [],
            'search' => $meta['search']['applied'] ?? []
        ];
        
        return md5(json_encode($keyData));
    }
    
    /**
     * Get available response formats
     */
    public function getAvailableFormats(): array
    {
        return [
            'standard' => 'Standard REST API format',
            'ag-grid' => 'AG-Grid server-side data source',
            'mui' => 'Material-UI DataGrid server-side',
            'tanstack-query' => 'TanStack Query (React Query)',
            'swr' => 'SWR data fetching',
            'infinite-scroll' => 'Infinite scroll pagination',
            'cursor' => 'Cursor-based pagination'
        ];
    }
    
    /**
     * Validate format parameter
     */
    public function isValidFormat(string $format): bool
    {
        return array_key_exists($format, $this->getAvailableFormats());
    }
    
    /**
     * Get format description
     */
    public function getFormatDescription(string $format): ?string
    {
        return $this->getAvailableFormats()[$format] ?? null;
    }
}
