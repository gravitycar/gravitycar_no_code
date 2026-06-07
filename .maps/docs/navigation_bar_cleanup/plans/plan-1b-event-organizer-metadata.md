# Implementation Plan: Item 1b — Event Organizer Model Metadata

## Spec Context

Implements spec §4.2 for the four event-related models. Each of these models must be grouped
together under the "Event Organizer" label in the navigation sidebar. The grouping is declared
directly in the model's own metadata file using the new `navigation_bar` property.

Catalog item: Item 1b — Add `navigation_bar` to Event Organizer model metadata files
Specification section: §4.1 (schema definition), §4.2 (model list)
Acceptance criteria addressed: AC-2, AC-5, AC-12

## Dependencies

- **Blocked by**: nothing — can start immediately, in parallel with Item 1a
- **Blocks**: Item 2 (NavigationBuilder reads `navigation_bar` from metadata to produce the grouped cache)
- **Uses**: No library dependencies; plain PHP array modification only

---

## File Changes

### New Files

None.

### Modified Files

| File | Change |
|---|---|
| `src/Models/events/events_metadata.php` | Add `'navigation_bar' => 'Event Organizer'` |
| `src/Models/eventcommitments/event_commitments_metadata.php` | Add `'navigation_bar' => 'Event Organizer'` |
| `src/Models/eventreminders/event_reminders_metadata.php` | Add `'navigation_bar' => 'Event Organizer'` |
| `src/Models/eventproposeddates/event_proposed_dates_metadata.php` | Add `'navigation_bar' => 'Event Organizer'` |

---

## Implementation Details

The change to each file is identical in nature: add one key-value pair to the top-level return
array. The property is named `navigation_bar`, the value is the plain PHP string
`'Event Organizer'`.

Per spec §4.1, the value is a plain string (not an array) because only one level of grouping
is planned. An absent `navigation_bar` key is treated identically to `''` (ungrouped) by the
NavigationBuilder (via `?? ''` null-coalescing). Adding the property explicitly to all four
files makes the grouping intent clear and eliminates reliance on a default.

### Placement Rule

In every metadata file, `navigation_bar` SHALL be placed as the **second key** in the
top-level return array, immediately after `'name'`. This keeps the most identity-relevant
properties (`name`, `navigation_bar`) visually prominent at the top of each file, before
`table`, `displayColumns`, `fields`, etc.

---

### File 1: `src/Models/events/events_metadata.php`

**Current opening** (lines 3–6):
```php
return [
    'name' => 'Events',
    'table' => 'events',
    'displayColumns' => ['name'],
```

**Modified opening** — add `navigation_bar` after `name`:
```php
return [
    'name' => 'Events',
    'navigation_bar' => 'Event Organizer',
    'table' => 'events',
    'displayColumns' => ['name'],
```

No other lines in this file change.

---

### File 2: `src/Models/eventcommitments/event_commitments_metadata.php`

**Current opening** (lines 3–6):
```php
return [
    'name' => 'EventCommitments',
    'table' => 'event_commitments',
    'displayColumns' => ['event_display', 'user_display', 'is_available'],
```

**Modified opening** — add `navigation_bar` after `name`:
```php
return [
    'name' => 'EventCommitments',
    'navigation_bar' => 'Event Organizer',
    'table' => 'event_commitments',
    'displayColumns' => ['event_display', 'user_display', 'is_available'],
```

No other lines in this file change.

---

### File 3: `src/Models/eventreminders/event_reminders_metadata.php`

**Current opening** (lines 3–6):
```php
return [
    'name' => 'EventReminders',
    'table' => 'event_reminders',
    'displayColumns' => ['reminder_type', 'status'],
```

**Modified opening** — add `navigation_bar` after `name`:
```php
return [
    'name' => 'EventReminders',
    'navigation_bar' => 'Event Organizer',
    'table' => 'event_reminders',
    'displayColumns' => ['reminder_type', 'status'],
```

No other lines in this file change.

---

### File 4: `src/Models/eventproposeddates/event_proposed_dates_metadata.php`

**Current opening** (lines 3–6):
```php
return [
    'name' => 'EventProposedDates',
    'table' => 'event_proposed_dates',
    'displayColumns' => ['proposed_date'],
```

**Modified opening** — add `navigation_bar` after `name`:
```php
return [
    'name' => 'EventProposedDates',
    'navigation_bar' => 'Event Organizer',
    'table' => 'event_proposed_dates',
    'displayColumns' => ['proposed_date'],
```

No other lines in this file change.

---

## Error Handling

No error handling is required. This change is purely declarative PHP array data. There is no
runtime logic introduced in this item. The NavigationBuilder (Item 2) is responsible for
reading and acting on the `navigation_bar` value.

---

## Unit Test Specifications

These metadata files are PHP arrays with no executable logic; they are tested indirectly via
the NavigationBuilder integration tests (Item 2). The direct test for this item is:

### MetadataEngine reads `navigation_bar` from each file

For each of the four files, after the file is modified, a test should confirm that calling
`MetadataEngine::getModelMetadata('Events')` (and the other three model names) returns an
array that includes the key `navigation_bar` with the value `'Event Organizer'`.

| Case | Input | Expected | Why |
|---|---|---|---|
| Events navigation_bar present | `getModelMetadata('Events')` | `$result['navigation_bar'] === 'Event Organizer'` | Confirms file edit is correct and MetadataEngine returns it |
| EventCommitments navigation_bar present | `getModelMetadata('EventCommitments')` | `$result['navigation_bar'] === 'Event Organizer'` | Same |
| EventReminders navigation_bar present | `getModelMetadata('EventReminders')` | `$result['navigation_bar'] === 'Event Organizer'` | Same |
| EventProposedDates navigation_bar present | `getModelMetadata('EventProposedDates')` | `$result['navigation_bar'] === 'Event Organizer'` | Same |

These tests can be written as a single parameterized test method in the existing
`MetadataEngineTest` class (or a new `NavigationBarMetadataTest` class if preferred).

**Key Scenario:**
- Setup: ensure the metadata cache is cleared so the updated file is loaded fresh
- Action: call `$metadataEngine->getModelMetadata('Events')`
- Expected: returned array has key `navigation_bar` equal to `'Event Organizer'`

---

## Notes

- The string value `'Event Organizer'` must be spelled and capitalized exactly as shown
  (capital E, capital O, one space between words). The NavigationBuilder uses this string
  directly as the group label in the navigation cache; any variation will produce a different
  group or no match.
- No trailing comma style inconsistency — all four files already use trailing commas on
  array entries (PHP 7.3+ style); the new line should match.
- The `navigation_bar` property does not appear in the `fields` array and is not a field
  definition — it is a top-level metadata property alongside `name`, `table`, `ui`, etc.
- After these files are saved, the metadata cache at `cache/` must be cleared (or allowed
  to expire) so the MetadataEngine picks up the new property. In development, the cache
  regenerates on the next request. In production, triggering `POST /navigation/cache/rebuild`
  (Item 2's post-build step) also regenerates the metadata cache.
- These edits are safe to merge before Item 2 is implemented. The absence of a
  NavigationBuilder that reads `navigation_bar` means the property is simply ignored — no
  regression to existing behavior.
