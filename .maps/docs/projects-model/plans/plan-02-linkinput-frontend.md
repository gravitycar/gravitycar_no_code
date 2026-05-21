# Implementation Plan: LinkInput Frontend + Registration

## Spec Context

This plan implements the React frontend component for the `Link` field type and wires it
into the two existing files that must know about field components. It fulfils spec §5
(LinkInput Component), §6 (FieldComponent Registration), and §7 (GenericCrudPage Link
Rendering), and directly addresses acceptance criteria 14, 15, and 22.

Catalog item: Item 2 — LinkInput Frontend + Registration  
Specification sections: §5, §6, §7  
Acceptance criteria addressed: 14, 15, 22

---

## Dependencies

- **Blocked by**: Plan 01 (LinkField Backend) — needs `LinkField.php` to exist so that
  the backend metadata API returns `react_component: 'LinkInput'` and `target: '_blank'`
  in the field payload. The frontend component itself can be built independently, but it
  will not be exercised until the Projects metadata is loaded.
- **Uses**:
  - `gravitycar-frontend/src/types/index.ts` — `FieldComponentProps`, `FieldMetadata`
  - `gravitycar-frontend/src/components/fields/TextInput.tsx` — visual/structural pattern
  - `gravitycar-frontend/src/components/fields/FieldComponent.tsx` — componentMap to update
  - `gravitycar-frontend/src/components/crud/GenericCrudPage.tsx` — renderFieldValue() switch

---

## File Changes

### New Files

- `gravitycar-frontend/src/components/fields/LinkInput.tsx` — React component for the
  Link field type. Edit mode: `<input type="url">`. Read-only mode: `<a>` anchor.

### Modified Files

- `gravitycar-frontend/src/components/fields/FieldComponent.tsx` — add import and
  add `'LinkInput': LinkInput` entry to componentMap.
- `gravitycar-frontend/src/components/crud/GenericCrudPage.tsx` — add `case 'Link':`
  branch inside the `renderFieldValue()` switch statement.
- `gravitycar-frontend/src/types/index.ts` — add `target?: string` property to
  `FieldMetadata` interface so TypeScript knows about the field metadata property.

---

## Implementation Details

### 1. FieldMetadata type extension

**File**: `gravitycar-frontend/src/types/index.ts`

The `FieldMetadata` interface at line 236 needs a new optional property so that
`LinkInput.tsx` and `GenericCrudPage.tsx` can reference `field.target` without TypeScript
errors.

Add the following line inside the `FieldMetadata` interface, in the section with other
field-specific properties (after `autoplay?: boolean;`, before `component_props`):

```typescript
// Link field specific properties
target?: string;
```

No other changes to `types/index.ts`.

---

### 2. LinkInput Component

**File**: `gravitycar-frontend/src/components/fields/LinkInput.tsx`

**Exports**: `default LinkInput` (React.FC)

**Props**: Uses the existing `FieldComponentProps` interface from `../../types`. No custom
props interface is needed — `field.target` is now declared on `FieldMetadata` (see above).
The relevant props are:
- `fieldMetadata: FieldMetadata` — contains `label`, `placeholder`, `required`, `help_text`, `target`
- `value: any` — the URL string or undefined/null/empty
- `onChange: (value: any) => void` — called when input changes
- `error?: string` — validation error message from parent form
- `readOnly?: boolean` — display mode toggle
- `disabled?: boolean` — disables the input
- `required?: boolean` — shows red asterisk on label
- `placeholder?: string` — can override fieldMetadata.placeholder

**Read-only mode** (`readOnly === true`):

```tsx
if (readOnly) {
  return (
    <div className="mb-4">
      {displayLabel && (
        <label className="block text-sm font-medium text-gray-700 mb-2">
          {displayLabel}
          {required && <span className="text-red-500 ml-1">*</span>}
        </label>
      )}

      <div className={`
        w-full px-3 py-2 border rounded-md shadow-sm bg-gray-50
        ${error ? 'border-red-500' : 'border-gray-300'}
      `}>
        {value ? (
          <a
            href={value}
            target={fieldMetadata?.target ?? '_blank'}
            rel="noopener noreferrer"
            className="text-blue-600 hover:text-blue-800 break-all underline"
          >
            {value}
          </a>
        ) : (
          <span className="text-gray-400">-</span>
        )}
      </div>

      {error && (
        <p className="mt-1 text-sm text-red-600">{error}</p>
      )}

      {fieldMetadata?.help_text && !error && (
        <p className="mt-1 text-sm text-gray-500">{fieldMetadata.help_text}</p>
      )}
    </div>
  );
}
```

**Edit mode** (default when `readOnly` is false/undefined):

```tsx
return (
  <div className="mb-4">
    {displayLabel && (
      <label className="block text-sm font-medium text-gray-700 mb-2">
        {displayLabel}
        {required && <span className="text-red-500 ml-1">*</span>}
      </label>
    )}

    <input
      type="url"
      value={value || ''}
      onChange={(e) => onChange(e.target.value)}
      placeholder={displayPlaceholder}
      disabled={disabled}
      required={required}
      maxLength={fieldMetadata?.max_length}
      className={`
        w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
        ${error ? 'border-red-500' : 'border-gray-300'}
        ${disabled ? 'bg-gray-100 cursor-not-allowed' : 'bg-white'}
      `}
    />

    {error && (
      <p className="mt-1 text-sm text-red-600">{error}</p>
    )}

    {fieldMetadata?.help_text && !error && (
      <p className="mt-1 text-sm text-gray-500">{fieldMetadata.help_text}</p>
    )}
  </div>
);
```

**Computed display values** (place before the early-return `if (readOnly)` block):

```typescript
const displayLabel = label || fieldMetadata?.label || fieldMetadata?.name;
const displayPlaceholder = placeholder || fieldMetadata?.placeholder || 'https://...';
```

**Full file structure**:

```typescript
import React from 'react';
import type { FieldComponentProps } from '../../types';

/**
 * URL input component for LinkField.
 * Edit mode: renders <input type="url"> with label and validation error display.
 * Read-only mode: renders an <a> anchor link (or a dash when empty).
 * The anchor target attribute is read from fieldMetadata.target (default: '_blank').
 */
const LinkInput: React.FC<FieldComponentProps> = ({
  value,
  onChange,
  error,
  disabled = false,
  readOnly = false,
  required = false,
  fieldMetadata,
  placeholder,
  label
}) => {
  const displayLabel = label || fieldMetadata?.label || fieldMetadata?.name;
  const displayPlaceholder = placeholder || fieldMetadata?.placeholder || 'https://...';

  if (readOnly) {
    // ... read-only JSX shown above
  }

  // ... edit mode JSX shown above
};

export default LinkInput;
```

File length: approximately 80 lines (well under the 300-line CLAUDE.md limit).

---

### 3. FieldComponent.tsx — Register LinkInput

**File**: `gravitycar-frontend/src/components/fields/FieldComponent.tsx`

**Change 1**: Add import after the existing `RadioGroup` import (line 19):

```typescript
import LinkInput from './LinkInput';
```

**Change 2**: Add entry to `componentMap` after `'RadioGroup': RadioGroup,` (line 36):

```typescript
'LinkInput': LinkInput,
```

The `componentMap` object after both changes will contain the new entry:

```typescript
const componentMap: Record<string, React.ComponentType<BaseFieldComponentProps>> = {
  'TextInput': TextInput,
  'EmailInput': EmailInput,
  'PasswordInput': PasswordInput,
  'Checkbox': Checkbox,
  'Select': Select,
  'TextArea': TextArea,
  'NumberInput': NumberInput,
  'DatePicker': DatePicker,
  'DateTimePicker': DateTimePicker,
  'HiddenInput': HiddenInput,
  'RelatedRecordSelect': RelatedRecordSelect,
  'ImageUpload': ImageUpload,
  'MultiSelect': MultiSelect,
  'RadioGroup': RadioGroup,
  'LinkInput': LinkInput,   // <-- NEW
};
```

No other changes to `FieldComponent.tsx`.

---

### 4. GenericCrudPage.tsx — case 'Link' in renderFieldValue()

**File**: `gravitycar-frontend/src/components/crud/GenericCrudPage.tsx`

**Where to insert**: Inside the `renderFieldValue()` switch statement, after the `case 'Video':` block (ends around line 333 with `break;` or closing brace), before the `default:` case.

**The note about early empty check**: The function already returns early at lines 249–251
with a dash span when `value` is null/undefined/empty string. When execution reaches the
switch, `value` is guaranteed non-empty, so the Link case does not need its own empty guard.

**New case to add**:

```tsx
case 'Link':
  return (
    <a
      href={stringValue}
      target={fieldMeta.target ?? '_blank'}
      rel="noopener noreferrer"
      className="text-blue-600 hover:text-blue-800 break-all underline"
    >
      {stringValue}
    </a>
  );
```

**Exact insertion point**: The new `case 'Link':` block goes immediately before the
`default:` case. In the current file, `default:` begins at approximately line 336. Insert
the new case block at that position (between the closing of the last explicit `case` and
`default:`).

**Why `fieldMeta.target ?? '_blank'`**: `fieldMeta` is of type `FieldMetadata` which, after
the types change in item 1 above, has `target?: string`. The `??` fallback ensures backward
compatibility for any metadata that does not include `target`.

**Why no `break`**: Like the other cases in this switch, the `case 'Link':` uses `return`
directly, so no `break` is needed.

---

## Error Handling

| Condition | Handling |
|-----------|----------|
| `value` is null / undefined / empty | Caught by `renderFieldValue()` early return before switch; dash is shown. In `LinkInput` read-only mode, a dash span is shown. |
| `fieldMeta.target` is undefined | `?? '_blank'` fallback ensures `_blank` is used. |
| `fieldMetadata` prop is undefined in `LinkInput` | All accesses are `fieldMetadata?.xxx` with optional chaining; display falls back gracefully. |

No new exception types needed.

---

## Unit Test Specifications

These tests cover the React component (`LinkInput`) and the `renderFieldValue` change.
Frontend tests use React Testing Library (if configured) or Jest snapshot tests.

### `LinkInput` — Edit Mode

| Case | Props | Expected DOM |
|------|-------|-------------|
| Empty value | `value=''`, `readOnly=false` | `<input type="url">` with empty value |
| With value | `value='https://ex.com'`, `readOnly=false` | `<input type="url" value="https://ex.com">` |
| With error | `error='Invalid URL'`, `readOnly=false` | `<p>` with red error text visible |
| With label | `label='My Link'` | `<label>` containing "My Link" |
| Required | `required=true` | `<span>*</span>` inside label |
| Disabled | `disabled=true` | `<input>` has `disabled` attribute and `bg-gray-100` class |
| Placeholder fallback | no `placeholder` prop, `fieldMetadata.placeholder='https://...'` | input placeholder is `'https://...'` |
| onChange fires | user types in input | `onChange` called with new value |

### `LinkInput` — Read-Only Mode

| Case | Props | Expected DOM |
|------|-------|-------------|
| Empty value | `value=''`, `readOnly=true` | Dash span; no `<a>` tag |
| Null value | `value=null`, `readOnly=true` | Dash span; no `<a>` tag |
| Non-empty value | `value='https://ex.com'`, `readOnly=true` | `<a href="https://ex.com">` with text "https://ex.com" |
| Default target | `fieldMetadata.target` undefined, `readOnly=true` | `<a target="_blank">` |
| Custom target | `fieldMetadata={..., target:'_self'}`, `readOnly=true` | `<a target="_self">` |
| rel attribute | any non-empty value, `readOnly=true` | `<a rel="noopener noreferrer">` |
| Error shown | `error='Required'`, `readOnly=true` | `<p>` with error text visible |

### Key Scenario: javascript: URL in Read-Only Mode

**Setup**: Render `<LinkInput value="javascript:alert(1)" readOnly={true} fieldMetadata={{...}} onChange={jest.fn()} />`  
**Expected**: The `<a>` tag is rendered with `href="javascript:alert(1)"`. Note: the
frontend component trusts that the backend has already rejected this value via
`LinkURLValidation`. The component itself does not re-validate. If defence-in-depth is
desired, a `scheme guard` can be added (see Notes), but the spec does not require it at the
component layer.

### Key Scenario: GenericCrudPage Link Rendering

**Setup**: Render `GenericCrudPage` with mocked metadata containing a `link` field with
`type: 'Link'`, and a row with `link: 'https://example.com'`.  
**Action**: The table renders.  
**Expected**: A cell containing `<a href="https://example.com" target="_blank"
rel="noopener noreferrer" class="...break-all...">https://example.com</a>`.

---

## Notes

1. **`type="url"` vs `type="text"`**: Using `<input type="url">` gives browsers native URL
   format hints and mobile keyboard optimization (`.com` key, etc.), consistent with spec
   §5. The backend `LinkURLValidation` is the authoritative validation; the HTML type
   attribute is a UX hint only.

2. **No scheme guard in component**: The spec places URL validation on the backend
   (`LinkURLValidation`). The React component does not duplicate that logic. Any value
   stored in the database has already been validated. The `<a href={value}>` in read-only
   mode will render whatever value comes from the API. This is the same trust model used by
   the existing `Email` case in `renderFieldValue()`.

3. **`break-all` vs `break-words`**: `break-all` is used (matching the spec §7 wording and
   the Email case in `renderFieldValue()`) to ensure long URLs wrap in narrow table cells.
   `break-words` would only break at word boundaries which URLs don't have.

4. **`underline` class**: The `<a>` tag in read-only mode uses `underline` to give a clear
   visual affordance that the text is a link. This matches standard hyperlink conventions.
   The `renderFieldValue` case also uses `underline` for consistency.

5. **Tailwind class parity with TextInput**: The edit-mode `<input>` uses the exact same
   Tailwind class string as `TextInput.tsx` (`w-full px-3 py-2 border rounded-md shadow-sm
   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500`) with the
   same conditional error/disabled variants. The read-only wrapper div uses the same
   classes as TextInput's read-only state (`w-full px-3 py-2 border rounded-md shadow-sm
   bg-gray-50 text-gray-700`).

6. **`FieldMetadata` type extension**: Adding `target?: string` to the interface is the
   correct approach because `FieldMetadata` is defined in `types/index.ts` as the shared
   type for all field metadata throughout the app. Field-specific properties for Image, Video
   etc. are already declared there (lines 259–271); `target` follows the same pattern.
