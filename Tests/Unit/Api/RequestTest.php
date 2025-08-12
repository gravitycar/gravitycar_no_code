<?php

namespace Tests\Unit\Api;

use Gravitycar\Api\Request;
use Gravitycar\Exceptions\GCException;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    public function testBasicParameterExtraction(): void
    {
        $request = new Request('/Users/123', ['', 'userId'], 'GET');
        
        $this->assertEquals('123', $request->get('userId'));
        $this->assertTrue($request->has('userId'));
        $this->assertFalse($request->has('nonexistent'));
        $this->assertNull($request->get('nonexistent'));
    }

    public function testMultipleParameterExtraction(): void
    {
        $request = new Request('/Users/123/orders/456', ['', 'userId', '', 'orderId'], 'POST');
        
        $this->assertEquals('123', $request->get('userId'));
        $this->assertEquals('456', $request->get('orderId'));
        $this->assertTrue($request->has('userId'));
        $this->assertTrue($request->has('orderId'));
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/Users/123/orders/456', $request->getUrl());
    }

    public function testAllParametersExtraction(): void
    {
        $request = new Request('/Users/123/orders/456', ['', 'userId', '', 'orderId'], 'PUT');
        
        $expected = [
            'userId' => '123',
            'orderId' => '456'
        ];
        
        $this->assertEquals($expected, $request->all());
    }

    public function testNamedExactComponents(): void
    {
        // Test extracting from exact match components too
        $request = new Request('/Users/123/orders/456', ['entityType', 'entityId', 'subType', 'subId'], 'GET');
        
        $this->assertEquals('Users', $request->get('entityType'));
        $this->assertEquals('123', $request->get('entityId'));
        $this->assertEquals('orders', $request->get('subType'));
        $this->assertEquals('456', $request->get('subId'));
    }

    public function testEmptyParameterNames(): void
    {
        // Only some components should be extracted
        $request = new Request('/api/v1/Users/123', ['', '', '', 'userId'], 'GET');
        
        $this->assertEquals('123', $request->get('userId'));
        $this->assertNull($request->get('api'));
        $this->assertNull($request->get('v1'));
        $this->assertNull($request->get('Users'));
        
        $expected = ['userId' => '123'];
        $this->assertEquals($expected, $request->all());
    }

    public function testHttpMethodHandling(): void
    {
        $request1 = new Request('/Users/123', ['', 'userId'], 'get');
        $request2 = new Request('/Users/123', ['', 'userId'], 'POST');
        $request3 = new Request('/Users/123', ['', 'userId'], 'Delete');
        
        $this->assertEquals('GET', $request1->getMethod());
        $this->assertEquals('POST', $request2->getMethod());
        $this->assertEquals('DELETE', $request3->getMethod());
    }

    public function testParameterCountMismatchThrowsException(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage("Parameter names count must match path components count");
        
        new Request('/Users/123/orders', ['', 'userId'], 'GET');
    }

    public function testParameterCountMismatchWithTooManyNames(): void
    {
        $this->expectException(GCException::class);
        $this->expectExceptionMessage("Parameter names count must match path components count");
        
        new Request('/Users/123', ['', 'userId', 'extra'], 'GET');
    }

    public function testEmptyPath(): void
    {
        $request = new Request('/', [], 'GET');
        
        $this->assertEquals([], $request->all());
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/', $request->getUrl());
    }

    public function testSingleComponent(): void
    {
        $request = new Request('/Users', ['entityType'], 'GET');
        
        $this->assertEquals('Users', $request->get('entityType'));
        $this->assertEquals(['entityType' => 'Users'], $request->all());
    }

    public function testPathWithTrailingSlash(): void
    {
        $request = new Request('/Users/123/', ['', 'userId'], 'GET');
        
        $this->assertEquals('123', $request->get('userId'));
    }

    public function testPathWithoutLeadingSlash(): void
    {
        $request = new Request('Users/123', ['entityType', 'entityId'], 'GET');
        
        $this->assertEquals('Users', $request->get('entityType'));
        $this->assertEquals('123', $request->get('entityId'));
    }

    public function testAllEmptyParameterNames(): void
    {
        $request = new Request('/Users/123/orders', ['', '', ''], 'GET');
        
        $this->assertEquals([], $request->all());
        $this->assertNull($request->get('anything'));
        $this->assertFalse($request->has('anything'));
    }

    public function testComplexPath(): void
    {
        $path = '/api/v2/organizations/abc123/users/xyz789/roles/admin';
        $paramNames = ['', 'version', '', 'orgId', '', 'userId', '', 'roleType'];
        
        $request = new Request($path, $paramNames, 'PATCH');
        
        $this->assertEquals('v2', $request->get('version'));
        $this->assertEquals('abc123', $request->get('orgId'));
        $this->assertEquals('xyz789', $request->get('userId'));
        $this->assertEquals('admin', $request->get('roleType'));
        $this->assertEquals('PATCH', $request->getMethod());
        
        $expected = [
            'version' => 'v2',
            'orgId' => 'abc123',
            'userId' => 'xyz789',
            'roleType' => 'admin'
        ];
        $this->assertEquals($expected, $request->all());
    }

    public function testSpecialCharactersInPath(): void
    {
        $request = new Request('/Users/user-123_test/orders', ['', 'userId', ''], 'GET');
        
        $this->assertEquals('user-123_test', $request->get('userId'));
    }
}
