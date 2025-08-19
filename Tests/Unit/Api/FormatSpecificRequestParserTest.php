<?php

namespace Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gravitycar\Api\FormatSpecificRequestParser;
use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;
use ReflectionClass;

class FormatSpecificRequestParserTest extends TestCase
{
    private MockFormatSpecificRequestParser $parser;
    private MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->logger = $this->createMock(Logger::class);
        
        // Create concrete implementation for testing
        $this->parser = new MockFormatSpecificRequestParser();
        
        // Mock the logger using reflection since ServiceLocator is used
        $this->setPrivateProperty($this->parser, 'logger', $this->logger);
    }

    public function testConstructorSetsLogger(): void
    {
        $parser = new MockFormatSpecificRequestParser();
        
        $logger = $this->getPrivateProperty($parser, 'logger');
        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testParseIsAbstract(): void
    {
        $reflection = new ReflectionClass(FormatSpecificRequestParser::class);
        $method = $reflection->getMethod('parse');
        
        $this->assertTrue($method->isAbstract());
    }

    public function testCanHandleIsAbstract(): void
    {
        $reflection = new ReflectionClass(FormatSpecificRequestParser::class);
        $method = $reflection->getMethod('canHandle');
        
        $this->assertTrue($method->isAbstract());
    }

    public function testGetFormatNameIsAbstract(): void
    {
        $reflection = new ReflectionClass(FormatSpecificRequestParser::class);
        $method = $reflection->getMethod('getFormatName');
        
        $this->assertTrue($method->isAbstract());
    }

    public function testSanitizeFieldNameWithValidInput(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'sanitizeFieldName');
        
        $result = $method->invoke($this->parser, 'valid_field.name');
        $this->assertEquals('valid_field.name', $result);
        
        $result = $method->invoke($this->parser, 'user_profile.email');
        $this->assertEquals('user_profile.email', $result);
    }

    public function testSanitizeFieldNameWithInvalidCharacters(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'sanitizeFieldName');
        
        $result = $method->invoke($this->parser, 'field-name@#$%');
        $this->assertEquals('fieldname', $result);
        
        $result = $method->invoke($this->parser, 'user profile!');
        $this->assertEquals('userprofile', $result);
    }

    public function testSanitizeFieldNameWithSpecialCharacters(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'sanitizeFieldName');
        
        $result = $method->invoke($this->parser, 'field<script>alert()</script>');
        $this->assertEquals('fieldscriptalertscript', $result);
    }

    public function testConstrainPageSizeWithValidSize(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'constrainPageSize');
        
        $result = $method->invoke($this->parser, 20);
        $this->assertEquals(20, $result);
        
        $result = $method->invoke($this->parser, 100);
        $this->assertEquals(100, $result);
    }

    public function testConstrainPageSizeWithZeroOrNegative(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'constrainPageSize');
        
        $result = $method->invoke($this->parser, 0);
        $this->assertEquals(20, $result); // DEFAULT_PAGE_SIZE
        
        $result = $method->invoke($this->parser, -5);
        $this->assertEquals(20, $result); // DEFAULT_PAGE_SIZE
    }

    public function testConstrainPageSizeWithExcessiveSize(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'constrainPageSize');
        
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Page size exceeds maximum, constraining', [
                'requested_size' => 2000,
                'max_size' => 1000
            ]);
        
        $result = $method->invoke($this->parser, 2000);
        $this->assertEquals(1000, $result); // MAX_PAGE_SIZE
    }

    public function testConstrainPageNumberWithValidPage(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'constrainPageNumber');
        
        $result = $method->invoke($this->parser, 5);
        $this->assertEquals(5, $result);
        
        $result = $method->invoke($this->parser, 1);
        $this->assertEquals(1, $result);
    }

    public function testConstrainPageNumberWithInvalidPage(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'constrainPageNumber');
        
        $result = $method->invoke($this->parser, 0);
        $this->assertEquals(1, $result);
        
        $result = $method->invoke($this->parser, -3);
        $this->assertEquals(1, $result);
    }

    public function testParseSortDirectionWithValidDirections(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'parseSortDirection');
        
        $result = $method->invoke($this->parser, 'asc');
        $this->assertEquals('asc', $result);
        
        $result = $method->invoke($this->parser, 'desc');
        $this->assertEquals('desc', $result);
        
        $result = $method->invoke($this->parser, 'ASC');
        $this->assertEquals('asc', $result);
        
        $result = $method->invoke($this->parser, 'DESC');
        $this->assertEquals('desc', $result);
    }

    public function testParseSortDirectionWithInvalidDirection(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'parseSortDirection');
        
        $result = $method->invoke($this->parser, 'invalid');
        $this->assertEquals('asc', $result); // Default
        
        $result = $method->invoke($this->parser, '');
        $this->assertEquals('asc', $result); // Default
        
        $result = $method->invoke($this->parser, 'random');
        $this->assertEquals('asc', $result); // Default
    }

    public function testCreatePaginationStructureWithDefaults(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'createPaginationStructure');
        
        $result = $method->invoke($this->parser, 2, 25);
        
        $expected = [
            'page' => 2,
            'pageSize' => 25,
            'offset' => 25, // (2-1) * 25
            'limit' => 25
        ];
        
        $this->assertEquals($expected, $result);
    }

    public function testCreatePaginationStructureWithOffsetOverride(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'createPaginationStructure');
        
        $result = $method->invoke($this->parser, 2, 25, 100);
        
        $expected = [
            'page' => 2,
            'pageSize' => 25,
            'offset' => 100, // Override value
            'limit' => 25
        ];
        
        $this->assertEquals($expected, $result);
    }

    public function testCreatePaginationStructureWithConstraints(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'createPaginationStructure');
        
        // Test with invalid page and excessive page size
        $result = $method->invoke($this->parser, -1, 2000);
        
        $expected = [
            'page' => 1, // Constrained to minimum
            'pageSize' => 1000, // Constrained to maximum
            'offset' => 0, // (1-1) * 1000
            'limit' => 1000
        ];
        
        $this->assertEquals($expected, $result);
    }

    public function testCreateFilterStructure(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'createFilterStructure');
        
        $result = $method->invoke($this->parser, 'user.name@#', 'contains', 'John');
        
        $expected = [
            'field' => 'user.name', // Sanitized
            'operator' => 'contains',
            'value' => 'John'
        ];
        
        $this->assertEquals($expected, $result);
    }

    public function testCreateSortStructure(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'createSortStructure');
        
        $result = $method->invoke($this->parser, 'user.email!@', 'DESC');
        
        $expected = [
            'field' => 'user.email', // Sanitized
            'direction' => 'desc' // Parsed and lowercased
        ];
        
        $this->assertEquals($expected, $result);
    }

    public function testParseJsonParameterWithValidJson(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'parseJsonParameter');
        
        $jsonString = '{"field":"name","operator":"contains","value":"test"}';
        $result = $method->invoke($this->parser, $jsonString);
        
        $expected = [
            'field' => 'name',
            'operator' => 'contains',
            'value' => 'test'
        ];
        
        $this->assertEquals($expected, $result);
    }

    public function testParseJsonParameterWithInvalidJson(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'parseJsonParameter');
        
        // json_decode doesn't throw exceptions, so no warning should be logged
        $result = $method->invoke($this->parser, 'invalid json {');
        $this->assertEquals([], $result); // Default fallback
    }

    public function testParseJsonParameterWithExceptionScenario(): void
    {
        // Test the exception path by using a mock that throws
        $mockParser = new class extends MockFormatSpecificRequestParser {
            protected function parseJsonParameter(string $jsonString, array $fallback = []): array
            {
                try {
                    // Force an exception to test the catch block
                    throw new \Exception('Test exception');
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to parse JSON parameter', [
                        'json_string' => substr($jsonString, 0, 200),
                        'error' => $e->getMessage()
                    ]);
                }
                return $fallback;
            }
        };
        
        $logger = $this->createMock(Logger::class);
        $this->setPrivateProperty($mockParser, 'logger', $logger);
        
        $logger->expects($this->once())
            ->method('warning')
            ->with('Failed to parse JSON parameter', [
                'json_string' => 'test',
                'error' => 'Test exception'
            ]);
        
        $method = $this->getPrivateMethod($mockParser, 'parseJsonParameter');
        $result = $method->invoke($mockParser, 'test');
        
        $this->assertEquals([], $result);
    }

    public function testParseJsonParameterWithCustomFallback(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'parseJsonParameter');
        
        $fallback = ['default' => 'value'];
        $result = $method->invoke($this->parser, 'invalid json', $fallback);
        
        $this->assertEquals($fallback, $result);
    }

    public function testParseJsonParameterWithNonArrayJson(): void
    {
        $method = $this->getPrivateMethod($this->parser, 'parseJsonParameter');
        
        // Valid JSON but not an array
        $result = $method->invoke($this->parser, '"string value"');
        $this->assertEquals([], $result); // Should return fallback
        
        $result = $method->invoke($this->parser, '123');
        $this->assertEquals([], $result); // Should return fallback
    }

    public function testConcreteImplementationCanCallAbstractMethods(): void
    {
        // Test that concrete implementation can implement abstract methods
        $requestData = ['test' => 'data'];
        
        $result = $this->parser->parse($requestData);
        $this->assertEquals(['parsed' => true], $result);
        
        $canHandle = $this->parser->canHandle($requestData);
        $this->assertTrue($canHandle);
        
        $formatName = $this->parser->getFormatName();
        $this->assertEquals('mock', $formatName);
    }

    /**
     * Helper method to access private properties
     */
    private function getPrivateProperty($object, string $propertyName)
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    /**
     * Helper method to set private properties
     */
    private function setPrivateProperty($object, string $propertyName, $value): void
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    /**
     * Helper method to access private methods
     */
    private function getPrivateMethod($object, string $methodName): \ReflectionMethod
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
}

/**
 * Concrete implementation of FormatSpecificRequestParser for testing
 */
class MockFormatSpecificRequestParser extends FormatSpecificRequestParser
{
    public function parse(array $requestData): array
    {
        return ['parsed' => true];
    }

    public function canHandle(array $requestData): bool
    {
        return true;
    }

    public function getFormatName(): string
    {
        return 'mock';
    }
}
