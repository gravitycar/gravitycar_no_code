<?php

namespace Tests\Unit\Api;

use Gravitycar\Api\APIPathScorer;
use Gravitycar\Exceptions\GCException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class APIPathScorerTest extends TestCase
{
    private APIPathScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new APIPathScorer(new NullLogger());
    }

    public function testExactMatchScoring(): void
    {
        $clientComponents = ['Users', '123'];
        $registeredComponents = ['Users', '123'];
        
        $score = $this->scorer->scoreRoute($clientComponents, $registeredComponents);
        
        // Position 0: Users vs Users = 2 * (2 - 0) = 4
        // Position 1: 123 vs 123 = 2 * (2 - 1) = 2
        // Total: 6
        $this->assertEquals(6, $score);
    }

    public function testWildcardMatchScoring(): void
    {
        $clientComponents = ['Users', '123'];
        $registeredComponents = ['Users', '?'];
        
        $score = $this->scorer->scoreRoute($clientComponents, $registeredComponents);
        
        // Position 0: Users vs Users = 2 * (2 - 0) = 4
        // Position 1: 123 vs ? = 1 * (2 - 1) = 1
        // Total: 5
        $this->assertEquals(5, $score);
    }

    public function testNoMatchScoring(): void
    {
        $clientComponents = ['Users', '123'];
        $registeredComponents = ['Products', '456'];
        
        $score = $this->scorer->scoreRoute($clientComponents, $registeredComponents);
        
        // Position 0: Users vs Products = 0 * (2 - 0) = 0
        // Position 1: 123 vs 456 = 0 * (2 - 1) = 0
        // Total: 0
        $this->assertEquals(0, $score);
    }

    public function testMixedMatchScoring(): void
    {
        $clientComponents = ['Users', '123', 'orders'];
        $registeredComponents = ['Users', '?', 'orders'];
        
        $score = $this->scorer->scoreRoute($clientComponents, $registeredComponents);
        
        // Position 0: Users vs Users = 2 * (3 - 0) = 6
        // Position 1: 123 vs ? = 1 * (3 - 1) = 2
        // Position 2: orders vs orders = 2 * (3 - 2) = 2
        // Total: 10
        $this->assertEquals(10, $score);
    }

    public function testPositionWeighting(): void
    {
        // Earlier components should have higher weight
        $clientComponents = ['A', 'B'];
        $registeredComponents1 = ['A', '?'];
        $registeredComponents2 = ['?', 'B'];
        
        $score1 = $this->scorer->scoreRoute($clientComponents, $registeredComponents1);
        $score2 = $this->scorer->scoreRoute($clientComponents, $registeredComponents2);
        
        // Score1: A=A (2*2=4) + B=? (1*1=1) = 5
        // Score2: A=? (1*2=2) + B=B (2*1=2) = 4
        $this->assertEquals(5, $score1);
        $this->assertEquals(4, $score2);
        $this->assertGreaterThan($score2, $score1);
    }

    public function testDifferentLengthPaths(): void
    {
        $clientComponents = ['Users', '123'];
        $registeredComponents = ['Users'];
        
        $score = $this->scorer->scoreRoute($clientComponents, $registeredComponents);
        
        // Different lengths should return 0
        $this->assertEquals(0, $score);
    }

    public function testParsePathComponents(): void
    {
        $this->assertEquals(['Users', '123'], $this->scorer->parsePathComponents('/Users/123'));
        $this->assertEquals(['Users'], $this->scorer->parsePathComponents('/Users/'));
        $this->assertEquals(['Users'], $this->scorer->parsePathComponents('Users'));
        $this->assertEquals([], $this->scorer->parsePathComponents('/'));
        $this->assertEquals([], $this->scorer->parsePathComponents(''));
    }

    public function testFindBestMatch(): void
    {
        $routes = [
            [
                'path' => '/Users/?',
                'pathComponents' => ['Users', '?'],
                'method' => 'GET',
                'apiClass' => 'UsersAPIController',
                'apiMethod' => 'get'
            ],
            [
                'path' => '/Users/123',
                'pathComponents' => ['Users', '123'],
                'method' => 'GET',
                'apiClass' => 'UsersAPIController',
                'apiMethod' => 'getSpecific'
            ]
        ];

        // Test exact match wins over wildcard
        $bestRoute = $this->scorer->findBestMatch('GET', '/Users/123', $routes);
        $this->assertEquals('/Users/123', $bestRoute['path']);
        
        // Test wildcard match when exact doesn't exist
        $bestRoute = $this->scorer->findBestMatch('GET', '/Users/456', $routes);
        $this->assertEquals('/Users/?', $bestRoute['path']);
    }

    public function testFindBestMatchNoResult(): void
    {
        $routes = [
            [
                'path' => '/Products/special/items',
                'pathComponents' => ['Products', 'special', 'items'],
                'method' => 'GET',
                'apiClass' => 'ProductsAPIController',
                'apiMethod' => 'get'
            ]
        ];

        // Completely different path structure should return null
        $bestRoute = $this->scorer->findBestMatch('GET', '/Users/123', $routes);
        $this->assertNull($bestRoute);
    }

    public function testCalculateComponentScore(): void
    {
        // Test exact match
        $score = $this->scorer->calculateComponentScore('Users', 'Users', 0, 2);
        $this->assertEquals(4, $score); // 2 * (2 - 0)
        
        // Test wildcard match
        $score = $this->scorer->calculateComponentScore('123', '?', 1, 2);
        $this->assertEquals(1, $score); // 1 * (2 - 1)
        
        // Test no match
        $score = $this->scorer->calculateComponentScore('Users', 'Products', 0, 2);
        $this->assertEquals(0, $score); // 0 * (2 - 0)
    }

    public function testEmptyPaths(): void
    {
        $this->assertEquals(0, $this->scorer->scoreRoute([], []));
        $this->assertEquals(0, $this->scorer->scoreRoute(['Users'], []));
        $this->assertEquals(0, $this->scorer->scoreRoute([], ['Users']));
    }

    public function testComplexScenario(): void
    {
        $routes = [
            [
                'path' => '/api/?/?',
                'pathComponents' => ['api', '?', '?'],
                'method' => 'GET',
                'apiClass' => 'GenericAPIController',
                'apiMethod' => 'handle'
            ],
            [
                'path' => '/api/users/?',
                'pathComponents' => ['api', 'users', '?'],
                'method' => 'GET',
                'apiClass' => 'UsersAPIController',
                'apiMethod' => 'get'
            ],
            [
                'path' => '/api/users/123',
                'pathComponents' => ['api', 'users', '123'],
                'method' => 'GET',
                'apiClass' => 'UsersAPIController',
                'apiMethod' => 'getSpecific'
            ]
        ];

        // Most specific route should win
        $bestRoute = $this->scorer->findBestMatch('GET', '/api/users/123', $routes);
        $this->assertEquals('/api/users/123', $bestRoute['path']);
        
        // Partial match should get medium specificity
        $bestRoute = $this->scorer->findBestMatch('GET', '/api/users/456', $routes);
        $this->assertEquals('/api/users/?', $bestRoute['path']);
        
        // Generic wildcard should be last resort
        $bestRoute = $this->scorer->findBestMatch('GET', '/api/products/789', $routes);
        $this->assertEquals('/api/?/?', $bestRoute['path']);
    }
}
