# Health Endpoint Implementation Plan

**Date**: August 28, 2025  
**Purpose**: Implementation plan for a high-performance `/health` endpoint for the Gravitycar Framework

## Overview

The `/health` endpoint will provide rapid system status checks with minimal overhead. The primary focus is on **performance** - this endpoint must be callable frequently without impacting system performance.

## Design Principles

### 1. **Performance First**
- No disk reads beyond essential checks
- No database queries unless absolutely necessary
- Minimal memory allocation
- Response time target: < 50ms

### 2. **Essential Checks Only**
- Focus on critical system availability
- Avoid deep validation or consistency checks
- Use cached values where possible
- Fail fast on critical errors

### 3. **Lightweight Validation**
- File existence checks (not content validation)
- Service availability (not functionality validation)
- Memory usage (current state only)
- No metadata consistency validation

## Implementation Structure

### Location
- **File**: `src/Api/HealthAPIController.php`
- **Routes**: 
  - `GET /health` - Comprehensive health checks
  - `GET /ping` - Minimal availability check
- **Registration**: Via existing route registration system

### Response Format

#### /health Endpoint
```json
{
  "success": true,
  "status": 200,
  "data": {
    "status": "healthy|degraded|unhealthy",
    "timestamp": "2025-08-28T15:45:00+00:00",
    "uptime": 3600,
    "version": "1.0.0",
    "checks": {
      "database": {
        "status": "healthy|unhealthy",
        "response_time_ms": 15
      },
      "metadata_cache": {
        "status": "healthy|missing|stale",
        "file_exists": true,
        "file_size_kb": 245,
        "last_modified": "2025-08-28T15:30:00+00:00"
      },
      "file_system": {
        "status": "healthy|unhealthy",
        "cache_writable": true,
        "logs_writable": true
      },
      "memory": {
        "usage_mb": 45.2,
        "peak_mb": 52.1,
        "limit_mb": 128,
        "percentage": 35.3
      }
    },
    "environment": "development|production|testing"
  }
}
```

#### /ping Endpoint
```json
{
  "success": true,
  "status": 200,
  "timestamp": "2025-08-28T15:45:00+00:00"
}
```

## Detailed Implementation Plan

### 1. Controller Structure

#### Class Definition
```php
class HealthAPIController {
    private Config $config;
    private LoggerInterface $logger;
    private static ?array $cachedChecks = null;
    private static ?float $lastCheckTime = null;
    
    // Cache health checks for 30 seconds to avoid repeated work
    private const CHECK_CACHE_TTL = 30;
}
```

#### Route Registration
```php
public function registerRoutes(): array {
    return [
        [
            'method' => 'GET',
            'path' => '/health',
            'apiClass' => '\\Gravitycar\\Api\\HealthAPIController',
            'apiMethod' => 'getHealth',
            'parameterNames' => []
        ],
        [
            'method' => 'GET',
            'path' => '/ping',
            'apiClass' => '\\Gravitycar\\Api\\HealthAPIController',
            'apiMethod' => 'getPing',
            'parameterNames' => []
        ]
    ];
}
```

### 2. Health Check Categories

#### Ping Endpoint Implementation
**Purpose**: Ultra-fast availability check
**Implementation**:
- Minimal processing overhead
- No external service checks
- Immediate response
- Target response time: < 5ms

```php
public function getPing(): array {
    return [
        'success' => true,
        'status' => 200,
        'timestamp' => date('c')
    ];
}
```

#### A. Database Health Check
**Purpose**: Verify database connectivity
**Implementation**:
- Single lightweight query: `SELECT 1`
- Timeout: 5 seconds maximum
- Measure response time
- **NO** complex queries or data validation

```php
private function checkDatabase(): array {
    $startTime = microtime(true);
    try {
        $pdo = ServiceLocator::getDatabase()->getConnection();
        $stmt = $pdo->query('SELECT 1');
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        
        return [
            'status' => 'healthy',
            'response_time_ms' => $responseTime
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'unhealthy',
            'error' => 'Connection failed'
        ];
    }
}
```

#### B. Metadata Cache Health Check
**Purpose**: Verify metadata cache file availability
**Implementation**:
- **File existence check only** (no content parsing)
- File size check (basic corruption indicator)
- Last modified timestamp
- **NO** content validation or consistency checks

```php
private function checkMetadataCache(): array {
    $cacheFile = $this->config->get('cache.metadata_file', 'cache/metadata_cache.php');
    
    if (!file_exists($cacheFile)) {
        return [
            'status' => 'missing',
            'file_exists' => false
        ];
    }
    
    $stat = stat($cacheFile);
    $fileSize = round($stat['size'] / 1024, 2); // KB
    $lastModified = date('c', $stat['mtime']);
    
    // Consider stale if older than configured threshold
    $staleThreshold = $this->config->get('health.metadata_stale_hours', 24) * 3600;
    $isStale = (time() - $stat['mtime']) > $staleThreshold;
    
    return [
        'status' => $isStale ? 'stale' : 'healthy',
        'file_exists' => true,
        'file_size_kb' => $fileSize,
        'last_modified' => $lastModified
    ];
}
```

#### C. File System Health Check
**Purpose**: Verify critical directories are writable
**Implementation**:
- Test write permissions on cache directory
- Test write permissions on logs directory
- **NO** extensive file system scanning

```php
private function checkFileSystem(): array {
    $cacheDir = $this->config->get('cache.directory', 'cache');
    $logsDir = $this->config->get('logging.directory', 'logs');
    
    $cacheWritable = is_writable($cacheDir);
    $logsWritable = is_writable($logsDir);
    
    return [
        'status' => ($cacheWritable && $logsWritable) ? 'healthy' : 'unhealthy',
        'cache_writable' => $cacheWritable,
        'logs_writable' => $logsWritable
    ];
}
```

#### D. Memory Health Check
**Purpose**: Report current memory usage
**Implementation**:
- Use PHP's built-in memory functions
- **NO** memory allocation tests or stress testing

```php
private function checkMemory(): array {
    $usage = memory_get_usage(true);
    $peak = memory_get_peak_usage(true);
    $limit = $this->getMemoryLimit();
    
    $usageMB = round($usage / 1024 / 1024, 2);
    $peakMB = round($peak / 1024 / 1024, 2);
    $limitMB = round($limit / 1024 / 1024, 2);
    $percentage = round(($usage / $limit) * 100, 1);
    
    return [
        'usage_mb' => $usageMB,
        'peak_mb' => $peakMB,
        'limit_mb' => $limitMB,
        'percentage' => $percentage
    ];
}
```

### 3. Performance Optimizations

#### A. Response Caching
- Cache health check results for 30 seconds
- Use static variables to avoid repeated checks
- Skip expensive checks if recently performed

```php
public function getHealth(): array {
    // Return cached result if recent enough
    if (self::$cachedChecks !== null && 
        self::$lastCheckTime !== null && 
        (microtime(true) - self::$lastCheckTime) < self::CHECK_CACHE_TTL) {
        return self::$cachedChecks;
    }
    
    // Perform checks and cache result
    $result = $this->performHealthChecks();
    self::$cachedChecks = $result;
    self::$lastCheckTime = microtime(true);
    
    return $result;
}
```

#### B. Configurable Check Levels
- Allow disabling expensive checks in production
- Configurable timeouts for database checks
- Optional checks based on environment

```php
private function performHealthChecks(): array {
    $checks = [];
    
    // Always perform lightweight checks
    $checks['metadata_cache'] = $this->checkMetadataCache();
    $checks['file_system'] = $this->checkFileSystem();
    $checks['memory'] = $this->checkMemory();
    
    // Conditional database check
    if ($this->config->get('health.check_database', true)) {
        $checks['database'] = $this->checkDatabase();
    }
    
    return $this->formatResponse($checks);
}
```

#### C. Fast Failure Detection
- Set aggressive timeouts
- Fail fast on critical errors
- Use circuit breaker pattern for database checks

### 4. Status Determination Logic

#### Overall Status Calculation
```php
private function calculateOverallStatus(array $checks): string {
    $criticalServices = ['database', 'metadata_cache'];
    $hasUnhealthyService = false;
    $hasDegradedService = false;
    
    foreach ($checks as $service => $result) {
        $status = $result['status'] ?? 'unknown';
        
        if ($status === 'unhealthy' && in_array($service, $criticalServices)) {
            return 'unhealthy';
        } elseif ($status === 'unhealthy' || $status === 'stale') {
            $hasDegradedService = true;
        }
    }
    
    return $hasDegradedService ? 'degraded' : 'healthy';
}
```

### 5. Configuration Options

#### Health Check Configuration
```php
// config.php additions
'health' => [
    'check_database' => true,
    'database_timeout' => 5, // seconds
    'metadata_stale_hours' => 24,
    'enable_caching' => true,
    'cache_ttl' => 30, // seconds
    'memory_warning_percentage' => 80,
    'expose_detailed_errors' => false // in production
]
```

### 6. Error Handling Strategy

#### Graceful Degradation
- Continue other checks if one fails
- Provide partial health information
- Log errors but don't expose sensitive details

```php
private function safeCheck(callable $checkFunction, string $checkName): array {
    try {
        return $checkFunction();
    } catch (\Exception $e) {
        $this->logger->warning("Health check failed: {$checkName}", [
            'error' => $e->getMessage()
        ]);
        
        return [
            'status' => 'unhealthy',
            'error' => $this->config->get('health.expose_detailed_errors', false) 
                ? $e->getMessage() 
                : 'Check failed'
        ];
    }
}
```

## What This Endpoint Will NOT Do

### ❌ **Expensive Operations to Avoid**
1. **Metadata Consistency Validation**
   - No parsing of metadata files
   - No comparison between cache and source files
   - No validation of metadata structure

2. **Deep Database Validation**
   - No table existence checks
   - No data integrity validation
   - No complex queries

3. **Extensive File System Scanning**
   - No recursive directory traversal
   - No file content validation
   - No disk space calculations

4. **Memory Stress Testing**
   - No memory allocation tests
   - No garbage collection forcing
   - No memory leak detection

5. **Service Functionality Testing**
   - No metadata engine initialization
   - No route registry validation
   - No dependency injection testing

## Integration Points

### 1. Route Registration
- Add both endpoints to existing route registration system
- Register in bootstrap process

### 2. Monitoring Integration
- **/ping endpoint**: Ultra-fast load balancer health checks
- **/health endpoint**: Detailed monitoring and alerting systems
- Compatible with automated monitoring tools
- Prometheus metrics compatibility

### 3. Development Tools
- Integration with VSCode custom tools
- Support for CI/CD health checks
- Development environment validation

### 4. Endpoint Usage Guidelines
- **Use /ping for**:
  - Load balancer health checks
  - High-frequency monitoring (every few seconds)
  - Basic availability testing
  - Uptime monitoring services
  
- **Use /health for**:
  - Detailed system diagnostics
  - Operational dashboards
  - Incident investigation
  - Capacity planning
  - Lower frequency monitoring (every 30-60 seconds)

## Testing Strategy

### 1. Performance Testing
- **/health** response time under 50ms target
- **/ping** response time under 5ms target
- Memory usage impact measurement
- Concurrent request handling for both endpoints

### 2. Reliability Testing
- Database unavailability scenarios
- File system permission issues
- Memory pressure situations

### 3. Integration Testing
- Load balancer integration
- Monitoring tool compatibility
- CI/CD pipeline integration

## Security Considerations

### 1. Information Disclosure
- Configurable detail levels
- Production vs development responses
- No sensitive path disclosure

### 2. Denial of Service Protection
- Response caching to prevent abuse
- Rate limiting compatibility
- Resource usage bounds

## Success Metrics

### 1. Performance Targets
- **/health** response time: < 50ms (95th percentile)
- **/ping** response time: < 5ms (95th percentile)
- Memory overhead: < 1MB additional for health checks
- CPU impact: < 1% under normal load

### 2. Reliability Targets
- 99.9% availability
- False positive rate: < 0.1%
- Recovery time: < 30 seconds

## Implementation Phases

### Phase 1: Core Implementation
- Basic health controller with both endpoints
- Ping endpoint implementation (ultra-fast)
- Essential health checks (database, file system)
- Route registration for both endpoints

### Phase 2: Enhancement
- Metadata cache validation
- Memory monitoring
- Response caching

### Phase 3: Integration
- Monitoring tool integration
- Configuration refinement
- Performance optimization

### Phase 4: Production Readiness
- Security hardening
- Documentation completion
- Load testing validation

## Conclusion

This dual-endpoint health monitoring implementation provides both ultra-fast availability checking (`/ping`) and comprehensive system diagnostics (`/health`). The design prioritizes performance with different response time targets for different use cases while avoiding expensive validation operations.

**The `/ping` endpoint** serves as the fastest possible availability check, suitable for high-frequency monitoring and load balancer health checks with sub-5ms response times.

**The `/health` endpoint** provides detailed system status information for operational monitoring, debugging, and capacity planning while maintaining rapid response times under 50ms.

Together, these endpoints will serve as a reliable foundation for system monitoring, load balancer health checks, and development environment validation without imposing performance overhead on the Gravitycar framework.
