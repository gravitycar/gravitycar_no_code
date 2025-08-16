# XDEBUG Profiling with VSCode Guide for Gravitycar Framework

This guide explains how to use XDEBUG's profiling features with VSCode to analyze performance issues in the Gravitycar Framework.

## Prerequisites

1. **VSCode Extensions Required:**
   - `devsense.profiler-php-vscode` - PHP Profiler extension for viewing cachegrind files
   - `xdebug.php-debug` - PHP Debug extension (already installed)

## Setup Instructions

### 1. Configure XDEBUG for Profiling

Run the setup script to configure XDEBUG:

```bash
./setup_xdebug_profiling.sh
```

This script will:
- Create `/tmp/xdebug_profiles/` directory for profile output
- Update XDEBUG configuration to enable profiling
- Configure both CLI and web server environments

### 2. Manual Configuration (Alternative)

If you prefer manual setup, update your XDEBUG configuration files:

**For CLI** (`/etc/php/8.2/cli/conf.d/20-xdebug.ini`):
```ini
zend_extension=xdebug.so
xdebug.mode=develop,debug,profile
xdebug.start_with_request=yes
xdebug.client_host=localhost
xdebug.client_port=9003
xdebug.output_dir=/tmp/xdebug_profiles
xdebug.profiler_output_name=cachegrind.out.%p.%r
xdebug.profiler_append=0
```

**For Web Server** (`/etc/php/8.2/apache2/conf.d/20-xdebug.ini`):
```ini
zend_extension=xdebug.so
xdebug.mode=develop,debug,profile
xdebug.start_with_request=trigger
xdebug.client_host=localhost
xdebug.client_port=9003
xdebug.output_dir=/tmp/xdebug_profiles
xdebug.profiler_output_name=cachegrind.out.%p.%r
xdebug.profiler_append=0
xdebug.trigger_value=XDEBUG_PROFILE
```

## Using XDEBUG Profiling

### Method 1: VSCode Debug Configurations

Use the preconfigured launch configurations in `.vscode/launch.json`:

1. **Debug with Profiling** - Profiles the currently open PHP file
2. **Profile Performance Diagnostic** - Specifically profiles `performance_diagnostic.php`

To use:
1. Open the PHP file you want to profile
2. Press `F5` or go to Run and Debug panel
3. Select the appropriate configuration
4. Run the script

### Method 2: Command Line Profiling

For CLI scripts, profiling is automatically enabled with the current configuration:

```bash
php performance_diagnostic.php
```

This will generate a profile file in `/tmp/xdebug_profiles/`

### Method 3: Web Request Profiling

For web requests, add the trigger parameter to your URL:

```
http://localhost:8081/api/users?XDEBUG_PROFILE=1
```

Or set the trigger in your request headers:
```
X-XDEBUG-PROFILE: 1
```

## Analyzing Profile Data

### 1. Finding Profile Files

Profile files are saved to `/tmp/xdebug_profiles/` with names like:
- `cachegrind.out.12345.abc123` (CLI)
- `cachegrind.out.67890.def456` (Web)

### 2. Opening in VSCode

1. Install the **PHP Profiler** extension if not already installed
2. Open VSCode
3. Use `Ctrl+Shift+P` and type "PHP: Open Profile"
4. Navigate to `/tmp/xdebug_profiles/` and select your profile file
5. The extension will display:
   - Function call hierarchy
   - Time spent in each function
   - Memory usage
   - Call counts

### 3. Understanding the Profile Data

The profiler shows:
- **Inclusive Time**: Total time including called functions
- **Exclusive Time**: Time spent only in that function
- **Call Count**: How many times the function was called
- **Memory Usage**: Memory consumed by the function

## Performance Analysis for Gravitycar Framework

### Key Areas to Profile

1. **API Route Discovery** (`APIRouteRegistry`)
2. **Database Operations** (`DatabaseConnector`)
3. **Model Instantiation** (`ModelFactory`)
4. **Metadata Loading** (`MetadataEngine`)
5. **Full Request Cycle** (API endpoints)

### Example Profiling Workflow

1. **Baseline Profile**:
   ```bash
   php performance_diagnostic.php
   ```

2. **API Endpoint Profile**:
   ```
   curl "http://localhost:8081/api/users?XDEBUG_PROFILE=1&limit=10"
   ```

3. **Specific Component Profile**:
   Use the VSCode debugger with profiling enabled on specific test files.

### Interpreting Results

Look for:
- **High Inclusive Time**: Functions that take the most total time
- **High Exclusive Time**: Functions doing expensive work themselves
- **High Call Count**: Functions called too frequently
- **Memory Hotspots**: Functions using excessive memory

## Common Performance Issues

### 1. Route Discovery
- **Symptom**: High time in `APIRouteRegistry` constructor
- **Solution**: Ensure route caching is working

### 2. Database Queries
- **Symptom**: High time in database-related functions
- **Solution**: Add indexes, optimize queries, implement query caching

### 3. Metadata Loading
- **Symptom**: High time in `MetadataEngine` methods
- **Solution**: Implement metadata caching, optimize metadata structure

### 4. Model Instantiation
- **Symptom**: High time in `ModelFactory::new()`
- **Solution**: Implement object pooling, optimize field creation

## Troubleshooting

### Profile Files Not Generated
1. Check XDEBUG configuration: `php -i | grep xdebug`
2. Verify directory permissions: `ls -la /tmp/xdebug_profiles/`
3. Check XDEBUG mode includes 'profile': `php -i | grep "xdebug.mode"`

### VSCode Not Opening Profiles
1. Ensure PHP Profiler extension is installed and enabled
2. Try opening files manually: File → Open → `/tmp/xdebug_profiles/`
3. Check file format (should be cachegrind format)

### Performance Impact
- Profiling adds overhead (20-100x slower)
- Only enable for development/testing
- Use trigger mode for web requests to profile selectively

## Advanced Tips

### 1. Conditional Profiling
You can enable profiling conditionally in your code:
```php
if (isset($_GET['profile'])) {
    ini_set('xdebug.mode', 'profile');
}
```

### 2. Profile Filtering
Focus on specific functions by using XDEBUG filters:
```ini
xdebug.profiler_enable_trigger_value=MYAPP_PROFILE
```

### 3. Multiple Profile Comparison
Compare profiles before and after optimizations to measure improvements.

### 4. Integration with CI/CD
Set up automated performance regression testing using profile data.

## File Locations

- **XDEBUG Config**: `/etc/php/8.2/*/conf.d/20-xdebug.ini`
- **Profile Output**: `/tmp/xdebug_profiles/`
- **VSCode Config**: `.vscode/launch.json`
- **Setup Script**: `setup_xdebug_profiling.sh`

This setup provides comprehensive profiling capabilities for identifying and resolving performance bottlenecks in the Gravitycar Framework.
