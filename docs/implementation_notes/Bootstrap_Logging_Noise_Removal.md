# Bootstrap Logging Noise Removal

## Issue Summary
The unit test suite was printing "Bootstrap step:" messages repeatedly during test execution, creating noise in the test output. The messages were appearing from the Gravitycar bootstrap process.

## Root Cause Analysis
The output was coming from the `Gravitycar::logBootstrapStep()` and `Gravitycar::logBootstrapStart()` methods in `src/Core/Gravitycar.php`. These methods use `error_log()` as a fallback when the logger is not yet available during bootstrap, which outputs to stderr and appears in the console during unit test runs.

### Source of the Output
```php
// In logBootstrapStep()
error_log("Bootstrap step: {$step}");

// In logBootstrapStart()  
error_log("Gravitycar application bootstrap starting (environment: {$this->environment})");
```

## Solution
Modified both logging methods to suppress `error_log()` output when running in CLI mode (which includes PHPUnit tests) while preserving the logging behavior for web environments where it might be useful for debugging.

### Code Changes

**File:** `src/Core/Gravitycar.php`

1. **Modified `logBootstrapStart()` method:**
```php
private function logBootstrapStart(): void {
    // Use error_log initially since logger might not be available yet
    // Only log to error_log in web environment, not during CLI/tests
    if (php_sapi_name() !== 'cli') {
        error_log("Gravitycar application bootstrap starting (environment: {$this->environment})");
    }
}
```

2. **Modified `logBootstrapStep()` method:**
```php
private function logBootstrapStep(string $step): void {
    if ($this->logger) {
        $this->logger->info("Bootstrap step: {$step}");
    } elseif (php_sapi_name() !== 'cli') {
        // Only log to error_log in web environment, not during CLI/tests
        error_log("Bootstrap step: {$step}");
    }
}
```

**File:** `Tests/Unit/Core/GravitycarTest.php`

3. **Updated `testBootstrapLogging()` test** to expect no error_log output in CLI mode:
```php
// In CLI mode (like PHPUnit), error_log output should be suppressed
// to avoid noise during test runs, so we expect no output
if (php_sapi_name() === 'cli') {
    $this->assertEmpty($logContent, 'Bootstrap logging should be suppressed in CLI mode');
} else {
    // In web environments, error_log fallback should work
    $this->assertStringContainsString('Gravitycar application bootstrap starting', $logContent);
}
```

## Test Results
- **Before Fix:** Bootstrap messages appeared in all unit test output
- **After Fix:** Clean test output with no bootstrap noise ✅

### Example Before:
```
Gravitycar application bootstrap starting (environment: production)
Bootstrap step: services
Bootstrap step: configuration
.                                                                   1 / 1 (100%)
```

### Example After:
```
.                                                                   1 / 1 (100%)
```

## Impact
- **Unit Tests:** Clean output without bootstrap noise during test execution
- **Web Environment:** Bootstrap logging still works via error_log when logger is not available
- **Logger-based Logging:** Still works normally when logger is available
- **Backward Compatibility:** Preserved for web environments

## Design Rationale
The `php_sapi_name() !== 'cli'` check ensures that:
1. Unit tests (which run in CLI) have clean output
2. Web environments still get helpful bootstrap debugging information
3. The fallback logging behavior is preserved where it's most useful
4. No functional changes to the actual bootstrap process

This approach maintains the intended debugging functionality while eliminating noise in test environments where such output is not useful.
