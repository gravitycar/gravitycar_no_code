# Gravitycar PHP Debug Scripts Tool - Complete Guide

## Overview
The Gravitycar PHP Debug Scripts tool (`gravitycar_php_debug_scripts`) is a VS Code language model tool that allows safe execution of PHP debugging and testing scripts located in the project's `tmp/` directory. This tool provides complete output capture, error reporting, and exit code analysis for thorough debugging of Gravitycar Framework functionality.

## Purpose and Use Cases

### 🔍 **Debugging Framework Components**
The `tmp/` directory contains specialized PHP scripts designed to test, debug, and analyze various aspects of the Gravitycar Framework:

#### **Metadata and Model Testing**
- **debug_metadata_loading.php** - Verify metadata engine functionality and cache loading
- **debug_model_fields.php** - Test model field definitions and configurations
- **debug_users_model.php** - Analyze Users model structure and behavior
- **test_metadata_options.php** - Validate metadata configuration options
- **debug_movie_quotes_metadata.php** - Test MovieQuotes model metadata structure

#### **API and Routing Analysis**
- **debug_api_registry.php** - Examine API route registration and discovery
- **debug_router_parameters.php** - Test router parameter parsing and handling
- **debug_users_route.php** - Verify Users API endpoint functionality
- **test_route_registration.php** - Debug route registration processes
- **check_wildcard_routes.php** - Validate wildcard route handling

#### **Database and Connectivity Testing**
- **debug_database_creation.php** - Test database creation and schema generation
- **debug_test_database.php** - Verify database connector functionality
- **check_users_table.php** - Validate Users table structure and data
- **test_database_connector_display_columns.php** - Test database display column configuration

#### **Authentication and Authorization**
- **debug_user_auth.php** - Test user authentication mechanisms
- **debug_jwt.php** - Analyze JWT token generation and validation
- **test_current_user.php** - Verify current user detection and management
- **simulate_frontend_auth_flow.php** - Test complete authentication workflows
- **debug_authz.php** - Debug authorization and permission systems

#### **Relationship and Data Analysis**
- **debug_relationships.php** - Test model relationship configurations
- **debug_movie_quotes_relationships.php** - Verify MovieQuotes relationship handling
- **test_relationship_implementation.php** - Debug relationship data flow
- **verify_relationships.php** - Validate relationship integrity

#### **Cache and Performance Testing**
- **test_rebuild_cache.php** - Test cache rebuilding processes
- **regenerate_cache_and_test.php** - Complete cache regeneration workflow
- **test_cache_directory.php** - Verify cache directory functionality

#### **Integration and Service Testing**
- **debug_tmdb_api.php** - Test TMDB API integration functionality
- **test_tmdb_integration_api.php** - Verify TMDB service workflows
- **debug_tmdb_controller.php** - Debug TMDB controller operations

## Security Features

### 🔒 **Path Validation and Security**
- **Directory Restriction**: Only executes files within the `tmp/` directory
- **File Type Validation**: Only allows `.php` files (no shell scripts or other executables)
- **Path Traversal Prevention**: Blocks attempts to access files outside tmp/ using `../` or absolute paths
- **File Existence Verification**: Validates file exists before execution

### 🛡️ **Execution Safety**
- **Controlled Environment**: Executes in isolated PHP process with timeout limits
- **Output Capture**: Safely captures both stdout and stderr streams
- **Exit Code Monitoring**: Reports process exit codes for error analysis
- **Resource Limits**: Configurable timeout and memory buffer limits

## Tool Options

```typescript
interface PhpDebugScriptInput {
    scriptFile: string;      // Required: Path to PHP script in tmp/ directory
    verbose?: boolean;       // Optional: Enable verbose output (default: false)
    showErrors?: boolean;    // Optional: Show detailed errors (default: true)
    timeout?: number;        // Optional: Timeout in milliseconds (default: 60000)
}
```

## Usage Examples

### Basic Script Execution
```
Run the PHP debug script tmp/debug_metadata_loading.php to check metadata loading.
```

### Verbose Debugging
```
Execute tmp/debug_user_auth.php with verbose output enabled to debug authentication issues.
```

### Custom Timeout for Long Scripts
```
Run tmp/test_rebuild_cache.php with a 120-second timeout for cache rebuilding analysis.
```

### Error Analysis
```
Execute tmp/debug_database_creation.php and show detailed error information.
```

## Expected Output Format

The tool returns a structured JSON response:

```json
{
  "success": true,
  "scriptFile": "tmp/debug_metadata_loading.php",
  "exitCode": 0,
  "output": "=== Debugging Metadata Loading ===\n\nInitializing Gravitycar framework...\n✓ Found ui.listFields: username, email, first_name\n✓ MetadataEngine cache loaded successfully",
  "error": "",
  "command": "php debug_metadata_loading.php",
  "executionPath": "/project/tmp",
  "options": {
    "verbose": false,
    "showErrors": true,
    "timeout": 60000
  }
}
```

## Common Script Categories in tmp/

### 📋 **Example Scripts** (template for new debugging scripts)
- **example.php** - Basic template showing framework initialization and common operations

### 🔧 **Debug Scripts** (analyze specific components)
- **debug_*.php** - Scripts that examine internal framework state and functionality
- Focus on diagnosing issues with specific components

### 🧪 **Test Scripts** (validate functionality)
- **test_*.php** - Scripts that verify expected behavior and integration
- Often include assertions and validation checks

### ✅ **Check Scripts** (verify configuration and setup)
- **check_*.php** - Scripts that validate system configuration and data integrity
- Typically used for health checks and verification

### 🔨 **Setup/Utility Scripts** (perform maintenance tasks)
- **create_*.php** - Scripts that create test data or configure system state
- **fix_*.php** - Scripts that repair or update system configurations

## Integration with Development Workflow

### 🐛 **Bug Investigation**
1. Identify the component with issues
2. Find or create appropriate debug script in tmp/
3. Run script using the tool
4. Analyze output and exit codes
5. Modify framework code as needed
6. Re-run script to verify fixes

### 🔍 **Feature Development**
1. Create test script to verify new functionality
2. Run script to establish baseline behavior
3. Implement new features
4. Use script to validate implementation
5. Refine based on script output

### 🧪 **Integration Testing**
1. Create comprehensive test scripts
2. Run scripts after framework changes
3. Verify all components work together
4. Use output for regression testing

## Performance Characteristics

- **Execution Time**: 1-60 seconds depending on script complexity
- **Memory Usage**: 10-100MB for typical debugging scripts
- **Output Size**: Typically 1KB-1MB of debug information
- **Timeout**: Default 60 seconds, configurable up to 5 minutes

## Error Handling and Troubleshooting

### Common Issues and Solutions

#### **File Not Found**
```json
{
  "success": false,
  "error": "Script file not found: tmp/nonexistent.php"
}
```
**Solution**: Verify file exists in tmp/ directory

#### **Permission Denied**
```json
{
  "success": false,
  "error": "Script file must be in the tmp/ directory"
}
```
**Solution**: Ensure script is in tmp/ and path doesn't use traversal

#### **PHP Execution Error**
```json
{
  "success": false,
  "exitCode": 1,
  "error": "PHP Fatal error: Class not found"
}
```
**Solution**: Check script dependencies and framework initialization

#### **Timeout**
```json
{
  "success": false,
  "error": "Script execution timed out after 60000ms"
}
```
**Solution**: Increase timeout value or optimize script performance

## Best Practices

### 📝 **Script Creation**
- Start with `example.php` as a template
- Include proper error handling and output formatting
- Use descriptive echo statements for progress tracking
- Always initialize the Gravitycar framework properly

### 🔍 **Debugging Strategy**
- Create focused scripts that test one component at a time
- Use verbose output for complex debugging scenarios
- Capture both success and failure cases
- Include relevant context in output messages

### 🚀 **Performance Optimization**
- Avoid infinite loops or excessive processing
- Use appropriate timeouts for script complexity
- Clean up resources and connections properly
- Consider memory usage for large data processing

## Related Files and Dependencies

- **tmp/example.php** - Template for creating new debug scripts
- **vendor/autoload.php** - Required for framework dependencies
- **config.php** - Database and application configuration
- **src/Core/Gravitycar.php** - Framework bootstrap class
- **src/Core/ServiceLocator.php** - Service access and dependency injection

## Tool Advantages Over Manual Execution

1. **Integrated Output Capture**: Captures all output streams and exit codes
2. **Security Validation**: Prevents execution of scripts outside tmp/ directory
3. **Structured Results**: Returns JSON-formatted results for analysis
4. **Error Isolation**: Proper error handling and reporting
5. **Timeout Management**: Prevents runaway scripts from hanging
6. **Development Integration**: Seamless integration with VS Code workflow
