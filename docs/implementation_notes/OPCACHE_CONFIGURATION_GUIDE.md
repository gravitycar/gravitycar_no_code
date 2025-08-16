# Opcache Configuration Recommendations

## Development Settings (Current - Good!)
```ini
opcache.validate_timestamps=1     # Check for file changes
opcache.revalidate_freq=60        # Check every 60 seconds
opcache.memory_consumption=512    # Generous memory
```

## Production Settings (Consider for Production)
```ini
opcache.validate_timestamps=0     # Don't check for changes (faster)
opcache.revalidate_freq=0         # No revalidation needed
opcache.memory_consumption=256    # Less memory needed
```

## When You DO Need to Restart Apache

### 1. **PHP Configuration Changes**
- Changes to `php.ini` files
- Changes to opcache settings
- Installing/removing PHP extensions

### 2. **Production Deployments (if validate_timestamps=0)**
- When deploying new code to production
- When you want 100% certainty that changes are loaded

### 3. **Debugging Opcache Issues**
- If you suspect opcache is causing problems
- If you want to start with a completely clean cache

## Manual Cache Control Commands

### Clear Entire Opcache
```bash
php -r "opcache_reset(); echo 'Opcache cleared\n';"
```

### Clear Specific File
```bash
php -r "opcache_invalidate('src/Models/SomeModel.php', true); echo 'File cache cleared\n';"
```

### Check Opcache Status
```bash
php -r "var_dump(opcache_get_status());"
```

## Development Workflow Tips

### 1. **Normal Development**
- ✅ Make code changes as usual
- ✅ Changes detected within 60 seconds
- ✅ No restart needed

### 2. **Immediate Testing Needed**
- Clear opcache manually: `php -r "opcache_reset();"`
- Or wait up to 60 seconds

### 3. **Production Deployment**
- Consider setting `validate_timestamps=0` for better performance
- Restart Apache/PHP-FPM after deployments

## Monitoring Opcache Health

### Check Performance
```bash
php -r "
\$s = opcache_get_status();
echo 'Hit rate: ' . \$s['opcache_statistics']['opcache_hit_rate'] . '%\n';
echo 'Memory used: ' . round(\$s['memory_usage']['used_memory']/1024/1024, 2) . 'MB\n';
echo 'Cached files: ' . \$s['opcache_statistics']['num_cached_scripts'] . '\n';
"
```

### Ideal Metrics
- **Hit rate**: >95% (you're getting 97.7%!)
- **Memory usage**: <80% of allocated
- **Cached files**: Should include all your framework files
