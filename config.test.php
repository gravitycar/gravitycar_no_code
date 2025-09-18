<?php
/**
 * Test Configuration for CI Environment
 * 
 * This configuration is used during testing to provide:
 * - SQLite in-memory database (no MySQL dependency)
 * - Mock API keys to prevent null assignment errors
 * - Test-specific settings
 */

return [
    'database' => [
        'driver' => 'pdo_sqlite',
        'memory' => true, // Use in-memory SQLite for testing
        'path' => ':memory:',
        'charset' => 'utf8',
    ],
    'installed' => false,
    'app' => [
        'name' => 'Gravitycar Framework (Test)',
        'version' => '1.0.0',
        'debug' => true,
        'backend_url' => 'http://localhost:8081',
        'frontend_url' => 'http://localhost:3000',
    ],
    'logging' => [
        'level' => 'error', // Reduce logging noise during tests
        'file' => 'logs/gravitycar-test.log',
        'daily_rotation' => false,
        'max_files' => 1,
        'date_format' => 'Y-m-d',
    ],
    'site_name' => 'GravitycarAI Test',
    
    // Mock API keys for testing to prevent null assignment errors
    'open_imdb_api_key' => 'test_tmdb_key_19a9f496',
    'google_books_api_key' => 'test_google_books_key',
    'tmdb_api_key' => 'test_tmdb_key',
    
    'default_page_size' => 20,
    
    // API Documentation System Configuration
    'documentation' => [
        'cache_enabled' => false, // Disable caching during tests
        'cache_ttl_seconds' => 0,
        'cache_directory' => 'cache/test_documentation/',
        'auto_clear_cache_on_metadata_change' => false,
        
        'default_react_component' => 'TextInput',
        'include_react_metadata' => true,
        'react_validation_mapping' => true,
        'fallback_component_on_missing' => true,
        
        'expose_internal_fields' => false,
        'expose_validation_rules' => true,
        'expose_field_capabilities' => true,
        'include_example_data' => false, // Reduce test complexity
        
        'include_related_fields' => true,
        'include_validation_examples' => false, // Reduce test complexity
        'sort_fields_alphabetically' => false,
        'include_deprecated_fields' => false,
    ],
    
    // Security Configuration for Testing
    'security' => [
        'jwt_secret' => 'test_jwt_secret_for_testing_only_do_not_use_in_production',
        'jwt_algorithm' => 'HS256',
        'jwt_expiration' => 3600, // 1 hour
        'refresh_token_expiration' => 604800, // 1 week
        'password_hash_algorithm' => PASSWORD_DEFAULT,
        'session_timeout' => 1800, // 30 minutes
        'max_login_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
        'require_https' => false, // Allow HTTP for testing
        'csrf_protection' => false, // Disable CSRF for API testing
        'cors_enabled' => true,
        'cors_origins' => ['http://localhost:3000', 'http://localhost:8081'],
        'cors_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'cors_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    ],
    
    // Google OAuth Configuration (test values)
    'google_oauth' => [
        'client_id' => 'test_google_oauth_client_id.apps.googleusercontent.com',
        'client_secret' => 'test_google_oauth_client_secret',
        'redirect_uri' => 'http://localhost:3000/auth/google/callback',
    ],
    
    // Testing specific settings
    'testing' => [
        'mock_external_apis' => true,
        'disable_email_sending' => true,
        'use_test_database' => true,
        'skip_database_migrations' => false,
        'clear_cache_between_tests' => true,
    ],
    
    // File Upload Configuration for Testing
    'file_upload' => [
        'max_file_size' => 1048576, // 1MB for testing
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt'],
        'upload_directory' => 'tmp/test_uploads/',
        'create_upload_directory' => true,
        'overwrite_existing' => true,
    ],
    
    // TMDB API Configuration (test values)
    'tmdb' => [
        'api_key' => 'test_tmdb_api_key',
        'base_url' => 'https://api.themoviedb.org/3',
        'image_base_url' => 'https://image.tmdb.org/t/p',
        'timeout' => 5, // Shorter timeout for testing
        'cache_duration' => 0, // Disable caching for tests
    ],
    
    // Email Configuration for Testing
    'email' => [
        'driver' => 'test', // Use test driver that doesn't send emails
        'from_address' => 'test@gravitycar.test',
        'from_name' => 'Gravitycar Test',
    ],
];