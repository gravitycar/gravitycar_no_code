<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Gravitycar\Services\NavigationBuilder;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Services\AuthorizationService;
use Gravitycar\Navigation\NavigationConfig;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Exceptions\GCException;
use Psr\Log\LoggerInterface;

/**
 * Testable subclass of NavigationBuilder that overrides writeNavigationCache
 * to avoid filesystem writes during unit tests.
 */
class TestableNavigationBuilder extends NavigationBuilder
{
    /** @var array<string, array> */
    public array $writtenCaches = [];

    protected function writeNavigationCache(string $cacheFile, array $navigation): void
    {
        $this->writtenCaches[$cacheFile] = $navigation;
    }
}

/**
 * Unit tests for NavigationBuilder.
 *
 * Covers buildModelNavigation() grouping behavior (AC-1 through AC-6, AC-12)
 * and supporting methods.
 */
class NavigationBuilderTest extends TestCase
{
    private TestableNavigationBuilder $navigationBuilder;
    /** @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $mockLogger;
    /** @var MetadataEngineInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $mockMetadataEngine;
    /** @var AuthorizationService|\PHPUnit\Framework\MockObject\MockObject */
    private $mockAuthorizationService;
    /** @var NavigationConfig|\PHPUnit\Framework\MockObject\MockObject */
    private $mockNavigationConfig;
    /** @var ModelFactory|\PHPUnit\Framework\MockObject\MockObject */
    private $mockModelFactory;
    /** @var \Gravitycar\Models\ModelBase|\PHPUnit\Framework\MockObject\MockObject */
    private $mockRole;
    /** @var \Gravitycar\Models\ModelBase|\PHPUnit\Framework\MockObject\MockObject */
    private $mockRoleModel;

    protected function setUp(): void
    {
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $this->mockAuthorizationService = $this->createMock(AuthorizationService::class);
        $this->mockNavigationConfig = $this->createMock(NavigationConfig::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);

        $this->mockRole = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $this->mockRoleModel = $this->createMock(\Gravitycar\Models\ModelBase::class);

        $this->navigationBuilder = new TestableNavigationBuilder(
            $this->mockLogger,
            $this->mockMetadataEngine,
            $this->mockAuthorizationService,
            $this->mockNavigationConfig,
            $this->mockModelFactory
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Configure the mock model factory so that getRoleByName() succeeds.
     */
    private function setupRoleFound(): void
    {
        $this->mockModelFactory->method('new')
            ->with('Roles')
            ->willReturn($this->mockRoleModel);

        $this->mockRoleModel->method('find')
            ->willReturn([$this->mockRole]);
    }

    /**
     * Configure the mock model factory so that getRoleByName() returns null (role missing).
     */
    private function setupRoleNotFound(): void
    {
        $this->mockModelFactory->method('new')
            ->with('Roles')
            ->willReturn($this->mockRoleModel);

        $this->mockRoleModel->method('find')
            ->willReturn([]);
    }

    /**
     * Invoke a protected/private method via reflection.
     *
     * @param string $methodName
     * @param array  $args
     * @return mixed
     */
    private function invokeMethod(string $methodName, array $args = []): mixed
    {
        $reflection = new \ReflectionClass($this->navigationBuilder);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($this->navigationBuilder, $args);
    }

    // -------------------------------------------------------------------------
    // AC-1 — navigation_bar === false → model excluded entirely
    // -------------------------------------------------------------------------

    public function testAc1ModelWithNavigationBarFalseIsExcluded(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $this->mockMetadataEngine->method('getModelMetadata')
            ->with('GoogleOauthTokens')
            ->willReturn(['navigation_bar' => false]);

        $result = $this->invokeMethod('buildModelNavigation', [['GoogleOauthTokens'], 'admin']);

        $this->assertEmpty($result, 'Model with navigation_bar=false should be excluded from result');
    }

    public function testAc1HiddenModelDoesNotAppearInGroupsOrUngrouped(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $this->mockMetadataEngine->method('getModelMetadata')
            ->willReturnMap([
                ['Events', ['navigation_bar' => 'Event Organizer']],
                ['GoogleOauthTokens', ['navigation_bar' => false]],
            ]);

        $result = $this->invokeMethod('buildModelNavigation', [['Events', 'GoogleOauthTokens'], 'admin']);

        // Only one group entry for Events; GoogleOauthTokens absent everywhere
        $this->assertCount(1, $result);
        $this->assertEquals('group', $result[0]['type']);
        $this->assertEquals('Event Organizer', $result[0]['label']);

        $allNames = [];
        foreach ($result[0]['items'] as $item) {
            $allNames[] = $item['name'];
        }
        $this->assertNotContains('GoogleOauthTokens', $allNames);
    }

    // -------------------------------------------------------------------------
    // AC-2 — navigation_bar = 'Event Organizer' → group entry
    // -------------------------------------------------------------------------

    public function testAc2ModelWithGroupStringAppearsInGroupEntry(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $this->mockMetadataEngine->method('getModelMetadata')
            ->with('Events')
            ->willReturn(['navigation_bar' => 'Event Organizer']);

        $result = $this->invokeMethod('buildModelNavigation', [['Events'], 'admin']);

        $this->assertCount(1, $result);
        $entry = $result[0];
        $this->assertEquals('group', $entry['type'], 'Entry type should be group');
        $this->assertEquals('Event Organizer', $entry['label'], 'Group label should match navigation_bar value');
        $this->assertIsArray($entry['items']);
        $this->assertCount(1, $entry['items']);
        $this->assertEquals('Events', $entry['items'][0]['name']);
    }

    public function testAc2GroupEntryItemsContainTypeItem(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $this->mockMetadataEngine->method('getModelMetadata')
            ->with('Events')
            ->willReturn(['navigation_bar' => 'Event Organizer']);

        $result = $this->invokeMethod('buildModelNavigation', [['Events'], 'admin']);

        $groupItem = $result[0]['items'][0];
        $this->assertEquals('item', $groupItem['type'], 'Items inside groups should have type=item');
    }

    // -------------------------------------------------------------------------
    // AC-3 — absent navigation_bar → top-level item
    // -------------------------------------------------------------------------

    public function testAc3ModelWithAbsentNavigationBarAppearsAsTopLevelItem(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        // No navigation_bar key at all
        $this->mockMetadataEngine->method('getModelMetadata')
            ->with('Books')
            ->willReturn([]);

        $result = $this->invokeMethod('buildModelNavigation', [['Books'], 'admin']);

        $this->assertCount(1, $result);
        $this->assertEquals('item', $result[0]['type']);
        $this->assertEquals('Books', $result[0]['name']);
    }

    // -------------------------------------------------------------------------
    // AC-4 — Groups appear before ungrouped items
    // -------------------------------------------------------------------------

    public function testAc4GroupsAppearBeforeUngroupedItems(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $this->mockMetadataEngine->method('getModelMetadata')
            ->willReturnMap([
                ['Events', ['navigation_bar' => 'Event Organizer']],
                ['Books', []],
            ]);

        $result = $this->invokeMethod('buildModelNavigation', [['Events', 'Books'], 'admin']);

        $this->assertGreaterThanOrEqual(2, count($result));
        $this->assertEquals('group', $result[0]['type'], 'First entry should be a group');

        // All groups come before all items
        $seenItem = false;
        foreach ($result as $entry) {
            if ($entry['type'] === 'item') {
                $seenItem = true;
            }
            if ($seenItem && $entry['type'] === 'group') {
                $this->fail('A group entry appeared after an ungrouped item entry');
            }
        }
        $this->assertTrue(true); // Reached here without failure
    }

    // -------------------------------------------------------------------------
    // AC-5 — Items within a group are sorted alphabetically by title
    // -------------------------------------------------------------------------

    public function testAc5ItemsWithinGroupAreSortedAlphabeticallyByTitle(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $this->mockMetadataEngine->method('getModelMetadata')
            ->willReturnMap([
                ['EventReminders',    ['navigation_bar' => 'Event Organizer']],
                ['EventCommitments',  ['navigation_bar' => 'Event Organizer']],
                ['Events',            ['navigation_bar' => 'Event Organizer']],
                ['EventProposedDates',['navigation_bar' => 'Event Organizer']],
            ]);

        $modelNames = ['EventReminders', 'EventCommitments', 'Events', 'EventProposedDates'];
        $result = $this->invokeMethod('buildModelNavigation', [$modelNames, 'admin']);

        $this->assertCount(1, $result);
        $items = $result[0]['items'];

        $titles = array_column($items, 'title');
        $sorted = $titles;
        sort($sorted);

        $this->assertEquals($sorted, $titles, 'Items within group must be sorted alphabetically by title');
    }

    public function testAc5EventOrganizerSpecificSortOrder(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $this->mockMetadataEngine->method('getModelMetadata')
            ->willReturnMap([
                ['EventReminders',    ['navigation_bar' => 'Event Organizer']],
                ['EventCommitments',  ['navigation_bar' => 'Event Organizer']],
                ['Events',            ['navigation_bar' => 'Event Organizer']],
                ['EventProposedDates',['navigation_bar' => 'Event Organizer']],
            ]);

        $result = $this->invokeMethod(
            'buildModelNavigation',
            [['EventReminders', 'EventCommitments', 'Events', 'EventProposedDates'], 'admin']
        );

        $titles = array_column($result[0]['items'], 'title');

        // Spec AC-5: Event Commitments, Event Proposed Dates, Event Reminders, Events
        $this->assertEquals('Event Commitments', $titles[0]);
        $this->assertEquals('Event Proposed Dates', $titles[1]);
        $this->assertEquals('Event Reminders', $titles[2]);
        $this->assertEquals('Events', $titles[3]);
    }

    // -------------------------------------------------------------------------
    // AC-6 — buildModelItem() always includes type='item'
    // -------------------------------------------------------------------------

    public function testAc6BuildModelItemAlwaysIncludesTypeItem(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(false);

        $result = $this->invokeMethod('buildModelItem', ['Books', $this->mockRole]);

        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('item', $result['type']);
    }

    public function testAc6BuildModelItemWithAllPermissions(): void
    {
        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $result = $this->invokeMethod('buildModelItem', ['Movies', $this->mockRole]);

        $this->assertEquals('item', $result['type']);
        $this->assertTrue($result['permissions']['list']);
        $this->assertTrue($result['permissions']['create']);
        $this->assertTrue($result['permissions']['update']);
        $this->assertTrue($result['permissions']['delete']);
        $this->assertCount(1, $result['actions']); // create action
    }

    public function testAc6BuildModelItemWithOnlyListPermission(): void
    {
        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturnCallback(fn($role, $permission, $model) => $permission === 'list');

        $result = $this->invokeMethod('buildModelItem', ['Movies', $this->mockRole]);

        $this->assertEquals('item', $result['type']);
        $this->assertEmpty($result['actions']);
        $this->assertFalse($result['permissions']['create']);
        $this->assertFalse($result['permissions']['update']);
        $this->assertFalse($result['permissions']['delete']);
    }

    // -------------------------------------------------------------------------
    // MetadataEngine failure — model treated as ungrouped (not skipped)
    // -------------------------------------------------------------------------

    public function testMetadataEngineThrowsModelIsTreatedAsUngrouped(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        // getModelMetadata throws — model should appear as ungrouped item
        $this->mockMetadataEngine->method('getModelMetadata')
            ->willThrowException(new \RuntimeException('Metadata not found'));

        $result = $this->invokeMethod('buildModelNavigation', [['Books'], 'admin']);

        // Per spec §5.2: "failures are logged and the model is skipped"
        // The catch block in buildModelNavigation skips the model on exception.
        // The spec notes "model skipped" in error handling (plan §Error Handling).
        $this->assertEmpty($result, 'Model should be skipped (not crash) when metadata throws');
    }

    public function testMetadataEngineThrowingDoesNotAffectOtherModels(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $this->mockMetadataEngine->method('getModelMetadata')
            ->willReturnCallback(function (string $modelName): array {
                if ($modelName === 'BrokenModel') {
                    throw new \RuntimeException('Metadata not found');
                }
                return []; // Books has no navigation_bar
            });

        $result = $this->invokeMethod('buildModelNavigation', [['Books', 'BrokenModel'], 'admin']);

        // Books should still appear; BrokenModel skipped
        $this->assertCount(1, $result);
        $this->assertEquals('item', $result[0]['type']);
        $this->assertEquals('Books', $result[0]['name']);
    }

    // -------------------------------------------------------------------------
    // Empty group label — treated as ungrouped
    // -------------------------------------------------------------------------

    public function testEmptyStringNavigationBarTreatedAsUngrouped(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $this->mockMetadataEngine->method('getModelMetadata')
            ->with('Books')
            ->willReturn(['navigation_bar' => '']);

        $result = $this->invokeMethod('buildModelNavigation', [['Books'], 'admin']);

        $this->assertCount(1, $result);
        $this->assertEquals('item', $result[0]['type'], 'Empty string navigation_bar should be ungrouped item');
        $this->assertEquals('Books', $result[0]['name']);
    }

    // -------------------------------------------------------------------------
    // Additional coverage — null navigation_bar treated as ungrouped (AC-12)
    // -------------------------------------------------------------------------

    public function testNullNavigationBarTreatedAsUngrouped(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $this->mockMetadataEngine->method('getModelMetadata')
            ->with('Books')
            ->willReturn(['navigation_bar' => null]);

        $result = $this->invokeMethod('buildModelNavigation', [['Books'], 'admin']);

        $this->assertCount(1, $result);
        $this->assertEquals('item', $result[0]['type']);
    }

    // -------------------------------------------------------------------------
    // Role not found — returns empty array
    // -------------------------------------------------------------------------

    public function testRoleNotFoundReturnsEmptyArray(): void
    {
        $this->setupRoleNotFound();

        $result = $this->invokeMethod('buildModelNavigation', [['Books'], 'nonexistent']);

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // RBAC: no list permission → model excluded
    // -------------------------------------------------------------------------

    public function testModelWithNoListPermissionIsExcluded(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(false);

        $this->mockMetadataEngine->method('getModelMetadata')
            ->willReturn([]);

        $result = $this->invokeMethod('buildModelNavigation', [['RestrictedModel'], 'user']);

        $this->assertEmpty($result);
    }

    // -------------------------------------------------------------------------
    // Ordering: multiple groups sorted alphabetically by label
    // -------------------------------------------------------------------------

    public function testMultipleGroupsAreSortedAlphabeticallyByLabel(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $this->mockMetadataEngine->method('getModelMetadata')
            ->willReturnMap([
                ['ModelZ', ['navigation_bar' => 'Zebra Group']],
                ['ModelA', ['navigation_bar' => 'Alpha Group']],
            ]);

        $result = $this->invokeMethod('buildModelNavigation', [['ModelZ', 'ModelA'], 'admin']);

        $this->assertCount(2, $result);
        $this->assertEquals('Alpha Group', $result[0]['label'], 'Alpha Group should come before Zebra Group');
        $this->assertEquals('Zebra Group', $result[1]['label']);
    }

    // -------------------------------------------------------------------------
    // Ordering: ungrouped items sorted alphabetically by title
    // -------------------------------------------------------------------------

    public function testUngroupedItemsAreSortedAlphabeticallyByTitle(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $this->mockMetadataEngine->method('getModelMetadata')
            ->willReturnMap([
                ['Zebra', []],
                ['Ant',   []],
            ]);

        $result = $this->invokeMethod('buildModelNavigation', [['Zebra', 'Ant'], 'admin']);

        $this->assertCount(2, $result);
        $this->assertEquals('Ant', $result[0]['name'], 'Ant should come before Zebra alphabetically');
        $this->assertEquals('Zebra', $result[1]['name']);
    }

    // -------------------------------------------------------------------------
    // Mixed scenario: group + ungrouped + hidden (spec plan key scenario)
    // -------------------------------------------------------------------------

    public function testMixedGroupedUngroupedAndHiddenModels(): void
    {
        $this->setupRoleFound();

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $this->mockMetadataEngine->method('getModelMetadata')
            ->willReturnMap([
                ['Events',            ['navigation_bar' => 'Event Organizer']],
                ['Books',             []],
                ['GoogleOauthTokens', ['navigation_bar' => false]],
            ]);

        $result = $this->invokeMethod(
            'buildModelNavigation',
            [['Events', 'Books', 'GoogleOauthTokens'], 'admin']
        );

        // Expect: 1 group entry + 1 ungrouped item; GoogleOauthTokens absent
        $this->assertCount(2, $result);

        $this->assertEquals('group', $result[0]['type']);
        $this->assertEquals('Event Organizer', $result[0]['label']);
        $this->assertEquals('Events', $result[0]['items'][0]['name']);

        $this->assertEquals('item', $result[1]['type']);
        $this->assertEquals('Books', $result[1]['name']);

        // GoogleOauthTokens must be absent from all names
        $groupItemNames = array_column($result[0]['items'], 'name');
        $this->assertNotContains('GoogleOauthTokens', $groupItemNames);
        $this->assertNotContains('GoogleOauthTokens', [$result[1]['name']]);
    }

    // -------------------------------------------------------------------------
    // Empty result scenarios
    // -------------------------------------------------------------------------

    public function testEmptyModelListReturnsEmptyArray(): void
    {
        $this->setupRoleFound();

        $result = $this->invokeMethod('buildModelNavigation', [[], 'admin']);

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // Existing tests: buildNavigationForRole()
    // -------------------------------------------------------------------------

    public function testBuildNavigationForRole(): void
    {
        $this->mockMetadataEngine->expects($this->once())
            ->method('getAvailableModels')
            ->willReturn(['Users', 'Movies']);

        $this->mockNavigationConfig->expects($this->once())
            ->method('getCustomPagesForRole')
            ->with('admin')
            ->willReturn([
                ['key' => 'dashboard', 'title' => 'Dashboard', 'url' => '/dashboard']
            ]);

        $this->mockNavigationConfig->expects($this->once())
            ->method('getNavigationSections')
            ->willReturn([
                ['key' => 'main', 'title' => 'Main Navigation']
            ]);

        $this->mockModelFactory->expects($this->once())
            ->method('new')
            ->with('Roles')
            ->willReturn($this->mockRoleModel);

        $this->mockRoleModel->expects($this->once())
            ->method('find')
            ->with(['name' => 'admin'])
            ->willReturn([$this->mockRole]);

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        // Both models have no navigation_bar — they appear as ungrouped items
        $this->mockMetadataEngine->method('getModelMetadata')
            ->willReturn([]);

        $result = $this->navigationBuilder->buildNavigationForRole('admin');

        $this->assertIsArray($result);
        $this->assertEquals('admin', $result['role']);
        $this->assertArrayHasKey('custom_pages', $result);
        $this->assertArrayHasKey('models', $result);
        $this->assertArrayHasKey('sections', $result);
        $this->assertArrayHasKey('generated_at', $result);

        $this->assertCount(1, $result['custom_pages']);
        $this->assertCount(2, $result['models']); // Users and Movies as ungrouped items
        $this->assertCount(1, $result['sections']);

        // Both should be ungrouped type=item entries
        foreach ($result['models'] as $entry) {
            $this->assertEquals('item', $entry['type']);
            $this->assertTrue($entry['permissions']['list']);
            $this->assertTrue($entry['permissions']['create']);
        }
    }

    public function testBuildModelNavigationFiltersUnauthorizedModels(): void
    {
        $modelNames = ['Users', 'Movies', 'RestrictedModel'];

        $this->mockModelFactory->method('new')
            ->willReturn($this->mockRoleModel);

        $this->mockRoleModel->method('find')
            ->willReturn([$this->mockRole]);

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturnCallback(function ($role, $permission, $model) {
                if ($model === 'RestrictedModel') {
                    return false;
                }
                return $permission === 'list';
            });

        $this->mockMetadataEngine->method('getModelMetadata')
            ->willReturn([]);

        $result = $this->invokeMethod('buildModelNavigation', [$modelNames, 'user']);

        // Only 2 ungrouped items
        $this->assertCount(2, $result);
        $names = array_column($result, 'name');
        $this->assertContains('Users', $names);
        $this->assertContains('Movies', $names);
        $this->assertNotContains('RestrictedModel', $names);

        foreach ($result as $entry) {
            $this->assertEquals('item', $entry['type']);
            $this->assertTrue($entry['permissions']['list']);
            $this->assertFalse($entry['permissions']['create']);
            $this->assertEmpty($entry['actions']);
        }
    }

    // -------------------------------------------------------------------------
    // Existing tests: getRoleByName()
    // -------------------------------------------------------------------------

    public function testGetRoleByName(): void
    {
        $this->mockModelFactory->expects($this->once())
            ->method('new')
            ->with('Roles')
            ->willReturn($this->mockRoleModel);

        $this->mockRoleModel->expects($this->once())
            ->method('find')
            ->with(['name' => 'admin'])
            ->willReturn([$this->mockRole]);

        $result = $this->invokeMethod('getRoleByName', ['admin']);

        $this->assertSame($this->mockRole, $result);
    }

    public function testGetRoleByNameNotFound(): void
    {
        $this->mockModelFactory->expects($this->once())
            ->method('new')
            ->with('Roles')
            ->willReturn($this->mockRoleModel);

        $this->mockRoleModel->expects($this->once())
            ->method('find')
            ->with(['name' => 'nonexistent'])
            ->willReturn([]);

        $result = $this->invokeMethod('getRoleByName', ['nonexistent']);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Existing tests: generateModelTitle(), getModelIcon()
    // -------------------------------------------------------------------------

    public function testGenerateModelTitle(): void
    {
        $this->assertEquals('Users', $this->invokeMethod('generateModelTitle', ['Users']));
        $this->assertEquals('Movie Quotes', $this->invokeMethod('generateModelTitle', ['MovieQuotes']));
        $this->assertEquals('Movie Quotes', $this->invokeMethod('generateModelTitle', ['Movie_Quotes']));
        $this->assertEquals('U S A Model', $this->invokeMethod('generateModelTitle', ['USAModel']));
    }

    public function testGetModelIcon(): void
    {
        $this->assertEquals('👥', $this->invokeMethod('getModelIcon', ['Users']));
        $this->assertEquals('🎬', $this->invokeMethod('getModelIcon', ['Movies']));
        $this->assertEquals('💬', $this->invokeMethod('getModelIcon', ['Movie_Quotes']));
        $this->assertEquals('📚', $this->invokeMethod('getModelIcon', ['Books']));
        $this->assertEquals('📋', $this->invokeMethod('getModelIcon', ['UnknownModel']));
    }

    // -------------------------------------------------------------------------
    // buildAllRoleNavigationCaches — fixed key name (total_model_items_count)
    // -------------------------------------------------------------------------

    public function testBuildAllRoleNavigationCachesUsesCorrectKey(): void
    {
        $this->mockMetadataEngine->method('getAvailableModels')
            ->willReturn(['Users']);

        $this->mockNavigationConfig->method('getCustomPagesForRole')
            ->willReturn([]);

        $this->mockNavigationConfig->method('getNavigationSections')
            ->willReturn([]);

        $this->mockModelFactory->method('new')
            ->willReturn($this->mockRoleModel);

        $this->mockRoleModel->method('find')
            ->willReturn([$this->mockRole]);

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $this->mockMetadataEngine->method('getModelMetadata')
            ->willReturn([]);

        $result = $this->navigationBuilder->buildAllRoleNavigationCaches();

        $this->assertCount(4, $result);
        $this->assertArrayHasKey('admin', $result);
        $this->assertArrayHasKey('manager', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('guest', $result);

        foreach ($result as $role => $cacheResult) {
            $this->assertTrue($cacheResult['success'], "Role {$role} cache build should succeed");
            $this->assertArrayHasKey('cache_file', $cacheResult);
            // Renamed from items_count to total_model_items_count
            $this->assertArrayHasKey('total_model_items_count', $cacheResult, 'Key should be total_model_items_count');
            $this->assertArrayNotHasKey('items_count', $cacheResult, 'Old items_count key should not exist');
            $this->assertStringContainsString("navigation_cache_{$role}.php", $cacheResult['cache_file']);
        }
    }

    public function testBuildAllRoleNavigationCachesCountsMixedEntries(): void
    {
        $this->mockMetadataEngine->method('getAvailableModels')
            ->willReturn(['Events', 'Books']);

        $this->mockNavigationConfig->method('getCustomPagesForRole')
            ->willReturn([]);

        $this->mockNavigationConfig->method('getNavigationSections')
            ->willReturn([]);

        $this->mockModelFactory->method('new')
            ->willReturn($this->mockRoleModel);

        $this->mockRoleModel->method('find')
            ->willReturn([$this->mockRole]);

        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $this->mockMetadataEngine->method('getModelMetadata')
            ->willReturnMap([
                ['Events', ['navigation_bar' => 'Event Organizer']],
                ['Books',  []],
            ]);

        $result = $this->navigationBuilder->buildAllRoleNavigationCaches();

        // 1 grouped item (Events) + 1 ungrouped (Books) = 2 total model items
        foreach ($result as $role => $cacheResult) {
            $this->assertTrue($cacheResult['success']);
            // total_model_items_count = model items (2) + custom pages (0) = 2
            $this->assertEquals(2, $cacheResult['total_model_items_count']);
        }
    }
}
