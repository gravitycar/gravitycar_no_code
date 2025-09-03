# Gravitycar Cache Rebuild Tool - Usage Guide

## Overview
The Gravitycar Cache Rebuild tool (`gravitycar_cache_rebuild`) is a VS Code language model tool that executes the `setup.php` script to refresh the Gravitycar Framework's cache files and run setup tasks.

## When to Use This Tool

### 🔄 **Model and Metadata Changes**
- After adding new model classes to `src/Models/`
- After modifying existing model field definitions
- After adding or changing relationship definitions
- After updating validation rules or field types
- After modifying model metadata configurations

### 🛣️ **API and Routing Changes**
- After adding new API controller classes
- After adding new API endpoints or routes
- After modifying existing API controller methods
- After changes to route configurations or path patterns

### 🏗️ **Framework Structure Changes**
- After adding new classes to the application
- After modifying dependency injection configurations
- After updating service definitions in ServiceLocator
- After structural changes to the framework architecture

### 🗄️ **Database Schema Updates**
- When database schema needs to be updated from model metadata
- After adding new tables through model definitions
- After modifying field types that affect database columns
- When database migrations need to be applied

### 🔧 **Troubleshooting**
- When experiencing cache-related issues or inconsistencies
- When models or API endpoints are not being recognized
- When debugging metadata loading problems
- After framework installation or major updates

## What the Tool Does

The `gravitycar_cache_rebuild` tool executes `setup.php` which performs these operations:

### 1. **Application Bootstrap**
- Initializes the Gravitycar framework
- Sets up service container and dependency injection
- Configures error handling and logging
- Establishes database connections

### 2. **Cache Management**
- 🧹 **Clears existing cache files** from `cache/` directory
- 🗑️ **Clears MetadataEngine internal caches**
- 🗑️ **Clears DocumentationCache**

### 3. **Cache Rebuilding**
- 📋 **Rebuilds metadata cache** (`cache/metadata_cache.php`) from all model definitions
- 🛣️ **Rebuilds API routes cache** from all controller classes
- 📊 **Reports cache statistics** (model count, relationship count, route count)

### 4. **Router Testing**
- 🧪 **Tests router functionality** with sample GET /Users request
- ✅ **Verifies route handling** and ModelBaseAPIController integration

### 5. **Database Operations**
- 🏗️ **Creates database** if it doesn't exist
- 📋 **Generates/updates database schema** from cached metadata
- 🔐 **Seeds authentication roles and permissions**

### 6. **Sample Data Creation**
- 👤 **Creates sample user accounts** for testing
- 🔑 **Sets up default roles** (admin, manager, user, guest)
- 🛡️ **Configures default permissions** for CRUD operations

## Tool Options

```typescript
interface CacheRebuildInput {
    fullSetup?: boolean;      // Run complete setup (default: true)
    clearOnly?: boolean;      // Only clear cache files (not implemented)
    skipDatabase?: boolean;   // Skip database operations (not implemented)
    skipUsers?: boolean;      // Skip user creation (not implemented)
    verbose?: boolean;        // Enable verbose output (default: false)
}
```

## Usage Examples

### Basic Cache Rebuild
```
Use the gravitycar_cache_rebuild tool to refresh the framework cache after model changes.
```

### Verbose Output for Debugging
```
Use the gravitycar_cache_rebuild tool with verbose output enabled to debug cache issues.
```

### Full Setup After Major Changes
```
Run a complete framework setup including database schema updates and sample data creation.
```

## Expected Output

The tool provides detailed colored output showing:

```
=== Gravitycar Framework Setup ===
✓ Gravitycar application bootstrapped successfully
✓ Deleted cache file: metadata_cache.php
✓ MetadataEngine caches cleared
✓ DocumentationCache cleared
✓ Metadata cache rebuilt: 8 models, 12 relationships
✓ API routes cache rebuilt: 45 routes registered
✓ Router test completed successfully
✓ Database schema generated successfully
✓ Authentication roles and permissions seeded successfully
✓ Created user: admin@example.com (ID: uuid-here)
🎉 Setup completed successfully!
```

## Performance Notes

- **Execution Time**: 30-60 seconds for full setup
- **Memory Usage**: 20-50MB depending on model complexity
- **Database Impact**: Creates/updates tables, safe for existing data
- **Cache Size**: Typically 100KB-1MB for metadata cache

## Integration with Development Workflow

### After Model Changes
1. Modify model class in `src/Models/`
2. Run cache rebuild tool
3. Verify API endpoints work correctly
4. Run tests to confirm functionality

### After API Changes  
1. Add/modify API controller
2. Run cache rebuild tool
3. Test new endpoints
4. Update documentation if needed

### During Development Setup
1. Clone repository
2. Install dependencies
3. Run cache rebuild tool (full setup)
4. Begin development

## Error Handling

The tool handles common errors gracefully:
- **Permission Issues**: Reports file system permission problems
- **Database Errors**: Shows database connection or schema issues
- **PHP Errors**: Captures and reports PHP runtime errors
- **Timeout Issues**: 2-minute timeout for long-running operations

## Troubleshooting

### Tool Not Available
- Ensure VS Code extension is compiled: `npm run compile`
- Restart VS Code to reload extension
- Check extension activation in VS Code output

### Setup Failures
- Verify database credentials in `config.php`
- Check file system permissions for `cache/` directory
- Ensure PHP and required extensions are installed
- Review setup.php output for specific error details

### Cache Issues
- Manually delete `cache/` directory contents
- Run tool again to regenerate all cache files
- Check disk space and file system permissions

## Related Files

- **`setup.php`** - Main setup script executed by the tool
- **`cache/metadata_cache.php`** - Generated metadata cache file
- **`src/Models/`** - Model classes that populate metadata cache
- **`src/Api/`** - API controllers that populate routes cache
- **`config.php`** - Database and application configuration
