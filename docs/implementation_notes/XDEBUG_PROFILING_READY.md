# XDEBUG Profiling Setup Complete - Quick Start Guide

## 🎉 Setup Status: COMPLETE ✅

XDEBUG profiling is now configured and working with VSCode for the Gravitycar Framework.

## Quick Start

### 1. Install Required VSCode Extension

Install the PHP Profiler extension:

```vscode-extensions
devsense.profiler-php-vscode
```

### 2. Generate Profile Files

**Method A: Command Line with Explicit Settings**
```bash
php -d xdebug.mode=profile -d xdebug.start_with_request=yes your_script.php
```

**Method B: Using Environment Variable**
```bash
XDEBUG_TRIGGER=1 php your_script.php
```

**Method C: VSCode Debug Configuration**
- Press `F5` in VSCode
- Select "Debug with Profiling" or "Profile Performance Diagnostic"

### 3. View Profile Results

1. Profile files are saved to: `/tmp/xdebug_profiles/`
2. Files are compressed (`.gz` format) - VSCode PHP Profiler extension handles this automatically
3. Open profile files in VSCode:
   - `Ctrl+Shift+P` → "PHP: Open Profile"
   - Navigate to `/tmp/xdebug_profiles/`
   - Select your profile file

## Example: Profile the Performance Diagnostic

```bash
# Generate profile
php -d xdebug.mode=profile -d xdebug.start_with_request=yes performance_diagnostic.php

# List generated files
ls -la /tmp/xdebug_profiles/
```

## Current Profile Files Available

```
cachegrind.out.412285.04ca9c.gz (865KB) - profiling_test.php
cachegrind.out.412321.07853f.gz (225KB) - performance_diagnostic.php
```

You can open these files now to analyze performance!

## Key Configuration Files

- **Setup Script**: `setup_xdebug_profiling.sh` ✅ Complete
- **VSCode Launch Config**: `.vscode/launch.json` ✅ Updated
- **XDEBUG Config**: `/etc/php/8.2/*/conf.d/20-xdebug.ini` ✅ Configured
- **Profile Output**: `/tmp/xdebug_profiles/` ✅ Working

## What to Look For in Profiles

1. **Function call hierarchy** - Which functions call which
2. **Time analysis** - Where your application spends the most time
3. **Call counts** - Functions called excessively
4. **Memory usage** - Memory-intensive operations

## Next Steps

1. **Install the PHP Profiler extension** in VSCode
2. **Open the generated profile files** to analyze performance
3. **Focus on the database and model operations** (identified as slow in diagnostic)
4. **Use profiling data** to optimize specific bottlenecks

## Troubleshooting

If profiles aren't generated:
```bash
# Check XDEBUG status
php -i | grep -A 10 "Enabled Features"

# Verify output directory
php -r "echo ini_get('xdebug.output_dir');"

# Test with simple script
php -d xdebug.mode=profile -d xdebug.start_with_request=yes profiling_test.php
```

**🚀 You're all set to start profiling and optimizing the Gravitycar Framework!**
