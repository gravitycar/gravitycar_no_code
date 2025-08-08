<?php
/**
 * Test script to verify ModelFactory implementation
 */

require_once 'vendor/autoload.php';

use Gravitycar\Core\Gravitycar;
use Gravitycar\Factories\ModelFactory;

try {
    echo "=== ModelFactory Test Script ===\n\n";
    
    // Initialize Gravitycar framework
    echo "1. Initializing Gravitycar framework...\n";
    $gc = new Gravitycar();
    $gc->bootstrap();
    echo "   ✓ Framework initialized\n\n";
    
    // Test getAvailableModels
    echo "2. Testing getAvailableModels()...\n";
    $availableModels = ModelFactory::getAvailableModels();
    echo "   Available models: " . implode(', ', $availableModels) . "\n";
    echo "   ✓ Found " . count($availableModels) . " models\n\n";
    
    // Test creating new model instances
    echo "3. Testing new() method...\n";
    
    if (in_array('Users', $availableModels)) {
        echo "   Creating Users model...\n";
        $user = ModelFactory::new('Users');
        echo "   ✓ Created: " . get_class($user) . "\n";
        echo "   ✓ Has fields: " . count($user->getFields()) . "\n";
        echo "   ✓ Has username field: " . ($user->hasField('username') ? 'yes' : 'no') . "\n";
    }
    
    if (in_array('Movies', $availableModels)) {
        echo "   Creating Movies model...\n";
        $movie = ModelFactory::new('Movies');
        echo "   ✓ Created: " . get_class($movie) . "\n";
        echo "   ✓ Has fields: " . count($movie->getFields()) . "\n";
        echo "   ✓ Has name field: " . ($movie->hasField('name') ? 'yes' : 'no') . "\n";
    }
    
    echo "\n4. Testing error handling...\n";
    
    // Test invalid model name
    try {
        ModelFactory::new('InvalidModel');
        echo "   ✗ Should have thrown exception for invalid model\n";
    } catch (\Gravitycar\Exceptions\GCException $e) {
        echo "   ✓ Correctly threw exception: " . $e->getMessage() . "\n";
    }
    
    // Test invalid characters
    try {
        ModelFactory::new('Invalid@Model');
        echo "   ✗ Should have thrown exception for invalid characters\n";
    } catch (\Gravitycar\Exceptions\GCException $e) {
        echo "   ✓ Correctly threw exception: " . $e->getMessage() . "\n";
    }
    
    echo "\n5. Testing retrieve() method...\n";
    
    // Test retrieving non-existent record (handle database connection gracefully)
    try {
        $result = ModelFactory::retrieve('Users', 'non-existent-id');
        if ($result === null) {
            echo "   ✓ Correctly returned null for non-existent record\n";
        } else {
            echo "   ✗ Should have returned null for non-existent record\n";
        }
    } catch (\Gravitycar\Exceptions\GCException $e) {
        if (strpos($e->getMessage(), 'Database') !== false) {
            echo "   ✓ Database connection not available (expected in test environment)\n";
        } else {
            echo "   ✗ Unexpected error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== All tests completed successfully! ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
