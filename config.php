<?php
return [
    'database' => [
        'driver' => 'pdo_mysql',
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'gravitycar_nc',
        'user' => 'mike',
        'password' => 'mike',
        'charset' => 'utf8mb4',
    ],
    'installed' => false,
    'app' => [
        'name' => 'Gravitycar Framework',
        'version' => '1.0.0',
        'debug' => true,
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
        'cache_enabled' => true,
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
    ]
];
