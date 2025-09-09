<?php
namespace Gravitycar\Api;

use Gravitycar\Core\ServiceLocator;
use Gravitycar\Core\Config;
use Gravitycar\Database\DatabaseConnector;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Contracts\DatabaseConnectorInterface;
use Gravitycar\Contracts\MetadataEngineInterface;
use Psr\Log\LoggerInterface;
use Monolog\Logger;

/**
 * HealthAPIController: Provides health monitoring endpoints for system status
 * 
 * This controller implements two endpoints:
 * - /ping: Ultra-fast availability check (< 5ms target)
 * - /health: Comprehensive system health diagnostics (< 50ms target)
 */
class HealthAPIController extends ApiControllerBase {
    private static ?array $cachedChecks = null;
    private static ?float $lastCheckTime = null;
    
    // Cache health checks for 30 seconds to avoid repeated work
    private const CHECK_CACHE_TTL = 30;
    
    public function __construct(
        Logger $logger = null,
        ModelFactory $modelFactory = null,
        DatabaseConnectorInterface $databaseConnector = null,
        MetadataEngineInterface $metadataEngine = null,
        Config $config = null
    ) {
        parent::__construct($logger, $modelFactory, $databaseConnector, $metadataEngine, $config);
    }
    
    /**
     * Register routes for this controller
     */
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
    
    /**
     * Ultra-fast availability check endpoint
     * Target response time: < 5ms
     */
    public function getPing(): array {
        return [
            'success' => true,
            'status' => 200,
            'timestamp' => date('c')
        ];
    }
    
    /**
     * Comprehensive health check endpoint
     * Target response time: < 50ms
     */
    public function getHealth(): array {
        // Return cached result if recent enough
        if (self::$cachedChecks !== null && 
            self::$lastCheckTime !== null && 
            (microtime(true) - self::$lastCheckTime) < self::CHECK_CACHE_TTL) {
            return self::$cachedChecks;
        }
        
        // Perform checks and cache result
        $result = $this->performHealthChecks();
        
        // Cache results if caching is enabled
        if ($this->config->get('health.enable_caching', true)) {
            self::$cachedChecks = $result;
            self::$lastCheckTime = microtime(true);
        }
        
        return $result;
    }
    
    /**
     * Perform all health checks and format response
     */
    private function performHealthChecks(): array {
        $startTime = microtime(true);
        $checks = [];
        
        // Always perform lightweight checks
        $checks['metadata_cache'] = $this->safeCheck([$this, 'checkMetadataCache'], 'metadata_cache');
        $checks['file_system'] = $this->safeCheck([$this, 'checkFileSystem'], 'file_system');
        $checks['memory'] = $this->safeCheck([$this, 'checkMemory'], 'memory');
        
        // Conditional database check
        if ($this->config->get('health.check_database', true)) {
            $checks['database'] = $this->safeCheck([$this, 'checkDatabase'], 'database');
        }
        
        // Calculate overall status
        $overallStatus = $this->calculateOverallStatus($checks);
        
        // Calculate uptime (approximate based on startup)
        $uptime = $this->calculateUptime();
        
        $result = [
            'success' => true,
            'status' => 200,
            'data' => [
                'status' => $overallStatus,
                'timestamp' => date('c'),
                'uptime' => $uptime,
                'version' => $this->config->get('app.version', '1.0.0'),
                'checks' => $checks,
                'environment' => $this->config->get('app.environment', 'development')
            ]
        ];
        
        // Add performance metrics if debug mode enabled
        if ($this->config->get('health.enable_debug_info', false)) {
            $result['data']['performance'] = [
                'check_duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'cached_result' => false
            ];
        }
        
        return $result;
    }
    
    /**
     * Check database connectivity with lightweight query
     */
    private function checkDatabase(): array {
        $startTime = microtime(true);
        $timeout = $this->config->get('health.database_timeout', 5);
        
        try {
            $database = $this->databaseConnector;
            $connection = $database->getConnection();
            
            // Execute lightweight query using Doctrine DBAL
            $stmt = $connection->executeQuery('SELECT 1');
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($stmt === false) {
                throw new \Exception('Query failed');
            }
            
            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime
            ];
            
        } catch (\Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => 'unhealthy',
                'response_time_ms' => $responseTime,
                'error' => $this->config->get('health.expose_detailed_errors', false) 
                    ? $e->getMessage() 
                    : 'Connection failed'
            ];
        }
    }
    
    /**
     * Check metadata cache file availability
     */
    private function checkMetadataCache(): array {
        $cacheFile = $this->config->get('cache.metadata_file', 'cache/metadata_cache.php');
        
        // Make path absolute if relative
        if (!str_starts_with($cacheFile, '/')) {
            $cacheFile = getcwd() . '/' . $cacheFile;
        }
        
        if (!file_exists($cacheFile)) {
            return [
                'status' => 'missing',
                'file_exists' => false
            ];
        }
        
        $stat = stat($cacheFile);
        if ($stat === false) {
            return [
                'status' => 'unhealthy',
                'file_exists' => true,
                'error' => 'Unable to read file stats'
            ];
        }
        
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
    
    /**
     * Check file system permissions for critical directories
     */
    private function checkFileSystem(): array {
        $cacheDir = $this->config->get('cache.directory', 'cache');
        $logsDir = $this->config->get('logging.directory', 'logs');
        
        // Make paths absolute if relative
        if (!str_starts_with($cacheDir, '/')) {
            $cacheDir = getcwd() . '/' . $cacheDir;
        }
        if (!str_starts_with($logsDir, '/')) {
            $logsDir = getcwd() . '/' . $logsDir;
        }
        
        $cacheWritable = is_dir($cacheDir) && is_writable($cacheDir);
        $logsWritable = is_dir($logsDir) && is_writable($logsDir);
        
        return [
            'status' => ($cacheWritable && $logsWritable) ? 'healthy' : 'unhealthy',
            'cache_writable' => $cacheWritable,
            'logs_writable' => $logsWritable
        ];
    }
    
    /**
     * Check current memory usage
     */
    private function checkMemory(): array {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = $this->getMemoryLimit();
        
        $usageMB = round($usage / 1024 / 1024, 2);
        $peakMB = round($peak / 1024 / 1024, 2);
        $limitMB = round($limit / 1024 / 1024, 2);
        $percentage = $limit > 0 ? round(($usage / $limit) * 100, 1) : 0;
        
        // Determine status based on memory usage
        $warningThreshold = $this->config->get('health.memory_warning_percentage', 80);
        $status = $percentage > $warningThreshold ? 'warning' : 'healthy';
        
        return [
            'status' => $status,
            'usage_mb' => $usageMB,
            'peak_mb' => $peakMB,
            'limit_mb' => $limitMB,
            'percentage' => $percentage
        ];
    }
    
    /**
     * Get PHP memory limit in bytes
     */
    private function getMemoryLimit(): int {
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            return 0; // Unlimited
        }
        
        // Convert to bytes
        $value = (int) $memoryLimit;
        $unit = strtolower(substr($memoryLimit, -1));
        
        switch ($unit) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }
        
        return $value;
    }
    
    /**
     * Calculate overall health status from individual checks
     */
    private function calculateOverallStatus(array $checks): string {
        $criticalServices = ['database', 'metadata_cache'];
        $hasDegradedService = false;
        
        foreach ($checks as $service => $result) {
            $status = $result['status'] ?? 'unknown';
            
            // Critical service failure = unhealthy
            if ($status === 'unhealthy' && in_array($service, $criticalServices)) {
                return 'unhealthy';
            }
            
            // Any unhealthy, stale, or warning status = degraded
            if (in_array($status, ['unhealthy', 'stale', 'warning'])) {
                $hasDegradedService = true;
            }
        }
        
        return $hasDegradedService ? 'degraded' : 'healthy';
    }
    
    /**
     * Calculate approximate uptime in seconds
     */
    private function calculateUptime(): int {
        // Simple approximation based on when the script started
        // For more accurate uptime, you'd need to track actual server startup
        static $startTime = null;
        if ($startTime === null) {
            $startTime = time();
        }
        
        return time() - $startTime;
    }
    
    /**
     * Execute a health check safely with error handling
     */
    private function safeCheck(callable $checkFunction, string $checkName): array {
        try {
            return $checkFunction();
        } catch (\Exception $e) {
            $this->logger->warning("Health check failed: {$checkName}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => 'unhealthy',
                'error' => $this->config->get('health.expose_detailed_errors', false) 
                    ? $e->getMessage() 
                    : 'Check failed'
            ];
        }
    }
}
