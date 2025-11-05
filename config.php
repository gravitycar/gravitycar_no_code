<?php
return [
    'database' => [
        'driver' => $_ENV['DB_DRIVER'] ?? 'pdo_mysql',
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => (int)($_ENV['DB_PORT'] ?? 3306),
        'dbname' => $_ENV['DB_NAME'] ?? 'gravitycar_nc',
        'user' => $_ENV['DB_USER'] ?? 'mike',
        'password' => $_ENV['DB_PASSWORD'] ?? 'mike',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    ],
    'installed' => false,
    'app' => [
        'name' => 'Gravitycar Framework',
        'version' => '1.0.0',
        'debug' => true,
        'backend_url' => $_ENV['BACKEND_URL'] ?? 'http://localhost:8081',
        'frontend_url' => $_ENV['FRONTEND_URL'] ?? 'http://localhost:3000',
    ],
    'logging' => [
        'level' => 'info',
        'file' => 'logs/gravitycar.log',
        'daily_rotation' => true,
        'max_files' => 30, // Keep 30 days of logs
        'date_format' => 'Y-m-d', // Daily rotation format
    ],
    'site_name' => 'GravitycarAI',
    'open_imdb_api_key' => '19a9f496',
    'default_page_size' => 20,
    
    // API Documentation System Configuration
    'documentation' => [
        // Caching Configuration
        'cache_enabled' => false,
        'cache_ttl_seconds' => 3600, // 1 hour cache expiration
        'cache_directory' => 'cache/documentation/',
        'auto_clear_cache_on_metadata_change' => false, // Manual cache clearing for small scale
        
        // React Integration Configuration  
        'default_react_component' => 'TextInput',
        'include_react_metadata' => true,
        'react_validation_mapping' => true,
        'fallback_component_on_missing' => true,
        
        // API Exposure Configuration
        'expose_internal_fields' => false, // Hide framework internal fields
        'expose_validation_rules' => true,
        'expose_field_capabilities' => true,
        'include_example_data' => true,
        
        // OpenAPI Configuration
        'openapi_version' => '3.0.3',
        'api_title' => 'Gravitycar Framework API',
        'api_version' => '1.0.0',
        'api_description' => 'Auto-generated API documentation for Gravitycar Framework',
        'include_deprecated_endpoints' => false,
        
        // Performance Configuration  
        'response_time_target_ms' => 200,
        'enable_response_compression' => true,
        'max_field_types_per_request' => 100, // Good practice even for small scale
        
        // Security Configuration (designed for small scale)
        'authentication_required' => false, // Not needed for 3 concurrent users
        'allowed_origins' => ['*'], // CORS configuration
        'rate_limiting_enabled' => false, // Not needed for small scale
        
        // Development Configuration
        'enable_debug_info' => true,
        'include_generation_timestamps' => true,
        'log_cache_operations' => false,
        'validate_generated_schemas' => true,
        
        // Error Handling Configuration
        'graceful_degradation' => true,
        'fallback_on_cache_corruption' => true,
        'detailed_error_responses' => true, // Include context in error responses
        'log_documentation_errors' => true
    ],
    
    // Health Check Configuration
    'health' => [
        'check_database' => true,
        'database_timeout' => 5, // seconds
        'metadata_stale_hours' => 24,
        'enable_caching' => true,
        'cache_ttl' => 30, // seconds
        'memory_warning_percentage' => 80,
        'expose_detailed_errors' => false, // in production
        'enable_debug_info' => false
    ],
    
    // Cache Configuration
    'cache' => [
        'directory' => 'cache',
        'metadata_file' => 'cache/metadata_cache.php'
    ],
    
    // TMDB API Configuration
    'tmdb' => [
        'api_key' => $_ENV['TMDB_API_KEY'] ?? null,
        'read_access_token' => $_ENV['TMDB_API_READ_ACCESS_TOKEN'] ?? null,
        'base_url' => 'https://api.themoviedb.org/3',
        'image_base_url' => 'https://image.tmdb.org/t/p'
    ],

    // Google Books API Configuration
    'google_books' => [
        'api_key' => $_ENV['GOOGLE_BOOKS_API_KEY'] ?? null,
        'base_url' => 'https://www.googleapis.com/books/v1',
        'max_results' => 40,
        'timeout' => 30
    ],

    // Google OAuth Configuration
    'google' => [
        'client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? 'test-client-id',
        'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? 'test-client-secret',
        'redirect_uri' => $_ENV['GOOGLE_REDIRECT_URI'] ?? ($_ENV['BACKEND_URL'] ?? 'http://localhost:8081') . '/auth/google/callback'
    ],

    // Authentication settings
    'auth' => [
        'inactivity_timeout' => (int)($_ENV['AUTH_INACTIVITY_TIMEOUT'] ?? 3600), // 1 hour in seconds
        'activity_debounce' => (int)($_ENV['AUTH_ACTIVITY_DEBOUNCE'] ?? 60),    // Update activity every 60 seconds max
    ]
];
