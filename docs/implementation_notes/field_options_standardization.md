# Field Options Loading Standardization

## Summary
Standardized the options loading convention across all field types to use only `optionsClass` and `optionsMethod` metadata properties, eliminating the legacy `className` and `methodName` naming convention.

## Changes Made

### Files Modified

#### 1. `src/Fields/EnumField.php`
- **Removed properties**: `className`, `methodName` 
- **Kept properties**: `optionsClass`, `optionsMethod`
- **Updated loadOptions()**: Simplified logic to only use the standardized naming convention
- **Added error handling**: Better logging when external class/method loading fails

#### 2. `src/Fields/RadioButtonSetField.php`
- **Removed properties**: `className`, `methodName`
- **Added properties**: `optionsClass`, `optionsMethod`
- **Updated loadOptions()**: Implemented same pattern as EnumField with improved error handling
- **Consistency**: Now follows the same convention as other option-based fields

#### 3. `src/Fields/MultiEnumField.php`
- **Removed properties**: `className`, `methodName`
- **Added properties**: `optionsClass`, `optionsMethod` 
- **Updated loadOptions()**: Implemented same pattern as EnumField with improved error handling
- **Consistency**: Now follows the same convention as other option-based fields

## Metadata Verification

### Existing Usage
✅ **No breaking changes**: Searched all existing metadata files and confirmed:
- No metadata files were using the old `className`/`methodName` conventions
- All existing enum fields use either static `options` arrays or the new `optionsClass`/`optionsMethod` convention
- The `users_metadata.php` already correctly uses `optionsClass: '\Gravitycar\Utils\Timezone'` and `optionsMethod: 'getTimezones'`

### Field Types Affected
- **EnumField**: Single-select dropdown fields
- **RadioButtonSetField**: Single-select radio button groups  
- **MultiEnumField**: Multi-select fields

## Testing Results

### Comprehensive Verification
All field types now correctly support:

1. **Static Options**: Direct options array in metadata
   ```php
   'options' => [
       'admin' => 'Administrator',
       'user' => 'Regular User'
   ]
   ```

2. **External Class Options**: Loading from utility classes
   ```php
   'optionsClass' => '\Gravitycar\Utils\Timezone',
   'optionsMethod' => 'getTimezones'
   ```

### Test Results
✅ **EnumField**: 72 options from external class, 3 options from static array  
✅ **RadioButtonSetField**: 72 options from external class  
✅ **MultiEnumField**: 72 options from external class  
✅ **User Creation**: Still works correctly with timezone field  
✅ **Backwards Compatibility**: No existing functionality broken  

## Benefits

1. **Consistency**: All option-based field types now use the same naming convention
2. **Clarity**: Property names clearly indicate their purpose (`optionsClass` vs generic `className`)
3. **Maintainability**: Single standard to remember and document
4. **Error Handling**: Improved logging when external class loading fails
5. **Code Quality**: Removed redundant fallback logic and simplified the codebase

## Developer Impact

### For New Fields
Developers creating new option-based field types should use:
- `optionsClass` - The fully qualified class name
- `optionsMethod` - The static method name that returns the options array

### For Metadata
All option-based field metadata should use:
```php
[
    'name' => 'field_name',
    'type' => 'Enum', // or RadioButtonSet, MultiEnum
    'optionsClass' => '\Namespace\ClassName',
    'optionsMethod' => 'getOptions'
]
```

## Future Considerations
- Documentation should be updated to reflect the standardized convention
- IDE autocomplete and field generators should use the new property names
- Any external code generators or tooling should be updated to use the new convention

This standardization ensures consistent, maintainable code across the entire field system while preserving all existing functionality.
