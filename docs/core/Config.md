# Config Class

## Purpose
Provides a centralized configuration management system for the Gravitycar framework.

## Location
- **Config Class**: `src/core/Config.php`
- **Config File**: `config.php` (root directory of the project)

## Constructor
- The Config class constructor takes **no arguments**
- The constructor instantiates its own Monolog logger internally
- Hard-codes the config file path to `config.php` in the `configFilePath` property

## Properties
- `configFilePath`: Hard-coded to `'config.php'`
- `logger`: Instantiated in constructor using `new Logger(static::class)`
- Configuration data loaded from the config file

## Methods

### `configFileExists(): bool`
- Confirms that the file located at the `configFilePath` property exists and is writable
- Returns `true` if the config file exists, `false` otherwise

### `getDatabaseParams(): array`
- Returns database connection parameters from the configuration
- Used by DatabaseConnector to establish database connections

### Configuration Management
- Loads configuration settings from the config file
- Provides access to configuration values throughout the framework
- Allows for dynamic updates to configuration settings
- Supports environment-specific configurations (development, production)
- Provides methods to get and set configuration values

## Implementation Notes
- The config file should be located in the root directory, not in the src directory
- No external dependencies required for instantiation
- Self-contained operation with internal logger management
