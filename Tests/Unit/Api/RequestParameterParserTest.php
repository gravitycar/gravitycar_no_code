<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Gravitycar\Api\RequestParameterParser;

class RequestParameterParserTest extends TestCase
{
    private RequestParameterParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new RequestParameterParser();
    }

    public function testDetectFormatWithAgGridParameters(): void
    {
        $requestData = [
            'startRow' => 0,
            'endRow' => 100,
            'filters[name][type]' => 'contains',
            'filters[name][filter]' => 'john'
        ];
        
        $format = $this->parser->detectFormat($requestData);
        $this->assertEquals('ag-grid', $format);
    }

    public function testDetectFormatWithMuiDataGridParameters(): void
    {
        $requestData = [
            'filterModel' => '{"items":[{"field":"name","operator":"contains","value":"john"}]}',
            'sortModel' => '[{"field":"created_at","sort":"desc"}]',
            'page' => 0,
            'pageSize' => 25
        ];
        
        $format = $this->parser->detectFormat($requestData);
        $this->assertEquals('mui-datagrid', $format);
    }

    public function testDetectFormatWithStructuredParameters(): void
    {
        $requestData = [
            'filter' => [
                'name' => ['contains' => 'john'],
                'age' => ['gte' => 18]
            ],
            'sort' => [
                0 => ['field' => 'created_at', 'direction' => 'desc']
            ]
        ];
        
        $format = $this->parser->detectFormat($requestData);
        $this->assertEquals('structured', $format);
    }

    public function testDetectFormatWithSimpleParametersFallback(): void
    {
        $requestData = [
            'page' => 1,
            'pageSize' => 20,
            'search' => 'john'
        ];
        
        $format = $this->parser->detectFormat($requestData);
        $this->assertEquals('simple', $format);
    }

    public function testParseUnifiedReturnsStandardizedStructure(): void
    {
        $requestData = [
            'startRow' => 0,
            'endRow' => 100,
            'filters[name][type]' => 'contains',
            'filters[name][filter]' => 'john'
        ];
        
        $result = $this->parser->parseUnified($requestData);
        
        // Check standard structure
        $this->assertArrayHasKey('filters', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('sorting', $result);
        $this->assertArrayHasKey('search', $result);
        $this->assertArrayHasKey('meta', $result);
        
        // Check metadata
        $this->assertEquals('ag-grid', $result['meta']['detectedFormat']);
        $this->assertArrayHasKey('parserClass', $result['meta']);
        $this->assertArrayHasKey('originalParamCount', $result['meta']);
        
        // Check pagination parsing
        $this->assertEquals(0, $result['pagination']['startRow']);
        $this->assertEquals(100, $result['pagination']['endRow']);
        
        // Check filter parsing
        $this->assertCount(1, $result['filters']);
        $this->assertEquals('name', $result['filters'][0]['field']);
        $this->assertEquals('contains', $result['filters'][0]['operator']);
        $this->assertEquals('john', $result['filters'][0]['value']);
    }

    public function testParseFiltersMethodDelegation(): void
    {
        $requestData = [
            'filters[name][type]' => 'contains',
            'filters[name][filter]' => 'test'
        ];
        
        $filters = $this->parser->parseFilters($requestData, 'ag-grid');
        
        $this->assertIsArray($filters);
        $this->assertCount(1, $filters);
        $this->assertEquals('name', $filters[0]['field']);
        $this->assertEquals('contains', $filters[0]['operator']);
        $this->assertEquals('test', $filters[0]['value']);
    }

    public function testParsePaginationMethodDelegation(): void
    {
        $requestData = [
            'startRow' => 20,
            'endRow' => 40
        ];
        
        $pagination = $this->parser->parsePagination($requestData, 'ag-grid');
        
        $this->assertIsArray($pagination);
        $this->assertEquals(20, $pagination['startRow']);
        $this->assertEquals(40, $pagination['endRow']);
    }

    public function testParseSortingMethodDelegation(): void
    {
        $requestData = [
            'sortBy' => 'name',
            'sortOrder' => 'desc'
        ];
        
        $sorting = $this->parser->parseSorting($requestData, 'simple');
        
        $this->assertIsArray($sorting);
        $this->assertCount(1, $sorting);
        $this->assertEquals('name', $sorting[0]['field']);
        $this->assertEquals('desc', $sorting[0]['direction']);
    }

    public function testParseSearchMethodDelegation(): void
    {
        $requestData = [
            'search' => 'john doe',
            'search_fields' => 'first_name,last_name,email'
        ];
        
        $search = $this->parser->parseSearch($requestData, 'advanced');
        
        $this->assertIsArray($search);
        $this->assertEquals('john doe', $search['term']);
        $this->assertEquals(['first_name', 'last_name', 'email'], $search['fields']);
    }

    public function testGetAvailableFormats(): void
    {
        $formats = $this->parser->getAvailableFormats();
        
        $this->assertIsArray($formats);
        $this->assertContains('ag-grid', $formats);
        $this->assertContains('mui-datagrid', $formats);
        $this->assertContains('structured', $formats);
        $this->assertContains('advanced', $formats);
        $this->assertContains('simple', $formats);
        
        // Should have exactly 5 parsers
        $this->assertCount(5, $formats);
    }

    public function testFormatDetectionPriority(): void
    {
        // Test that AG-Grid is detected over simple format
        $agGridWithSimpleParams = [
            'startRow' => 0,
            'endRow' => 20,
            'page' => 1,
            'pageSize' => 20
        ];
        
        $format = $this->parser->detectFormat($agGridWithSimpleParams);
        $this->assertEquals('ag-grid', $format);
        
        // Test that MUI DataGrid is detected over others
        $muiWithOtherParams = [
            'filterModel' => '{"items":[]}',
            'page' => 1,
            'search' => 'test'
        ];
        
        $format = $this->parser->detectFormat($muiWithOtherParams);
        $this->assertEquals('mui-datagrid', $format);
    }

    public function testEmptyParametersDefaultToSimple(): void
    {
        $requestData = [];
        
        $format = $this->parser->detectFormat($requestData);
        $this->assertEquals('simple', $format);
        
        $result = $this->parser->parseUnified($requestData);
        $this->assertEquals('simple', $result['meta']['detectedFormat']);
    }
}
