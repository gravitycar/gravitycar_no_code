# Dependency Injection Implementation

## Overview

The Gravitycar framework uses Aura.Di for comprehensive dependency injection management. This implementation provides automated, container-based architecture with robust error handling, graceful degradation, and **future-proof auto-wiring** that requires zero configuration for new services.

## Architecture Overview

### Container-Based Design

The dependency injection system is built around three core components:

1. **ContainerConfig** - Central configuration and container bootstrap with auto-wiring
2. **ServiceLocator** - Simplified service access with typed methods and mixed parameter support
3. **Service Stubs** - Graceful degradation when services fail

### Future-Proof Auto-Wiring

**Key Innovation:** Any new class added to the `Gravitycar\` namespace is automatically discoverable and wireable without configuration changes.

**Before (Manual Dependency Hell):**
```php
$logger = new Logger('gravitycar');
$config = new Config($logger);
$dbParams = $config->getDatabaseParams();
$dbConnector = new DatabaseConnector($logger, $dbParams);
$metadataEngine = new MetadataEngine($logger, 'src/models', 'src/relationships', 'cache/');
$schemaGenerator = new SchemaGenerator($logger, $dbConnector);
```

**After (Container-Based with Auto-Wiring):**
```php
// Core services (explicitly configured)
$logger = ServiceLocator::getLogger();
$config = ServiceLocator::getConfig();
$dbConnector = ServiceLocator::getDatabaseConnector();

// New services (automatically discovered)
$emailService = ServiceLocator::create(EmailService::class);
$analyticsService = ServiceLocator::create(AnalyticsService::class);
$reportGenerator = ServiceLocator::create(ReportGenerator::class, [
    'reportType' => 'monthly',
    'options' => ['format' => 'pdf']
]);
```

## Core Components

### 1. ContainerConfig Class

**Location:** `src/core/ContainerConfig.php`

**Purpose:** Central container configuration with error handling, fallback mechanisms, and future-proof auto-wiring.

#### Key Features:
- Singleton container management
- Service configuration with lazy loading
- Automatic fallback container for critical failures
- **Future-proof auto-wiring for all Gravitycar classes**
- Factory methods for dynamic object creation
- Circular dependency detection and prevention

#### Class Structure:
```php
<?php
namespace Gravitycar\Core;

use Aura\Di\Container;
use Aura\Di\ContainerBuilder;
// ... other imports

class ContainerConfig {
    private static ?Container $container = null;
    
    // Public API
    public static function getContainer(): Container
    public static function autoWire(string $className): object
    public static function resetContainer(): void
    public static function configureForTesting(array $testServices = []): Container
    public static function createModel(string $modelClass, array $metadata = []): object
    public static function createField(string $fieldClass, array $metadata): object
    public static function createValidationRule(string $ruleClass): object
    
    // Container building
    private static function buildContainer(): Container
    private static function configureCoreServices(Container $di): void
    private static function configureFactories(Container $di): void
    private static function configureApplicationServices(Container $di): void
    private static function buildFallbackContainer(Exception $originalError): Container
    
    // Auto-wiring engine
    private static function createInstanceWithAutoWiring(string $className, Container $di, array $resolutionStack = [], int $depth = 0): object
    private static function resolveDependency(\ReflectionParameter $parameter, Container $di, array $resolutionStack = [], int $depth = 0): mixed
    private static function resolvePrimitiveDependency(\ReflectionParameter $parameter, Container $di): mixed
}
```

#### Core Services Configuration:

**Logger Service (with fallback):**
```php
$di->set('logger', $di->lazy(function() {
    try {
        $logger = new Logger('gravitycar');
        $logDir = dirname('logs/gravitycar.log');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $handler = new StreamHandler('logs/gravitycar.log', Logger::INFO);
        $logger->pushHandler($handler);
        return $logger;
    } catch (Exception $e) {
        // Fallback to stderr if file logging fails
        $logger = new Logger('gravitycar');
        $logger->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));
        $logger->warning('File logging failed, using stderr: ' . $e->getMessage());
        return $logger;
    }
}));
```

**Config Service (with error handling):**
```php
$di->set('config', $di->lazy(function() use ($di) {
    try {
        return new Config($di->get('logger'));
    } catch (\Gravitycar\Exceptions\GCException $e) {
        $logger = $di->get('logger');
        $logger->error('Config loading failed: ' . $e->getMessage());
        return new \Gravitycar\Core\ConfigStub($logger, $e);
    }
}));
```

**DatabaseConnector Service (with graceful degradation):**
```php
$di->set('database_connector', $di->lazy(function() use ($di) {
    try {
        $config = $di->get('config');
        $dbParams = $config->getDatabaseParams();
        if (empty($dbParams)) {
            throw new \Gravitycar\Exceptions\GCException('Database parameters not configured');
        }
        return new DatabaseConnector($di->get('logger'), $dbParams);
    } catch (Exception $e) {
        $logger = $di->get('logger');
        $logger->error('Database connector failed: ' . $e->getMessage());
        return new \Gravitycar\Database\DatabaseConnectorStub($logger, $e);
    }
}));
```

### 2. Future-Proof Auto-Wiring Engine

#### Auto-Wiring Rules:

**Automatically Wired:** All classes matching `Gravitycar\*`

**Excluded Namespaces:**
- `Gravitycar\External\` - Third-party integrations
- `Gravitycar\Legacy\` - Legacy code that shouldn't be auto-wired  
- `Gravitycar\Tests\` - Test classes

#### Implementation:
```php
public static function autoWire(string $className): object {
    $di = self::getContainer();
    
    try {
        if ($di->has($className)) {
            return $di->get($className);
        }
        
        return self::createInstanceWithAutoWiring($className, $di, [], 0);
    } catch (Exception $e) {
        throw new \Gravitycar\Exceptions\GCException(
            "Auto-wiring failed for class $className: " . $e->getMessage(),
            $di->get('logger'),
            $e
        );
    }
}

private static function resolveDependency(\ReflectionParameter $parameter, Container $di, array $resolutionStack = [], int $depth = 0): mixed {
    $type = $parameter->getType();
    
    if (!$type || $type->isBuiltin()) {
        return self::resolvePrimitiveDependency($parameter, $di);
    }
    
    $typeName = $type->getName();
    
    // Map to container services for core framework classes
    $serviceMap = [
        'Monolog\\Logger' => 'logger',
        'Gravitycar\\Core\\Config' => 'config',
        'Gravitycar\\Database\\DatabaseConnector' => 'database_connector',
        'Gravitycar\\Metadata\\MetadataEngine' => 'metadata_engine',
        'Gravitycar\\Factories\\FieldFactory' => 'field_factory',
        'Gravitycar\\Factories\\ValidationRuleFactory' => 'validation_rule_factory',
    ];
    
    if (isset($serviceMap[$typeName])) {
        return $di->get($serviceMap[$typeName]);
    }
    
    // Auto-wire all Gravitycar classes except excluded namespaces
    $excludedNamespaces = [
        'Gravitycar\\External\\',
        'Gravitycar\\Legacy\\',  
        'Gravitycar\\Tests\\',
    ];
    
    $isAutoWirable = str_starts_with($typeName, 'Gravitycar\\');
    
    foreach ($excludedNamespaces as $excluded) {
        if (str_starts_with($typeName, $excluded)) {
            $isAutoWirable = false;
            break;
        }
    }
    
    if (!$isAutoWirable) {
        // Handle non-auto-wirable dependencies with fallbacks
        if ($parameter->allowsNull()) return null;
        if ($parameter->isDefaultValueAvailable()) return $parameter->getDefaultValue();
        throw new \Exception("Cannot auto-wire type $typeName");
    }
    
    // Recursively auto-wire the dependency
    return self::createInstanceWithAutoWiring($typeName, $di, $resolutionStack, $depth);
}
```

### 3. ServiceLocator Class

**Location:** `src/core/ServiceLocator.php`

**Purpose:** Simplified service access with typed methods and support for mixed parameters.

#### Key Features:
- Typed methods for core services
- Auto-wiring support for new services
- Mixed parameter support (explicit + auto-wired)
- Container locking protection
- Error handling with meaningful messages

#### Class Structure:
```php
<?php
namespace Gravitycar\Core;

class ServiceLocator {
    private static ?Container $container = null;
    
    // Core service accessors
    public static function getLogger(): Logger
    public static function getConfig(): Config
    public static function getDatabaseConnector(): DatabaseConnector
    public static function getMetadataEngine(): MetadataEngine
    public static function getSchemaGenerator(): SchemaGenerator
    public static function getRouter(): \Gravitycar\Api\Router
    public static function getFieldFactory(): FieldFactory
    public static function getValidationRuleFactory(): ValidationRuleFactory
    public static function getInstaller(): \Gravitycar\Models\Installer
    
    // Dynamic creation methods
    public static function createModel(string $modelClass, array $metadata = []): object
    public static function createField(string $fieldClass, array $metadata): object
    public static function createValidationRule(string $ruleClass): object
    
    // Auto-wiring methods
    public static function get(string $serviceName): object
    public static function create(string $className, array $parameters = []): object
    
    // Testing support
    public static function setContainer(Container $container): void
    public static function reset(): void
    
    // Private implementation
    private static function createWithMixedParameters(string $className, array $parameters): object
}
```

#### Mixed Parameter Support:

The `create()` method supports mixing explicit parameters with auto-wired dependencies:

```php
public static function create(string $className, array $parameters = []): object {
    if (empty($parameters)) {
        return ContainerConfig::autoWire($className);
    }
    
    return self::createWithMixedParameters($className, $parameters);
}

private static function createWithMixedParameters(string $className, array $parameters): object {
    // Uses reflection to examine constructor parameters
    // For each parameter:
    //   1. If explicitly provided in $parameters, use that value
    //   2. If auto-wirable dependency, auto-wire it
    //   3. If primitive with default, use default
    //   4. If nullable, use null
    //   5. Otherwise throw exception
    
    $reflection = new \ReflectionClass($className);
    $constructor = $reflection->getConstructor();
    
    if (!$constructor) {
        return new $className();
    }
    
    $dependencies = [];
    
    foreach ($constructor->getParameters() as $param) {
        $paramName = $param->getName();
        
        if (array_key_exists($paramName, $parameters)) {
            $dependencies[] = $parameters[$paramName];
            continue;
        }
        
        // Auto-wire this parameter
        $type = $param->getType();
        
        if (!$type || $type->isBuiltin()) {
            // Handle primitives with defaults/null
        } else {
            // Auto-wire dependencies
            $typeName = $type->getName();
            // Map to container services or auto-wire custom classes
        }
    }
    
    return $reflection->newInstanceArgs($dependencies);
}
```

## Usage Examples

### 1. Core Services (Explicitly Configured)
```php
$logger = ServiceLocator::getLogger();
$config = ServiceLocator::getConfig();
$dbConnector = ServiceLocator::getDatabaseConnector();
```

### 2. Simple Auto-Wiring (Zero Configuration)
```php
// These work automatically - no container configuration needed!
$emailService = ServiceLocator::create(EmailService::class);
$analyticsService = ServiceLocator::create(AnalyticsService::class);
$notificationService = ServiceLocator::create(NotificationService::class);
```

### 3. Mixed Parameters (Auto-wire + Explicit)
```php
// Auto-wire Logger/Config, provide custom parameters
$reportGenerator = ServiceLocator::create(ReportGenerator::class, [
    'reportType' => 'monthly',
    'options' => ['format' => 'pdf', 'include_charts' => true]
]);
```

### 4. Service Dependencies (Nested Auto-Wiring)
```php
// NotificationService constructor takes EmailService parameter
// EmailService is automatically created and injected
$notificationService = ServiceLocator::create(NotificationService::class);
$notificationService->sendWelcomeNotification('user@example.com');
```

### 5. Attribute-Based Services
```php
class AnalyticsService {
    public function __construct(
        #[Inject('logger')] private Logger $logger,
        #[Inject('config')] private Config $config
    ) {}
}

// Works automatically
$analytics = ServiceLocator::create(AnalyticsService::class);
```

## Error Handling and Recovery

### Graceful Degradation

The system includes comprehensive error handling:

1. **Service Failures:** Fallback to stub services that provide meaningful error messages
2. **Missing Config:** ConfigStub provides default values and logs issues
3. **Database Unavailable:** DatabaseConnectorStub prevents crashes
4. **Circular Dependencies:** Detection and prevention with helpful error messages

### Fallback Container

When critical failures occur during container initialization:

```php
private static function buildFallbackContainer(Exception $originalError): Container {
    $builder = new ContainerBuilder();
    $di = $builder->newInstance();
    
    // Minimal logger that works without file system
    $di->set('logger', $di->lazy(function() use ($originalError) {
        $logger = new Logger('gravitycar-fallback');
        $logger->pushHandler(new StreamHandler('php://stderr', Logger::ERROR));
        $logger->error('Container initialization failed: ' . $originalError->getMessage());
        return $logger;
    }));
    
    // Stub services that provide error messages instead of crashing
    $di->set('config', $di->lazy(function() use ($di, $originalError) {
        return new \Gravitycar\Core\ConfigStub($di->get('logger'), $originalError);
    }));
    
    return $di;
}
```

## Testing Support

### Container Replacement for Testing
```php
// Replace services with mocks for testing
$testServices = [
    'database_connector' => $mockDatabase,
    'logger' => $mockLogger
];

$testContainer = ContainerConfig::configureForTesting($testServices);
ServiceLocator::setContainer($testContainer);
```

### Container Reset
```php
// Reset container state between tests
ServiceLocator::reset();
```

## Future-Proofing Benefits

### 1. Zero Configuration for New Services
When you add a new service like `Gravitycar\Analytics\MetricsCollector`:

```php
// This works immediately without any configuration changes!
$metrics = ServiceLocator::create(MetricsCollector::class);
```

### 2. Automatic Dependency Resolution
New services automatically get their dependencies:

```php
class MetricsCollector {
    public function __construct(
        private Logger $logger,              // Auto-injected
        private Config $config,              // Auto-injected
        private DatabaseConnector $db        // Auto-injected
    ) {}
}
```

### 3. Maintainable Exclusions
Only update configuration for special cases:

```php
$excludedNamespaces = [
    'Gravitycar\\External\\',    // Third-party integrations
    'Gravitycar\\Legacy\\',      // Legacy code
    'Gravitycar\\Tests\\',       // Test classes
    'Gravitycar\\Experimental\\' // New: experimental features
];
```

## Implementation Checklist

To rebuild this system from scratch:

### 1. Install Dependencies
```bash
composer require aura/di monolog/monolog
```

### 2. Create ContainerConfig Class
- Implement singleton container management
- Configure core services with error handling
- Implement auto-wiring engine with namespace filtering
- Add circular dependency detection
- Create fallback container for critical failures

### 3. Create ServiceLocator Class  
- Add typed methods for core services
- Implement auto-wiring methods
- Add mixed parameter support with container lock protection
- Include testing support methods

### 4. Create Service Stubs
- ConfigStub for missing configuration
- DatabaseConnectorStub for database failures
- MetadataEngineStub for metadata failures

### 5. Update All Classes
- Remove hardcoded constructor arguments
- Use ServiceLocator for dependency access
- Remove manual dependency passing

### 6. Testing
- Create demo files to verify auto-wiring
- Test error conditions and fallbacks
- Verify container locking behavior

This implementation provides a robust, future-proof dependency injection system that requires minimal maintenance while providing comprehensive error handling and graceful degradation.
