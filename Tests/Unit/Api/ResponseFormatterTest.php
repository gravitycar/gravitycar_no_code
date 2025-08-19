<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Gravitycar\Api\ResponseFormatter;

class ResponseFormatterTest extends TestCase
{
    private ResponseFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new ResponseFormatter();
    }

    public function testFormatAgGridWithCompleteData(): void
    {
        $data = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane']
        ];

        $meta = [
            'pagination' => [
                'page' => 1,
                'pageSize' => 100,
                'offset' => 0,
                'hasNextPage' => false
            ]
        ];

        $result = $this->formatter->format($data, $meta, 'ag-grid');

        $this->assertTrue($result['success']);
        $this->assertEquals($data, $result['data']);
        $this->assertEquals(2, $result['lastRow']); // Should be set when no next page
    }

    public function testFormatAgGridWithMoreDataAvailable(): void
    {
        $data = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane']
        ];

        $meta = [
            'pagination' => [
                'page' => 1,
                'pageSize' => 2,
                'offset' => 0,
                'hasNextPage' => true
            ]
        ];

        $result = $this->formatter->format($data, $meta, 'ag-grid');

        $this->assertTrue($result['success']);
        $this->assertEquals($data, $result['data']);
        $this->assertNull($result['lastRow']); // Should be null when more data available
    }

    public function testFormatMuiDataGrid(): void
    {
        $data = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane']
        ];

        $meta = [
            'pagination' => [
                'page' => 0, // MUI uses 0-based pages
                'pageSize' => 25,
                'total' => 100,
                'hasNextPage' => true,
                'hasPreviousPage' => false
            ],
            'filters' => ['applied' => []],
            'sorting' => ['applied' => []]
        ];

        $result = $this->formatter->format($data, $meta, 'mui-datagrid');

        $this->assertTrue($result['success']);
        $this->assertEquals($data, $result['data']);
        $this->assertEquals(100, $result['rowCount']);
        $this->assertEquals(0, $result['meta']['page']);
        $this->assertEquals(25, $result['meta']['pageSize']);
        $this->assertEquals(100, $result['meta']['total']);
        $this->assertTrue($result['meta']['hasNextPage']);
        $this->assertFalse($result['meta']['hasPreviousPage']);
    }

    public function testFormatTanStackQuery(): void
    {
        $data = [
            ['id' => 1, 'name' => 'John']
        ];

        $meta = [
            'pagination' => [
                'page' => 1,
                'pageSize' => 20,
                'total' => 50,
                'pageCount' => 3,
                'hasNextPage' => true,
                'hasPreviousPage' => false
            ]
        ];

        $result = $this->formatter->format($data, $meta, 'tanstack-query');

        $this->assertTrue($result['success']);
        $this->assertEquals($data, $result['data']);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('links', $result);
        $this->assertArrayHasKey('timestamp', $result);
        
        // Check pagination links
        $this->assertArrayHasKey('next', $result['links']);
        $this->assertArrayHasKey('self', $result['links']);
        $this->assertStringContainsString('page=2', $result['links']['next']);
    }

    public function testFormatSWR(): void
    {
        $data = [
            ['id' => 1, 'name' => 'John']
        ];

        $meta = [
            'pagination' => [
                'page' => 1,
                'pageSize' => 20,
                'total' => 50,
                'hasNextPage' => true
            ]
        ];

        $result = $this->formatter->format($data, $meta, 'swr');

        $this->assertTrue($result['success']);
        $this->assertEquals($data, $result['data']);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('cache_key', $result);
        $this->assertArrayHasKey('timestamp', $result);
        
        // Check SWR-specific pagination format
        $this->assertEquals(1, $result['pagination']['current']);
        $this->assertEquals(20, $result['pagination']['size']);
        $this->assertEquals(50, $result['pagination']['total']);
        $this->assertTrue($result['pagination']['hasMore']);
    }

    public function testFormatInfiniteScroll(): void
    {
        $data = [
            ['id' => 1, 'name' => 'John']
        ];

        $meta = [
            'pagination' => [
                'hasNextPage' => true,
                'nextCursor' => 'cursor123',
                'pageSize' => 10
            ]
        ];

        $result = $this->formatter->format($data, $meta, 'infinite-scroll');

        $this->assertTrue($result['success']);
        $this->assertEquals($data, $result['data']);
        $this->assertTrue($result['pagination']['hasNextPage']);
        $this->assertEquals('cursor123', $result['pagination']['nextCursor']);
        $this->assertEquals(10, $result['pagination']['pageSize']);
    }

    public function testFormatCursorPagination(): void
    {
        $data = [
            ['id' => 1, 'name' => 'John']
        ];

        $meta = [
            'pagination' => [
                'hasNextPage' => true,
                'hasPreviousPage' => false,
                'startCursor' => 'start123',
                'endCursor' => 'end456'
            ]
        ];

        $result = $this->formatter->format($data, $meta, 'cursor');

        $this->assertTrue($result['success']);
        $this->assertEquals($data, $result['data']);
        $this->assertArrayHasKey('pageInfo', $result);
        $this->assertTrue($result['pageInfo']['hasNextPage']);
        $this->assertFalse($result['pageInfo']['hasPreviousPage']);
        $this->assertEquals('start123', $result['pageInfo']['startCursor']);
        $this->assertEquals('end456', $result['pageInfo']['endCursor']);
    }

    public function testFormatStandard(): void
    {
        $data = [
            ['id' => 1, 'name' => 'John']
        ];

        $meta = [
            'pagination' => [
                'page' => 1,
                'pageSize' => 20,
                'total' => 50
            ]
        ];

        $result = $this->formatter->format($data, $meta, 'standard');

        $this->assertTrue($result['success']);
        $this->assertEquals($data, $result['data']);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('pagination', $result);
        
        // Check standard pagination structure
        $this->assertEquals(1, $result['pagination']['page']);
        $this->assertEquals(20, $result['pagination']['pageSize']);
        $this->assertEquals(50, $result['pagination']['total']);
    }

    public function testFormatDefaultToStandard(): void
    {
        $data = [['id' => 1]];
        $meta = ['pagination' => ['page' => 1]];

        $result = $this->formatter->format($data, $meta, 'unknown-format');

        $this->assertTrue($result['success']);
        $this->assertEquals($data, $result['data']);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('pagination', $result);
    }

    public function testFormatWithEmptyData(): void
    {
        $data = [];
        $meta = [
            'pagination' => [
                'page' => 1,
                'pageSize' => 20,
                'total' => 0
            ]
        ];

        $result = $this->formatter->format($data, $meta, 'standard');

        $this->assertTrue($result['success']);
        $this->assertEmpty($result['data']);
        $this->assertEquals(0, $result['pagination']['total']);
    }

    public function testFormatWithPerformanceMetadata(): void
    {
        $data = [['id' => 1]];
        $meta = [
            'pagination' => ['total' => 100],
            'query_time' => 42.5
        ];

        $result = $this->formatter->format($data, $meta, 'tanstack-query');

        $this->assertArrayHasKey('performance', $result['meta']);
        $this->assertEquals(42.5, $result['meta']['performance']['query_time_ms']);
        $this->assertEquals(100, $result['meta']['performance']['total_records']);
    }

    public function testGetAvailableFormats(): void
    {
        $formats = $this->formatter->getAvailableFormats();

        $this->assertIsArray($formats);
        $this->assertArrayHasKey('standard', $formats);
        $this->assertArrayHasKey('ag-grid', $formats);
        $this->assertArrayHasKey('mui', $formats);
        $this->assertArrayHasKey('tanstack-query', $formats);
        $this->assertArrayHasKey('swr', $formats);
        $this->assertArrayHasKey('infinite-scroll', $formats);
        $this->assertArrayHasKey('cursor', $formats);
    }

    public function testIsValidFormat(): void
    {
        $this->assertTrue($this->formatter->isValidFormat('standard'));
        $this->assertTrue($this->formatter->isValidFormat('ag-grid'));
        $this->assertTrue($this->formatter->isValidFormat('mui'));
        $this->assertFalse($this->formatter->isValidFormat('invalid-format'));
        $this->assertFalse($this->formatter->isValidFormat(''));
    }

    public function testGetFormatDescription(): void
    {
        $description = $this->formatter->getFormatDescription('ag-grid');
        $this->assertNotNull($description);
        $this->assertStringContainsString('AG-Grid', $description);

        $invalidDescription = $this->formatter->getFormatDescription('invalid');
        $this->assertNull($invalidDescription);
    }

    public function testFormatMuiAlias(): void
    {
        $data = [['id' => 1]];
        $meta = ['pagination' => ['page' => 0]];

        // Test that 'mui' alias works the same as 'mui-datagrid'
        $result1 = $this->formatter->format($data, $meta, 'mui');
        $result2 = $this->formatter->format($data, $meta, 'mui-datagrid');

        $this->assertEquals($result1, $result2);
    }

    public function testFormatTanStackQueryAlias(): void
    {
        $data = [['id' => 1]];
        $meta = ['pagination' => ['page' => 1]];

        // Test that 'react-query' alias works the same as 'tanstack-query'
        $result1 = $this->formatter->format($data, $meta, 'react-query');
        $result2 = $this->formatter->format($data, $meta, 'tanstack-query');

        $this->assertEquals($result1, $result2);
    }

    public function testPaginationLinksGeneration(): void
    {
        $data = [['id' => 1]];
        $meta = [
            'pagination' => [
                'page' => 2,
                'pageSize' => 10,
                'pageCount' => 5,
                'hasNextPage' => true,
                'hasPreviousPage' => true
            ]
        ];

        $result = $this->formatter->format($data, $meta, 'tanstack-query');

        $links = $result['links'];
        $this->assertArrayHasKey('first', $links);
        $this->assertArrayHasKey('prev', $links);
        $this->assertArrayHasKey('next', $links);
        $this->assertArrayHasKey('last', $links);
        $this->assertArrayHasKey('self', $links);

        $this->assertStringContainsString('page=1', $links['first']);
        $this->assertStringContainsString('page=1', $links['prev']);
        $this->assertStringContainsString('page=3', $links['next']);
        $this->assertStringContainsString('page=5', $links['last']);
        $this->assertStringContainsString('page=2', $links['self']);
    }

    public function testComprehensiveMetaBuild(): void
    {
        $data = [['id' => 1]];
        $meta = [
            'pagination' => ['page' => 1, 'total' => 100],
            'filters' => [
                'applied' => [['field' => 'name', 'operator' => 'contains', 'value' => 'john']],
                'available' => [['field' => 'name', 'operators' => ['contains', 'equals']]]
            ],
            'sorting' => [
                'applied' => [['field' => 'created_at', 'direction' => 'desc']],
                'available' => [['field' => 'created_at', 'directions' => ['asc', 'desc']]]
            ],
            'search' => [
                'applied' => [['term' => 'john', 'fields' => ['name']]],
                'available_fields' => ['name', 'email']
            ]
        ];

        $result = $this->formatter->format($data, $meta, 'tanstack-query');

        $resultMeta = $result['meta'];
        $this->assertArrayHasKey('pagination', $resultMeta);
        $this->assertArrayHasKey('filters', $resultMeta);
        $this->assertArrayHasKey('sorting', $resultMeta);
        $this->assertArrayHasKey('search', $resultMeta);

        $this->assertArrayHasKey('applied', $resultMeta['filters']);
        $this->assertArrayHasKey('available', $resultMeta['filters']);
        $this->assertCount(1, $resultMeta['filters']['applied']);
        $this->assertCount(1, $resultMeta['filters']['available']);
    }

    public function testCacheKeyGeneration(): void
    {
        $meta1 = [
            'pagination' => ['page' => 1],
            'filters' => ['applied' => []],
            'sorting' => ['applied' => []],
            'search' => ['applied' => []]
        ];

        $meta2 = [
            'pagination' => ['page' => 2], // Different page
            'filters' => ['applied' => []],
            'sorting' => ['applied' => []],
            'search' => ['applied' => []]
        ];

        $result1 = $this->formatter->format([], $meta1, 'swr');
        $result2 = $this->formatter->format([], $meta2, 'swr');

        // Cache keys should be different for different pagination
        $this->assertNotEquals($result1['cache_key'], $result2['cache_key']);
    }
}
