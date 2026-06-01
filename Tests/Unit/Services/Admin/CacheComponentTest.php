<?php

declare(strict_types=1);

namespace Gravitycar\Tests\Unit\Services\Admin;

use Gravitycar\Services\Admin\CacheComponent;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CacheComponent pure-static class.
 */
class CacheComponentTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testMetadataConstantValue(): void
    {
        $this->assertSame('metadata', CacheComponent::METADATA);
    }

    public function testRoutesConstantValue(): void
    {
        $this->assertSame('routes', CacheComponent::ROUTES);
    }

    public function testDocsConstantValue(): void
    {
        $this->assertSame('docs', CacheComponent::DOCS);
    }

    public function testNavigationConstantValue(): void
    {
        $this->assertSame('navigation', CacheComponent::NAVIGATION);
    }

    // -------------------------------------------------------------------------
    // all()
    // -------------------------------------------------------------------------

    public function testAllReturnsExactlyFourItems(): void
    {
        $all = CacheComponent::all();
        $this->assertCount(4, $all);
    }

    public function testAllContainsAllFourComponentConstants(): void
    {
        $all = CacheComponent::all();
        $this->assertContains(CacheComponent::METADATA, $all);
        $this->assertContains(CacheComponent::ROUTES, $all);
        $this->assertContains(CacheComponent::DOCS, $all);
        $this->assertContains(CacheComponent::NAVIGATION, $all);
    }

    // -------------------------------------------------------------------------
    // isValid()
    // -------------------------------------------------------------------------

    public function testIsValidReturnsTrueForMetadata(): void
    {
        $this->assertTrue(CacheComponent::isValid('metadata'));
    }

    public function testIsValidReturnsTrueForRoutes(): void
    {
        $this->assertTrue(CacheComponent::isValid('routes'));
    }

    public function testIsValidReturnsTrueForDocs(): void
    {
        $this->assertTrue(CacheComponent::isValid('docs'));
    }

    public function testIsValidReturnsTrueForNavigation(): void
    {
        $this->assertTrue(CacheComponent::isValid('navigation'));
    }

    public function testIsValidReturnsFalseForUnknownString(): void
    {
        $this->assertFalse(CacheComponent::isValid('unknown'));
    }

    public function testIsValidReturnsFalseForEmptyString(): void
    {
        $this->assertFalse(CacheComponent::isValid(''));
    }

    public function testIsValidReturnsFalseForPartialMatch(): void
    {
        $this->assertFalse(CacheComponent::isValid('meta'));
    }

    public function testIsValidReturnsFalseForCaseVariant(): void
    {
        $this->assertFalse(CacheComponent::isValid('METADATA'));
    }
}
