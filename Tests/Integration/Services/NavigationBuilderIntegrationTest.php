<?php

namespace Tests\Integration\Services;

use PHPUnit\Framework\TestCase;
use Gravitycar\Services\NavigationBuilder;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Services\AuthorizationService;
use Gravitycar\Navigation\NavigationConfig;
use Gravitycar\Factories\ModelFactory;
use Psr\Log\LoggerInterface;

/**
 * Stub MetadataEngine that loads real navigation_bar values directly from
 * the on-disk metadata PHP files, bypassing the stale metadata cache.
 * This lets integration tests verify the actual metadata files without needing
 * a rebuilt disk cache or a real database.
 */
class RealMetadataStub implements MetadataEngineInterface
{
    private const PROJECT_ROOT = __DIR__ . '/../../../';

    /** @var array<string, array> */
    private array $modelData = [];

    public function __construct()
    {
        $this->loadModelsFromDisk();
    }

    /**
     * Walk src/Models, find every *_metadata.php and load it.
     * This mirrors exactly what MetadataEngine::scanAndLoadMetadata() does
     * but stores only the raw metadata (no core-field merging needed for navigation tests).
     */
    private function loadModelsFromDisk(): void
    {
        $modelsDir = self::PROJECT_ROOT . 'src/Models';

        if (!is_dir($modelsDir)) {
            return;
        }

        foreach (scandir($modelsDir) as $subDir) {
            if ($subDir === '.' || $subDir === '..') {
                continue;
            }

            $subDirPath = $modelsDir . DIRECTORY_SEPARATOR . $subDir;
            if (!is_dir($subDirPath)) {
                continue;
            }

            foreach (scandir($subDirPath) as $file) {
                if (!preg_match('/^.*_metadata\.php$/', $file)) {
                    continue;
                }

                $filePath = $subDirPath . DIRECTORY_SEPARATOR . $file;
                $data = include $filePath;

                if (is_array($data) && isset($data['name'])) {
                    $this->modelData[$data['name']] = $data;
                }
            }
        }
    }

    public function getModelMetadata(string $modelName): array
    {
        if (!isset($this->modelData[$modelName])) {
            throw new \RuntimeException("Model '{$modelName}' not found in real metadata stub.");
        }

        return $this->modelData[$modelName];
    }

    public function getAvailableModels(): array
    {
        return array_keys($this->modelData);
    }

    public function modelExists(string $modelName): bool
    {
        return isset($this->modelData[$modelName]);
    }

    // ---- Remaining interface methods — unused for navigation integration tests ----

    public function getAllMetadata(): array
    {
        return ['models' => $this->modelData, 'relationships' => []];
    }

    public function isLoaded(): bool
    {
        return !empty($this->modelData);
    }

    public function reloadMetadata(): void
    {
        $this->modelData = [];
        $this->loadModelsFromDisk();
    }

    public function getRelationshipMetadata(string $relationshipName): array
    {
        return [];
    }

    public function buildRelationshipMetadataPath(string $relationshipName): string
    {
        return '';
    }

    public function resolveModelName(string $className): string
    {
        return $className;
    }

    public function buildModelMetadataPath(string $modelName): string
    {
        return '';
    }

    public function getCachedMetadata(): array
    {
        return $this->getAllMetadata();
    }

    public function getFieldTypeDefinitions(): array
    {
        return [];
    }

    public function getValidationRuleDefinitions(): array
    {
        return [];
    }
}

/**
 * Subclass of NavigationBuilder that prevents filesystem writes during tests.
 */
class TestableNavigationBuilderIntegration extends NavigationBuilder
{
    /** @var array<string, array> */
    public array $writtenCaches = [];

    protected function writeNavigationCache(string $cacheFile, array $navigation): void
    {
        $this->writtenCaches[$cacheFile] = $navigation;
    }
}

/**
 * Integration tests for NavigationBuilder against REAL metadata files.
 *
 * These tests verify AC-1 through AC-5 end-to-end by using actual on-disk metadata files
 * (via RealMetadataStub) while still mocking AuthorizationService and ModelFactory
 * to avoid needing a real database.
 *
 * Key assertions per acceptance criterion:
 *   AC-1  — GoogleOauthTokens and JwtRefreshTokens never appear anywhere in the output
 *   AC-2  — Events, EventCommitments, EventReminders, EventProposedDates appear inside
 *            a single 'Event Organizer' group
 *   Cache — Every top-level entry has type 'group' or type 'item'; no other shapes present
 *   Group structure — Each group has type/label/items; each item inside has type 'item'
 *   AC-5  — Event Organizer group items are alphabetically sorted by title
 */
class NavigationBuilderIntegrationTest extends TestCase
{
    /** @var TestableNavigationBuilderIntegration */
    private TestableNavigationBuilderIntegration $navigationBuilder;

    /** @var RealMetadataStub */
    private RealMetadataStub $realMetadataEngine;

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
        $mockLogger = $this->createMock(LoggerInterface::class);
        $this->realMetadataEngine = new RealMetadataStub();
        $this->mockAuthorizationService = $this->createMock(AuthorizationService::class);
        $this->mockNavigationConfig = $this->createMock(NavigationConfig::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);
        $this->mockRole = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $this->mockRoleModel = $this->createMock(\Gravitycar\Models\ModelBase::class);

        $this->navigationBuilder = new TestableNavigationBuilderIntegration(
            $mockLogger,
            $this->realMetadataEngine,
            $this->mockAuthorizationService,
            $this->mockNavigationConfig,
            $this->mockModelFactory
        );

        // Configure role lookup to succeed for all tests
        $this->mockModelFactory->method('new')
            ->with('Roles')
            ->willReturn($this->mockRoleModel);

        $this->mockRoleModel->method('find')
            ->willReturn([$this->mockRole]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Grant list permission for all models, deny everything else.
     */
    private function grantListPermissionOnly(): void
    {
        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturnCallback(
                fn($role, string $action, string $model): bool => $action === 'list'
            );
    }

    /**
     * Grant all permissions for all models (admin scenario).
     */
    private function grantAllPermissions(): void
    {
        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);
    }

    /**
     * Invoke buildModelNavigation() via reflection (it is protected).
     */
    private function buildModelNavigation(array $modelNames, string $role = 'admin'): array
    {
        $reflection = new \ReflectionClass($this->navigationBuilder);
        $method = $reflection->getMethod('buildModelNavigation');
        $method->setAccessible(true);

        return $method->invokeArgs($this->navigationBuilder, [$modelNames, $role]);
    }

    /**
     * Recursively collect all model names from a navigation result array.
     */
    private function collectAllModelNames(array $navResult): array
    {
        $names = [];
        foreach ($navResult as $entry) {
            if ($entry['type'] === 'item') {
                $names[] = $entry['name'];
            } elseif ($entry['type'] === 'group') {
                foreach ($entry['items'] as $item) {
                    $names[] = $item['name'];
                }
            }
        }

        return $names;
    }

    /**
     * Return the Event Organizer group entry from a navigation result, or null if absent.
     */
    private function findEventOrganizerGroup(array $navResult): ?array
    {
        foreach ($navResult as $entry) {
            if ($entry['type'] === 'group' && $entry['label'] === 'Event Organizer') {
                return $entry;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Prerequisite: verify RealMetadataStub loaded the real metadata files
    // -------------------------------------------------------------------------

    public function testRealMetadataStubLoadsNavigationBarFalseForGoogleOauthTokens(): void
    {
        $metadata = $this->realMetadataEngine->getModelMetadata('GoogleOauthTokens');

        $this->assertArrayHasKey('navigation_bar', $metadata, 'GoogleOauthTokens metadata must have navigation_bar key');
        $this->assertFalse($metadata['navigation_bar'], 'GoogleOauthTokens navigation_bar must be false');
    }

    public function testRealMetadataStubLoadsNavigationBarFalseForJwtRefreshTokens(): void
    {
        $metadata = $this->realMetadataEngine->getModelMetadata('JwtRefreshTokens');

        $this->assertArrayHasKey('navigation_bar', $metadata, 'JwtRefreshTokens metadata must have navigation_bar key');
        $this->assertFalse($metadata['navigation_bar'], 'JwtRefreshTokens navigation_bar must be false');
    }

    public function testRealMetadataStubLoadsEventOrganizerGroupForEventsModel(): void
    {
        $metadata = $this->realMetadataEngine->getModelMetadata('Events');

        $this->assertArrayHasKey('navigation_bar', $metadata, 'Events metadata must have navigation_bar key');
        $this->assertEquals('Event Organizer', $metadata['navigation_bar']);
    }

    public function testRealMetadataStubLoadsEventOrganizerGroupForAllFourEventModels(): void
    {
        $eventModels = ['Events', 'EventCommitments', 'EventReminders', 'EventProposedDates'];

        foreach ($eventModels as $modelName) {
            $metadata = $this->realMetadataEngine->getModelMetadata($modelName);
            $this->assertEquals(
                'Event Organizer',
                $metadata['navigation_bar'],
                "Model {$modelName} must have navigation_bar = 'Event Organizer'"
            );
        }
    }

    // -------------------------------------------------------------------------
    // AC-1 end-to-end — Hidden models must not appear anywhere in the output
    // -------------------------------------------------------------------------

    public function testAc1GoogleOauthTokensAbsentFromFullBuildOutput(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        $allNames = $this->collectAllModelNames($result);
        $this->assertNotContains(
            'GoogleOauthTokens',
            $allNames,
            'GoogleOauthTokens must not appear anywhere in the navigation output (navigation_bar=false)'
        );
    }

    public function testAc1JwtRefreshTokensAbsentFromFullBuildOutput(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        $allNames = $this->collectAllModelNames($result);
        $this->assertNotContains(
            'JwtRefreshTokens',
            $allNames,
            'JwtRefreshTokens must not appear anywhere in the navigation output (navigation_bar=false)'
        );
    }

    public function testAc1BothHiddenModelsAbsentFromGroupsAndUngroupedItems(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        // Verify not in ungrouped items
        $ungroupedNames = [];
        $groupItemNames = [];

        foreach ($result as $entry) {
            if ($entry['type'] === 'item') {
                $ungroupedNames[] = $entry['name'];
            } elseif ($entry['type'] === 'group') {
                foreach ($entry['items'] as $item) {
                    $groupItemNames[] = $item['name'];
                }
            }
        }

        $this->assertNotContains('GoogleOauthTokens', $ungroupedNames);
        $this->assertNotContains('GoogleOauthTokens', $groupItemNames);
        $this->assertNotContains('JwtRefreshTokens', $ungroupedNames);
        $this->assertNotContains('JwtRefreshTokens', $groupItemNames);
    }

    // -------------------------------------------------------------------------
    // AC-2 end-to-end — Event Organizer group contains all four event models
    // -------------------------------------------------------------------------

    public function testAc2EventOrganizerGroupExistsInOutput(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        $group = $this->findEventOrganizerGroup($result);
        $this->assertNotNull($group, "Expected exactly one 'Event Organizer' group entry in the navigation output");
    }

    public function testAc2EventsInsideEventOrganizerGroup(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        $group = $this->findEventOrganizerGroup($result);
        $this->assertNotNull($group, "Event Organizer group must be present");

        $groupModelNames = array_column($group['items'], 'name');
        $this->assertContains('Events', $groupModelNames, 'Events must be inside Event Organizer group');
    }

    public function testAc2EventCommitmentsInsideEventOrganizerGroup(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        $group = $this->findEventOrganizerGroup($result);
        $this->assertNotNull($group, "Event Organizer group must be present");

        $groupModelNames = array_column($group['items'], 'name');
        $this->assertContains('EventCommitments', $groupModelNames, 'EventCommitments must be inside Event Organizer group');
    }

    public function testAc2EventRemindersInsideEventOrganizerGroup(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        $group = $this->findEventOrganizerGroup($result);
        $this->assertNotNull($group, "Event Organizer group must be present");

        $groupModelNames = array_column($group['items'], 'name');
        $this->assertContains('EventReminders', $groupModelNames, 'EventReminders must be inside Event Organizer group');
    }

    public function testAc2EventProposedDatesInsideEventOrganizerGroup(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        $group = $this->findEventOrganizerGroup($result);
        $this->assertNotNull($group, "Event Organizer group must be present");

        $groupModelNames = array_column($group['items'], 'name');
        $this->assertContains('EventProposedDates', $groupModelNames, 'EventProposedDates must be inside Event Organizer group');
    }

    public function testAc2EventOrganizerGroupContainsExactlyOnceInOutput(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        $groupCount = 0;
        foreach ($result as $entry) {
            if ($entry['type'] === 'group' && $entry['label'] === 'Event Organizer') {
                $groupCount++;
            }
        }

        $this->assertEquals(1, $groupCount, 'There must be exactly one Event Organizer group in the output');
    }

    public function testAc2AllFourEventModelsCollectedIntoSingleGroup(): void
    {
        $this->grantAllPermissions();

        $eventModelNames = ['Events', 'EventCommitments', 'EventReminders', 'EventProposedDates'];
        $result = $this->buildModelNavigation($eventModelNames, 'admin');

        $this->assertCount(1, $result, 'All four event models should collapse into a single group entry');

        $group = $result[0];
        $this->assertEquals('group', $group['type']);
        $this->assertEquals('Event Organizer', $group['label']);

        $itemNames = array_column($group['items'], 'name');
        foreach ($eventModelNames as $modelName) {
            $this->assertContains($modelName, $itemNames, "{$modelName} must be in the Event Organizer group");
        }
    }

    // -------------------------------------------------------------------------
    // Cache format correctness — every top-level entry has type 'group' or 'item'
    // -------------------------------------------------------------------------

    public function testCacheFormatEveryTopLevelEntryHasTypeField(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        $this->assertNotEmpty($result, 'Navigation result must not be empty for admin with all permissions');

        foreach ($result as $index => $entry) {
            $this->assertArrayHasKey('type', $entry, "Entry at index {$index} must have a 'type' field");
        }
    }

    public function testCacheFormatTopLevelEntriesAreOnlyGroupOrItem(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        foreach ($result as $index => $entry) {
            $this->assertContains(
                $entry['type'],
                ['group', 'item'],
                "Entry at index {$index} has unexpected type '{$entry['type']}'; only 'group' or 'item' are valid"
            );
        }
    }

    public function testCacheFormatNoFlatEntriesWithoutTypeField(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        foreach ($result as $index => $entry) {
            $this->assertIsString($entry['type'] ?? null, "Entry at index {$index} must have a string 'type' field");
        }
    }

    // -------------------------------------------------------------------------
    // Group structure — each group has type/label/items; each item has type 'item'
    // -------------------------------------------------------------------------

    public function testGroupStructureHasRequiredKeys(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        foreach ($result as $index => $entry) {
            if ($entry['type'] !== 'group') {
                continue;
            }

            $this->assertArrayHasKey('type', $entry, "Group at index {$index} must have 'type' key");
            $this->assertArrayHasKey('label', $entry, "Group at index {$index} must have 'label' key");
            $this->assertArrayHasKey('items', $entry, "Group at index {$index} must have 'items' key");
            $this->assertIsString($entry['label'], "Group label must be a string");
            $this->assertIsArray($entry['items'], "Group items must be an array");
        }
    }

    public function testGroupItemsAllHaveTypeItem(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        foreach ($result as $entry) {
            if ($entry['type'] !== 'group') {
                continue;
            }

            foreach ($entry['items'] as $itemIndex => $item) {
                $this->assertArrayHasKey('type', $item, "Item at index {$itemIndex} inside group '{$entry['label']}' must have 'type' key");
                $this->assertEquals(
                    'item',
                    $item['type'],
                    "Item at index {$itemIndex} inside group '{$entry['label']}' must have type='item'"
                );
            }
        }
    }

    public function testGroupItemsHaveRequiredNavigationKeys(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        foreach ($result as $entry) {
            if ($entry['type'] !== 'group') {
                continue;
            }

            foreach ($entry['items'] as $item) {
                $this->assertArrayHasKey('name', $item, "Group item must have 'name' key");
                $this->assertArrayHasKey('title', $item, "Group item must have 'title' key");
                $this->assertArrayHasKey('url', $item, "Group item must have 'url' key");
                $this->assertArrayHasKey('permissions', $item, "Group item must have 'permissions' key");
            }
        }
    }

    public function testUngroupedItemsHaveRequiredNavigationKeys(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        foreach ($result as $entry) {
            if ($entry['type'] !== 'item') {
                continue;
            }

            $this->assertArrayHasKey('name', $entry);
            $this->assertArrayHasKey('title', $entry);
            $this->assertArrayHasKey('url', $entry);
            $this->assertArrayHasKey('permissions', $entry);
        }
    }

    // -------------------------------------------------------------------------
    // AC-5 — Event Organizer group items sorted alphabetically by title
    // -------------------------------------------------------------------------

    public function testAc5EventOrganizerGroupItemsSortedAlphabeticallyByTitle(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        $group = $this->findEventOrganizerGroup($result);
        $this->assertNotNull($group, "Event Organizer group must be present");

        $titles = array_column($group['items'], 'title');
        $sortedTitles = $titles;
        sort($sortedTitles);

        $this->assertEquals(
            $sortedTitles,
            $titles,
            'Items inside Event Organizer group must be sorted alphabetically by title. ' .
            'Actual order: ' . implode(', ', $titles)
        );
    }

    public function testAc5EventOrganizerSpecificExpectedOrder(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        $group = $this->findEventOrganizerGroup($result);
        $this->assertNotNull($group, "Event Organizer group must be present");

        $titles = array_column($group['items'], 'title');

        // Per AC-5: Event Commitments, Event Proposed Dates, Event Reminders, Events
        $this->assertContains('Event Commitments', $titles, 'Event Commitments must be in the group');
        $this->assertContains('Event Proposed Dates', $titles, 'Event Proposed Dates must be in the group');
        $this->assertContains('Event Reminders', $titles, 'Event Reminders must be in the group');
        $this->assertContains('Events', $titles, 'Events must be in the group');

        // Verify actual alphabetical positions
        $commitmentIndex    = array_search('Event Commitments', $titles);
        $proposedDatesIndex = array_search('Event Proposed Dates', $titles);
        $remindersIndex     = array_search('Event Reminders', $titles);
        $eventsIndex        = array_search('Events', $titles);

        $this->assertLessThan($proposedDatesIndex, $commitmentIndex,
            '"Event Commitments" must come before "Event Proposed Dates"');
        $this->assertLessThan($remindersIndex, $proposedDatesIndex,
            '"Event Proposed Dates" must come before "Event Reminders"');
        $this->assertLessThan($eventsIndex, $remindersIndex,
            '"Event Reminders" must come before "Events"');
    }

    // -------------------------------------------------------------------------
    // Groups before ungrouped items (AC-4 end-to-end with real metadata)
    // -------------------------------------------------------------------------

    public function testGroupsAppearBeforeUngroupedItemsInFullBuild(): void
    {
        $this->grantAllPermissions();

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        $seenUngroupedItem = false;
        foreach ($result as $index => $entry) {
            if ($entry['type'] === 'item') {
                $seenUngroupedItem = true;
            }

            if ($seenUngroupedItem && $entry['type'] === 'group') {
                $this->fail(
                    "Group entry '{$entry['label']}' at index {$index} appeared after an ungrouped item. " .
                    "All groups must come before all ungrouped items."
                );
            }
        }

        $this->assertTrue(true, 'All groups appeared before ungrouped items');
    }

    // -------------------------------------------------------------------------
    // RBAC interaction — models without list permission excluded even in real-metadata scenario
    // -------------------------------------------------------------------------

    public function testModelsWithoutListPermissionAreExcludedEvenWithRealMetadata(): void
    {
        // Only grant 'list' permission for Events; deny for all others
        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturnCallback(
                fn($role, string $action, string $model): bool =>
                    $model === 'Events' && $action === 'list'
            );

        $allModels = $this->realMetadataEngine->getAvailableModels();
        $result = $this->buildModelNavigation($allModels, 'admin');

        // Only Events should appear — inside the Event Organizer group
        $allNames = $this->collectAllModelNames($result);
        $this->assertCount(1, $allNames, 'Only Events should appear when only Events has list permission');
        $this->assertContains('Events', $allNames);

        // It should be inside a group, not ungrouped
        $this->assertCount(1, $result);
        $this->assertEquals('group', $result[0]['type']);
        $this->assertEquals('Event Organizer', $result[0]['label']);
    }

    // -------------------------------------------------------------------------
    // buildAllRoleNavigationCaches() — end-to-end with real metadata
    // -------------------------------------------------------------------------

    public function testBuildAllRoleNavigationCachesWithRealMetadataProducesNoHiddenModels(): void
    {
        $this->grantAllPermissions();

        $this->mockNavigationConfig->method('getCustomPagesForRole')
            ->willReturn([]);

        $this->mockNavigationConfig->method('getNavigationSections')
            ->willReturn([]);

        $this->mockMetadataEngineForAvailableModels();

        $result = $this->navigationBuilder->buildAllRoleNavigationCaches();

        foreach (['admin', 'manager', 'user', 'guest'] as $role) {
            $this->assertArrayHasKey($role, $result);
            $this->assertTrue($result[$role]['success'], "Cache build must succeed for role: {$role}");

            $navigation = $this->navigationBuilder->writtenCaches["cache/navigation_cache_{$role}.php"];
            $allNames = $this->collectAllModelNames($navigation['models']);

            $this->assertNotContains('GoogleOauthTokens', $allNames,
                "GoogleOauthTokens must not appear in {$role} cache");
            $this->assertNotContains('JwtRefreshTokens', $allNames,
                "JwtRefreshTokens must not appear in {$role} cache");
        }
    }

    public function testBuildAllRoleNavigationCachesResultUsesCorrectCountKey(): void
    {
        $this->grantAllPermissions();

        $this->mockNavigationConfig->method('getCustomPagesForRole')
            ->willReturn([]);

        $this->mockNavigationConfig->method('getNavigationSections')
            ->willReturn([]);

        $this->mockMetadataEngineForAvailableModels();

        $result = $this->navigationBuilder->buildAllRoleNavigationCaches();

        foreach (['admin', 'manager', 'user', 'guest'] as $role) {
            $this->assertArrayHasKey('total_model_items_count', $result[$role],
                "Result for {$role} must use key 'total_model_items_count'");
            $this->assertArrayNotHasKey('items_count', $result[$role],
                "Old key 'items_count' must not exist in result for {$role}");
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers for buildAllRoleNavigationCaches tests
    // -------------------------------------------------------------------------

    /**
     * Configure the real MetadataEngine stub to also expose getAvailableModels via the
     * MetadataEngine method (NavigationBuilder calls $this->metadataEngine->getAvailableModels()).
     * Since RealMetadataStub already implements this, no extra setup is needed here —
     * but NavigationBuilder's buildNavigationForRole() calls metadataEngine->getAvailableModels()
     * so we verify that the stub correctly returns all model names from disk.
     */
    private function mockMetadataEngineForAvailableModels(): void
    {
        // RealMetadataStub::getAvailableModels() already returns real model names from disk.
        // No additional setup needed — just a self-documenting no-op.
    }
}
