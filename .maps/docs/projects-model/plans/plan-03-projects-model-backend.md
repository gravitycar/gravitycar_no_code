# Implementation Plan: Projects Model Backend

## Spec Context

This plan implements the two backend files that define the Projects model within the Gravitycar metadata-driven framework. The metadata file drives all field definitions, RBAC, validation rules, and UI hints. The PHP model class registers the model with the framework and extends `ModelBase` for all standard CRUD operations. Together these two files are sufficient to support admin CRUD at `/Projects` and the guest-accessible public showcase view.

Catalog item: Item 3 — Projects Model Backend  
Specification section: §2 Projects Metadata File, §3 Projects PHP Model Class  
Acceptance criteria addressed: 1, 2, 3, 4, 14, 15, 17, 18, 19, 20, 21

---

## Dependencies

- **Blocked by**: None — this is the foundational model definition for the Projects feature. All other backend and frontend items depend on these files.
- **Uses**:
  - `src/Models/ModelBase.php` — base class all models extend
  - `src/Factories/FieldFactory.php`, `RelationshipFactory.php`, `ModelFactory.php`
  - `src/Contracts/MetadataEngineInterface.php`, `DatabaseConnectorInterface.php`, `CurrentUserProviderInterface.php`
  - `Monolog\Logger` — injected for logging
  - `src/Fields/LinkField.php` — must exist before the metadata file can resolve the `Link` field type (built in a separate plan item)
- **Blocks**: All other Projects feature plans — they depend on `Projects` model metadata and class existing.

---

## File Changes

### New Files

- `src/Models/projects/projects_metadata.php` — full metadata array for the Projects model
- `src/Models/projects/Projects.php` — PHP model class extending `ModelBase`

### Modified Files

None — this plan creates new files only.

---

## Implementation Details

### File 1: `src/Models/projects/projects_metadata.php`

**Purpose**: Returns a PHP array consumed by `MetadataEngine` to drive all framework behaviour for the Projects model: field definitions, RBAC, DB table name, UI column lists.

**Pattern to follow**: `src/Models/books/books_metadata.php` (structure and key names) and `src/Models/movies/movies_metadata.php` (RBAC guest pattern).

**Key observations from existing metadata files**:
- Top-level keys are: `name`, `table`, `displayColumns`, `fields`, `rolesAndActions`, `validationRules`, `relationships`, `ui`
- The `ui` key contains: `listFields`, `createFields`, `editFields`
- Each field entry includes at minimum: `name`, `type`, `label`; optional: `required`, `maxLength`, `nullable`, `allowRemote`, `allowLocal`, `altText`, `validationRules`
- The `validationRules` key on individual fields holds rule class names as strings; top-level `validationRules` is an empty array when field-level validation is sufficient

**Exact PHP array to implement**:

```php
<?php
// Projects model metadata for Gravitycar framework
return [
    'name' => 'Projects',
    'table' => 'projects',
    'displayColumns' => ['title'],
    'fields' => [
        'title' => [
            'name' => 'title',
            'type' => 'Text',
            'label' => 'Title',
            'required' => true,
            'maxLength' => 256,
            'validationRules' => ['Required'],
        ],
        'tag_line' => [
            'name' => 'tag_line',
            'type' => 'Text',
            'label' => 'Tag Line',
            'required' => true,
            'maxLength' => 1024,
            'validationRules' => ['Required'],
        ],
        'description' => [
            'name' => 'description',
            'type' => 'BigText',
            'label' => 'Description',
            'required' => true,
            'maxLength' => 16000,
            'validationRules' => ['Required'],
        ],
        'screenshot' => [
            'name' => 'screenshot',
            'type' => 'Image',
            'label' => 'Screenshot',
            'required' => true,
            'maxLength' => 500,
            'allowRemote' => true,
            'allowLocal' => false,
            'altText' => 'Project screenshot',
            'validationRules' => ['Required'],
        ],
        'link' => [
            'name' => 'link',
            'type' => 'Link',
            'label' => 'Link',
            'required' => false,
            'nullable' => true,
            'maxLength' => 256,
            'validationRules' => [],
        ],
    ],
    'rolesAndActions' => [
        'admin' => ['*'],
        'user'  => ['list', 'read'],
        'guest' => ['list', 'read'],
    ],
    'validationRules' => [],
    'relationships' => [],
    'ui' => [
        'listFields'   => ['title', 'tag_line', 'screenshot', 'link'],
        'createFields' => ['title', 'tag_line', 'description', 'screenshot', 'link'],
        'editFields'   => ['title', 'tag_line', 'description', 'screenshot', 'link'],
    ],
];
```

**Notes**:
- `displayColumns` uses `['title']` to match the spec's model identity section.
- `screenshot` has `maxLength => 500` matching the DB schema column `VARCHAR(500)` and the spec table.
- `link` uses `maxLength => 256` matching the DB schema column `VARCHAR(256)`.
- No `validationRules` string needed on `link` because `LinkField` itself handles URL/scheme validation internally (spec §4); an empty array is correct.
- The `ui` block has no `createButtons`, `editButtons`, or `relatedItemsSections` — Projects is a plain CRUD model.
- RBAC mirrors the Events model pattern: admin gets `'*'`, user and guest both get `['list', 'read']`.
- No `manager` role entry — only roles used by the existing seeder pattern need to be listed.

---

### File 2: `src/Models/projects/Projects.php`

**Purpose**: Registers the Projects model class with the framework. Extends `ModelBase` to inherit all CRUD, find, validation, and relationship handling. No custom domain logic is needed.

**Pattern to follow exactly**: `src/Models/books/Books.php` — specifically the constructor signature and `parent::__construct(...)` call.

**Exact class skeleton to implement**:

```php
<?php

declare(strict_types=1);

namespace Gravitycar\Models\projects;

use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\FieldFactory;
use Gravitycar\Factories\RelationshipFactory;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\CurrentUserProviderInterface;
use Monolog\Logger;

/**
 * Projects Model
 *
 * Represents a portfolio project record. Provides title, tag line,
 * description, screenshot URL, and optional project link. Supports
 * admin CRUD and public (guest) read/list access.
 *
 * All CRUD operations are handled by ModelBase; no custom domain
 * logic is required for this model.
 */
class Projects extends ModelBase
{
    /**
     * Constructs a Projects model instance with full dependency injection.
     *
     * @param Logger                       $logger              Monolog logger instance
     * @param MetadataEngineInterface      $metadataEngine      Metadata resolver
     * @param FieldFactory                 $fieldFactory        Field instance factory
     * @param DatabaseConnectorInterface   $databaseConnector   Doctrine DBAL connector
     * @param RelationshipFactory          $relationshipFactory Relationship instance factory
     * @param ModelFactory                 $modelFactory        Model instance factory
     * @param CurrentUserProviderInterface $currentUserProvider Current authenticated user
     */
    public function __construct(
        Logger $logger,
        MetadataEngineInterface $metadataEngine,
        FieldFactory $fieldFactory,
        DatabaseConnectorInterface $databaseConnector,
        RelationshipFactory $relationshipFactory,
        ModelFactory $modelFactory,
        CurrentUserProviderInterface $currentUserProvider
    ) {
        parent::__construct(
            $logger,
            $metadataEngine,
            $fieldFactory,
            $databaseConnector,
            $relationshipFactory,
            $modelFactory,
            $currentUserProvider
        );
    }
}
```

**Notes**:
- `declare(strict_types=1)` at the top per PSR-12 and CLAUDE.md requirements.
- Constructor parameter order MUST match `Books.php` exactly: `Logger, MetadataEngineInterface, FieldFactory, DatabaseConnectorInterface, RelationshipFactory, ModelFactory, CurrentUserProviderInterface`.
- `parent::__construct(...)` passes all seven parameters in the same order.
- No additional methods are needed. `ModelBase` provides `create()`, `update()`, `delete()`, `find()`, `findRaw()`, `get()`, `set()`, etc.
- The `logger` and `config` properties required by CLAUDE.md are inherited from `ModelBase` — do not redeclare them.
- Class-level PHPDoc explains the purpose and documents the CRUD/access pattern.

---

## Error Handling

- `Projects.php` itself has no error-prone logic; all error handling is inherited from `ModelBase`.
- If the metadata file references a `Link` field type before `LinkField.php` is built, `MetadataEngine` will throw a field-type-not-found exception at runtime. This is expected during incremental builds and will resolve once LinkField (a different plan item) is implemented.

---

## Unit Test Specifications

The constructor and the metadata file are the only testable surfaces in this plan item.

### `projects_metadata.php` — Structure Tests

| Case | What to assert | Why |
|------|---------------|-----|
| All required top-level keys present | `name`, `table`, `displayColumns`, `fields`, `rolesAndActions`, `validationRules`, `relationships`, `ui` all exist | MetadataEngine expects these keys |
| `name` equals `'Projects'` | `$metadata['name'] === 'Projects'` | Framework uses this for routing and display |
| `table` equals `'projects'` | `$metadata['table'] === 'projects'` | SchemaGenerator derives DB table name from this |
| All five field keys present | `title`, `tag_line`, `description`, `screenshot`, `link` all in `$metadata['fields']` | Field resolution will fail if keys missing |
| `title` field is required with maxLength 256 | `$metadata['fields']['title']['required'] === true` and `maxLength === 256` | Spec §2 requirement |
| `tag_line` field is required with maxLength 1024 | `required === true`, `maxLength === 1024` | Spec §2 requirement |
| `description` field is BigText, required, maxLength 16000 | type, required, maxLength | Spec §2 requirement |
| `screenshot` field: type Image, required, allowRemote true, allowLocal false | All four properties | Spec §2 and §7 requirement |
| `link` field: type Link, not required, nullable, maxLength 256 | `required === false`, `nullable === true`, `maxLength === 256` | Spec §2 — link is optional |
| RBAC admin has wildcard | `$metadata['rolesAndActions']['admin'] === ['*']` | Admin full access |
| RBAC user has list+read only | `$metadata['rolesAndActions']['user'] === ['list', 'read']` | User cannot create/edit/delete |
| RBAC guest has list+read only | `$metadata['rolesAndActions']['guest'] === ['list', 'read']` | Guest public access |
| `ui.listFields` correct order | `['title', 'tag_line', 'screenshot', 'link']` | Spec §2 |
| `ui.createFields` correct order | `['title', 'tag_line', 'description', 'screenshot', 'link']` | Spec §2 |
| `ui.editFields` correct order | `['title', 'tag_line', 'description', 'screenshot', 'link']` | Spec §2 |
| Top-level `validationRules` is empty array | `$metadata['validationRules'] === []` | Field-level validation is sufficient |
| `relationships` is empty array | `$metadata['relationships'] === []` | No relationships for initial release |

### `Projects.php` — Constructor Test

| Case | What to assert | Why |
|------|---------------|-----|
| Can be instantiated with mocked dependencies | `new Projects(...)` does not throw | Constructor delegates to parent cleanly |
| Is instance of ModelBase | `$projects instanceof ModelBase` | Confirms inheritance chain |
| Namespace resolves correctly | `Projects` class is in `Gravitycar\Models\projects` | PSR-4 autoloading requires exact namespace |

**Key scenario — instantiation with mocks:**

```php
// Setup: create mocks for all 7 constructor parameters
$logger = $this->createMock(Logger::class);
$metadataEngine = $this->createMock(MetadataEngineInterface::class);
$fieldFactory = $this->createMock(FieldFactory::class);
$dbConnector = $this->createMock(DatabaseConnectorInterface::class);
$relationshipFactory = $this->createMock(RelationshipFactory::class);
$modelFactory = $this->createMock(ModelFactory::class);
$currentUserProvider = $this->createMock(CurrentUserProviderInterface::class);

// Action: instantiate
$project = new Projects(
    $logger, $metadataEngine, $fieldFactory,
    $dbConnector, $relationshipFactory, $modelFactory, $currentUserProvider
);

// Expected: no exception; $project instanceof ModelBase === true
```

---

## Notes

1. **Constructor order matters**: `ModelBase::__construct` has a specific parameter order. The `Projects` constructor must match it exactly — `Logger` first, then `MetadataEngineInterface`, then `FieldFactory`, `DatabaseConnectorInterface`, `RelationshipFactory`, `ModelFactory`, `CurrentUserProviderInterface`. Verify against the actual `ModelBase` signature if Books.php diverges from it.

2. **`declare(strict_types=1)` placement**: Must be the very first statement after the opening `<?php` tag — no blank lines between them.

3. **Metadata file has no PHP class**: It is a plain PHP file that `return`s an array. No `<?php declare(strict_types=1);` header is required (consistent with books and movies metadata files, which do not use strict_types).

4. **`displayColumns` vs `ui.listFields`**: These are different things. `displayColumns` is used by the framework for model-to-model display (e.g. in relationship dropdowns). `ui.listFields` is the columns shown in the GenericCrudPage table. Both must be set; `displayColumns` uses `['title']` and `listFields` uses the full four-field array per spec.

5. **`screenshot` maxLength is 500**: The spec DB schema says `VARCHAR(500)` for the screenshot column. The metadata `maxLength => 500` must match. Do not use 1024 or 256.

6. **Link field validationRules**: Leave as empty array `[]`. The URL and scheme validation is performed inside `LinkField::validate()` (a different plan item). Adding a validation rule class name here would require that class to exist; leaving it empty avoids a dependency.

7. **No `config` property in Projects.php**: CLAUDE.md says every class that _needs_ config values should have a `config` property. `Projects` has no config-dependent logic, so no `$config` property is added. `ModelBase` handles config for the base operations.

8. **File size**: Both files are well under the 300-line limit (metadata ~50 lines, class ~60 lines).
