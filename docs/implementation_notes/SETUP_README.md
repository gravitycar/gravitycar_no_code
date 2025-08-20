# Gravitycar Setup Script

This setup script (`setup.php`) provides a complete initialization solution for the Gravitycar framework. It handles application bootstrapping, cache management, database schema generation, and initial data creation.

## What it does

1. **Bootstraps the Gravitycar Application**
   - Initializes the dependency injection container
   - Sets up configuration, logging, and core services
   - Validates database connectivity

2. **Clears and Rebuilds Cache**
   - Deletes existing cache files (api_routes.php, metadata_cache.php)
   - Clears MetadataEngine internal caches
   - Forces fresh reload of all metadata and API routes
   - Rebuilds optimized cache files for performance

3. **Generates Database Schema**
   - Creates the database if it doesn't exist
   - Uses freshly loaded metadata to generate/update tables
   - Handles core fields (id, timestamps, soft delete, etc.)
   - Creates relationship tables

4. **Creates Sample User Records**
   - Creates an admin user for you: `mike@gravitycar.com`
   - Creates additional sample users with different roles
   - Safely skips users that already exist
   - Uses proper password hashing

## Usage

Simply run the script from the project root:

```bash
php setup.php
```

## Created Users

The script creates the following users:

| Username | Password | Role | Timezone |
|----------|----------|------|----------|
| mike@gravitycar.com | secure123 | admin | UTC |
| admin@example.com | admin123 | admin | UTC |
| john@example.com | manager123 | manager | America/New_York |
| jane@example.com | user123 | user | America/Los_Angeles |

## Safety Features

- **Idempotent**: Can be run multiple times safely
- **Cache Refresh**: Always rebuilds fresh cache files from current metadata
- **Skip Existing**: Won't create duplicate users
- **Error Handling**: Comprehensive error handling with clear messages
- **Logging**: All operations are logged for debugging
- **Validation**: Checks for database connectivity and required fields

## Requirements

- PHP 8.2+
- MySQL 8.0+ (configured in config.php)
- Composer dependencies installed
- Valid config.php file with database credentials

## What Gets Created

### Cache Files
- `cache/metadata_cache.php` - Cached model and relationship metadata
- `cache/api_routes.php` - Cached API route definitions for fast lookup

### Database Tables
- `users` - User accounts and authentication
- `movies` - Movie information
- `movie_quotes` - Movie quotes
- `rel_1_movies_M_movie_quotes` - Movie-to-quotes relationships

### Core Fields (added to all tables)
- `id` - UUID primary key
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp
- `deleted_at` - Soft delete timestamp
- `created_by` - User who created the record
- `updated_by` - User who last updated the record
- `deleted_by` - User who deleted the record

## Testing

After running setup, you can verify everything works with:

```bash
php test_setup.php
```

This will test:
- User creation and retrieval
- ModelFactory functionality
- Database connectivity
- Model relationships

## Troubleshooting

### Database Connection Issues
- Verify config.php has correct database credentials
- Ensure MySQL server is running
- Check database user has CREATE privileges

### Cache Issues
- If metadata changes, run setup again to refresh cache
- Check file permissions on cache/ directory
- Verify all metadata files are valid PHP arrays

### Performance Issues
- Ensure PHP can write to logs/ directory
- Check file permissions on config.php

### Metadata Issues
- Verify all model metadata files exist in src/Models/
- Check for syntax errors in metadata files

## Next Steps

After running setup:

1. **Test the API**: Use the ModelBaseAPIController endpoints
2. **Create Additional Data**: Use ModelFactory to create more records
3. **Set up Authentication**: Implement login/session management
4. **Build Frontend**: Create UI components for your models
5. **Add Business Logic**: Extend models with custom methods

## Advanced Usage

You can customize the setup by modifying the `$usersData` array in the script to create different initial users, or extend the script to create additional sample data for your specific use case.
