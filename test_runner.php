<?php
/**
 * Simple test runner to verify our test infrastructure works
 * Run this with: php test_runner.php
 */

// Load Composer autoloader
require_once 'vendor/autoload.php';

echo "Testing Gravitycar Test Infrastructure\n";
echo "=====================================\n\n";

try {
    // Test 1: Check if our base classes can be loaded
    echo "1. Testing class autoloading...\n";

    if (class_exists('Gravitycar\Tests\TestCase')) {
        echo "   ✓ TestCase class loaded successfully\n";
    } else {
        echo "   ✗ TestCase class failed to load\n";
        exit(1);
    }

    if (class_exists('Gravitycar\Tests\Unit\UnitTestCase')) {
        echo "   ✓ UnitTestCase class loaded successfully\n";
    } else {
        echo "   ✗ UnitTestCase class failed to load\n";
        exit(1);
    }

    if (class_exists('Gravitycar\Tests\Fixtures\FixtureFactory')) {
        echo "   ✓ FixtureFactory class loaded successfully\n";
    } else {
        echo "   ✗ FixtureFactory class failed to load\n";
        exit(1);
    }

    // Test 2: Test fixture creation
    echo "\n2. Testing fixture factory...\n";

    $userData = \Gravitycar\Tests\Fixtures\FixtureFactory::createUser();
    if (is_array($userData) && isset($userData['username']) && isset($userData['email'])) {
        echo "   ✓ User fixture created successfully\n";
        echo "   - Username: " . $userData['username'] . "\n";
        echo "   - Email: " . $userData['email'] . "\n";
    } else {
        echo "   ✗ User fixture creation failed\n";
        exit(1);
    }

    // Test 3: Test data builder
    echo "\n3. Testing data builder...\n";

    $customUser = \Gravitycar\Tests\Helpers\TestDataBuilder::user()
        ->with('username', 'testbuilder')
        ->with('email', 'builder@test.com')
        ->build();

    if ($customUser['username'] === 'testbuilder' && $customUser['email'] === 'builder@test.com') {
        echo "   ✓ TestDataBuilder working correctly\n";
        echo "   - Built username: " . $customUser['username'] . "\n";
        echo "   - Built email: " . $customUser['email'] . "\n";
    } else {
        echo "   ✗ TestDataBuilder failed\n";
        exit(1);
    }

    // Test 4: Check if we can instantiate a simple test
    echo "\n4. Testing basic validation logic...\n";

    if (class_exists('Gravitycar\Validation\AlphanumericValidation')) {
        // Create a simple logger for testing
        $logger = new \Monolog\Logger('test');
        $logger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Level::Info));

        $validator = new \Gravitycar\Validation\AlphanumericValidation($logger);

        // Test some simple validation cases
        $testCases = [
            'abc123' => true,
            'test@email' => false,
            'ValidInput' => true,
            'Invalid-Input' => false
        ];

        $allPassed = true;
        foreach ($testCases as $input => $expected) {
            $result = $validator->validate($input);
            if ($result === $expected) {
                echo "   ✓ '{$input}' -> " . ($result ? 'valid' : 'invalid') . " (correct)\n";
            } else {
                echo "   ✗ '{$input}' -> " . ($result ? 'valid' : 'invalid') . " (expected " . ($expected ? 'valid' : 'invalid') . ")\n";
                $allPassed = false;
            }
        }

        if ($allPassed) {
            echo "   ✓ All validation tests passed\n";
        } else {
            echo "   ✗ Some validation tests failed\n";
            exit(1);
        }
    } else {
        echo "   ! AlphanumericValidation class not found, skipping validation test\n";
    }

    echo "\n=====================================\n";
    echo "✓ All test infrastructure checks passed!\n";
    echo "Your test environment is ready to use.\n\n";

    echo "Next steps:\n";
    echo "- Run 'composer dump-autoload' to regenerate autoload files\n";
    echo "- Run 'vendor/bin/phpunit' to execute the full test suite\n";
    echo "- Or run 'vendor/bin/phpunit Tests/Unit' for just unit tests\n";

} catch (Exception $e) {
    echo "\n✗ Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} catch (Error $e) {
    echo "\n✗ Fatal error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
