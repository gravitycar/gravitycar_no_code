<?php
/**
 * Test script to verify migration changes work correctly
 */
require_once 'vendor/autoload.php';

use Gravitycar\Core\Gravitycar;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Models\installer\Installer;

echo "=== Migration Test Script ===\n\n";

// Bootstrap the framework
echo "1. Bootstrapping framework...\n";
$gc = new Gravitycar();
$gc->bootstrap();
echo "   ✓ Framework bootstrapped successfully\n\n";

// Test 1: Verify ModelFactory::new() works 
echo "2. Testing ModelFactory::new() method...\n";
try {
    $user = ModelFactory::new('Users');
    echo "   ✓ Created Users model: " . get_class($user) . "\n";
    
    $movie = ModelFactory::new('Movies');
    echo "   ✓ Created Movies model: " . get_class($movie) . "\n";
    
    $installer = ModelFactory::new('Installer');
    echo "   ✓ Created Installer model: " . get_class($installer) . "\n";
    
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Verify we can instantiate models that were updated in migration
echo "3. Testing migrated files...\n";

// Test the updated test.php functionality
echo "   Testing Movie_Quotes creation from test.php pattern...\n";
try {
    $movieQuote = ModelFactory::new('Movie_Quotes');
    echo "   ✓ Movie_Quotes model created: " . get_class($movieQuote) . "\n";
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test the updated Installer functionality
echo "   Testing Installer model creation...\n";
try {
    $installer = new Installer(\Gravitycar\Core\ServiceLocator::getLogger());
    echo "   ✓ Installer model instantiated successfully\n";
    
    // Simulate the pattern that was changed in the migration
    $usersModel = ModelFactory::new('Users');
    echo "   ✓ Users model created via ModelFactory (replaces ServiceLocator::createModel)\n";
    
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Check available models
echo "4. Checking available models...\n";
$availableModels = ModelFactory::getAvailableModels();
echo "   Available models: " . implode(', ', $availableModels) . "\n";
echo "   Total models: " . count($availableModels) . "\n\n";

echo "=== Migration test completed successfully! ===\n";
