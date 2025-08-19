<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Gravitycar\Api\SimpleRequestParser;

class SimpleRequestParserTest extends TestCase
{
    private SimpleRequestParser $parser;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SimpleRequestParser();
    }

    public function testCanHandleAlwaysReturnsTrue(): void
    {
        // Fallback parser should handle any request
        $this->assertTrue($this->parser->canHandle([]));
        $this->assertTrue($this->parser->canHandle(['page' => 1]));
        $this->assertTrue($this->parser->canHandle(['complex' => ['nested' => 'data']]));
    }

    public function testGetFormatNameReturnsSimple(): void
    {
        $this->assertEquals('simple', $this->parser->getFormatName());
    }

    public function testParseWithBasicStructure(): void
    {
        $requestData = ['page' => 1];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('sorting', $result);
        $this->assertArrayHasKey('filters', $result);
        $this->assertArrayHasKey('search', $result);
        $this->assertArrayHasKey('responseFormat', $result);
        $this->assertEquals('simple', $result['responseFormat']);
    }

    public function testParseWithBasicPagination(): void
    {
        $requestData = [
            'page' => 2,
            'pageSize' => 50
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEquals(2, $result['pagination']['page']);
        $this->assertEquals(50, $result['pagination']['pageSize']);
        $this->assertEquals(50, $result['pagination']['offset']); // (2-1) * 50
        $this->assertEquals(50, $result['pagination']['limit']);
    }

    public function testParseWithPerPageAlternative(): void
    {
        $requestData = [
            'page' => 3,
            'per_page' => 25
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should use per_page as alternative to pageSize
        $this->assertEquals(3, $result['pagination']['page']);
        $this->assertEquals(25, $result['pagination']['pageSize']);
        $this->assertEquals(50, $result['pagination']['offset']); // (3-1) * 25
    }

    public function testParseWithDefaultPagination(): void
    {
        $requestData = ['search' => 'test'];
        
        $result = $this->parser->parse($requestData);
        
        // Should default to page 1 and pageSize 20
        $this->assertEquals(1, $result['pagination']['page']);
        $this->assertEquals(20, $result['pagination']['pageSize']);
        $this->assertEquals(0, $result['pagination']['offset']);
    }

    public function testParseWithPageSizeOverPerPage(): void
    {
        $requestData = [
            'page' => 1,
            'pageSize' => 30,
            'per_page' => 25 // pageSize should take precedence
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEquals(30, $result['pagination']['pageSize']);
    }

    public function testParseWithSortByAndSortOrder(): void
    {
        $requestData = [
            'sortBy' => 'name',
            'sortOrder' => 'desc'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(1, $result['sorting']);
        $this->assertEquals('name', $result['sorting'][0]['field']);
        $this->assertEquals('desc', $result['sorting'][0]['direction']);
    }

    public function testParseWithSortByDefaultOrder(): void
    {
        $requestData = [
            'sortBy' => 'created_at'
            // No sortOrder should default to asc
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(1, $result['sorting']);
        $this->assertEquals('created_at', $result['sorting'][0]['field']);
        $this->assertEquals('asc', $result['sorting'][0]['direction']);
    }

    public function testParseWithSortParameterColonSyntax(): void
    {
        $requestData = [
            'sort' => 'name:asc,created_at:desc,updated_at'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(3, $result['sorting']);
        
        $this->assertEquals('name', $result['sorting'][0]['field']);
        $this->assertEquals('asc', $result['sorting'][0]['direction']);
        
        $this->assertEquals('created_at', $result['sorting'][1]['field']);
        $this->assertEquals('desc', $result['sorting'][1]['direction']);
        
        // Should default to asc when no direction specified
        $this->assertEquals('updated_at', $result['sorting'][2]['field']);
        $this->assertEquals('asc', $result['sorting'][2]['direction']);
    }

    public function testParseWithSortParameterNoDirection(): void
    {
        $requestData = [
            'sort' => 'name,created_at'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(2, $result['sorting']);
        
        // Both should default to asc
        $this->assertEquals('name', $result['sorting'][0]['field']);
        $this->assertEquals('asc', $result['sorting'][0]['direction']);
        
        $this->assertEquals('created_at', $result['sorting'][1]['field']);
        $this->assertEquals('asc', $result['sorting'][1]['direction']);
    }

    public function testParseWithEmptySortFields(): void
    {
        $requestData = [
            'sort' => ',name:asc,,'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should ignore empty sort fields
        $this->assertCount(1, $result['sorting']);
        $this->assertEquals('name', $result['sorting'][0]['field']);
    }

    public function testParseWithBothSortByAndSortParameters(): void
    {
        $requestData = [
            'sortBy' => 'name',
            'sortOrder' => 'desc',
            'sort' => 'created_at:asc'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should include both sorting methods
        $this->assertCount(2, $result['sorting']);
        
        $this->assertEquals('name', $result['sorting'][0]['field']);
        $this->assertEquals('desc', $result['sorting'][0]['direction']);
        
        $this->assertEquals('created_at', $result['sorting'][1]['field']);
        $this->assertEquals('asc', $result['sorting'][1]['direction']);
    }

    public function testParseWithSimpleFilters(): void
    {
        $requestData = [
            'page' => 1,
            'name' => 'john',
            'status' => 'active',
            'age' => '25'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(3, $result['filters']); // Excluding 'page'
        
        // Check filters are created with equals operator
        $nameFilter = $result['filters'][0];
        $this->assertEquals('name', $nameFilter['field']);
        $this->assertEquals('equals', $nameFilter['operator']);
        $this->assertEquals('john', $nameFilter['value']);
        
        $statusFilter = $result['filters'][1];
        $this->assertEquals('status', $statusFilter['field']);
        $this->assertEquals('equals', $statusFilter['operator']);
        $this->assertEquals('active', $statusFilter['value']);
    }

    public function testParseWithReservedParametersExcluded(): void
    {
        $requestData = [
            'page' => 1,
            'pageSize' => 20,
            'per_page' => 25,
            'sortBy' => 'name',
            'sortOrder' => 'asc',
            'sort' => 'created_at:desc',
            'search' => 'john',
            'search_fields' => 'name,email',
            'q' => 'query',
            'include_total' => 'true',
            'include_available_filters' => 'true',
            'responseFormat' => 'json',
            'format' => 'xml',
            'name' => 'john',
            'status' => 'active'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should only include 'name' and 'status' as filters
        $this->assertCount(2, $result['filters']);
        $this->assertEquals('name', $result['filters'][0]['field']);
        $this->assertEquals('status', $result['filters'][1]['field']);
    }

    public function testParseWithEmptyAndNullValuesExcluded(): void
    {
        $requestData = [
            'name' => 'john',
            'empty_string' => '',
            'null_value' => null,
            'status' => 'active',
            'zero_value' => '0'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should exclude empty string and null, but include '0'
        $this->assertCount(3, $result['filters']);
        
        $fields = array_column($result['filters'], 'field');
        $this->assertContains('name', $fields);
        $this->assertContains('status', $fields);
        $this->assertContains('zero_value', $fields);
        $this->assertNotContains('empty_string', $fields);
        $this->assertNotContains('null_value', $fields);
    }

    public function testParseWithSearchParameter(): void
    {
        $requestData = [
            'search' => 'john doe'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertIsArray($result['search']);
        $this->assertEquals('john doe', $result['search']['term']);
        $this->assertEquals([], $result['search']['fields']);
        $this->assertEquals('contains', $result['search']['operator']);
    }

    public function testParseWithQParameterAsAlternative(): void
    {
        $requestData = [
            'q' => 'search term'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEquals('search term', $result['search']['term']);
    }

    public function testParseWithSearchFieldsAsString(): void
    {
        $requestData = [
            'search' => 'john',
            'search_fields' => 'first_name,last_name,email'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEquals('john', $result['search']['term']);
        $this->assertEquals(['first_name', 'last_name', 'email'], $result['search']['fields']);
    }

    public function testParseWithSearchFieldsWithWhitespace(): void
    {
        $requestData = [
            'search' => 'john',
            'search_fields' => ' first_name , last_name , email '
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should trim whitespace
        $this->assertEquals(['first_name', 'last_name', 'email'], $result['search']['fields']);
    }

    public function testParseWithSearchFieldsWithEmptyValues(): void
    {
        $requestData = [
            'search' => 'john',
            'search_fields' => 'first_name,,last_name,'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should filter out empty values but may preserve original indices
        $expectedFields = ['first_name', 'last_name'];
        $actualFields = array_values($result['search']['fields']); // Normalize indices
        $this->assertEquals($expectedFields, $actualFields);
    }

    public function testParseWithEmptySearchTerm(): void
    {
        $requestData = [
            'search' => ''
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEquals('', $result['search']['term']);
    }

    public function testParseWithWhitespaceOnlySearchTerm(): void
    {
        $requestData = [
            'search' => '   '
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEquals('', $result['search']['term']); // Should be trimmed
    }

    public function testParseWithSearchPrecedenceOverQ(): void
    {
        $requestData = [
            'search' => 'search term',
            'q' => 'q term'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // 'search' should take precedence over 'q'
        $this->assertEquals('search term', $result['search']['term']);
    }

    public function testParseWithAllParameters(): void
    {
        $requestData = [
            'page' => 2,
            'pageSize' => 50,
            'sortBy' => 'name',
            'sortOrder' => 'desc',
            'sort' => 'created_at:asc',
            'search' => 'john',
            'search_fields' => 'name,email',
            'status' => 'active',
            'role' => 'admin'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Check all sections populated
        $this->assertEquals(2, $result['pagination']['page']);
        $this->assertEquals(50, $result['pagination']['pageSize']);
        $this->assertCount(2, $result['sorting']);
        $this->assertCount(2, $result['filters']);
        $this->assertEquals('john', $result['search']['term']);
        $this->assertEquals(['name', 'email'], $result['search']['fields']);
        $this->assertEquals('simple', $result['responseFormat']);
    }

    public function testParseWithFieldNameSanitization(): void
    {
        $requestData = [
            'field@#$%' => 'value',
            'sortBy' => 'sort_field!@#'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Field names should be sanitized
        $this->assertEquals('field', $result['filters'][0]['field']);
        $this->assertEquals('sort_field', $result['sorting'][0]['field']);
    }

    public function testParseWithEmptyRequestData(): void
    {
        $requestData = [];
        
        $result = $this->parser->parse($requestData);
        
        // Should return default structure
        $this->assertEquals(1, $result['pagination']['page']);
        $this->assertEquals(20, $result['pagination']['pageSize']);
        $this->assertEmpty($result['sorting']);
        $this->assertEmpty($result['filters']);
        $this->assertEquals('', $result['search']['term']);
        $this->assertEquals([], $result['search']['fields']);
    }

    public function testParseWithNumericStringValues(): void
    {
        $requestData = [
            'page' => '3',
            'pageSize' => '25',
            'age' => '30'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should handle string numbers correctly
        $this->assertEquals(3, $result['pagination']['page']);
        $this->assertEquals(25, $result['pagination']['pageSize']);
        $this->assertEquals('30', $result['filters'][0]['value']); // Filter values remain as strings
    }

    public function testParseWithInvalidPaginationValues(): void
    {
        $requestData = [
            'page' => 'invalid',
            'pageSize' => 'invalid'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should handle invalid values gracefully
        $this->assertEquals(1, $result['pagination']['page']); // Default page
        $this->assertEquals(20, $result['pagination']['pageSize']); // Default pageSize
    }

    public function testParseWithZeroPageValues(): void
    {
        $requestData = [
            'page' => 0,
            'pageSize' => 0
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should constrain to valid values
        $this->assertEquals(1, $result['pagination']['page']); // Minimum page
        $this->assertEquals(20, $result['pagination']['pageSize']); // Default pageSize
    }
}
