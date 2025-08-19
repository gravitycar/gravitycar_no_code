<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Gravitycar\Api\RequestParameterParser;

class SimpleRequestParameterParserTest extends TestCase
{
    public function testCanInstantiateRequestParameterParser(): void
    {
        $parser = new RequestParameterParser();
        $this->assertInstanceOf(RequestParameterParser::class, $parser);
    }

    public function testDetectFormatWithAgGridParameters(): void
    {
        $parser = new RequestParameterParser();
        
        $requestData = [
            'startRow' => 0,
            'endRow' => 100,
            'filters' => [
                'name' => ['type' => 'contains', 'filter' => 'john']
            ]
        ];
        
        $format = $parser->detectFormat($requestData);
        
        $this->assertEquals('ag-grid', $format);
    }

    public function testParseUnifiedReturnsExpectedStructure(): void
    {
        $parser = new RequestParameterParser();
        
        $requestData = [
            'startRow' => 0,
            'endRow' => 100
        ];
        
        $result = $parser->parseUnified($requestData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('filters', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('sorting', $result);
        $this->assertArrayHasKey('search', $result);
        $this->assertArrayHasKey('meta', $result);
    }
}
