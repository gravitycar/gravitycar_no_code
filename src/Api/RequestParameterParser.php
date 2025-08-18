<?php

namespace Gravitycar\Api;

use Gravitycar\Core\ServiceLocator;
use Monolog\Logger;

/**
 * Request Parameter Parser - Factory/Coordinator for format-specific parsers
 * 
 * Detects request format and delegates to appropriate format-specific parser.
 * Provides unified interface for parsing different React library query parameter formats.
 */
class RequestParameterParser
{
    private Logger $logger;
    private const MAX_PAGE_SIZE = 1000;
    
    /** @var FormatSpecificRequestParser[] */
    private array $parsers = [];
    
    public function __construct()
    {
        $this->logger = ServiceLocator::getLogger();
        $this->initializeParsers();
    }
    
    /**
     * Parse unified request parameters using format detection
     * 
     * @param array $requestData Raw request data
     * @return array Standardized parsed parameters
     */
    public function parseUnified(array $requestData): array
    {
        $format = $this->detectFormat($requestData);
        $parser = $this->getParserForFormat($format);
        
        $this->logger->debug('Parsing request with detected format', [
            'format' => $format,
            'parser_class' => get_class($parser),
            'param_count' => count($requestData)
        ]);
        
        $result = $parser->parse($requestData);
        
        // Add metadata about parsing
        $result['meta'] = [
            'detectedFormat' => $format,
            'parserClass' => get_class($parser),
            'originalParamCount' => count($requestData),
            'parsedAt' => date('c')
        ];
        
        $this->logger->info('Request parsing completed', [
            'format' => $format,
            'filters_count' => count($result['filters'] ?? []),
            'sorts_count' => count($result['sorting'] ?? []),
            'has_search' => !empty($result['search']['term'] ?? ''),
            'page' => $result['pagination']['page'] ?? null
        ]);
        
        return $result;
    }
    
    /**
     * Detect request format based on parameter patterns
     * 
     * @param array $requestData Raw request data
     * @return string Format name
     */
    public function detectFormat(array $requestData): string
    {
        // Try each parser in priority order (most specific first)
        foreach ($this->parsers as $parser) {
            if ($parser->canHandle($requestData)) {
                $format = $parser->getFormatName();
                $this->logger->debug('Format detected', [
                    'format' => $format,
                    'parser_class' => get_class($parser)
                ]);
                return $format;
            }
        }
        
        // Default to simple format
        return 'simple';
    }
    
    /**
     * Parse filters from request data using specific format
     * 
     * @param array $requestData Raw request data
     * @param string $format Format to use for parsing
     * @return array Parsed filters
     */
    public function parseFilters(array $requestData, string $format): array
    {
        $parser = $this->getParserForFormat($format);
        $result = $parser->parse($requestData);
        return $result['filters'] ?? [];
    }
    
    /**
     * Parse pagination from request data using specific format
     * 
     * @param array $requestData Raw request data
     * @param string $format Format to use for parsing
     * @return array Parsed pagination
     */
    public function parsePagination(array $requestData, string $format): array
    {
        $parser = $this->getParserForFormat($format);
        $result = $parser->parse($requestData);
        return $result['pagination'] ?? [];
    }
    
    /**
     * Parse sorting from request data using specific format
     * 
     * @param array $requestData Raw request data
     * @param string $format Format to use for parsing
     * @return array Parsed sorting
     */
    public function parseSorting(array $requestData, string $format): array
    {
        $parser = $this->getParserForFormat($format);
        $result = $parser->parse($requestData);
        return $result['sorting'] ?? [];
    }
    
    /**
     * Parse search from request data using specific format
     * 
     * @param array $requestData Raw request data
     * @param string $format Format to use for parsing
     * @return array Parsed search
     */
    public function parseSearch(array $requestData, string $format): array
    {
        $parser = $this->getParserForFormat($format);
        $result = $parser->parse($requestData);
        return $result['search'] ?? [];
    }
    
    /**
     * Get available formats
     * 
     * @return array List of available format names
     */
    public function getAvailableFormats(): array
    {
        return array_map(function($parser) {
            return $parser->getFormatName();
        }, $this->parsers);
    }
    
    /**
     * Initialize format-specific parsers in priority order
     */
    private function initializeParsers(): void
    {
        // Order matters - most specific parsers first
        $this->parsers = [
            new AgGridRequestParser(),
            new MuiDataGridRequestParser(),
            // TODO: Add StructuredRequestParser and AdvancedRequestParser when implemented
            new SimpleRequestParser() // Always last (fallback)
        ];
        
        $this->logger->debug('Initialized request parsers', [
            'parser_count' => count($this->parsers),
            'parsers' => array_map(function($parser) {
                return [
                    'class' => get_class($parser),
                    'format' => $parser->getFormatName()
                ];
            }, $this->parsers)
        ]);
    }
    
    /**
     * Get parser for specific format
     * 
     * @param string $format Format name
     * @return FormatSpecificRequestParser Parser instance
     */
    private function getParserForFormat(string $format): FormatSpecificRequestParser
    {
        foreach ($this->parsers as $parser) {
            if ($parser->getFormatName() === $format) {
                return $parser;
            }
        }
        
        // Fallback to SimpleRequestParser
        return new SimpleRequestParser();
    }
}
