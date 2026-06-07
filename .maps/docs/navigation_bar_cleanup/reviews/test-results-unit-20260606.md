# Test Results: Unit - 2026-06-06

## Summary
- Total tests: 29
- Passed: 29
- Failed: 0
- Skipped: 0

## Test File
`Tests/Unit/Services/NavigationBuilderTest.php`

## Passed Tests

### New tests added for AC coverage
- `testAc1ModelWithNavigationBarFalseIsExcluded` — AC-1: navigation_bar=false model excluded
- `testAc1HiddenModelDoesNotAppearInGroupsOrUngrouped` — AC-1: hidden model absent from all output
- `testAc2ModelWithGroupStringAppearsInGroupEntry` — AC-2: group entry created with correct label
- `testAc2GroupEntryItemsContainTypeItem` — AC-2: items inside groups have type=item
- `testAc3ModelWithAbsentNavigationBarAppearsAsTopLevelItem` — AC-3: absent property → ungrouped
- `testAc4GroupsAppearBeforeUngroupedItems` — AC-4: groups before ungrouped items
- `testAc5ItemsWithinGroupAreSortedAlphabeticallyByTitle` — AC-5: alphabetical sort within group
- `testAc5EventOrganizerSpecificSortOrder` — AC-5: exact expected order for Event Organizer group
- `testAc6BuildModelItemAlwaysIncludesTypeItem` — AC-6: type=item always present
- `testAc6BuildModelItemWithAllPermissions` — AC-6: all permissions and create action
- `testAc6BuildModelItemWithOnlyListPermission` — AC-6: restricted permissions
- `testMetadataEngineThrowsModelIsTreatedAsUngrouped` — metadata failure: model skipped
- `testMetadataEngineThrowingDoesNotAffectOtherModels` — partial metadata failure: other models unaffected
- `testEmptyStringNavigationBarTreatedAsUngrouped` — empty string → ungrouped
- `testNullNavigationBarTreatedAsUngrouped` — null → ungrouped (AC-12)
- `testRoleNotFoundReturnsEmptyArray` — role not found → []
- `testModelWithNoListPermissionIsExcluded` — RBAC: no list → excluded
- `testMultipleGroupsAreSortedAlphabeticallyByLabel` — groups sorted by label
- `testUngroupedItemsAreSortedAlphabeticallyByTitle` — ungrouped sorted by title
- `testMixedGroupedUngroupedAndHiddenModels` — combined scenario from spec plan
- `testEmptyModelListReturnsEmptyArray` — empty input → empty output

### Updated/retained existing tests
- `testBuildNavigationForRole` — updated to mock getModelMetadata, adjusted assertions for new structure
- `testBuildModelNavigationFiltersUnauthorizedModels` — updated to mock getModelMetadata
- `testGetRoleByName` — unchanged
- `testGetRoleByNameNotFound` — unchanged
- `testGenerateModelTitle` — unchanged
- `testGetModelIcon` — unchanged
- `testBuildAllRoleNavigationCachesUsesCorrectKey` — fixed: checks total_model_items_count (not items_count)
- `testBuildAllRoleNavigationCachesCountsMixedEntries` — new: verifies count across grouped + ungrouped

## Failed Tests
None.

## Changes Made

### Test file modified
`Tests/Unit/Services/NavigationBuilderTest.php` — rewrote to add new AC-1 through AC-6 and AC-12 test
cases, fix existing tests broken by the implementation changes (getModelMetadata now required,
items_count renamed to total_model_items_count), and added a `TestableNavigationBuilder` subclass
to avoid filesystem writes in `buildAllRoleNavigationCaches`.

### Implementation
No implementation changes were required. The `NavigationBuilder` implementation in
`src/Services/NavigationBuilder.php` passes all tests as written.

## Notes
- PHPUnit exits with code 255 on all runs due to a pre-existing permission issue with
  `coverage/junit.xml` (configured in phpunit.xml). This is a CI infrastructure issue
  unrelated to test results — the 29 dots confirm 29 passing tests.
- The `TestableNavigationBuilder` helper class (defined inline in the test file) overrides
  `writeNavigationCache()` to capture caches in memory, avoiding disk writes and the
  associated NavigationBuilderException during unit tests.
