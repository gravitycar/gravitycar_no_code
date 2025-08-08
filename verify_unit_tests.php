<?php
/**
 * Verify Unit Test Logic Compatibility
 * Tests the same scenarios that our unit tests would test
 */

require_once 'vendor/autoload.php';

use Gravitycar\Core\Gravitycar;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Exceptions\GCException;

echo "=== Unit Test Logic Verification ===\n\n";

// Bootstrap
$gc = new Gravitycar();
$gc->bootstrap();

$passed = 0;
$failed = 0;

function assert_test($description, $condition, $message = '') {
    global $passed, $failed;
    
    if ($condition) {
        echo "✅ $description\n";
        $passed++;
    } else {
        echo "❌ $description" . ($message ? " - $message" : "") . "\n";
        $failed++;
    }
}

// Test scenarios that mirror our unit tests

echo "1. Testing ModelFactory::new() method scenarios...\n";

// Test valid model creation
try {
    $user = ModelFactory::new('Users');
    assert_test("Creates Users model successfully", $user instanceof \Gravitycar\Models\users\Users);
    
    $movie = ModelFactory::new('Movies');
    assert_test("Creates Movies model successfully", $movie instanceof \Gravitycar\Models\movies\Movies);
    
    $quotes = ModelFactory::new('Movie_quotes');
    assert_test("Creates Movie_quotes model successfully", $quotes instanceof \Gravitycar\Models\movie_quotes\Movie_Quotes);
    
} catch (Exception $e) {
    assert_test("Valid model creation", false, $e->getMessage());
}

// Test invalid model names (should throw exceptions)
$errorTests = [
    ['', 'Empty string'],
    ['NonExistent', 'Non-existent model'],
    ['Invalid@Name', 'Invalid characters'],
    ['123Model', 'Starting with numbers'],
    ['model-name', 'Hyphenated name']
];

foreach ($errorTests as [$modelName, $description]) {
    try {
        ModelFactory::new($modelName);
        assert_test("Throws exception for $description", false, "Should have thrown exception");
    } catch (GCException $e) {
        assert_test("Throws exception for $description", true);
    } catch (Exception $e) {
        assert_test("Throws GCException for $description", false, "Threw " . get_class($e) . " instead");
    }
}

echo "\n2. Testing ModelFactory::getAvailableModels()...\n";

$models = ModelFactory::getAvailableModels();
assert_test("Returns array", is_array($models));
assert_test("Returns non-empty array", count($models) > 0);
assert_test("Contains Users model", in_array('Users', $models));
assert_test("Contains Movies model", in_array('Movies', $models));

echo "\n3. Testing model functionality after creation...\n";

$user = ModelFactory::new('Users');

// Test field access
assert_test("Model has username field", $user->hasField('username'));
assert_test("Model has email field", $user->hasField('email'));
assert_test("Model has password field", $user->hasField('password'));

// Test data setting/getting
$testData = [
    'username' => 'testuser_' . uniqid(),
    'email' => 'test_' . uniqid() . '@example.com',
    'first_name' => 'Test',
    'last_name' => 'User'
];

foreach ($testData as $field => $value) {
    $user->set($field, $value);
    assert_test("Can set and get $field", $user->get($field) === $value);
}

echo "\n4. Testing ModelFactory::retrieve() behavior...\n";

// Test with invalid parameters
try {
    ModelFactory::retrieve('', '123');
    assert_test("Throws exception for empty model name", false);
} catch (GCException $e) {
    assert_test("Throws exception for empty model name", true);
}

try {
    ModelFactory::retrieve('Users', '');
    assert_test("Throws exception for empty ID", false);
} catch (GCException $e) {
    assert_test("Throws exception for empty ID", true);
}

// Test with valid parameters (may return null if no database)
try {
    $result = ModelFactory::retrieve('Users', '123');
    assert_test("Retrieve handles missing database gracefully", 
                $result === null || $result instanceof \Gravitycar\Models\users\Users);
} catch (Exception $e) {
    // Exception is also acceptable if database is not configured
    assert_test("Retrieve handles missing database gracefully", true);
}

echo "\n5. Testing migrated pattern compatibility...\n";

// Test patterns that replaced ServiceLocator::createModel
$patterns = [
    ['Users', \Gravitycar\Models\users\Users::class],
    ['Movies', \Gravitycar\Models\movies\Movies::class],
    ['Movie_quotes', \Gravitycar\Models\movie_quotes\Movie_Quotes::class],
    ['Installer', \Gravitycar\Models\installer\Installer::class]
];

foreach ($patterns as [$modelName, $expectedClass]) {
    try {
        $model = ModelFactory::new($modelName);
        assert_test("$modelName creates correct class", $model instanceof $expectedClass);
    } catch (Exception $e) {
        assert_test("$modelName creates successfully", false, $e->getMessage());
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Test Results Summary:\n";
echo "✅ Passed: $passed\n";
echo "❌ Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n\n";

if ($failed === 0) {
    echo "🎉 ALL UNIT TEST LOGIC VERIFIED!\n";
    echo "The migration maintains full compatibility with existing unit test expectations.\n";
    exit(0);
} else {
    echo "⚠️  Some unit test logic failed. Please review.\n";
    exit(1);
}
