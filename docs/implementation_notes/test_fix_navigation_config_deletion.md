# Test Fix: NavigationAPIIntegrationTest File Deletion Issue

## Problem
The `NavigationAPIIntegrationTest` was deleting the source file `src/Navigation/navigation_config.php` in its `tearDown()` method. This is a tracked source file that should NEVER be modified or deleted by tests.

## Root Cause
The test was creating a test configuration file directly in the source directory (`src/Navigation/navigation_config.php`) and then deleting it in `tearDown()`. This violated the fundamental principle that **tests should never modify source files**.

Original problematic approach:
```php
// ❌ WRONG: Using source directory for test data
$this->testConfigFile = 'src/Navigation/navigation_config.php';
$this->createTestNavigationConfig();

// ❌ WRONG: Deleting source file in tearDown
if (file_exists($this->testConfigFile)) {
    unlink($this->testConfigFile);
}
```

## Solution
Complete redesign to use a **temporary test directory** that is completely separate from source files:

1. Create a unique temporary directory for each test run
2. Create test config file in that temporary directory
3. Override the NavigationConfig dependency to use the test file path
4. Clean up ONLY the test directory in tearDown

### Key Changes

#### 1. Modified NavigationConfig Class
Added optional constructor parameter to allow path override for testing:

**File**: `src/Navigation/NavigationConfig.php`
```php
public function __construct(?string $configFilePath = null)
{
    $this->configFilePath = $configFilePath ?? 'src/Navigation/navigation_config.php';
    $this->loadConfig();
}
```

#### 2. Redesigned Test Setup
**File**: `Tests/Integration/Api/NavigationAPIIntegrationTest.php`

```php
protected function setUp(): void
{
    parent::setUp();
    
    // Create test-specific config directory - NEVER touch source files!
    $this->testConfigDir = sys_get_temp_dir() . '/gravitycar_test_navigation_' . uniqid();
    mkdir($this->testConfigDir, 0755, true);
    $this->testConfigFile = $this->testConfigDir . '/navigation_config.php';
    
    // Create test navigation config file in test directory
    $this->createTestNavigationConfig();
    
    // Create a custom NavigationConfig instance pointing to our test file
    $testNavigationConfig = new \Gravitycar\Navigation\NavigationConfig($this->testConfigFile);
    
    // Manually construct NavigationBuilder with test config
    $this->navigationBuilder = new \Gravitycar\Services\NavigationBuilder(
        $this->container->get('logger'),
        $this->container->get('metadata_engine'),
        $this->container->get('authorization_service'),
        $testNavigationConfig,  // ✅ Using test config!
        $this->container->get('model_factory')
    );
}
```

#### 3. Safe Cleanup
```php
protected function tearDown(): void
{
    // Clean up test config directory - this is OUR test directory, safe to delete
    if (is_dir($this->testConfigDir)) {
        $files = glob($this->testConfigDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);  // ✅ Safe: Only deleting OUR test files
            }
        }
        rmdir($this->testConfigDir);  // ✅ Safe: Only deleting OUR test directory
    }
    
    // Clean up cache files
    $cacheFiles = glob('cache/navigation_cache_*.php');
    foreach ($cacheFiles as $cacheFile) {
        if (file_exists($cacheFile)) {
            unlink($cacheFile);  // ✅ Safe: Cache files are meant to be regenerated
        }
    }
}
```

## Test Results
✅ All 7 tests in `NavigationAPIIntegrationTest` pass  
✅ 190 assertions successful  
✅ Source file `src/Navigation/navigation_config.php` is NEVER modified  
✅ Test directories are created in `/tmp/` and cleaned up properly  
✅ No side effects on the source code  

## Principles Demonstrated

### 1. Test Isolation
Tests should run in complete isolation from production/source files. Use temporary directories for any file operations.

### 2. Dependency Injection for Testability
By allowing `NavigationConfig` to accept an optional path parameter, we can easily inject test-specific paths without modifying source files.

### 3. Clean Teardown
Tests should leave the system in exactly the same state as before they ran. Delete ONLY files/directories that the test created.

### 4. No Source File Modification
**NEVER** modify, create, or delete files in:
- `src/` directory
- `config/` directory (unless using `.test.php` variants)
- Any tracked files in git

**ALWAYS** use:
- Temporary directories (`sys_get_temp_dir()`)
- Test-specific directories under `Tests/`
- Mock objects and dependency injection

## Architecture Benefits
This fix demonstrates proper test architecture:
- ✅ Tests are completely isolated
- ✅ Tests can run in parallel safely
- ✅ Source files are immutable during testing
- ✅ Dependency injection enables testability
- ✅ No backup/restore hacks needed
- ✅ Clean and maintainable code

## Future Improvements
This pattern should be applied to any other tests that need to work with configuration files:
1. Check for tests modifying files in `src/` or `config/`
2. Refactor to use temporary directories
3. Add constructor parameters for testability where needed
4. Document the test isolation principles
