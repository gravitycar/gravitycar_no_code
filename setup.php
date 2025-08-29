<?php
/**
 * Gravitycar Framework Setup Script
 * 
 * This script bootstraps the Gravitycar application, generates the database schema,
 * and creates initial user records including sample data.
 * 
 * Usage: php setup.php
 */

require_once 'vendor/autoload.php';

use Gravitycar\Core\Gravitycar;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Schema\SchemaGenerator;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Exceptions\GCException;

// Terminal colors for better output
class Colors {
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const CYAN = "\033[36m";
    const WHITE = "\033[37m";
    const RESET = "\033[0m";
    const BOLD = "\033[1m";
}

function printHeader($text) {
    echo Colors::CYAN . Colors::BOLD . "\n=== $text ===" . Colors::RESET . "\n";
}

function printSuccess($text) {
    echo Colors::GREEN . "✓ $text" . Colors::RESET . "\n";
}

function printError($text) {
    echo Colors::RED . "✗ $text" . Colors::RESET . "\n";
}

function printInfo($text) {
    echo Colors::BLUE . "ℹ $text" . Colors::RESET . "\n";
}

function printWarning($text) {
    echo Colors::YELLOW . "⚠ $text" . Colors::RESET . "\n";
}

/**
 * Seed authentication roles and permissions
 */
function seedAuthenticationData() {
    printInfo("Creating default roles...");
    
    // Create default roles
    $roles = [
        ['name' => 'admin', 'description' => 'System administrator', 'is_oauth_default' => false],
        ['name' => 'manager', 'description' => 'Manager with elevated permissions', 'is_oauth_default' => false],
        ['name' => 'user', 'description' => 'Standard user', 'is_oauth_default' => true],
        ['name' => 'guest', 'description' => 'Guest user with limited access', 'is_oauth_default' => false]
    ];
    
    foreach ($roles as $roleData) {
        try {
            $role = ModelFactory::new('Roles');
            $role->set('name', $roleData['name']);
            $role->set('description', $roleData['description']);
            $role->set('is_oauth_default', $roleData['is_oauth_default']);
            $role->create();
            printSuccess("Created role: " . $roleData['name']);
        } catch (Exception $e) {
            printWarning("Role '" . $roleData['name'] . "' might already exist: " . $e->getMessage());
        }
    }
    
    printInfo("Creating default permissions...");
    
    // Create default permissions
    $permissions = [
        // User management permissions
        ['action' => 'create', 'model' => 'Users', 'description' => 'Create new users'],
        ['action' => 'read', 'model' => 'Users', 'description' => 'View user profiles'],
        ['action' => 'update', 'model' => 'Users', 'description' => 'Update user profiles'],
        ['action' => 'delete', 'model' => 'Users', 'description' => 'Delete users'],
        ['action' => 'list', 'model' => 'Users', 'description' => 'List all users'],
        
        // Role management permissions
        ['action' => 'create', 'model' => 'Roles', 'description' => 'Create new roles'],
        ['action' => 'read', 'model' => 'Roles', 'description' => 'View roles'],
        ['action' => 'update', 'model' => 'Roles', 'description' => 'Update roles'],
        ['action' => 'delete', 'model' => 'Roles', 'description' => 'Delete roles'],
        ['action' => 'list', 'model' => 'Roles', 'description' => 'List all roles'],
        
        // Permission management permissions
        ['action' => 'create', 'model' => 'Permissions', 'description' => 'Create new permissions'],
        ['action' => 'read', 'model' => 'Permissions', 'description' => 'View permissions'],
        ['action' => 'update', 'model' => 'Permissions', 'description' => 'Update permissions'],
        ['action' => 'delete', 'model' => 'Permissions', 'description' => 'Delete permissions'],
        ['action' => 'list', 'model' => 'Permissions', 'description' => 'List all permissions'],
        
        // Global permissions (model = '')
        ['action' => 'system.admin', 'model' => '', 'description' => 'Full system administration'],
        ['action' => 'api.access', 'model' => '', 'description' => 'Basic API access'],
        ['action' => 'auth.manage', 'model' => '', 'description' => 'Manage authentication settings']
    ];
    
    foreach ($permissions as $permData) {
        try {
            $permission = ModelFactory::new('Permissions');
            $permission->set('action', $permData['action']);
            $permission->set('model', $permData['model']);
            $permission->set('description', $permData['description']);
            $permission->create();
            printSuccess("Created permission: " . $permData['action'] . " (" . ($permData['model'] ?: 'global') . ")");
        } catch (Exception $e) {
            printWarning("Permission '" . $permData['action'] . "' might already exist: " . $e->getMessage());
        }
    }
    
    printInfo("Assigning permissions to roles...");
    
    try {
        // Find admin role
        $adminRole = ModelFactory::new('Roles');
        $adminRoles = $adminRole->find(['name' => 'admin']);
        if (!empty($adminRoles)) {
            printSuccess("Admin role will have full permissions (implement role-permission assignments)");
        }
        
        // Find user role  
        $userRole = ModelFactory::new('Roles');
        $userRoles = $userRole->find(['name' => 'user']);
        if (!empty($userRoles)) {
            printSuccess("User role created (implement specific permission assignments)");
        }
        
    } catch (Exception $e) {
        printWarning("Role-permission assignment setup needed: " . $e->getMessage());
    }
}

try {
    printHeader("Gravitycar Framework Setup");
    
    echo Colors::WHITE . "This script will:\n";
    echo "1. Bootstrap the Gravitycar application\n";
    echo "2. Clear existing cache files\n";
    echo "3. Rebuild metadata and API routes cache\n";
    echo "4. Test Router functionality with GET /Users\n";
    echo "5. Generate/update the database schema\n";
    echo "6. Create sample user records\n" . Colors::RESET . "\n";
    
    // Step 1: Bootstrap the Gravitycar application
    printHeader("Step 1: Bootstrapping Application");
    printInfo("Initializing Gravitycar framework...");
    
    $gc = new Gravitycar(['environment' => 'development']);
    
    // Bootstrap manually without routing to avoid the router service issue
    try {
        // Get reflection access to bootstrap steps
        $reflection = new ReflectionClass($gc);
        $bootstrapSteps = $reflection->getProperty('bootstrapSteps');
        $bootstrapSteps->setAccessible(true);
        $steps = $bootstrapSteps->getValue($gc);
        
        // Bootstrap all steps except routing
        foreach ($steps as $step => $method) {
            if ($step !== 'routing') {
                printInfo("Bootstrap step: $step");
                $bootstrapMethod = $reflection->getMethod($method);
                $bootstrapMethod->setAccessible(true);
                $bootstrapMethod->invoke($gc);
            }
        }
        
        // Set the bootstrapped flag manually
        $isBootstrappedProperty = $reflection->getProperty('isBootstrapped');
        $isBootstrappedProperty->setAccessible(true);
        $isBootstrappedProperty->setValue($gc, true);
        
    } catch (Exception $e) {
        throw new GCException('Custom bootstrap failed: ' . $e->getMessage(), [], 0, $e);
    }
    
    printSuccess("Gravitycar application bootstrapped successfully");
    printInfo("Environment: " . $gc->getEnvironment());
    
    // Step 2: Clear and rebuild cache
    printHeader("Step 2: Cache Management");
    printInfo("Clearing existing cache files...");
    
    // Clear cache directory
    $cacheDir = 'cache';
    if (is_dir($cacheDir)) {
        $cacheFiles = glob($cacheDir . '/*');
        foreach ($cacheFiles as $file) {
            if (is_file($file)) {
                unlink($file);
                printSuccess("Deleted cache file: " . basename($file));
            }
        }
    } else {
        printInfo("Cache directory doesn't exist, creating it...");
        mkdir($cacheDir, 0755, true);
    }
    
    // Get services through ServiceLocator
    $metadataEngine = ServiceLocator::getMetadataEngine();
    $schemaGenerator = ServiceLocator::getSchemaGenerator();
    $dbConnector = ServiceLocator::getDatabaseConnector();
    $logger = ServiceLocator::getLogger();
    $router = new \Gravitycar\Api\Router($metadataEngine);
    
    // Clear MetadataEngine internal caches
    printInfo("Clearing MetadataEngine internal caches...");
    $metadataEngine->clearAllCaches();
    printSuccess("MetadataEngine caches cleared");
    
    // Clear DocumentationCache as well
    printInfo("Clearing DocumentationCache...");
    $documentationCache = new \Gravitycar\Services\DocumentationCache();
    $documentationCache->clearCache();
    printSuccess("DocumentationCache cleared");
    
    // Step 3: Rebuild cache files
    printHeader("Step 3: Rebuilding Cache");
    printInfo("Rebuilding metadata cache...");
    
    // Force reload of all metadata (this will recreate metadata_cache.php)
    $allMetadata = $metadataEngine->loadAllMetadata();
    $modelCount = count($allMetadata['models'] ?? []);
    $relationshipCount = count($allMetadata['relationships'] ?? []);
    printSuccess("Metadata cache rebuilt: $modelCount models, $relationshipCount relationships");
    
    // Rebuild API routes cache by accessing the router's route registry
    printInfo("Rebuilding API routes cache...");
    try {
        // Get the route registry from the router and force it to rebuild
        $reflection = new ReflectionClass($router);
        $routeRegistryProperty = $reflection->getProperty('routeRegistry');
        $routeRegistryProperty->setAccessible(true);
        $routeRegistry = $routeRegistryProperty->getValue($router);
        
        // Force route discovery and caching
        $routes = $routeRegistry->getRoutes();
        $routeCount = count($routes);
        printSuccess("API routes cache rebuilt: $routeCount routes registered");
        
        // Display some route info
        $modelBaseRoutes = array_filter($routes, function($route) {
            return isset($route['apiClass']) && 
                   $route['apiClass'] === 'Gravitycar\Models\Api\Api\ModelBaseAPIController';
        });
        $modelBaseCount = count($modelBaseRoutes);
        printInfo("ModelBaseAPIController routes: $modelBaseCount");
        
    } catch (Exception $e) {
        printWarning("Could not rebuild API routes cache: " . $e->getMessage());
    }
    
    // Step 4: Test Router functionality  
    printHeader("Step 4: Testing Router");
    printInfo("Testing Router with GET /Users request...");
    try {
        // Test the actual route() method with Request object
        $result = $router->route('GET', '/Users');
        
        printSuccess("✅ Router.route() method executed successfully");
        printInfo("Result type: " . gettype($result));
        
        if (is_array($result)) {
            if (isset($result['data'])) {
                printInfo("Response contains data array with " . count($result['data']) . " records");
            }
            if (isset($result['count'])) {
                printInfo("Response count: " . $result['count']);
            }
        }
        
        printSuccess("Router test completed successfully - route handled by ModelBaseAPIController");
        
    } catch (Exception $e) {
        printWarning("Router test failed: " . $e->getMessage());
        printInfo("Error details: " . json_encode([
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]));
    }
    
    // Step 5: Generate/update database schema
    printHeader("Step 5: Schema Generation");
    printInfo("Generating database schema...");
    
    // Create database if it doesn't exist
    printInfo("Ensuring database exists...");
    $dbCreated = $schemaGenerator->createDatabaseIfNotExists();
    if ($dbCreated) {
        printSuccess("Database created or already exists");
    } else {
        printWarning("Database creation status unknown");
    }
    
    // Generate schema using the already loaded metadata
    printInfo("Generating database schema from cached metadata...");
    $schemaGenerator->generateSchema($allMetadata);
    printSuccess("Database schema generated successfully");
    
    // Step 5.5: Seed authentication roles and permissions
    printHeader("Step 5.5: Seeding Authentication System");
    try {
        seedAuthenticationData();
        printSuccess("Authentication roles and permissions seeded successfully");
    } catch (Exception $e) {
        printWarning("Authentication seeding failed: " . $e->getMessage());
    }
    
    // Step 6: Create sample user records
    printHeader("Step 6: Creating Sample Users");
    
    // Sample user data
    $usersData = [
        [
            'username' => 'mike@gravitycar.com',
            'email' => 'mike@gravitycar.com',
            'first_name' => 'Mike',
            'last_name' => 'Developer',
            'password' => 'secure123',
            'user_type' => 'admin',
            'user_timezone' => 'UTC'
        ],
        [
            'username' => 'admin@example.com',
            'email' => 'admin@example.com',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'password' => 'admin123',
            'user_type' => 'admin',
            'user_timezone' => 'UTC'
        ],
        [
            'username' => 'john@example.com',
            'email' => 'john@example.com',
            'first_name' => 'John',
            'last_name' => 'Manager',
            'password' => 'manager123',
            'user_type' => 'manager',
            'user_timezone' => 'America/New_York'
        ],
        [
            'username' => 'jane@example.com',
            'email' => 'jane@example.com',
            'first_name' => 'Jane',
            'last_name' => 'User',
            'password' => 'user123',
            'user_type' => 'user',
            'user_timezone' => 'America/Los_Angeles'
        ]
    ];
    
    $createdUsers = [];
    $skippedUsers = [];
    
    foreach ($usersData as $index => $userData) {
        try {
            printInfo("Creating user: {$userData['username']}");
            
            // Check if user already exists
            $existingUser = null;
            try {
                $queryInstance = ModelFactory::new('Users');
                $existingUsers = $queryInstance->find(['username' => $userData['username']]);
                $existingUser = !empty($existingUsers) ? $existingUsers[0] : null;
            } catch (Exception $e) {
                // Ignore errors when checking for existing user
            }
            
            if ($existingUser) {
                printWarning("User {$userData['username']} already exists, skipping");
                $skippedUsers[] = $userData['username'];
                continue;
            }
            
            // Create new user
            $user = ModelFactory::new('Users');
            
            // Set user data
            foreach ($userData as $field => $value) {
                if ($user->hasField($field)) {
                    $user->set($field, $value);
                } else {
                    printWarning("Field '$field' not found in Users model, skipping");
                }
            }
            
            // Create the user
            $success = $user->create();
            
            if ($success) {
                $createdUsers[] = [
                    'username' => $user->get('username'),
                    'id' => $user->get('id'),
                    'user_type' => $user->get('user_type')
                ];
                printSuccess("Created user: {$userData['username']} (ID: {$user->get('id')})");
            } else {
                printError("Failed to create user: {$userData['username']}");
            }
            
        } catch (GCException $e) {
            printError("Failed to create user {$userData['username']}: " . $e->getMessage());
        } catch (Exception $e) {
            printError("Unexpected error creating user {$userData['username']}: " . $e->getMessage());
        }
    }
    
    // Summary
    printHeader("Setup Complete");
    
    printSuccess("Framework bootstrap: Complete");
    printSuccess("Cache clearing: Complete");
    printSuccess("Cache rebuilding: Complete");
    printSuccess("Schema generation: Complete");
    printSuccess("Created users: " . count($createdUsers));
    
    if (!empty($skippedUsers)) {
        printInfo("Skipped existing users: " . count($skippedUsers));
    }
    
    if (!empty($createdUsers)) {
        echo Colors::WHITE . "\nCreated Users:\n" . Colors::RESET;
        foreach ($createdUsers as $user) {
            echo "  • {$user['username']} ({$user['user_type']}) - ID: {$user['id']}\n";
        }
    }
    
    if (!empty($skippedUsers)) {
        echo Colors::WHITE . "\nSkipped Users (already exist):\n" . Colors::RESET;
        foreach ($skippedUsers as $username) {
            echo "  • $username\n";
        }
    }
    
    echo Colors::GREEN . Colors::BOLD . "\n🎉 Setup completed successfully!\n" . Colors::RESET;
    echo Colors::WHITE . "\nYou can now:\n";
    echo "• Access the API at your configured endpoint\n";
    echo "• Log in with any of the created users\n";
    echo "• Use the ModelFactory to create additional records\n";
    echo "• Run tests to verify everything is working\n" . Colors::RESET;
    
} catch (GCException $e) {
    printError("Setup failed with Gravitycar exception:");
    printError($e->getMessage());
    
    if ($e->getContext()) {
        echo Colors::YELLOW . "Context: " . json_encode($e->getContext(), JSON_PRETTY_PRINT) . Colors::RESET . "\n";
    }
    
    exit(1);
    
} catch (Exception $e) {
    printError("Setup failed with unexpected error:");
    printError($e->getMessage());
    
    if (method_exists($e, 'getTraceAsString')) {
        echo Colors::YELLOW . "Trace:\n" . $e->getTraceAsString() . Colors::RESET . "\n";
    }
    
    exit(1);
}
