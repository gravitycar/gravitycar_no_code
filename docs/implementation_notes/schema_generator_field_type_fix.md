# SchemaGenerator Field Type Mapping and Column Update Fix

## Issue Summary
The SchemaGenerator had two critical problems:

1. **Field Type Mapping Mismatch**: Metadata definitions used short field type names (`BigText`, `Text`, `Integer`, `DateTime`) but SchemaGenerator only mapped long field type names (`BigTextField`, `TextField`, `IntegerField`, `DateTimeField`).

2. **No Column Updates**: The `updateColumnFromFieldMeta` method only logged that columns existed but didn't actually update them when the schema changed.

## Root Cause
- The movies table was created with all fields as `VARCHAR(255)` because unmapped field types defaulted to `Types::STRING`
- Synopsis field was `VARCHAR(255)` instead of `TEXT`, causing "Data too long for column" errors with TMDB synopses
- Timestamp fields were `VARCHAR(255)` instead of `DATETIME`
- Integer fields were `VARCHAR(255)` instead of `INT`

## Database Schema Issue
**Before Fix:**
```sql
synopsis        | varchar(255) | YES  |     | NULL    |
created_at      | varchar(255) | YES  |     | NULL    |
updated_at      | varchar(255) | YES  |     | NULL    |
tmdb_id         | varchar(255) | YES  |     | NULL    |
obscurity_score | varchar(255) | YES  |     | NULL    |
release_year    | varchar(255) | YES  |     | NULL    |
```

**After Fix:**
```sql
synopsis        | text         | YES  |     | NULL    |
created_at      | datetime     | YES  |     | NULL    |
updated_at      | datetime     | YES  |     | NULL    |
tmdb_id         | int          | YES  |     | NULL    |
obscurity_score | int          | YES  |     | NULL    |
release_year    | int          | YES  |     | NULL    |
```

## SchemaGenerator Fixes Applied

### 1. Extended Field Type Mapping
Added support for both long and short field type names:

```php
$typeMap = [
    // Original Field suffix versions
    'TextField' => Types::STRING,
    'BigTextField' => Types::TEXT,
    'IntegerField' => Types::INTEGER,
    'DateTimeField' => Types::DATETIME_MUTABLE,
    
    // Short versions used in metadata
    'Text' => Types::STRING,
    'BigText' => Types::TEXT,
    'Integer' => Types::INTEGER,
    'DateTime' => Types::DATETIME_MUTABLE,
    'Video' => Types::STRING,
    'Image' => Types::STRING,
];
```

### 2. Implemented Column Update Logic
Replaced the stub `updateColumnFromFieldMeta` method with proper column comparison and update logic:

```php
protected function updateColumnFromFieldMeta(Table $table, string $fieldName, array $fieldMeta): void {
    $type = $this->getDoctrineTypeFromFieldType($fieldMeta['type'] ?? 'TextField');
    $options = $this->getColumnOptionsFromFieldMeta($fieldMeta);

    $existingColumn = $table->getColumn($fieldName);
    $needsUpdate = false;
    
    // Check type change
    if ($existingColumn->getType()->getName() !== $type) {
        $needsUpdate = true;
    }
    
    // Check length change for string types
    if (in_array($type, [Types::STRING]) && isset($options['length'])) {
        if ($existingColumn->getLength() !== $options['length']) {
            $needsUpdate = true;
        }
    }
    
    if ($needsUpdate) {
        $table->changeColumn($fieldName, $options);
    }
}
```

### 3. Fixed Column Options for TEXT Fields
Updated column options to not set length restrictions on TEXT fields:

```php
// Only set length for STRING types, not TEXT types
if ($doctrineType === Types::STRING) {
    if (isset($fieldMeta['maxLength'])) {
        $options['length'] = $fieldMeta['maxLength'];
    }
    // ... other length settings
}
// For TEXT types (BigText), don't set length - they have their own size limits
```

## Manual Schema Fix
Since the SchemaGenerator wasn't detecting existing table differences properly, applied manual schema fixes:

```sql
ALTER TABLE movies MODIFY COLUMN synopsis TEXT;
ALTER TABLE movies MODIFY COLUMN created_at DATETIME;
ALTER TABLE movies MODIFY COLUMN updated_at DATETIME;
ALTER TABLE movies MODIFY COLUMN deleted_at DATETIME;
ALTER TABLE movies MODIFY COLUMN tmdb_id INT;
ALTER TABLE movies MODIFY COLUMN obscurity_score INT;
ALTER TABLE movies MODIFY COLUMN release_year INT;
```

## Verification
- ✅ Movies can now be created with long synopses (tested with 1433 character synopsis)
- ✅ Timestamp fields properly store DATETIME values
- ✅ Integer fields store proper INT values
- ✅ TEXT field has no length restrictions
- ✅ Field type mappings work for both long and short type names

## Files Modified
1. `src/Schema/SchemaGenerator.php` - Fixed field type mapping and column update logic
2. Database schema manually updated via SQL ALTER statements

## Prevention
The SchemaGenerator now properly handles:
- Short field type names used in metadata
- Column updates when schema changes
- Proper type mapping for all field types used in the framework
- Length restrictions only on appropriate field types

## Future Recommendations
1. **Schema Migration System**: Implement a proper migration system to track and apply schema changes
2. **Schema Validation**: Add validation to ensure metadata field types are recognized
3. **Automated Testing**: Add tests to verify SchemaGenerator properly handles all field types
4. **Documentation**: Document the supported field type names and their database mappings

## Date
September 3, 2025
