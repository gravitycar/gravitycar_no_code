<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Gravitycar\Api\AgGridRequestParser;

class AgGridRequestParserTest extends TestCase
{
    private AgGridRequestParser $parser;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AgGridRequestParser();
    }

    public function testCanHandleReturnsTrueWhenStartRowAndEndRowArePresent(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => '20'
        ];
        
        $this->assertTrue($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsFalseWhenStartRowIsMissing(): void
    {
        $requestData = [
            'endRow' => '20'
        ];
        
        $this->assertFalse($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsFalseWhenEndRowIsMissing(): void
    {
        $requestData = [
            'startRow' => '0'
        ];
        
        $this->assertFalse($this->parser->canHandle($requestData));
    }

    public function testCanHandleReturnsFalseWhenBothStartRowAndEndRowAreMissing(): void
    {
        $requestData = [
            'someOtherParam' => 'value'
        ];
        
        $this->assertFalse($this->parser->canHandle($requestData));
    }

    public function testGetFormatNameReturnsAgGrid(): void
    {
        $this->assertEquals('ag-grid', $this->parser->getFormatName());
    }

    public function testParseWithBasicPaginationParameters(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => '20'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('sorting', $result);
        $this->assertArrayHasKey('filters', $result);
        $this->assertArrayHasKey('search', $result);
        
        // Check pagination
        $this->assertEquals(1, $result['pagination']['page']);
        $this->assertEquals(20, $result['pagination']['pageSize']);
    }

    public function testParseWithDifferentPaginationRange(): void
    {
        $requestData = [
            'startRow' => '20',
            'endRow' => '40'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should be page 2 with size 20
        $this->assertEquals(2, $result['pagination']['page']);
        $this->assertEquals(20, $result['pagination']['pageSize']);
    }

    public function testParseWithLargePaginationRange(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => '100'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEquals(1, $result['pagination']['page']);
        $this->assertEquals(100, $result['pagination']['pageSize']);
    }

    public function testParseWithSortingParameters(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => '20',
            'sort[0][colId]' => 'name',
            'sort[0][sort]' => 'asc',
            'sort[1][colId]' => 'date',
            'sort[1][sort]' => 'desc'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(2, $result['sorting']);
        
        $this->assertEquals('name', $result['sorting'][0]['field']);
        $this->assertEquals('asc', $result['sorting'][0]['direction']);
        
        $this->assertEquals('date', $result['sorting'][1]['field']);
        $this->assertEquals('desc', $result['sorting'][1]['direction']);
    }

    public function testParseWithInvalidSortingJsonIgnoresSorting(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => '20',
            'sort[0][colId]' => '', // Empty field name should be ignored
            'sort[0][sort]' => 'asc'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEmpty($result['sorting']);
    }

    public function testParseWithFilterParameters(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => '20',
            'filters[name][type]' => 'equals',
            'filters[name][filter]' => 'John',
            'filters[age][type]' => 'greaterThan',
            'filters[age][filter]' => '25'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(2, $result['filters']);
        
        // Check name filter
        $nameFilter = $result['filters'][0];
        $this->assertEquals('name', $nameFilter['field']);
        $this->assertEquals('equals', $nameFilter['operator']);
        $this->assertEquals('John', $nameFilter['value']);
        
        // Check age filter
        $ageFilter = $result['filters'][1];
        $this->assertEquals('age', $ageFilter['field']);
        $this->assertEquals('greaterThan', $ageFilter['operator']);
        $this->assertEquals('25', $ageFilter['value']);
    }

    public function testParseWithInvalidFilterJsonIgnoresFilters(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => '20',
            'filters[empty][type]' => 'equals',
            'filters[empty][filter]' => '' // Empty filter value should be ignored
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertEmpty($result['filters']);
    }

    public function testParseWithSearchParameter(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => '20',
            'search' => 'john doe'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertIsArray($result['search']);
        $this->assertEquals('john doe', $result['search']['term']);
        $this->assertEquals('contains', $result['search']['operator']);
        $this->assertIsArray($result['search']['fields']);
    }

    public function testParseWithAllParameters(): void
    {
        $requestData = [
            'startRow' => '10',
            'endRow' => '25',
            'sort[0][colId]' => 'name',
            'sort[0][sort]' => 'asc',
            'filters[status][type]' => 'equals',
            'filters[status][filter]' => 'active',
            'search' => 'test search'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Check all sections populated
        $this->assertEquals(1, $result['pagination']['page']); // (10-0)/15 + 1 = 1 (for size 15)
        $this->assertEquals(15, $result['pagination']['pageSize']);
        $this->assertCount(1, $result['sorting']);
        $this->assertCount(1, $result['filters']);
        $this->assertEquals('test search', $result['search']['term']);
    }

    public function testParseWithEmptyStringSearch(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => '20',
            'search' => ''
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertIsArray($result['search']);
        $this->assertEquals('', $result['search']['term']);
    }

    public function testParseWithWhitespaceOnlySearchTrimsToNull(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => '20',
            'search' => '   '
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertIsArray($result['search']);
        $this->assertEquals('', $result['search']['term']); // trim() removes whitespace
    }

    public function testParseHandlesNonNumericStartRow(): void
    {
        $requestData = [
            'startRow' => 'invalid',
            'endRow' => '20'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should default to appropriate values
        $this->assertEquals(1, $result['pagination']['page']);
        $this->assertEquals(20, $result['pagination']['pageSize']);
    }

    public function testParseHandlesNonNumericEndRow(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => 'invalid'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should handle gracefully
        $this->assertIsArray($result['pagination']);
    }

    public function testParseWithComplexFilterModel(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => '20',
            'filters[name][type]' => 'contains',
            'filters[name][filter]' => 'test',
            'filters[category][type]' => 'equals',
            'filters[category][filter]' => 'A'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(2, $result['filters']);
        
        // Check complex filter structure
        $nameFilter = $result['filters'][0];
        $this->assertEquals('name', $nameFilter['field']);
        $this->assertEquals('contains', $nameFilter['operator']);
        $this->assertEquals('test', $nameFilter['value']);
    }

    public function testParseWithGlobalFilterParameter(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => '20',
            'globalFilter' => 'global search term'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertIsArray($result['search']);
        $this->assertEquals('global search term', $result['search']['term']);
        $this->assertEquals('contains', $result['search']['operator']);
    }

    public function testParseWithMissingSort(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => '20',
            'sort[0][colId]' => 'name'
            // Missing sort[0][sort] should default to 'asc'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(1, $result['sorting']);
        $this->assertEquals('name', $result['sorting'][0]['field']);
        $this->assertEquals('asc', $result['sorting'][0]['direction']);
    }

    public function testParseWithMultipleSortsPriority(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => '20',
            'sort[2][colId]' => 'third',
            'sort[2][sort]' => 'asc',
            'sort[0][colId]' => 'first',
            'sort[0][sort]' => 'desc',
            'sort[1][colId]' => 'second',
            'sort[1][sort]' => 'asc'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(3, $result['sorting']);
        // Should be ordered by priority
        $this->assertEquals('first', $result['sorting'][0]['field']);
        $this->assertEquals('second', $result['sorting'][1]['field']);
        $this->assertEquals('third', $result['sorting'][2]['field']);
    }

    public function testParseWithFilterTypeMapping(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => '20',
            'filters[field1][type]' => 'notEqual',
            'filters[field1][filter]' => 'value1',
            'filters[field2][type]' => 'startsWith',
            'filters[field2][filter]' => 'value2',
            'filters[field3][type]' => 'unknownType',
            'filters[field3][filter]' => 'value3'
        ];
        
        $result = $this->parser->parse($requestData);
        
        $this->assertCount(3, $result['filters']);
        
        // Check operator mapping
        $this->assertEquals('notEquals', $result['filters'][0]['operator']);
        $this->assertEquals('startsWith', $result['filters'][1]['operator']);
        $this->assertEquals('equals', $result['filters'][2]['operator']); // Unknown type defaults to 'equals'
    }

    public function testParseWithZeroPaginationRange(): void
    {
        $requestData = [
            'startRow' => '10',
            'endRow' => '10' // Zero size range
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Should handle zero-size gracefully
        $this->assertIsArray($result['pagination']);
        $this->assertGreaterThan(0, $result['pagination']['pageSize']); // Should default to minimum
    }

    public function testParseWithFieldNameSanitization(): void
    {
        $requestData = [
            'startRow' => '0',
            'endRow' => '20',
            'filters[field@#$%][type]' => 'equals',
            'filters[field@#$%][filter]' => 'value',
            'sort[0][colId]' => 'sort_field!@#',
            'sort[0][sort]' => 'asc'
        ];
        
        $result = $this->parser->parse($requestData);
        
        // Field names should be sanitized
        $this->assertEquals('field', $result['filters'][0]['field']);
        $this->assertEquals('sort_field', $result['sorting'][0]['field']);
    }
}
