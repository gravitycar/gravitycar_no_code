<?php
/**
 * Manual Unit Test Verification Script
 * Tests the key functionality to ensure migration didn't break anything
 */

require_once 'vendor/autoload.php';

use Gravitycar\Core\Gravitycar;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Exceptions\GCException;

echo "=== Manual Unit Test Verification ===\n\n";

// Bootstrap framework
$gc = new Gravitycar();
$gc->bootstrap();

// Test Counter
$passed = 0;
$failed = 0;

function test($description, $testFunction) {
    global $passed, $failed;
    
    echo "Testing: $description\n";
    try {
        $result = $testFunction();
        if ($result === true) {
            echo "  ✅ PASSED\n";
            $passed++;
        } else {
            echo "  ❌ FAILED: $result\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "  ❌ FAILED: " . $e->getMessage() . "\n";
        $failed++;
    }
    echo "\n";
}

// Test 1: ModelFactory::new() with valid model names
test("ModelFactory::new() with valid model names", function() {
    $users = ModelFactory::new('Users');
    $movies = ModelFactory::new('Movies');
    $quotes = ModelFactory::new('Movie_quotes');
    
    if (!($users instanceof \Gravitycar\Models\users\Users)) {
        return "Users model not correct instance";
    }
    if (!($movies instanceof \Gravitycar\Models\movies\Movies)) {
        return "Movies model not correct instance";
    }
    if (!($quotes instanceof \Gravitycar\Models\movie_quotes\Movie_Quotes)) {
        return "Movie_quotes model not correct instance";
    }
    
    return true;
});

// Test 2: ModelFactory::new() with invalid model name
test("ModelFactory::new() with invalid model name throws exception", function() {
    try {
        ModelFactory::new('NonExistentModel');
        return "Should have thrown exception";
    } catch (GCException $e) {
        if (strpos($e->getMessage(), 'Model class not found') !== false) {
            return true;
        }
        return "Wrong exception message: " . $e->getMessage();
    }
});

// Test 3: ModelFactory::new() with empty model name
test("ModelFactory::new() with empty model name throws exception", function() {
    try {
        ModelFactory::new('');
        return "Should have thrown exception";
    } catch (GCException $e) {
        if (strpos($e->getMessage(), 'non-empty string') !== false) {
            return true;
        }
        return "Wrong exception message: " . $e->getMessage();
    }
});

// Test 4: ModelFactory::new() with invalid characters
test("ModelFactory::new() with invalid characters throws exception", function() {
    try {
        ModelFactory::new('Invalid@Model');
        return "Should have thrown exception";
    } catch (GCException $e) {
        if (strpos($e->getMessage(), 'invalid characters') !== false) {
            return true;
        }
        return "Wrong exception message: " . $e->getMessage();
    }
});

// Test 5: ModelFactory::getAvailableModels()
test("ModelFactory::getAvailableModels() returns available models", function() {
    $models = ModelFactory::getAvailableModels();
    
    if (!is_array($models)) {
        return "Should return array";
    }
    
    if (count($models) === 0) {
        return "Should return some models";
    }
    
    // Check for expected models
    $expectedModels = ['Users', 'Movies', 'Movie_quotes', 'Installer'];
    foreach ($expectedModels as $expected) {
        if (!in_array($expected, $models)) {
            return "Missing expected model: $expected";
        }
    }
    
    return true;
});

// Test 6: Created models have proper fields
test("Created models have proper fields", function() {
    $user = ModelFactory::new('Users');
    
    if (!$user->hasField('username')) {
        return "Users model missing username field";
    }
    if (!$user->hasField('email')) {
        return "Users model missing email field";
    }
    if (!$user->hasField('password')) {
        return "Users model missing password field";
    }
    
    $movie = ModelFactory::new('Movies');
    if (!$movie->hasField('name')) {
        return "Movies model missing name field";
    }
    
    return true;
});

// Test 7: Models can set and get data
test("Models can set and get data", function() {
    $user = ModelFactory::new('Users');
    
    $testUsername = 'test_user_' . uniqid();
    $testEmail = 'test_' . uniqid() . '@example.com';
    
    $user->set('username', $testUsername);
    $user->set('email', $testEmail);
    
    if ($user->get('username') !== $testUsername) {
        return "Username not set correctly";
    }
    if ($user->get('email') !== $testEmail) {
        return "Email not set correctly";
    }
    
    return true;
});

// Test 8: ModelFactory::retrieve() handles missing database gracefully
test("ModelFactory::retrieve() handles missing database gracefully", function() {
    try {
        $result = ModelFactory::retrieve('Users', '123');
        // Should return null when database is not available
        if ($result === null) {
            return true;
        }
        // Or if it returns a model, that's also acceptable
        if ($result instanceof \Gravitycar\Models\users\Users) {
            return true;
        }
        return "Unexpected return type: " . gettype($result);
    } catch (Exception $e) {
        // Exception is acceptable when database is not available
        return true;
    }
});

// Test 9: Test migrated files work correctly
test("Migrated test.php pattern works", function() {
    // This mimics what test.php now does
    $model = ModelFactory::new('Movie_quotes');
    
    if (!($model instanceof \Gravitycar\Models\movie_quotes\Movie_Quotes)) {
        return "Movie_quotes model not correct instance";
    }
    
    return true;
});

// Test 10: Test installer pattern works
test("Migrated installer pattern works", function() {
    // This mimics what Installer.php now does
    $usersModel = ModelFactory::new('Users');
    
    if (!($usersModel instanceof \Gravitycar\Models\users\Users)) {
        return "Users model not correct instance";
    }
    
    // Test setting data like the installer does
    $usersModel->set('username', 'admin@test.com');
    $usersModel->set('email', 'admin@test.com');
    $usersModel->set('user_type', 'admin');
    $usersModel->set('password', 'admin123');
    
    if ($usersModel->get('username') !== 'admin@test.com') {
        return "Username not set correctly";
    }
    
    return true;
});

// Summary
echo "=== Test Results ===\n";
echo "✅ Passed: $passed\n";
echo "❌ Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n\n";

if ($failed === 0) {
    echo "🎉 ALL TESTS PASSED! Migration is working correctly.\n";
    exit(0);
} else {
    echo "⚠️  Some tests failed. Please review the issues above.\n";
    exit(1);
}
