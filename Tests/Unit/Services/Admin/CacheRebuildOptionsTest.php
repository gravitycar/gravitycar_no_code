<?php

declare(strict_types=1);

namespace Gravitycar\Tests\Unit\Services\Admin;

use Gravitycar\Services\Admin\CacheComponent;
use Gravitycar\Services\Admin\CacheRebuildOptions;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CacheRebuildOptions value object.
 */
class CacheRebuildOptionsTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Constructor validation
    // -------------------------------------------------------------------------

    public function testConstructorThrowsForEmptyComponents(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CacheRebuildOptions([]);
    }

    public function testConstructorThrowsForUnknownComponent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CacheRebuildOptions(['not_a_real_component']);
    }

    public function testConstructorThrowsForMixedValidAndInvalidComponents(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CacheRebuildOptions([CacheComponent::METADATA, 'bogus']);
    }

    public function testConstructorSucceedsWithValidComponents(): void
    {
        $options = new CacheRebuildOptions([CacheComponent::METADATA]);
        $this->assertSame([CacheComponent::METADATA], $options->getComponents());
    }

    // -------------------------------------------------------------------------
    // all() factory
    // -------------------------------------------------------------------------

    public function testAllReturnsAllFourComponents(): void
    {
        $options = CacheRebuildOptions::all();
        $components = $options->getComponents();
        $this->assertCount(4, $components);
        $this->assertContains(CacheComponent::METADATA, $components);
        $this->assertContains(CacheComponent::ROUTES, $components);
        $this->assertContains(CacheComponent::DOCS, $components);
        $this->assertContains(CacheComponent::NAVIGATION, $components);
    }

    public function testAllSetsUpdateSchemaTrue(): void
    {
        $options = CacheRebuildOptions::all();
        $this->assertTrue($options->isUpdateSchema());
    }

    public function testAllSetsUpdatePermissionsTrue(): void
    {
        $options = CacheRebuildOptions::all();
        $this->assertTrue($options->isUpdatePermissions());
    }

    public function testAllSetsDryRunFalse(): void
    {
        $options = CacheRebuildOptions::all();
        $this->assertFalse($options->isDryRun());
    }

    // -------------------------------------------------------------------------
    // fromArray() factory
    // -------------------------------------------------------------------------

    public function testFromArrayThrowsForEmptyComponents(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CacheRebuildOptions::fromArray(['components' => []]);
    }

    public function testFromArrayThrowsForMissingComponents(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CacheRebuildOptions::fromArray([]);
    }

    public function testFromArrayParsesComponentsCorrectly(): void
    {
        $options = CacheRebuildOptions::fromArray([
            'components' => [CacheComponent::METADATA, CacheComponent::ROUTES],
        ]);
        $this->assertSame([CacheComponent::METADATA, CacheComponent::ROUTES], $options->getComponents());
    }

    public function testFromArrayDefaultsUpdateSchemaToFalse(): void
    {
        $options = CacheRebuildOptions::fromArray([
            'components' => [CacheComponent::METADATA],
        ]);
        $this->assertFalse($options->isUpdateSchema());
    }

    public function testFromArrayDefaultsUpdatePermissionsToFalse(): void
    {
        $options = CacheRebuildOptions::fromArray([
            'components' => [CacheComponent::METADATA],
        ]);
        $this->assertFalse($options->isUpdatePermissions());
    }

    public function testFromArrayDefaultsDryRunToFalse(): void
    {
        $options = CacheRebuildOptions::fromArray([
            'components' => [CacheComponent::METADATA],
        ]);
        $this->assertFalse($options->isDryRun());
    }

    public function testFromArrayParsesUpdateSchemaTrue(): void
    {
        $options = CacheRebuildOptions::fromArray([
            'components'   => [CacheComponent::METADATA],
            'updateSchema' => true,
        ]);
        $this->assertTrue($options->isUpdateSchema());
    }

    public function testFromArrayParsesUpdatePermissionsTrue(): void
    {
        $options = CacheRebuildOptions::fromArray([
            'components'        => [CacheComponent::METADATA],
            'updatePermissions' => true,
        ]);
        $this->assertTrue($options->isUpdatePermissions());
    }

    public function testFromArrayParsesDryRunTrue(): void
    {
        $options = CacheRebuildOptions::fromArray([
            'components' => [CacheComponent::METADATA],
            'dryRun'     => true,
        ]);
        $this->assertTrue($options->isDryRun());
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function testGetComponentsReturnsConstructorValue(): void
    {
        $components = [CacheComponent::ROUTES, CacheComponent::DOCS];
        $options = new CacheRebuildOptions($components);
        $this->assertSame($components, $options->getComponents());
    }

    public function testIsDryRunReturnsCorrectValue(): void
    {
        $options = new CacheRebuildOptions([CacheComponent::METADATA], false, false, true);
        $this->assertTrue($options->isDryRun());
    }

    public function testIsUpdateSchemaReturnsCorrectValue(): void
    {
        $options = new CacheRebuildOptions([CacheComponent::METADATA], true);
        $this->assertTrue($options->isUpdateSchema());
    }

    public function testIsUpdatePermissionsReturnsCorrectValue(): void
    {
        $options = new CacheRebuildOptions([CacheComponent::METADATA], false, true);
        $this->assertTrue($options->isUpdatePermissions());
    }
}
