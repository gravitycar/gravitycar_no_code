# Movie Quote to Movie Relationship Bug Fix

## Issue Summary
Movie quotes were not showing their associated movie titles in the edit view because relationship records were being unnecessarily soft-deleted during updates.

## Root Cause Analysis

### **The Problem**
When editing a movie quote through the UI, the `movie_selection` relationship field was processed by the API's `ModelBaseAPIController::processParentSelection()` method, which had flawed logic:

```php
// BUGGY CODE (before fix):
// Remove any existing relationships for this child
$existingRelations = $childModel->getRelated($relationshipName);
foreach ($existingRelations as $existingRelation) {
    $childModel->removeRelation($relationshipName, $existingParent); // ❌ ALWAYS soft-deletes!
}

// Add the new relationship
$childModel->addRelation($relationshipName, $parentModel);
```

### **The Issue**
**Every time** a movie quote was updated via the API (even without changing the movie), the system would:

1. ✅ Find existing relationships
2. ❌ **Soft-delete ALL existing relationships** (even if the movie hadn't changed)
3. ✅ Create a new relationship record

This meant that updating a quote with the same movie would unnecessarily soft-delete and recreate the relationship.

### **Evidence Found**
- Quote ID `1f76191e-dffa-4a41-90d8-9b6644c86e2a` had its relationship soft-deleted at `2025-09-05 21:21:51` 
- The quote was updated at `2025-09-05 21:21:50` by Mike Andersen
- The timestamps matched exactly, confirming the bug

## The Fix

### **Improved Logic**
Modified `ModelBaseAPIController::processParentSelection()` to check if the relationship already exists with the same parent before making any changes:

```php
// FIXED CODE:
// Check if the relationship already exists with the same parent
$existingRelations = $childModel->getRelated($relationshipName);
$relationshipAlreadyExists = false;

foreach ($existingRelations as $existingRelation) {
    $existingParentId = $existingRelation['one_' . strtolower($parentModelName) . '_id'];
    
    if ($existingParentId === $parentId) {
        // Relationship already exists with the same parent - no changes needed
        $relationshipAlreadyExists = true;
        break;
    }
}

if (!$relationshipAlreadyExists) {
    // Only remove/add relationships if actually changing to a different parent
    // ... existing logic for removal and addition
}
```

### **Benefits**
- ✅ **Preserves existing relationships** when the parent hasn't changed
- ✅ **Prevents unnecessary soft-deletion** and recreation
- ✅ **Maintains data integrity** and relationship history
- ✅ **Improves performance** by avoiding unnecessary database operations

## Testing Results

### **Before Fix**
- Quote relationships were soft-deleted on every update
- Movie titles didn't appear in quote edit forms
- Unnecessary database churn with relationship recreation

### **After Fix**
- ✅ **Existing relationships preserved**: No soft-deletion when movie_selection is unchanged
- ✅ **Movie titles display correctly**: Relationship integrity maintained
- ✅ **Clean relationship changes**: Only removes/adds when actually changing movies
- ✅ **Database efficiency**: No unnecessary relationship churn

### **Test Cases Verified**
1. **Same movie update**: Relationship preserved, no soft-deletion
2. **Different movie update**: Old relationship removed, new one created
3. **Previously broken quotes**: Can be updated and relationships restored

## Files Modified
- `/src/Models/api/Api/ModelBaseAPIController.php` - Fixed `processParentSelection()` method

## Impact
- **Immediate**: Fixed movie quote edit forms showing movie titles correctly
- **Performance**: Reduced unnecessary database operations
- **Data Integrity**: Preserved relationship history and audit trails
- **User Experience**: Movie selection now works as expected in quote editing

## Prevention
This fix includes logging to help identify similar issues in the future:

```php
$this->logger->debug('Relationship already exists with same parent, skipping', [
    'child' => $childModel->getName(),
    'childId' => $childModel->get('id'),
    'parent' => $parentModelName,
    'parentId' => $parentId
]);
```

## Notes
- The fix is backward compatible and doesn't affect existing data
- Soft-deleted relationships remain in the database for audit purposes
- Future relationship updates will now be more efficient and preserve data integrity
