<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Gravitycar\Api\MuiDataGridRequestParser;

class MuiDataGridRequestParserTest extends TestCase
{
    private MuiDataGridRequestParser $parser;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new MuiDataGridRequestParser();
    }

    public function testCanHandleReturnsTrueWhenFilterModelIsPresent(): void
    {
        $requestData = [
            'filterModel' => '{"items":[]}'
        ];
        
        $this->assertTrue($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsTrueWhenSortModelIsPresent(): void
    {
        $requestData = [
            'sortModel' => '[{"field":"name","sort":"asc"}]'
        ];
        
        $this->assertTrue($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsTrueWhenBothArePresent(): void
    {
        $requestData = [
            'filterModel' => '{"items":[]}',
            'sortModel' => '[{"field":"name","sort":"asc"}]'
        ];
        
        $this->assertTrue($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsFalseWhenNeitherIsPresent(): void
    {
        $requestData = [
            'page' => 0,
            'pageSize' => 25
        ];
        
        $this->assertFalse($this->parser->canHandle($requestData));
    }

    public function testGetFormatNameReturnsMuiDatagrid(): void
    {
        $this->assertEquals('mui-datagrid', $this->parser->getFormatName());
    }

    public function testParseWithBasicPaginationParameters(): void
    {
        $requestData = [
            'filterModel' => '{"items":[]}',
            'page' => 0,
            'pageSize' => 25
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('sorting', $result);
        $this->assertArrayHasKey('filters', $result);
        $this->assertArrayHasKey('search', $result);
        $this->assertArrayHasKey('responseFormat', $result);
        
        // Check pagination (MUI uses 0-based pages, converted to 1-based)
        $this->assertEquals(1, $result['pagination']['page']);
        $this->assertEquals(25, $result['pagination']['pageSize']);
    }

    public function testParseWithDifferentPaginationPage(): void
    {
        $requestData = [
            'filterModel' => '{"items":[]}',
            'page' => 2,
            'pageSize' => 50
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should be page 3 (2+1) with size 50
        $this->assertEquals(3, $result['pagination']['page']);
        $this->assertEquals(50, $result['pagination']['pageSize']);
    }

    public function testParseWithDefaultPagination(): void
    {
        $requestData = [
            'sortModel' => '[{"field":"name","sort":"asc"}]'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should default to page 1 and default page size
        $this->assertEquals(1, $result['pagination']['page']);
        $this->assertEquals(20, $result['pagination']['pageSize']); // DEFAULT_PAGE_SIZE
    }

    public function testParseWithSortingParameters(): void
    {
        $requestData = [
            'sortModel' => '[{"field":"name","sort":"asc"},{"field":"created_at","sort":"desc"}]'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(2, $result['sorting']);
        
        $this->assertEquals('name', $result['sorting'][0]['field']);
        $this->assertEquals('asc', $result['sorting'][0]['direction']);
        
        $this->assertEquals('created_at', $result['sorting'][1]['field']);
        $this->assertEquals('desc', $result['sorting'][1]['direction']);
    }

    public function testParseWithInvalidSortingJsonIgnoresSorting(): void
    {
        $requestData = [
            'sortModel' => 'invalid-json'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEmpty($result['sorting']);
    }

    public function testParseWithFilterModelItemsFormat(): void
    {
        $requestData = [
            'filterModel' => '{"items":[{"field":"name","operator":"contains","value":"john"},{"field":"age","operator":">=","value":18}]}'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(2, $result['filters']);
        
        // Check name filter
        $nameFilter = $result['filters'][0];
        $this->assertEquals('name', $nameFilter['field']);
        $this->assertEquals('contains', $nameFilter['operator']);
        $this->assertEquals('john', $nameFilter['value']);
        
        // Check age filter
        $ageFilter = $result['filters'][1];
        $this->assertEquals('age', $ageFilter['field']);
        $this->assertEquals('greaterThanOrEqual', $ageFilter['operator']);
        $this->assertEquals(18, $ageFilter['value']);
    }

    public function testParseWithFilterModelAlternativeFormat(): void
    {
        $requestData = [
            'filterModel' => '{"name":"john","status":"active"}'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(2, $result['filters']);
        
        // Check simple field => value mapping defaults to equals
        $nameFilter = $result['filters'][0];
        $this->assertEquals('name', $nameFilter['field']);
        $this->assertEquals('equals', $nameFilter['operator']);
        $this->assertEquals('john', $nameFilter['value']);
    }

    public function testParseWithFilterModelComplexOperators(): void
    {
        $requestData = [
            'filterModel' => '{"age":{"gte":18,"lte":65},"salary":{"gt":50000}}'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(3, $result['filters']);
        
        // Check complex operators
        $ageGteFilter = $result['filters'][0];
        $this->assertEquals('age', $ageGteFilter['field']);
        $this->assertEquals('greaterThanOrEqual', $ageGteFilter['operator']);
        $this->assertEquals(18, $ageGteFilter['value']);
        
        $ageLteFilter = $result['filters'][1];
        $this->assertEquals('age', $ageLteFilter['field']);
        $this->assertEquals('lessThanOrEqual', $ageLteFilter['operator']);
        $this->assertEquals(65, $ageLteFilter['value']);
    }

    public function testParseWithInvalidFilterJsonIgnoresFilters(): void
    {
        $requestData = [
            'filterModel' => 'invalid-json'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEmpty($result['filters']);
    }

    public function testParseWithSearchParameter(): void
    {
        $requestData = [
            'filterModel' => '{"items":[]}',
            'search' => 'john doe'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertIsArray($result['search']);
        $this->assertEquals('john doe', $result['search']['term']);
        $this->assertEquals('contains', $result['search']['operator']);
        $this->assertIsArray($result['search']['fields']);
    }

    public function testParseWithQParameter(): void
    {
        $requestData = [
            'sortModel' => '[{"field":"name","sort":"asc"}]',
            'q' => 'search term'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertIsArray($result['search']);
        $this->assertEquals('search term', $result['search']['term']);
    }

    public function testParseWithAllParameters(): void
    {
        $requestData = [
            'page' => 1,
            'pageSize' => 30,
            'sortModel' => '[{"field":"name","sort":"asc"}]',
            'filterModel' => '{"items":[{"field":"status","operator":"equals","value":"active"}]}',
            'search' => 'test search'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Check all sections populated
        $this->assertEquals(2, $result['pagination']['page']); // 1+1 (0-based to 1-based)
        $this->assertEquals(30, $result['pagination']['pageSize']);
        $this->assertCount(1, $result['sorting']);
        $this->assertCount(1, $result['filters']);
        $this->assertEquals('test search', $result['search']['term']);
        $this->assertEquals('mui-datagrid', $result['responseFormat']);
    }

    public function testParseWithEmptyStringSearch(): void
    {
        $requestData = [
            'filterModel' => '{"items":[]}',
            'search' => ''
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertIsArray($result['search']);
        $this->assertEquals('', $result['search']['term']);
    }

    public function testParseWithWhitespaceOnlySearchTrimmed(): void
    {
        $requestData = [
            'sortModel' => '[{"field":"name","sort":"asc"}]',
            'search' => '   '
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertIsArray($result['search']);
        $this->assertEquals('', $result['search']['term']); // trim() removes whitespace
    }

    public function testOperatorMappingStringOperators(): void
    {
        $requestData = [
            'filterModel' => '{"items":[{"field":"name","operator":"startsWith","value":"j"},{"field":"desc","operator":"endsWith","value":"ing"}]}'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(2, $result['filters']);
        $this->assertEquals('startsWith', $result['filters'][0]['operator']);
        $this->assertEquals('endsWith', $result['filters'][1]['operator']);
    }

    public function testOperatorMappingNumberOperators(): void
    {
        $requestData = [
            'filterModel' => '{"items":[{"field":"age","operator":">","value":18},{"field":"score","operator":"!=","value":0}]}'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(2, $result['filters']);
        $this->assertEquals('greaterThan', $result['filters'][0]['operator']);
        $this->assertEquals('notEquals', $result['filters'][1]['operator']);
    }

    public function testOperatorMappingDateOperators(): void
    {
        $requestData = [
            'filterModel' => '{"items":[{"field":"created_at","operator":"after","value":"2023-01-01"},{"field":"updated_at","operator":"onOrBefore","value":"2023-12-31"}]}'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(2, $result['filters']);
        $this->assertEquals('greaterThan', $result['filters'][0]['operator']);
        $this->assertEquals('lessThanOrEqual', $result['filters'][1]['operator']);
    }

    public function testOperatorMappingUnknownOperatorDefaultsToEquals(): void
    {
        $requestData = [
            'filterModel' => '{"items":[{"field":"field1","operator":"unknownOperator","value":"value1"}]}'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(1, $result['filters']);
        $this->assertEquals('equals', $result['filters'][0]['operator']);
    }

    public function testParseWithIncompleteFilterItems(): void
    {
        $requestData = [
            'filterModel' => '{"items":[{"field":"name"},{"operator":"contains","value":"john"},{"field":"age","operator":">="}]}'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should ignore incomplete filter items (missing field, operator, or value)
        $this->assertEmpty($result['filters']);
    }

    public function testParseWithIncompleteSortItems(): void
    {
        $requestData = [
            'sortModel' => '[{"field":"name"},{"sort":"asc"},{"field":"age","sort":"desc"}]'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should only include complete sort items
        $this->assertCount(1, $result['sorting']);
        $this->assertEquals('age', $result['sorting'][0]['field']);
        $this->assertEquals('desc', $result['sorting'][0]['direction']);
    }

    public function testParseWithFieldNameSanitization(): void
    {
        $requestData = [
            'filterModel' => '{"items":[{"field":"field@#$%","operator":"equals","value":"value"}]}',
            'sortModel' => '[{"field":"sort_field!@#","sort":"asc"}]'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Field names should be sanitized
        $this->assertEquals('field', $result['filters'][0]['field']);
        $this->assertEquals('sort_field', $result['sorting'][0]['field']);
    }

    public function testParseWithLogicOperatorInFilterModel(): void
    {
        $requestData = [
            'filterModel' => '{"items":[{"field":"name","operator":"contains","value":"john"}],"logicOperator":"and"}'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should parse items regardless of logicOperator presence
        $this->assertCount(1, $result['filters']);
        $this->assertEquals('name', $result['filters'][0]['field']);
    }
}
