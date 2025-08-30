# DatabaseConnector JOIN Alias Fix

## Problem Description
The DatabaseConnector's `find()` method was generating SQL with inconsistent JOIN aliases that caused an off-by-one error. The SELECT clause referenced aliases like `rel_1`, `rel_2`, `rel_3` while the JOIN clauses used `rel_0`, `rel_1`, `rel_2`. 

**Example problematic SQL:**
```sql
SELECT movie_quotes.id, ..., CONCAT_WS(' ', COALESCE(rel_1.first_name, ''), ...) as created_by_name, ...
FROM movie_quotes movie_quotes 
LEFT JOIN users rel_0 ON movie_quotes.created_by = rel_0.id 
LEFT JOIN users rel_1 ON movie_quotes.updated_by = rel_1.id 
...
```

Notice that the SELECT clause uses `rel_1.first_name` but the first JOIN creates `rel_0`.

## Root Cause
In the `handleRelatedRecordField()` method, the join counter was being incremented **before** the `concatDisplayName()` method was called:

1. Line 569: `$joinAlias = "rel_{$this->joinCounter}";` (uses current counter, e.g., 0)
2. Line 570: `$this->joinCounter++;` (increments to 1)  
3. Line 581: `$concatDisplayName = $this->concatDisplayName($relatedModel, $field);` (uses incremented counter, e.g., 1)

This caused the JOIN to be created with `rel_0` but the SELECT clause to reference `rel_1`.

## Solution Implemented

### 1. Fixed Counter Timing
**File**: `/src/Database/DatabaseConnector.php`
**Method**: `handleRelatedRecordField()`

- Moved the counter increment to **after** both the JOIN creation and SELECT clause building
- This ensures both operations use the same counter value

### 2. Added Table Name Prefixes  
- Changed alias format from `rel_N` to `{table_name}_rel_N`
- Examples: `users_rel_0`, `movies_rel_3`
- This makes the SQL more readable and debuggable

### 3. Updated concatDisplayName Method Signature
**Method**: `concatDisplayName()`

- Added `string $joinAlias` parameter
- Removed dependency on `$this->joinCounter` which was causing the off-by-one error
- Now uses the exact alias passed from the calling method

## Code Changes

### Before:
```php
$joinAlias = "rel_{$this->joinCounter}";
$this->joinCounter++;

$queryBuilder->leftJoin(..., $joinAlias, ...);
$concatDisplayName = $this->concatDisplayName($relatedModel, $field);
```

### After:
```php
$joinAlias = "{$relatedModel->getTableName()}_rel_{$this->joinCounter}";

$queryBuilder->leftJoin(..., $joinAlias, ...);
$concatDisplayName = $this->concatDisplayName($relatedModel, $field, $joinAlias);

$this->joinCounter++; // Increment AFTER using the alias
```

### concatDisplayName Method:
```php
// Before
private function concatDisplayName($relatedModel, \Gravitycar\Fields\RelatedRecordField $field): string {
    // Used $this->joinCounter (incorrect value)
    return "COALESCE(rel_{$this->joinCounter}.{$column}, '')";
}

// After  
private function concatDisplayName($relatedModel, \Gravitycar\Fields\RelatedRecordField $field, string $joinAlias): string {
    // Uses exact alias passed as parameter
    return "COALESCE({$joinAlias}.{$column}, '')";
}
```

## Verification Results

### Generated SQL (After Fix):
```sql
SELECT movie_quotes.id, movie_quotes.created_at, movie_quotes.updated_at, movie_quotes.deleted_at, 
       movie_quotes.created_by, CONCAT_WS(' ', COALESCE(users_rel_0.first_name, ''), COALESCE(users_rel_0.last_name, ''), COALESCE(users_rel_0.username, '')) as created_by_name, 
       movie_quotes.updated_by, CONCAT_WS(' ', COALESCE(users_rel_1.first_name, ''), COALESCE(users_rel_1.last_name, ''), COALESCE(users_rel_1.username, '')) as updated_by_name, 
       movie_quotes.deleted_by, CONCAT_WS(' ', COALESCE(users_rel_2.first_name, ''), COALESCE(users_rel_2.last_name, ''), COALESCE(users_rel_2.username, '')) as deleted_by_name, 
       movie_quotes.quote, movie_quotes.movie_id, COALESCE(movies_rel_3.name, '') as movie_name 
FROM movie_quotes movie_quotes 
LEFT JOIN users users_rel_0 ON movie_quotes.created_by = users_rel_0.id 
LEFT JOIN users users_rel_1 ON movie_quotes.updated_by = users_rel_1.id 
LEFT JOIN users users_rel_2 ON movie_quotes.deleted_by = users_rel_2.id 
LEFT JOIN movies movies_rel_3 ON movie_quotes.movie_id = movies_rel_3.id 
WHERE movie_quotes.id = :id LIMIT 1
```

### Alias Analysis:
- **SELECT clause aliases**: users_rel_0, users_rel_1, users_rel_2, movies_rel_3
- **JOIN clause aliases**: users_rel_0, users_rel_1, users_rel_2, movies_rel_3
- **✅ Result**: Perfect match - no off-by-one error

### Table Prefix Analysis:
- **users** table → **users_rel_0, users_rel_1, users_rel_2**
- **movies** table → **movies_rel_3**  
- **✅ Result**: All table names correctly prefixed in aliases

## Testing
- ✅ All existing unit tests pass (1017 tests, 4566 assertions)
- ✅ RelatedRecordField tests pass (17 tests, 43 assertions)
- ✅ API calls return correct data with populated relationship fields
- ✅ SQL analysis script confirms alias consistency

## Benefits
1. **Fixed SQL Generation**: Eliminates off-by-one alias errors
2. **Improved Readability**: Table-prefixed aliases make SQL easier to debug
3. **Better Maintainability**: Cleaner code with explicit alias passing
4. **Backwards Compatible**: No API changes, all existing functionality preserved

## Files Modified
- `/src/Database/DatabaseConnector.php`
  - `handleRelatedRecordField()` method  
  - `concatDisplayName()` method signature and implementation
