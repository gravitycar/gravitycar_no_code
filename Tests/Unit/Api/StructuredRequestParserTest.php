<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Gravitycar\Api\StructuredRequestParser;

class StructuredRequestParserTest extends TestCase
{
    private StructuredRequestParser $parser;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new StructuredRequestParser();
    }

    public function testCanHandleReturnsTrueWithStructuredFilterFormat(): void
    {
        $requestData = [
            'filter' => [
                'name' => ['contains' => 'john'],
                'age' => ['greaterThan' => 18]
            ]
        ];
        
        $this->assertTrue($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsTrueWithStructuredSortFormat(): void
    {
        $requestData = [
            'sort' => [
                0 => ['field' => 'name', 'direction' => 'asc']
            ]
        ];
        
        $this->assertTrue($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsFalseWithSimpleFilterFormat(): void
    {
        $requestData = [
            'filter' => [
                'name' => 'john',
                'age' => 18
            ]
        ];
        
        $this->assertFalse($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsFalseWithNoStructuredParameters(): void
    {
        $requestData = [
            'page' => 1,
            'pageSize' => 20,
            'search' => 'john'
        ];
        
        $this->assertFalse($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsFalseWithInvalidOperators(): void
    {
        $requestData = [
            'filter' => [
                'name' => ['invalidOperator' => 'john']
            ]
        ];
        
        $this->assertFalse($this->parser->canHandle($requestData));
    }

    public function testGetFormatNameReturnsStructured(): void
    {
        $this->assertEquals('structured', $this->parser->getFormatName());
    }

    public function testParseWithStartRowEndRowPagination(): void
    {
        $requestData = [
            'startRow' => 20,
            'endRow' => 40,
            'filter' => ['name' => ['equals' => 'john']]
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('filters', $result);
        $this->assertArrayHasKey('sorting', $result);
        $this->assertArrayHasKey('search', $result);
        
        // Check pagination calculation
        $this->assertEquals(2, $result['pagination']['page']); // (20/20) + 1
        $this->assertEquals(20, $result['pagination']['pageSize']); // 40 - 20
        $this->assertEquals(20, $result['pagination']['offset']);
    }

    public function testParseWithPagePageSizePagination(): void
    {
        $requestData = [
            'page' => 3,
            'pageSize' => 25,
            'filter' => ['name' => ['equals' => 'john']]
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should use page/pageSize when startRow/endRow not present
        $this->assertEquals(3, $result['pagination']['page']);
        $this->assertEquals(25, $result['pagination']['pageSize']);
        $this->assertEquals(50, $result['pagination']['offset']); // (3-1) * 25
    }

    public function testParseWithDefaultPagination(): void
    {
        $requestData = [
            'filter' => ['name' => ['equals' => 'john']]
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should default to page 1 and pageSize 20
        $this->assertEquals(1, $result['pagination']['page']);
        $this->assertEquals(20, $result['pagination']['pageSize']);
        $this->assertEquals(0, $result['pagination']['offset']);
    }

    public function testParseWithStructuredFilters(): void
    {
        $requestData = [
            'filter' => [
                'name' => ['contains' => 'john'],
                'age' => ['greaterThan' => 18, 'lessThan' => 65],
                'status' => ['equals' => 'active']
            ]
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(4, $result['filters']); // name(1) + age(2) + status(1)
        
        // Check name filter
        $nameFilter = $result['filters'][0];
        $this->assertEquals('name', $nameFilter['field']);
        $this->assertEquals('contains', $nameFilter['operator']);
        $this->assertEquals('john', $nameFilter['value']);
        
        // Check age filters (multiple operators for same field)
        $ageGtFilter = $result['filters'][1];
        $this->assertEquals('age', $ageGtFilter['field']);
        $this->assertEquals('greaterThan', $ageGtFilter['operator']);
        $this->assertEquals(18, $ageGtFilter['value']);
        
        $ageLtFilter = $result['filters'][2];
        $this->assertEquals('age', $ageLtFilter['field']);
        $this->assertEquals('lessThan', $ageLtFilter['operator']);
        $this->assertEquals(65, $ageLtFilter['value']);
    }

    public function testParseWithSimpleFiltersFallback(): void
    {
        $requestData = [
            'filter' => [
                'name' => 'john',  // Simple value, not structured
                'status' => ['equals' => 'active']  // Mixed with structured
            ]
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(2, $result['filters']);
        
        // Simple filter should default to equals
        $nameFilter = $result['filters'][0];
        $this->assertEquals('name', $nameFilter['field']);
        $this->assertEquals('equals', $nameFilter['operator']);
        $this->assertEquals('john', $nameFilter['value']);
    }

    public function testParseWithInOperatorAndCommaSeparatedValues(): void
    {
        $requestData = [
            'filter' => [
                'status' => ['in' => 'active,pending,review']
            ]
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(1, $result['filters']);
        
        $statusFilter = $result['filters'][0];
        $this->assertEquals('status', $statusFilter['field']);
        $this->assertEquals('in', $statusFilter['operator']);
        $this->assertEquals(['active', 'pending', 'review'], $statusFilter['value']);
    }

    public function testParseWithBetweenOperatorAndCommaSeparatedValues(): void
    {
        $requestData = [
            'filter' => [
                'age' => ['between' => '18,65']
            ]
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(1, $result['filters']);
        
        $ageFilter = $result['filters'][0];
        $this->assertEquals('age', $ageFilter['field']);
        $this->assertEquals('between', $ageFilter['operator']);
        $this->assertEquals(['18', '65'], $ageFilter['value']);
    }

    public function testParseWithInvalidOperatorIgnored(): void
    {
        $requestData = [
            'filter' => [
                'field1' => [
                    'invalidOperator' => 'value',
                    'equals' => 'validValue'
                ]
            ]
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should only include valid operators
        $this->assertCount(1, $result['filters']);
        $this->assertEquals('equals', $result['filters'][0]['operator']);
        $this->assertEquals('validValue', $result['filters'][0]['value']);
    }

    public function testParseWithStructuredSorting(): void
    {
        $requestData = [
            'sort' => [
                0 => ['field' => 'created_at', 'direction' => 'desc'],
                1 => ['field' => 'name', 'direction' => 'asc'],
                2 => ['field' => 'updated_at', 'direction' => 'desc']
            ]
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(3, $result['sorting']);
        
        // Check order is maintained by priority
        $this->assertEquals('created_at', $result['sorting'][0]['field']);
        $this->assertEquals('desc', $result['sorting'][0]['direction']);
        $this->assertEquals(0, $result['sorting'][0]['priority']);
        
        $this->assertEquals('name', $result['sorting'][1]['field']);
        $this->assertEquals('asc', $result['sorting'][1]['direction']);
        $this->assertEquals(1, $result['sorting'][1]['priority']);
    }

    public function testParseWithSortDefaultDirection(): void
    {
        $requestData = [
            'sort' => [
                0 => ['field' => 'name'], // Missing direction should default to asc
                1 => ['field' => 'created_at', 'direction' => 'desc']
            ]
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(2, $result['sorting']);
        
        // Missing direction should default to 'asc'
        $this->assertEquals('name', $result['sorting'][0]['field']);
        $this->assertEquals('asc', $result['sorting'][0]['direction']);
    }

    public function testParseWithInvalidSortDirection(): void
    {
        $requestData = [
            'sort' => [
                0 => ['field' => 'name', 'direction' => 'invalid'],
                1 => ['field' => 'created_at', 'direction' => 'desc']
            ]
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should only include valid sort directions
        $this->assertCount(1, $result['sorting']);
        $this->assertEquals('created_at', $result['sorting'][0]['field']);
        $this->assertEquals('desc', $result['sorting'][0]['direction']);
    }

    public function testParseWithMissingSortField(): void
    {
        $requestData = [
            'sort' => [
                0 => ['direction' => 'asc'], // Missing field
                1 => ['field' => 'created_at', 'direction' => 'desc']
            ]
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should only include complete sort items
        $this->assertCount(1, $result['sorting']);
        $this->assertEquals('created_at', $result['sorting'][0]['field']);
    }

    public function testParseWithNonArraySortItem(): void
    {
        $requestData = [
            'sort' => [
                0 => 'invalid', // Non-array sort item
                1 => ['field' => 'created_at', 'direction' => 'desc']
            ]
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should ignore non-array sort items
        $this->assertCount(1, $result['sorting']);
        $this->assertEquals('created_at', $result['sorting'][0]['field']);
    }

    public function testParseWithBasicSearch(): void
    {
        $requestData = [
            'search' => 'john doe'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertIsArray($result['search']);
        $this->assertEquals('john doe', $result['search']['term']);
    }

    public function testParseWithSearchFieldsAsString(): void
    {
        $requestData = [
            'search' => 'john',
            'searchFields' => 'first_name,last_name,email'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEquals('john', $result['search']['term']);
        $this->assertEquals(['first_name', 'last_name', 'email'], $result['search']['fields']);
    }

    public function testParseWithSearchFieldsAsArray(): void
    {
        $requestData = [
            'search' => 'john',
            'searchFields' => ['first_name', 'last_name', 'email']
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEquals(['first_name', 'last_name', 'email'], $result['search']['fields']);
    }

    public function testParseWithAllParameters(): void
    {
        $requestData = [
            'page' => 2,
            'pageSize' => 50,
            'sort' => [
                0 => ['field' => 'created_at', 'direction' => 'desc'],
                1 => ['field' => 'name', 'direction' => 'asc']
            ],
            'filter' => [
                'status' => ['equals' => 'active'],
                'age' => ['greaterThan' => 18]
            ],
            'search' => 'john',
            'searchFields' => 'name,email'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Check all sections populated
        $this->assertEquals(2, $result['pagination']['page']);
        $this->assertEquals(50, $result['pagination']['pageSize']);
        $this->assertCount(2, $result['sorting']);
        $this->assertCount(2, $result['filters']);
        $this->assertEquals('john', $result['search']['term']);
        $this->assertEquals(['name', 'email'], $result['search']['fields']);
        $this->assertEquals('structured', $result['responseFormat']);
    }

    public function testParseWithFieldNameSanitization(): void
    {
        $requestData = [
            'filter' => [
                'field@#$%' => ['equals' => 'value']
            ],
            'sort' => [
                0 => ['field' => 'sort_field!@#', 'direction' => 'asc']
            ]
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Field names should be sanitized
        $this->assertEquals('field', $result['filters'][0]['field']);
        $this->assertEquals('sort_field', $result['sorting'][0]['field']);
    }

    public function testParseWithEmptyFilterArray(): void
    {
        $requestData = [
            'filter' => []
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEmpty($result['filters']);
    }

    public function testParseWithNoFilterParameter(): void
    {
        $requestData = ['page' => 1];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEmpty($result['filters']);
    }

    public function testParseWithNonArrayFilterParameter(): void
    {
        $requestData = [
            'filter' => 'invalid'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEmpty($result['filters']);
    }

    public function testParseWithEmptySortArray(): void
    {
        $requestData = [
            'sort' => []
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEmpty($result['sorting']);
    }

    public function testParseWithNonArraySortParameter(): void
    {
        $requestData = [
            'sort' => 'invalid'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEmpty($result['sorting']);
    }

    public function testParseWithSortPriorityOrdering(): void
    {
        $requestData = [
            'sort' => [
                5 => ['field' => 'field5', 'direction' => 'asc'],
                1 => ['field' => 'field1', 'direction' => 'desc'],
                3 => ['field' => 'field3', 'direction' => 'asc']
            ]
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(3, $result['sorting']);
        
        // Should be ordered by priority (index)
        $this->assertEquals('field1', $result['sorting'][0]['field']);
        $this->assertEquals(1, $result['sorting'][0]['priority']);
        
        $this->assertEquals('field3', $result['sorting'][1]['field']);
        $this->assertEquals(3, $result['sorting'][1]['priority']);
        
        $this->assertEquals('field5', $result['sorting'][2]['field']);
        $this->assertEquals(5, $result['sorting'][2]['priority']);
    }

    public function testParseWithZeroPaginationRange(): void
    {
        $requestData = [
            'startRow' => 10,
            'endRow' => 10, // Zero size range
            'filter' => ['name' => ['equals' => 'john']]
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should handle zero-size gracefully
        $this->assertIsArray($result['pagination']);
        $this->assertGreaterThan(0, $result['pagination']['pageSize']); // Should default to minimum
    }
}
