# Movies Edit Buttons - TMDB Functionality Implementation

## Overview
Successfully implemented metadata-driven custom edit buttons for Movies model, specifically adding TMDB re-selection functionality for existing movie records. This allows users to edit movies and search for different TMDB matches or clear existing TMDB data.

## Implementation Summary

### 1. Metadata Configuration (`src/Models/movies/movies_metadata.php`)
Enhanced the movies metadata with `editButtons` array containing two custom buttons:

```php
'editButtons' => [
    [
        'name' => 'choose_tmdb',
        'label' => 'Choose TMDB Match',
        'type' => 'tmdb_search',
        'variant' => 'primary',
        'showWhen' => [
            'field' => 'name',
            'condition' => 'has_value'
        ],
        'description' => 'Search TMDB to find and select a different movie match'
    ],
    [
        'name' => 'clear_tmdb',
        'label' => 'Clear TMDB Data',
        'type' => 'tmdb_clear',
        'variant' => 'danger',
        'showWhen' => [
            'field' => 'tmdb_id',
            'condition' => 'has_value'
        ],
        'description' => 'Remove TMDB association and auto-populated data'
    ]
]
```

**Key Features:**
- **Conditional Display**: Buttons only appear when relevant conditions are met
- **Semantic Types**: Button types indicate functionality (`tmdb_search`, `tmdb_clear`)
- **Visual Variants**: Different styling for different actions (primary, danger)

### 2. TypeScript Interface Enhancement (`gravitycar-frontend/src/types/index.ts`)
Extended type definitions to support metadata-driven edit buttons:

```typescript
export interface EditButtonMetadata {
  name: string;
  label: string;
  type: string;
  variant?: 'primary' | 'secondary' | 'danger' | 'success' | 'warning';
  showWhen?: ButtonCondition;
  description?: string;
}

export interface ButtonCondition {
  field: string;
  condition: 'has_value' | 'is_empty' | 'equals';
  value?: any;
}

export interface UIMetadata {
  // ... existing properties
  editButtons?: EditButtonMetadata[];
}
```

### 3. ModelForm Component Enhancement (`gravitycar-frontend/src/components/forms/ModelForm.tsx`)

#### Custom Button Rendering
Added comprehensive button handling logic:

```typescript
const evaluateShowCondition = (condition: ButtonCondition): boolean => {
  const fieldValue = formData[condition.field];
  
  switch (condition.condition) {
    case 'has_value':
      return fieldValue !== undefined && fieldValue !== null && fieldValue !== '';
    case 'is_empty':
      return fieldValue === undefined || fieldValue === null || fieldValue === '';
    case 'equals':
      return fieldValue === condition.value;
    default:
      return true;
  }
};
```

#### TMDB Integration Functions

**TMDB Search Handler:**
```typescript
const handleTMDBSearch = async () => {
  const response = await apiService.searchTMDB(formData.name);
  if (response.success && response.data) {
    const { exact_match, partial_matches } = response.data;
    // Open TMDBMovieSelector modal with results
  }
};
```

**TMDB Clear Handler:**
```typescript
const handleTMDBClear = () => {
  setFormData(prev => ({
    ...prev,
    tmdb_id: undefined,
    synopsis: '',
    poster_url: '',
    trailer_url: '',
    obscurity_score: undefined,
    release_year: undefined
  }));
};
```

**TMDB Movie Selection:**
```typescript
const handleTMDBMovieSelect = async (tmdbMovie: any) => {
  const enrichmentResponse = await apiService.enrichMovieWithTMDB(tmdbMovie.tmdb_id.toString());
  if (enrichmentResponse.success && enrichmentResponse.data) {
    setFormData(prev => ({ ...prev, ...enrichmentResponse.data.data }));
  }
};
```

### 4. API Response Structure Fixes
Resolved critical bug where API responses were wrapped in `ApiResponse<T>` structure:

**Problem:** Code was trying to access `response.data.exact_match` directly
**Solution:** Fixed to access `response.data.data.exact_match` (nested structure)

**API Response Format:**
```json
{
  "success": true,
  "data": {
    "exact_match": { ... },
    "partial_matches": [ ... ],
    "match_type": "exact"
  }
}
```

## User Experience

### "Choose TMDB Match" Button
- **Visibility:** Only appears when movie has a title (name field has value)
- **Functionality:** 
  1. Searches TMDB using current movie title
  2. Opens TMDBMovieSelector modal with results
  3. User can select different movie match
  4. Auto-enriches form with selected movie data
- **Styling:** Primary blue button

### "Clear TMDB Data" Button  
- **Visibility:** Only appears when movie has TMDB association (tmdb_id has value)
- **Functionality:**
  1. Clears all TMDB-related fields in form
  2. Removes tmdb_id, synopsis, poster_url, trailer_url, etc.
  3. Allows manual data entry without TMDB interference
- **Styling:** Danger red button

## Technical Benefits

### 1. Metadata-Driven Architecture
- **Declarative Configuration:** Buttons defined in PHP metadata, not hardcoded in frontend
- **Type Safety:** Full TypeScript support with proper interfaces
- **Maintainability:** Easy to add/modify buttons through metadata changes

### 2. Conditional Rendering System
- **Smart Display Logic:** Buttons only appear when contextually relevant
- **Flexible Conditions:** Supports has_value, is_empty, equals conditions
- **Extensible:** Easy to add new condition types

### 3. Reusable Infrastructure
- **Generic Implementation:** Can be applied to any model with custom buttons
- **Consistent Styling:** Variant system ensures UI consistency
- **Error Handling:** Robust error management and user feedback

## Files Modified

1. **`src/Models/movies/movies_metadata.php`** - Added editButtons metadata
2. **`gravitycar-frontend/src/types/index.ts`** - Enhanced TypeScript interfaces
3. **`gravitycar-frontend/src/components/forms/ModelForm.tsx`** - Implemented button logic
4. **Cache files** - Rebuilt metadata cache to include new button definitions

## Testing Verified

✅ **TMDB Search Button:**
- Appears only when movie has title
- Successfully searches TMDB API
- Opens modal with search results
- Enriches form data on selection

✅ **Clear TMDB Data Button:**
- Appears only when movie has TMDB ID
- Clears all TMDB fields from form
- Allows manual data entry

✅ **API Integration:**
- Fixed response structure handling
- Proper error handling and logging
- Consistent data flow

✅ **Conditional Rendering:**
- Buttons appear/disappear based on form state
- Correct styling variants applied
- Responsive behavior

## Usage Instructions

### For Users
1. **Edit any existing movie** through the Movies page
2. **Choose TMDB Match button** will appear if the movie has a title
3. **Clear TMDB Data button** will appear if the movie has TMDB data
4. **Click buttons** to search for new matches or clear existing data

### For Developers  
1. **Add editButtons array** to any model's metadata
2. **Define button properties** (name, label, type, variant, showWhen)
3. **Implement button handlers** in ModelForm for custom types
4. **Rebuild cache** using `php setup.php` after metadata changes

## Future Enhancements

### Additional Button Types
- `refresh` - Refresh data from external APIs
- `validate` - Run custom validation checks  
- `export` - Export record data
- `duplicate` - Create copy of record

### Enhanced Conditions
- `greater_than`, `less_than` - Numeric comparisons
- `contains`, `starts_with` - String pattern matching
- `in_array`, `not_in_array` - Array membership tests

### Advanced Features
- **Multi-field conditions** - AND/OR logic between conditions
- **Dynamic button labels** - Change text based on context
- **Confirmation dialogs** - User confirmation for destructive actions
- **Progress indicators** - Loading states for async operations

## Conclusion

Successfully implemented a robust, metadata-driven custom button system that enhances the Movies editing experience with TMDB re-selection capabilities. The implementation provides a solid foundation for extending similar functionality to other models and adding new button types as needed.

The solution demonstrates the power of the Gravitycar Framework's metadata-driven architecture, allowing complex UI behavior to be defined declaratively while maintaining type safety and code reusability.
