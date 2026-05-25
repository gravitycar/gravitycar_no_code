# Implementation Plan: LinkField Backend

## Spec Context

This plan implements the new `Link` field type for the Gravitycar Framework, as required by
spec section 4 ("New LinkField (Backend)"). `LinkField` is a URL-storage field that accepts
only `http`/`https` scheme values, blocking unsafe schemes such as `javascript:`. It is
used by the Projects model's `link` field (spec section 2) and is designed as a reusable
framework field type available to any future model.

Catalog item: Item 1 — `src/Fields/LinkField.php`
Specification section: §4 (New LinkField — Backend)
Acceptance criteria addressed: 15, 17, 18, 19

---

## Dependencies

- **Blocked by**: nothing — `FieldBase` exists at `src/Fields/FieldBase.php`
- **Uses**: `src/Fields/FieldBase.php`, `src/Validation/ValidationRuleBase.php`,
  `src/Exceptions/GCException.php`, Monolog Logger
- **Blocks**: Plan 02 (Projects Metadata File) — needs the `Link` type to exist before
  the Projects metadata can reference it

---

## File Changes

### New Files

- `src/Fields/LinkField.php` — the `Link` field type class
- `src/Validation/LinkURLValidation.php` — validation rule enforcing http/https scheme

### Modified Files

None. MetadataEngine auto-discovers field types by scanning `src/Fields/*.php`. No manual
registration is required.

---

## Implementation Details

### 1. LinkURLValidation Rule

**File**: `src/Validation/LinkURLValidation.php`

**Purpose**: Extends `ValidationRuleBase` to validate that a URL is (a) well-formed and
(b) uses only `http` or `https` scheme. Empty values pass (Required rule handles those).

**Class declaration**:
```php
<?php
declare(strict_types=1);
namespace Gravitycar\Validation;

use Gravitycar\Validation\ValidationRuleBase;

class LinkURLValidation extends ValidationRuleBase
```

**Constructor**:
```php
public function __construct()
{
    parent::__construct('LinkURL', 'URL must use http or https scheme.');
}
```

**Validation logic** — `validate($value, $model = null): bool`:

```
if value is empty/null → return true   // let Required rule handle emptiness
if filter_var($value, FILTER_VALIDATE_URL) is false → return false
scheme = parse_url($value, PHP_URL_SCHEME)
if scheme is not 'http' and not 'https' → return false
return true
```

The two-step check (filter_var then parse_url scheme) ensures both structural validity
and safe scheme in a single rule, matching the spec requirement in §4 and criteria 15 and 18.

**Private helper** — `isValidScheme(string $scheme): bool`:
```
return in_array(strtolower($scheme), ['http', 'https'], true)
```

Keeping the scheme check in a private helper keeps `validate()` under 10 lines and
complexity under 4/10.

**Full class structure**:
```php
<?php
declare(strict_types=1);
namespace Gravitycar\Validation;

/**
 * Validates that a value is a well-formed URL with an http or https scheme.
 * Empty values pass (Required rule handles emptiness).
 */
class LinkURLValidation extends ValidationRuleBase
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    public function __construct()
    {
        parent::__construct('LinkURL', 'URL must use http or https scheme.');
    }

    public function validate($value, $model = null): bool
    {
        if (empty($value)) {
            return true;
        }
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = parse_url($value, PHP_URL_SCHEME);
        return $this->isValidScheme((string) $scheme);
    }

    private function isValidScheme(string $scheme): bool
    {
        return in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true);
    }

    public function getJavascriptValidation(): string
    {
        return "
        function validateLinkURL(value) {
            if (!value || value === '') return { valid: true };
            try {
                const url = new URL(value);
                if (url.protocol !== 'http:' && url.protocol !== 'https:') {
                    return { valid: false, message: 'URL must use http or https scheme.' };
                }
                return { valid: true };
            } catch (e) {
                return { valid: false, message: 'URL must use http or https scheme.' };
            }
        }";
    }
}
```

---

### 2. LinkField Class

**File**: `src/Fields/LinkField.php`

**Namespace**: `Gravitycar\Fields`

**Extends**: `FieldBase`

**Class declaration**:
```php
<?php
declare(strict_types=1);
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
use Monolog\Logger;

/**
 * LinkField: URL input field that stores and validates http/https links.
 *
 * Default properties can be overridden per-model in the metadata file.
 * The $target property controls the <a> tag target attribute in the frontend
 * LinkInput component and is automatically serialised into the metadata array
 * via FieldBase::syncPropertiesToMetadata().
 */
class LinkField extends FieldBase
```

**Property declarations** (all `protected`, matching FieldBase convention):

```php
protected string $type = 'Link';
protected string $reactComponent = 'LinkInput';
protected string $label = '';
protected bool $required = false;
protected bool $nullable = true;
protected int $maxLength = 256;
protected string $placeholder = 'https://...';
protected string $target = '_blank';

/** @var array Supported filter operators for link fields */
protected array $operators = ['equals', 'notEquals', 'isNull', 'isNotNull'];

/** @var array Default validation rules applied to every instance */
protected array $validationRules = ['LinkURL'];
```

**Why `protected array $validationRules = ['LinkURL']`**: FieldBase's constructor calls
`setUpValidationRules()`, which iterates `$this->validationRules`, resolves string names
via `ValidationRuleFactory`, and replaces the array with instantiated rule objects. By
declaring `['LinkURL']` as the default here, every `LinkField` instance automatically gets
the `LinkURLValidation` rule without the metadata file needing to repeat it. The metadata
file can still pass an empty `validationRules` array or additional rules if desired;
`ingestMetadata()` will overwrite this property from the incoming array when the key is
present.

**Constructor**:
```php
public function __construct(array $metadata, ?Logger $logger = null)
{
    parent::__construct($metadata, $logger);
}
```

No additional constructor logic is needed. The parent constructor calls:
1. `ingestMetadata($metadata)` — copies matching metadata keys onto properties; overwrites
   `$target`, `$maxLength`, `$placeholder` etc. if the metadata file supplies them
2. `syncPropertiesToMetadata()` — writes all properties (including `$target`) back into
   `$this->metadata`; this is what makes `$target` visible to the frontend via
   `getMetadata()`
3. `setUpValidationRules()` — instantiates `LinkURLValidation` from the string `'LinkURL'`

**The $target → frontend flow** (important for `LinkInput.tsx` to read `field.target`):

`syncPropertiesToMetadata()` in `FieldBase` iterates all protected/private properties via
reflection and writes them to `$this->metadata`. Because `$target` is a declared
`protected string`, it is included in that sync. When the MetadataEngine returns field
metadata to the frontend, `$this->metadata` is the source, so `$target` arrives in the
payload as `field.target`. The React `LinkInput` component reads it as
`field.target ?? '_blank'`.

No override of `getMetadata()` is needed. No override of `validate()` is needed.

**generateOpenAPISchema()** override:
```php
public function generateOpenAPISchema(): array
{
    return [
        'type'      => 'string',
        'format'    => 'uri',
        'maxLength' => $this->metadata['maxLength'] ?? $this->maxLength,
    ];
}
```

This provides an accurate OpenAPI representation (URI format, max length). Keeps the
method under 10 lines.

**Full class structure**:
```php
<?php
declare(strict_types=1);
namespace Gravitycar\Fields;

use Gravitycar\Fields\FieldBase;
use Monolog\Logger;

/**
 * LinkField: URL input field that stores and validates http/https links.
 *
 * Stores a URL string (max 256 chars by default). Validates that the scheme
 * is http or https when a value is provided; empty values are accepted when
 * the field is not required.
 *
 * The $target property controls the HTML <a> target attribute rendered by the
 * LinkInput React component. It defaults to '_blank' and can be overridden in
 * the model metadata file. It is automatically serialised into the metadata
 * array by FieldBase::syncPropertiesToMetadata() so the frontend receives it
 * as field.target.
 *
 * Auto-discovered by MetadataEngine via src/Fields/ scan. No manual
 * registration required.
 */
class LinkField extends FieldBase
{
    protected string $type = 'Link';
    protected string $reactComponent = 'LinkInput';
    protected string $label = '';
    protected bool $required = false;
    protected bool $nullable = true;
    protected int $maxLength = 256;
    protected string $placeholder = 'https://...';

    /**
     * Controls the HTML <a> target attribute in the LinkInput React component.
     * Override per-model by setting 'target' in the field's metadata array.
     */
    protected string $target = '_blank';

    /** @var array Supported filter operators */
    protected array $operators = ['equals', 'notEquals', 'isNull', 'isNotNull'];

    /**
     * Default validation rules. The 'LinkURL' string is resolved to a
     * LinkURLValidation instance by FieldBase::setUpValidationRules().
     */
    protected array $validationRules = ['LinkURL'];

    public function __construct(array $metadata, ?Logger $logger = null)
    {
        parent::__construct($metadata, $logger);
    }

    /**
     * Generate OpenAPI schema for link field.
     */
    public function generateOpenAPISchema(): array
    {
        return [
            'type'      => 'string',
            'format'    => 'uri',
            'maxLength' => $this->metadata['maxLength'] ?? $this->maxLength,
        ];
    }
}
```

---

## Error Handling

| Condition | Handling |
|-----------|----------|
| Value fails `filter_var(FILTER_VALIDATE_URL)` | `LinkURLValidation::validate()` returns false; FieldBase adds error via `registerValidationError()`; `setValue()` reverts the value |
| Scheme is not http/https | Same as above |
| Empty value, field not required | `LinkURLValidation::validate()` returns true (empty pass-through); no error |
| Empty value, field required | The `Required` validation rule (if configured in metadata) handles this separately |
| `ValidationRuleFactory` cannot resolve `'LinkURL'` | FieldBase logs an error and skips the rule (existing behaviour in `setUpValidationRules()`); this would only happen if the class file is missing |

No new exception classes are needed. `LinkURLValidation` returns `bool` and lets
`FieldBase::validate()` handle error registration.

---

## Auto-Discovery by MetadataEngine

The MetadataEngine scans `src/Fields/*.php` and derives the field type name by stripping
the `Field` suffix from the class name (`LinkField` → type `Link`). No manual registration
is required. The only requirement is that the file exists at `src/Fields/LinkField.php`
with class `LinkField` in namespace `Gravitycar\Fields`. This is already satisfied by this
plan.

Similarly, `ValidationRuleFactory` discovers validation rules by scanning
`src/Validation/*.php`. `LinkURLValidation` at `src/Validation/LinkURLValidation.php` will
be auto-discovered and resolvable by the string key `'LinkURL'` (class name minus
`Validation` suffix).

---

## Unit Test Specifications

### `LinkURLValidation::validate()`

| Case | Input | Expected | Why |
|------|-------|----------|-----|
| Empty string | `''` | `true` | Empty pass-through; Required handles emptiness |
| Null | `null` | `true` | Same |
| Valid https URL | `'https://example.com'` | `true` | Happy path |
| Valid http URL | `'http://example.com/path?q=1'` | `true` | http is allowed |
| javascript: scheme | `'javascript:alert(1)'` | `false` | Blocked scheme |
| data: URI | `'data:text/html,<h1>X</h1>'` | `false` | Blocked scheme |
| ftp: URL | `'ftp://files.example.com'` | `false` | Only http/https allowed |
| Malformed URL | `'not-a-url'` | `false` | Fails filter_var |
| No scheme | `'example.com'` | `false` | Fails filter_var (no scheme) |
| Mixed-case scheme | `'HTTPS://example.com'` | `true` | Scheme comparison is case-insensitive |

### Key Scenario: javascript: Injection
**Setup**: Instantiate `new LinkURLValidation()`
**Action**: Call `validate('javascript:alert(document.cookie)')`
**Expected**: Returns `false`
**Why**: Spec criterion 18 requires `javascript:` values to be rejected

### `LinkField` — constructor and metadata ingestion

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Default $target in metadata | `new LinkField(['name' => 'link'])` | `$field->getMetadata()['target'] === '_blank'` | syncPropertiesToMetadata includes $target |
| Overridden $target | `new LinkField(['name' => 'link', 'target' => '_self'])` | `$field->getMetadata()['target'] === '_self'` | ingestMetadata overwrites default |
| Default $maxLength in metadata | `new LinkField(['name' => 'link'])` | `$field->getMetadata()['maxLength'] === 256` | Default value synced |
| Validation rule instantiated | `new LinkField(['name' => 'link'])` | `$field->getValidationErrors()` empty after `$field->validate()` on empty value | LinkURL rule allows empty |
| Invalid URL rejected | After `setValue('javascript:alert(1)')` | Value reverts; `getValidationErrors()` non-empty | LinkURLValidation fires |
| Valid URL accepted | After `setValue('https://example.com')` | Value is `'https://example.com'` | Happy path |

### `LinkField::generateOpenAPISchema()`

| Case | Setup | Expected |
|------|-------|----------|
| Default metadata | `new LinkField(['name' => 'link'])` | Returns `['type'=>'string','format'=>'uri','maxLength'=>256]` |
| Custom maxLength | `new LinkField(['name' => 'link', 'maxLength' => 500])` | Returns `['type'=>'string','format'=>'uri','maxLength'=>500]` |

---

## Notes

1. **Why a new `LinkURLValidation` rather than reusing `URLValidation`**: The existing
   `URLValidation` only calls `filter_var(FILTER_VALIDATE_URL)` — it does not check the
   scheme. The spec (§4, criteria 15 and 18) explicitly requires blocking `javascript:` and
   other non-http schemes. Creating `LinkURLValidation` preserves the open/closed principle:
   `URLValidation` is unchanged and still usable by other fields.

2. **Why `$validationRules = ['LinkURL']` as a class default**: Fields in this framework
   get validation via two paths — from `$this->validationRules` (the field-class default)
   and from the `validationRules` key in the metadata array. Declaring the rule at class
   level means every `LinkField` gets URL validation automatically, even if the model
   metadata doesn't list it. This is consistent with the spec: "if a value is provided it
   MUST pass URL validation" — it's a type invariant, not an optional rule.

3. **$target serialisation**: `FieldBase::syncPropertiesToMetadata()` iterates all
   protected/private properties via reflection and writes them to `$this->metadata`. This
   means `$target` (a `protected string`) will be included in the metadata array without
   any extra code in `LinkField`. The frontend then reads `field.target` from the API
   response. No override of `getMetadata()` is needed.

4. **`$nullable = true`**: FieldBase does not declare `$nullable` itself, but
   `syncPropertiesToMetadata()` will include it if declared in the subclass. Including it
   makes the metadata complete for any frontend or schema generation code that reads it.

5. **Line count**: `LinkField.php` is approximately 60 lines; `LinkURLValidation.php` is
   approximately 55 lines. Both are well under the 300-line CLAUDE.md limit.

6. **Complexity**: `LinkField` has no branching logic (complexity = 0/10).
   `LinkURLValidation::validate()` has 3 guard clauses (complexity ≈ 3/10). Both meet the
   CLAUDE.md target of under 4/10.
