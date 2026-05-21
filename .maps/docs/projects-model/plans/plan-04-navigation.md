# Implementation Plan: Navigation Bar Entry

## Spec Context

This plan fulfills the requirement to expose the Projects Showcase page via the sidebar navigation. The entry must be visible to all users including unauthenticated guests (`roles: ['*']`) and must route to `/projects_showcase`.

Catalog item: Navigation Bar Entry (Item 4)
Specification section: Projects Showcase UI
Acceptance criteria addressed: Projects Showcase is reachable from the sidebar navigation by any visitor.

## Dependencies

- **Blocked by**: Nothing — this is a standalone config change.
- **Uses**: `src/Navigation/navigation_config.php` — existing `custom_pages` array.

## File Changes

### Modified Files

- `src/Navigation/navigation_config.php` — Add one entry to the `custom_pages` array.

## Implementation Details

### Location in File

Insert the new entry at the **end of the `custom_pages` array**, after the existing `events_list` entry (line 48), before the closing `]` on line 49.

### Exact PHP Snippet to Insert

Add a comma after the closing `]` of the `events_list` entry, then append:

```php
        [
            'key' => 'projects',
            'title' => 'Projects',
            'url' => '/projects_showcase',
            'icon' => '🗂️',
            'roles' => ['*'] // All roles
        ]
```

### Resulting `custom_pages` Array (relevant tail)

```php
        [
            'key' => 'events_list',
            'title' => 'List Events',
            'url' => '/events',
            'icon' => '📋',
            'roles' => ['*'] // All roles
        ],
        [
            'key' => 'projects',
            'title' => 'Projects',
            'url' => '/projects_showcase',
            'icon' => '🗂️',
            'roles' => ['*'] // All roles
        ]
    ],
```

## Notes

- The file comment on line 5 states: "Navigation elements will be displayed in source-code order." Placing the entry at the end puts Projects after Events entries in the sidebar. If a different position is preferred, move the snippet accordingly.
- The `roles` value `['*']` matches the pattern used by `dashboard` and `events_list`, making the link visible to guests (unauthenticated users) as well as all authenticated roles.
- No other files need to change — the navigation system reads this config file and renders entries automatically.

## Unit Test Specifications

This change is a pure data/config modification with no executable logic. No unit tests are required. Manual verification: load the app, confirm "Projects" appears in the sidebar and clicking it navigates to `/projects_showcase`.
