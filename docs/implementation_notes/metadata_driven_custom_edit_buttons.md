# Metadata-Driven Custom Edit Buttons Implementation

## Overview
Successfully implemented a metadata-driven system for adding custom buttons to model edit forms, specifically for TMDB functionality in movie editing.

## Architecture

### **Metadata-Driven Approach**
Instead of hardcoding buttons in React components, buttons are now defined declaratively in PHP metadata files and rendered dynamically by the frontend.

### **Key Components**

#### 1. **Backend Metadata Configuration**
**File**: `/src/Models/movies/movies_metadata.php`
```php
'ui' => [
    'editButtons' => [
        [
            'name' => 'tmdb_search',
            'label' => 'Choose TMDB Match',
            'type' => 'tmdb_search',
            'variant' => 'secondary',
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
]
```

#### 2. **TypeScript Interface Definitions**
**File**: `/gravitycar-frontend/src/types/index.ts`
```typescript
// Enhanced UIMetadata interface
export interface UIMetadata {
  listFields: string[];
  createFields: string[];
  editFields?: string[];
  relationshipFields?: Record<string, RelationshipFieldMetadata>;
  relatedItemsSections?: Record<string, RelatedItemsSectionMetadata>;
  editButtons?: EditButtonMetadata[]; // NEW
}

// Button metadata structure
export interface EditButtonMetadata {
  name: string;
  label: string;
  type: string; // Button action type
  variant?: string; // Styling variant
  showWhen?: ButtonCondition; // Visibility condition
  description?: string; // Tooltip/help text
}

// Condition evaluation for button visibility
export interface ButtonCondition {
  field: string; // Field name to evaluate
  condition: 'has_value' | 'is_empty' | 'equals' | 'not_equals';
  value?: any; // Value to compare against
}
```

#### 3. **Enhanced ModelForm Component**
**File**: `/gravitycar-frontend/src/components/forms/ModelForm.tsx`

**Key Features:**
- **Condition Evaluation**: Smart button visibility based on form data
- **Variant Styling**: Support for different button styles (primary, secondary, danger, etc.)
- **TMDB Integration**: Specific handlers for TMDB search and clear operations
- **Type Safety**: Full TypeScript integration with proper error handling

**Core Functions:**
```typescript
// Evaluate button visibility conditions
const evaluateShowCondition = (condition: any): boolean => {
  const fieldValue = formData[condition.field];
  switch (condition.condition) {
    case 'has_value': return fieldValue !== undefined && fieldValue !== null && fieldValue !== '';
    case 'is_empty': return fieldValue === undefined || fieldValue === null || fieldValue === '';
    case 'equals': return fieldValue === condition.value;
    case 'not_equals': return fieldValue !== condition.value;
    default: return true;
  }
};

// Get button styling classes
const getButtonVariantClasses = (variant?: string): string => {
  switch (variant) {
    case 'primary': return 'bg-blue-600 text-white border-blue-600 hover:bg-blue-700';
    case 'secondary': return 'bg-white text-blue-600 border-blue-600 hover:bg-blue-50';
    case 'danger': return 'bg-white text-red-600 border-red-600 hover:bg-red-50';
    // ... etc
  }
};

// Handle custom button actions
const handleCustomButtonClick = async (button: any) => {
  switch (button.type) {
    case 'tmdb_search': await handleTMDBSearch(); break;
    case 'tmdb_clear': handleTMDBClear(); break;
    default: console.warn(`Unknown button type: ${button.type}`);
  }
};
```

## Button Behavior

### **TMDB Search Button**
- **Shows When**: Movie has a name (name field has value)
- **Style**: Secondary (blue border, white background)
- **Action**: Opens TMDB search dialog with all available matches
- **Result**: Updates movie with selected TMDB data

### **Clear TMDB Data Button**
- **Shows When**: Movie has TMDB data (tmdb_id has value)
- **Style**: Danger (red text, indicates destructive action)
- **Action**: Removes TMDB association and clears auto-populated fields
- **Result**: Resets synopsis, poster_url, trailer_url, obscurity_score, release_year

## Extensibility

### **Adding New Button Types**
1. **Define in metadata**:
```php
[
    'name' => 'custom_action',
    'label' => 'Custom Action',
    'type' => 'custom_type',
    'variant' => 'success',
    'showWhen' => ['field' => 'status', 'condition' => 'equals', 'value' => 'ready']
]
```

2. **Add handler in ModelForm**:
```typescript
case 'custom_type':
  handleCustomAction();
  break;
```

### **Supported Conditions**
- `has_value`: Field is not empty/null/undefined
- `is_empty`: Field is empty/null/undefined  
- `equals`: Field equals specific value
- `not_equals`: Field does not equal specific value

### **Supported Variants**
- `primary`: Main action (blue background)
- `secondary`: Secondary action (blue border)
- `danger`: Destructive action (red)
- `success`: Positive action (green)
- `warning`: Caution action (yellow)
- Default: Gray styling if no variant specified

## Benefits

### **1. Metadata-Driven Architecture**
- ✅ Configuration lives in backend metadata, not frontend code
- ✅ No frontend deploys needed for button changes
- ✅ Consistent with framework's metadata-driven philosophy

### **2. Type Safety & Maintainability**
- ✅ Full TypeScript support with proper interfaces
- ✅ Compile-time validation of button configurations
- ✅ Clear separation of concerns

### **3. Flexible & Extensible**
- ✅ Easy to add new button types for any model
- ✅ Declarative condition system for complex visibility rules
- ✅ Variant system for consistent styling

### **4. User Experience**
- ✅ Context-aware buttons (only show when relevant)
- ✅ Clear visual hierarchy with variant styling
- ✅ Tooltip descriptions for button actions

## Testing Verification

### **Manual Testing Steps**
1. Navigate to `/movies` page
2. Edit any movie record (most have TMDB data)
3. Verify custom buttons appear next to Cancel/Update
4. Test button functionality:
   - "Choose TMDB Match" opens search dialog
   - "Clear TMDB Data" removes TMDB fields
5. Test conditional rendering by editing movies with/without TMDB data

### **Expected Results**
- Movies with TMDB data: Show both buttons
- Movies without TMDB data: Show only search button
- Button styling matches variant specifications
- Tooltips display description text

## Implementation Status
✅ **Backend metadata configuration** - Complete  
✅ **TypeScript interface definitions** - Complete  
✅ **ModelForm component enhancement** - Complete  
✅ **TMDB functionality integration** - Complete  
✅ **Condition evaluation system** - Complete  
✅ **Variant styling system** - Complete  
✅ **Cache rebuild and API verification** - Complete  

## Future Enhancements

### **Potential Extensions**
1. **Button Groups**: Organize related buttons into groups
2. **Icon Support**: Add icon support for visual button identification
3. **Permission-Based Visibility**: Hide buttons based on user permissions
4. **Async Button States**: Loading states for async button actions
5. **Custom Validation**: Button-specific validation before action execution

### **Other Models**
This system can be extended to any model:
- **Users**: Password reset, role assignment buttons
- **Orders**: Payment processing, fulfillment buttons  
- **Articles**: Publishing workflow buttons
- **Files**: Processing, conversion buttons

The metadata-driven approach makes adding model-specific functionality seamless and maintainable across the entire application.
