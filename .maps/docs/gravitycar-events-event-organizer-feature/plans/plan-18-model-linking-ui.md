# Implementation Plan: Model Linking UI

## Spec Context

When creating or editing an event, the admin can optionally link the event to a record from any other model in the system (e.g., link a "Book Club Meeting" event to a specific Books record). This uses the `linked_model_name` and `linked_record_id` fields on the Events model. Additionally, the Chart of Goodness header displays the linked record's info and image (if the linked model has an Image-type field).

- **Catalog item**: 18 - Model Linking UI
- **Specification section**: UI Components -- Model Linking UI, UI Components -- Chart of Goodness (Header area)
- **Acceptance criteria addressed**: AC-12

## Dependencies

- **Blocked by**: Item 3 (Events Model -- provides linked_model_name and linked_record_id fields), Item 16 (Chart of Goodness UI -- provides ChartOfGoodness.tsx to integrate linked record display into)
- **Uses**: `gravitycar-frontend/src/services/api.ts` (apiService for fetching model lists and records), `gravitycar-frontend/src/components/fields/RelatedRecordSelect.tsx` (pattern reference for search/select UI), `gravitycar-frontend/src/services/navigationService.ts` (to get available model names), `gravitycar-frontend/src/hooks/useModelMetadata.ts` (to fetch metadata for the selected model)

## File Changes

### New Files

- `gravitycar-frontend/src/components/fields/ModelLinker.tsx` -- Two-step picker: model selector dropdown + record search/select
- `gravitycar-frontend/src/components/fields/__tests__/ModelLinker.test.tsx` -- Unit tests

### Modified Files

- `gravitycar-frontend/src/pages/ChartOfGoodness.tsx` -- Add linked record display (info + image) in the header area
- `gravitycar-frontend/src/services/api.ts` -- Add `getModelList()` and `getLinkedRecordWithMetadata()` methods

## Implementation Details

### 1. API Service Methods

**File**: `gravitycar-frontend/src/services/api.ts`

Add two methods to the `ApiService` class:

```typescript
/**
 * Get list of available model names from the navigation endpoint.
 * Returns model names that the current user has access to.
 */
async getAvailableModels(): Promise<Array<{ name: string; title: string }>> {
  const response = await this.api.get('/navigation');
  const navData = response.data as NavigationResponse;
  if (navData.success && navData.data?.models) {
    return navData.data.models.map((m: NavigationItem) => ({
      name: m.name,
      title: m.title,
    }));
  }
  return [];
}

/**
 * Fetch a record by model name and ID, plus the model's metadata
 * to determine if it has an Image field.
 */
async getRecordWithImageInfo(
  modelName: string,
  recordId: string
): Promise<{ record: Record<string, any>; imageFieldName: string | null }> {
  const [recordResponse, metadataResponse] = await Promise.all([
    this.api.get(`/${modelName}/${recordId}`),
    this.api.get(`/metadata/models/${modelName}`),
  ]);

  const record = recordResponse.data?.data ?? recordResponse.data ?? {};
  const metadata = metadataResponse.data?.data ?? metadataResponse.data ?? {};

  // Find the first Image-type field in metadata
  let imageFieldName: string | null = null;
  if (metadata.fields) {
    for (const [fieldName, fieldDef] of Object.entries(metadata.fields)) {
      if ((fieldDef as any).type === 'Image') {
        imageFieldName = fieldName;
        break;
      }
    }
  }

  return { record, imageFieldName };
}
```

These methods reuse the existing axios instance (`this.api`) which already has JWT auth and error handling via interceptors.

### 2. ModelLinker Component

**File**: `gravitycar-frontend/src/components/fields/ModelLinker.tsx`

**Props interface:**

```typescript
interface ModelLinkerProps {
  modelName: string | null;        // Current linked_model_name value
  recordId: string | null;         // Current linked_record_id value
  onModelChange: (modelName: string | null) => void;
  onRecordChange: (recordId: string | null) => void;
  disabled?: boolean;
  error?: string;
}
```

**Component behavior (functional component with hooks):**

```typescript
import React, { useState, useEffect } from 'react';
import { apiService } from '../../services/api';
import { fetchWithDebug } from '../../utils/apiUtils';

const ModelLinker: React.FC<ModelLinkerProps> = ({
  modelName,
  recordId,
  onModelChange,
  onRecordChange,
  disabled = false,
  error,
}) => {
```

**State:**

```typescript
const [availableModels, setAvailableModels] = useState<Array<{ name: string; title: string }>>([]);
const [loadingModels, setLoadingModels] = useState(true);
const [records, setRecords] = useState<Array<{ value: string; label: string }>>([]);
const [loadingRecords, setLoadingRecords] = useState(false);
const [searchTerm, setSearchTerm] = useState('');
const [selectedRecordLabel, setSelectedRecordLabel] = useState<string | null>(null);
const [isRecordDropdownOpen, setIsRecordDropdownOpen] = useState(false);
const [displayColumns, setDisplayColumns] = useState<string[]>([]);
```

**Fetch available models on mount:**

```typescript
useEffect(() => {
  const fetchModels = async () => {
    setLoadingModels(true);
    try {
      const models = await apiService.getAvailableModels();
      // Exclude system/internal models that should not be linkable
      const EXCLUDED_MODELS = ['Events', 'Event_Proposed_Dates', 'Event_Commitments',
        'Event_Invitations', 'Event_Reminders', 'Email_Queue',
        'Jwt_Refresh_Tokens', 'Google_Oauth_Tokens', 'Permissions', 'Roles'];
      const linkableModels = models.filter(m => !EXCLUDED_MODELS.includes(m.name));
      setAvailableModels(linkableModels);
    } catch {
      setAvailableModels([]);
    } finally {
      setLoadingModels(false);
    }
  };
  fetchModels();
}, []);
```

**Key implementation note**: The `EXCLUDED_MODELS` array is defined as a constant at the top of the file (per CLAUDE.md rule about arrays used only within a method). It filters out event-related models, auth tokens, and role/permission models that make no sense as link targets.

**When model changes, reset record and fetch metadata for displayColumns:**

```typescript
const handleModelChange = (newModelName: string | null) => {
  onModelChange(newModelName);
  onRecordChange(null);
  setSelectedRecordLabel(null);
  setRecords([]);
  setSearchTerm('');

  if (newModelName) {
    // Fetch metadata to get displayColumns for record labels
    fetchWithDebug(`/metadata/models/${newModelName}`, { method: 'GET' })
      .then(res => res.json())
      .then(data => {
        const meta = data.data || data;
        setDisplayColumns(meta.displayColumns || ['name']);
      })
      .catch(() => setDisplayColumns(['name']));
  }
};
```

**Debounced record search (follows RelatedRecordSelect pattern):**

```typescript
useEffect(() => {
  if (!modelName) return;
  const timeout = setTimeout(() => {
    fetchRecords(searchTerm);
  }, 300);
  return () => clearTimeout(timeout);
}, [searchTerm, modelName, displayColumns]);

const fetchRecords = async (search: string) => {
  if (!modelName) return;
  setLoadingRecords(true);
  try {
    const params = new URLSearchParams({ limit: '20' });
    if (search.trim()) params.append('search', search.trim());
    const response = await fetchWithDebug(`/${modelName}?${params}`, { method: 'GET' });
    const data = await response.json();
    const rawRecords = data.success && Array.isArray(data.data) ? data.data : [];
    const options = rawRecords.map((rec: Record<string, any>) => ({
      value: rec.id,
      label: buildDisplayLabel(rec, displayColumns),
    }));
    setRecords(options);
  } catch {
    setRecords([]);
  } finally {
    setLoadingRecords(false);
  }
};
```

**Display label builder (reuses displayColumns pattern from RelatedRecordSelect):**

```typescript
const buildDisplayLabel = (record: Record<string, any>, cols: string[]): string => {
  const parts = cols
    .map(col => record[col])
    .filter(v => v && String(v).trim())
    .map(v => String(v).trim());
  if (parts.length > 0) return parts.join(' ');

  // Fallback chain
  for (const fallback of ['name', 'title', 'username', 'email']) {
    if (record[fallback]) return String(record[fallback]);
  }
  return `Record #${record.id}`;
};
```

**Render structure:**

```tsx
return (
  <div className="mb-4 space-y-3">
    <label className="block text-sm font-medium text-gray-700">
      Link to Model Record (optional)
    </label>

    {/* Step 1: Model Selector Dropdown */}
    <div>
      <label className="block text-xs text-gray-500 mb-1">Model</label>
      <select
        value={modelName || ''}
        onChange={(e) => handleModelChange(e.target.value || null)}
        disabled={disabled || loadingModels}
        className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm
                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                   disabled:bg-gray-100 disabled:cursor-not-allowed"
      >
        <option value="">-- No linked model --</option>
        {availableModels.map(m => (
          <option key={m.name} value={m.name}>{m.title}</option>
        ))}
      </select>
    </div>

    {/* Step 2: Record Search/Select (only shown when model selected) */}
    {modelName && (
      <div className="relative">
        <label className="block text-xs text-gray-500 mb-1">Record</label>
        {/* Search input + dropdown following RelatedRecordSelect pattern */}
        {/* ... search input, dropdown list, clear button ... */}
      </div>
    )}

    {error && <p className="text-sm text-red-600">{error}</p>}
  </div>
);
```

The record search/select UI follows the same pattern as `RelatedRecordSelect.tsx`: a text input for search-as-you-type with a dropdown of results, keyboard navigation, loading spinner, and a clear button. The implementation mirrors lines 528-670 of RelatedRecordSelect but is simplified (no preview/edit buttons, no relationship context).

**Integration with event create/edit forms**: The ModelLinker component is used within the Events model's create/edit form. The GenericCrudPage form renders fields based on metadata. The `linked_model_name` and `linked_record_id` fields in Events metadata should specify a custom React component. However, since these are two separate fields that operate together, the recommended approach is:

- In the Events metadata, mark `linked_model_name` and `linked_record_id` with `showInForm: false` (to hide them from the default field renderer).
- In the Events metadata `ui.createFields` and `ui.editFields`, add a custom section entry referencing `ModelLinker`.
- Alternatively, if the form renderer does not support custom section components, the ModelLinker can be rendered as a custom component for the `linked_model_name` field, and internally manage both `linked_model_name` and `linked_record_id` via its `onModelChange` and `onRecordChange` callbacks that update form state for both fields.

**Recommended approach**: Register ModelLinker as the React component for `linked_model_name` in the Events metadata. The component receives the current form values and updates both `linked_model_name` and `linked_record_id` through a parent form state callback. This requires passing the full form value set to ModelLinker so it can read `linked_record_id` as well.

### 3. Chart of Goodness Header -- Linked Record Display

**File**: `gravitycar-frontend/src/pages/ChartOfGoodness.tsx`

**Changes to existing header section:**

Add state for linked record data:

```typescript
const [linkedRecord, setLinkedRecord] = useState<{
  record: Record<string, any>;
  imageFieldName: string | null;
  modelName: string;
} | null>(null);
```

Fetch linked record when chart data loads (add to the `fetchData` function or a separate useEffect):

```typescript
useEffect(() => {
  if (!chartData?.event.linked_model_name || !chartData?.event.linked_record_id) {
    setLinkedRecord(null);
    return;
  }
  const fetchLinkedRecord = async () => {
    try {
      const result = await apiService.getRecordWithImageInfo(
        chartData.event.linked_model_name!,
        chartData.event.linked_record_id!
      );
      setLinkedRecord({
        ...result,
        modelName: chartData.event.linked_model_name!,
      });
    } catch {
      setLinkedRecord(null);
    }
  };
  fetchLinkedRecord();
}, [chartData?.event.linked_model_name, chartData?.event.linked_record_id]);
```

**Render linked record in the header area** (after event name/description/location, before banners):

```tsx
{/* Linked Record Display */}
{linkedRecord && (
  <div className="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-lg flex items-center gap-4">
    {/* Image from linked record (if Image field exists) */}
    {linkedRecord.imageFieldName && linkedRecord.record[linkedRecord.imageFieldName] && (
      <img
        src={linkedRecord.record[linkedRecord.imageFieldName]}
        alt={`${linkedRecord.modelName} image`}
        className="w-20 h-20 object-cover rounded-md flex-shrink-0"
      />
    )}
    {/* Linked record info */}
    <div>
      <p className="text-xs text-gray-500 uppercase tracking-wide">
        Linked {linkedRecord.modelName}
      </p>
      <a
        href={`/${linkedRecord.modelName.toLowerCase()}/${linkedRecord.record.id}`}
        className="text-indigo-600 hover:underline font-medium"
      >
        {getLinkedRecordDisplayName(linkedRecord.record)}
      </a>
    </div>
  </div>
)}
```

**Helper for linked record display name:**

```typescript
const getLinkedRecordDisplayName = (record: Record<string, any>): string => {
  for (const field of ['name', 'title', 'username', 'email']) {
    if (record[field]) return String(record[field]);
  }
  return `Record #${record.id}`;
};
```

**Key decision**: The image is rendered as a small thumbnail (80x80px) in the header. The image URL comes from the linked record's first Image-type field value. The `getRecordWithImageInfo` API method determines which field is the Image field by inspecting metadata.

## Error Handling

- **Model list fetch failure**: ModelLinker shows an empty dropdown with no models. No crash.
- **Record search failure**: Record dropdown shows "No results" message. No crash.
- **Linked record fetch failure on chart page**: Linked record section simply does not render (`linkedRecord` stays null).
- **Image URL broken/missing**: The `<img>` tag only renders if `linkedRecord.imageFieldName` exists AND the record has a value for that field. A broken image URL shows the browser's default broken image icon; this is acceptable.
- **Model has no Image field**: `imageFieldName` is null, image is not rendered, only text info is shown.

## Unit Test Specifications

**File**: `gravitycar-frontend/src/components/fields/__tests__/ModelLinker.test.tsx`

Use React Testing Library with mocks for apiService and fetchWithDebug.

### ModelLinker Component

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Renders model dropdown | Mock getAvailableModels returning [{name:'Books',title:'Books'},{name:'Movies',title:'Movies'}] | Select has "Books" and "Movies" options plus empty option | Basic render |
| Model selection triggers record reset | Select "Books", then change to "Movies" | onRecordChange(null) called, record dropdown resets | Changing model invalidates previous record |
| Record search shows results | Select "Books", type "Harry" | Dropdown shows matching records from API | Search functionality |
| Record selection fires callback | Select a record from dropdown | onRecordChange(recordId) called with correct ID | Selection callback |
| Clear record | Click clear button | onRecordChange(null) called | Clear functionality |
| Disabled state | disabled=true | Both dropdowns are disabled | Disabled prop |
| No record picker without model | No model selected | Record search field not rendered | Two-step UX |
| Excludes internal models | Mock returns Events, Books, Email_Queue | Only Books appears in dropdown | Filter logic |

### Key Scenario: Full Flow

**Setup**: Mock `getAvailableModels` to return `[{name:'Books',title:'Books'}]`. Mock `fetchWithDebug` for `/metadata/models/Books` to return `{displayColumns:['title','author']}`. Mock `fetchWithDebug` for `/Books?search=Harry&limit=20` to return two records.
**Action**: Select "Books" from model dropdown. Type "Harry" in record search. Select first result.
**Expected**: `onModelChange('Books')` called. After typing, dropdown shows 2 records with labels built from title+author. After selecting, `onRecordChange('<record-id>')` called.

### Chart Header Linked Record Display

| Case | Setup | Expected | Why |
|------|-------|----------|-----|
| Shows linked record with image | chartData has linked_model_name="Books", linked_record_id="123". Mock getRecordWithImageInfo returns record with imageFieldName="cover_image", record.cover_image="http://..." | Image thumbnail + "Linked Books" label + record name link | AC-12 with image |
| Shows linked record without image | Same but imageFieldName=null | No image, just text info and link | Model has no Image field |
| No linked record section | chartData has linked_model_name=null | No linked record div rendered | No link set |
| Linked record fetch fails | Mock getRecordWithImageInfo rejects | No linked record div rendered | Graceful failure |

## Notes

- The ModelLinker component follows the same search/dropdown pattern as `RelatedRecordSelect.tsx` (lines 45-681) but is simpler: no relationship context, no preview/edit, no create-new. It adds a model selector step before the record picker.
- The `EXCLUDED_MODELS` constant filters out models that are internal to the events feature or system infrastructure. This list should be maintained as new internal models are added.
- The navigation endpoint (`GET /navigation`) already returns the list of models the current user can access, making it the ideal source for the model dropdown. No new backend endpoint is needed.
- The image display in the chart header uses the first Image-type field found in the linked model's metadata. This is per spec: "The image SHALL be fetched from the linked record's first Image-type field as defined in its metadata."
- The linked record link in the chart header points to `/{modelName.toLowerCase()}/{recordId}`, which uses the framework's standard model routing via `DynamicModelRoute` in `App.tsx`.
