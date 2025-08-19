<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Gravitycar\Api\AdvancedRequestParser;

class AdvancedRequestParserTest extends TestCase
{
    private AdvancedRequestParser $parser;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AdvancedRequestParser();
    }

    public function testCanHandleReturnsTrueWithPerPageParameter(): void
    {
        $requestData = ['per_page' => 25];
        $this->assertTrue($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsTrueWithSearchFieldsParameter(): void
    {
        $requestData = ['search_fields' => 'name,email'];
        $this->assertTrue($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsTrueWithIncludeTotalParameter(): void
    {
        $requestData = ['include_total' => 'true'];
        $this->assertTrue($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsTrueWithIncludeAvailableFiltersParameter(): void
    {
        $requestData = ['include_available_filters' => 'true'];
        $this->assertTrue($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsTrueWithIncludeMetadataParameter(): void
    {
        $requestData = ['include_metadata' => 'true'];
        $this->assertTrue($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsTrueWithColonSortSyntax(): void
    {
        $requestData = ['sort' => 'created_at:desc,name:asc'];
        $this->assertTrue($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsFalseWithBasicSortSyntax(): void
    {
        $requestData = ['sort' => 'created_at'];
        $this->assertFalse($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsFalseWithNoAdvancedParameters(): void
    {
        $requestData = ['page' => 1, 'pageSize' => 20];
        $this->assertFalse($this->parser->canHandle($requestData));
    }

    public function testGetFormatNameReturnsAdvanced(): void
    {
        $this->assertEquals('advanced', $this->parser->getFormatName());
    }

    public function testParseWithBasicPagination(): void
    {
        $requestData = [
            'per_page' => 25,
            'page' => 2
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('filters', $result);
        $this->assertArrayHasKey('sorting', $result);
        $this->assertArrayHasKey('search', $result);
        $this->assertArrayHasKey('options', $result);
        
        // Check pagination
        $this->assertEquals(2, $result['pagination']['page']);
        $this->assertEquals(25, $result['pagination']['pageSize']);
        $this->assertEquals(25, $result['pagination']['offset']); // (2-1) * 25
    }

    public function testParseWithPageSizeAlternative(): void
    {
        $requestData = [
            'pageSize' => 50,
            'page' => 1
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should handle pageSize as alternative to per_page
        $this->assertEquals(50, $result['pagination']['pageSize']);
        $this->assertEquals(0, $result['pagination']['offset']);
    }

    public function testParseWithDefaultPagination(): void
    {
        $requestData = ['include_total' => 'true'];
        
        $result = $this->parser->parse($requestData);
        
        // Should default to page 1 and pageSize 20
        $this->assertEquals(1, $result['pagination']['page']);
        $this->assertEquals(20, $result['pagination']['pageSize']);
        $this->assertEquals(0, $result['pagination']['offset']);
    }

    public function testParseWithSimpleFilters(): void
    {
        $requestData = [
            'per_page' => 20,
            'filter' => [
                'status' => 'active',
                'role' => 'admin'
            ]
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(2, $result['filters']);
        
        // Check simple filters default to equals
        $statusFilter = $result['filters'][0];
        $this->assertEquals('status', $statusFilter['field']);
        $this->assertEquals('equals', $statusFilter['operator']);
        $this->assertEquals('active', $statusFilter['value']);
    }

    public function testParseWithAdvancedFilters(): void
    {
        $requestData = [
            'per_page' => 20,
            'filter' => [
                'age' => [
                    'gte' => '18',
                    'lte' => '65'
                ],
                'name' => [
                    'contains' => 'john'
                ]
            ]
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(3, $result['filters']);
        
        // Check advanced operator format
        $ageGteFilter = $result['filters'][0];
        $this->assertEquals('age', $ageGteFilter['field']);
        $this->assertEquals('gte', $ageGteFilter['operator']);
        $this->assertEquals('18', $ageGteFilter['value']);
        
        $nameFilter = $result['filters'][2];
        $this->assertEquals('name', $nameFilter['field']);
        $this->assertEquals('contains', $nameFilter['operator']);
        $this->assertEquals('john', $nameFilter['value']);
    }

    public function testParseWithInOperatorAndCommaSeparatedValues(): void
    {
        $requestData = [
            'per_page' => 20,
            'filter' => [
                'status' => [
                    'in' => 'active,pending,review'
                ]
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
            'per_page' => 20,
            'filter' => [
                'age' => [
                    'between' => '18,65'
                ]
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
            'per_page' => 20,
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

    public function testParseWithColonSortSyntax(): void
    {
        $requestData = [
            'per_page' => 20,
            'sort' => 'created_at:desc,name:asc,updated_at:desc'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(3, $result['sorting']);
        
        $this->assertEquals('created_at', $result['sorting'][0]['field']);
        $this->assertEquals('desc', $result['sorting'][0]['direction']);
        $this->assertEquals(0, $result['sorting'][0]['priority']);
        
        $this->assertEquals('name', $result['sorting'][1]['field']);
        $this->assertEquals('asc', $result['sorting'][1]['direction']);
        $this->assertEquals(1, $result['sorting'][1]['priority']);
    }

    public function testParseWithSortDefaultsToAscending(): void
    {
        $requestData = [
            'per_page' => 20,
            'sort' => 'name,created_at:desc'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(2, $result['sorting']);
        
        // Field without direction should default to ascending
        $this->assertEquals('name', $result['sorting'][0]['field']);
        $this->assertEquals('asc', $result['sorting'][0]['direction']);
        
        $this->assertEquals('created_at', $result['sorting'][1]['field']);
        $this->assertEquals('desc', $result['sorting'][1]['direction']);
    }

    public function testParseWithInvalidSortDirection(): void
    {
        $requestData = [
            'per_page' => 20,
            'sort' => 'name:invalid,created_at:desc'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should only include valid sort directions
        $this->assertCount(1, $result['sorting']);
        $this->assertEquals('created_at', $result['sorting'][0]['field']);
        $this->assertEquals('desc', $result['sorting'][0]['direction']);
    }

    public function testParseWithBasicSearch(): void
    {
        $requestData = [
            'per_page' => 20,
            'search' => 'john doe'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertIsArray($result['search']);
        $this->assertEquals('john doe', $result['search']['term']);
    }

    public function testParseWithSearchFieldsAsString(): void
    {
        $requestData = [
            'per_page' => 20,
            'search' => 'john',
            'search_fields' => 'first_name,last_name,email'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEquals('john', $result['search']['term']);
        $this->assertEquals(['first_name', 'last_name', 'email'], $result['search']['fields']);
    }

    public function testParseWithSearchFieldsAsArray(): void
    {
        $requestData = [
            'per_page' => 20,
            'search' => 'john',
            'search_fields' => ['first_name', 'last_name', 'email']
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEquals(['first_name', 'last_name', 'email'], $result['search']['fields']);
    }

    public function testParseWithSearchOperator(): void
    {
        $requestData = [
            'per_page' => 20,
            'search' => 'john',
            'search_operator' => 'startsWith'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEquals('startsWith', $result['search']['operator']);
    }

    public function testParseWithBooleanOptions(): void
    {
        $requestData = [
            'per_page' => 20,
            'include_total' => 'true',
            'include_available_filters' => '1',
            'include_metadata' => 'yes'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertTrue($result['options']['includeTotal']);
        $this->assertTrue($result['options']['includeAvailableFilters']);
        $this->assertTrue($result['options']['includeMetadata']);
    }

    public function testParseWithFalseBooleanOptions(): void
    {
        $requestData = [
            'per_page' => 20,
            'include_total' => 'false',
            'include_available_filters' => '0',
            'include_metadata' => 'no'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertFalse($result['options']['includeTotal']);
        $this->assertFalse($result['options']['includeAvailableFilters']);
        $this->assertFalse($result['options']['includeMetadata']);
    }

    public function testParseWithIncludeAsString(): void
    {
        $requestData = [
            'per_page' => 20,
            'include' => 'profile,posts,comments'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEquals(['profile', 'posts', 'comments'], $result['options']['include']);
    }

    public function testParseWithIncludeAsArray(): void
    {
        $requestData = [
            'per_page' => 20,
            'include' => ['profile', 'posts', 'comments']
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEquals(['profile', 'posts', 'comments'], $result['options']['include']);
    }

    public function testParseWithAllParameters(): void
    {
        $requestData = [
            'page' => 2,
            'per_page' => 50,
            'sort' => 'created_at:desc,name:asc',
            'filter' => [
                'status' => 'active',
                'age' => ['gte' => '18']
            ],
            'search' => 'john',
            'search_fields' => 'name,email',
            'search_operator' => 'contains',
            'include_total' => 'true',
            'include_metadata' => 'true',
            'include' => 'profile,posts'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Check all sections populated
        $this->assertEquals(2, $result['pagination']['page']);
        $this->assertEquals(50, $result['pagination']['pageSize']);
        $this->assertCount(2, $result['sorting']);
        $this->assertCount(2, $result['filters']);
        $this->assertEquals('john', $result['search']['term']);
        $this->assertEquals(['name', 'email'], $result['search']['fields']);
        $this->assertEquals('contains', $result['search']['operator']);
        $this->assertTrue($result['options']['includeTotal']);
        $this->assertTrue($result['options']['includeMetadata']);
        $this->assertEquals(['profile', 'posts'], $result['options']['include']);
        $this->assertEquals('advanced', $result['responseFormat']);
    }

    public function testParseWithFieldNameSanitization(): void
    {
        $requestData = [
            'per_page' => 20,
            'filter' => [
                'field@#$%' => 'value'
            ],
            'sort' => 'sort_field!@#:asc'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Field names should be sanitized
        $this->assertEquals('field', $result['filters'][0]['field']);
        $this->assertEquals('sort_field', $result['sorting'][0]['field']);
    }

    public function testParseWithEmptyFilterArray(): void
    {
        $requestData = [
            'per_page' => 20,
            'filter' => []
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEmpty($result['filters']);
    }

    public function testParseWithNoFilterParameter(): void
    {
        $requestData = ['per_page' => 20];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEmpty($result['filters']);
    }

    public function testParseWithNonArraySortParameter(): void
    {
        $requestData = [
            'per_page' => 20,
            'sort' => 123 // Non-string sort parameter
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEmpty($result['sorting']);
    }

    public function testParseBooleanParamWithActualBoolean(): void
    {
        $requestData = [
            'per_page' => 20,
            'include_total' => true,
            'include_metadata' => false
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertTrue($result['options']['includeTotal']);
        $this->assertFalse($result['options']['includeMetadata']);
    }

    public function testParseWithShorthandOperators(): void
    {
        $requestData = [
            'per_page' => 20,
            'filter' => [
                'age' => [
                    'gt' => '18',
                    'lt' => '65'
                ],
                'score' => [
                    'ne' => '0'
                ]
            ]
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(3, $result['filters']);
        
        // Check shorthand operators are accepted
        $this->assertEquals('gt', $result['filters'][0]['operator']);
        $this->assertEquals('lt', $result['filters'][1]['operator']);
        $this->assertEquals('ne', $result['filters'][2]['operator']);
    }
}
