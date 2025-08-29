# Implementation Plan: VS Code Language Model Tools for Gravitycar Framework

## Feature Overview

This feature implements VS Code Language Model Tools that provide seamless integration with the Gravitycar Framework API, eliminating URL pattern confusion and providing automatic tool availability without confirmation prompts. The tools work like built-in VS Code tools (searchResults, runCommands) and are automatically available in language model interfaces like GitHub Copilot Chat.

## Requirements

### Functional Requirements

1. **Gravitycar API Tool**
   - Support all major Gravitycar API operations (Users, Movies, MovieQuotes, Health, Auth)
   - Handle proper URL patterns (localhost:8081/Users not /api/Users)
   - Provide both predefined operations and custom endpoint support
   - Include proper error handling and response parsing
   - Support JSON request/response handling

2. **Test Runner Tool**
   - Execute PHPUnit tests with various configurations
   - Support unit, integration, feature, and all test types
   - Include coverage reporting options
   - Handle test output parsing and status detection

3. **Server Control Tool**
   - Restart Apache and frontend development servers
   - Check server status and health
   - Display recent application logs
   - Perform health checks on both backend and frontend

4. **Automatic Integration**
   - Tools register automatically when VS Code starts
   - No manual invocation or confirmation prompts required
   - Work seamlessly with language model interfaces
   - Provide consistent JSON response format

### Non-Functional Requirements

1. **Performance**
   - API calls complete within 30 seconds timeout
   - Test runs complete within 2 minutes timeout
   - Minimal extension load time impact

2. **Reliability**
   - Proper error handling and logging
   - Graceful failure modes
   - Consistent response formats

3. **Usability**
   - Clear tool descriptions and parameter schemas
   - Intuitive operation names matching common API patterns
   - Comprehensive documentation

## Design

### High-Level Architecture

```
VS Code Extension Host
├── Extension Activation (extension.ts)
├── Tool Registration (vscode.lm.registerTool)
└── Tool Implementations
    ├── GravitycarApiTool (API operations)
    ├── GravitycarTestTool (PHPUnit execution)
    └── GravitycarServerTool (Server control)

Language Model Interface
├── Automatic Tool Discovery
├── Input Schema Validation
└── Response Processing
```

### Component Interactions

1. **Extension Activation Flow**
   - VS Code loads extension on startup (onStartupFinished)
   - Extension registers three tools with vscode.lm.registerTool()
   - Tools become available in language model interfaces

2. **Tool Invocation Flow**
   - Language model selects appropriate tool based on user intent
   - Input validated against JSON schema
   - Tool executes operation (API call, test run, server command)
   - Response formatted as JSON and returned to language model

3. **API Operation Mapping**
   - Predefined operations map to specific HTTP methods and endpoints
   - Custom operations allow flexible endpoint access
   - URL parameter substitution for resource IDs
   - Request body construction from input data

## Implementation Steps

### Phase 1: Extension Foundation ✅ COMPLETED
- [x] Create VS Code extension structure
- [x] Configure TypeScript compilation
- [x] Define package.json with tool contributions
- [x] Set up build scripts and dependencies

### Phase 2: Core Tool Implementation ✅ COMPLETED
- [x] Implement GravitycarApiTool with operation mapping
- [x] Create GravitycarTestTool for PHPUnit execution
- [x] Build GravitycarServerTool for server management
- [x] Add proper TypeScript interfaces and error handling

### Phase 3: Testing and Validation
- [ ] Test extension compilation and registration
- [ ] Validate tool functionality with sample operations
- [ ] Test error handling and edge cases
- [ ] Verify integration with VS Code language model interface

### Phase 4: Documentation and Deployment
- [x] Create comprehensive README with usage examples
- [x] Document all available operations and parameters
- [ ] Create installation and troubleshooting guides
- [ ] Package extension for distribution

## Testing Strategy

### Unit Testing
- Tool input validation and parameter handling
- Operation mapping and URL construction
- Error handling and response formatting
- JSON parsing and status code interpretation

### Integration Testing
- End-to-end API calls against running Gravitycar backend
- PHPUnit test execution with various configurations
- Server control operations (restart, status, logs)
- VS Code extension loading and tool registration

### User Acceptance Testing
- Language model tool discovery and selection
- Natural language tool invocation through Copilot Chat
- Response accuracy and formatting validation
- Error message clarity and usefulness

## Documentation

### API Documentation
- Complete operation reference with parameters
- Example requests and responses for each operation
- Error code meanings and troubleshooting steps

### User Guides
- Installation instructions for development and production
- Usage examples with language model interfaces
- Common workflows and best practices

### Developer Documentation
- Extension architecture and component overview
- Adding new operations and tools
- Build and deployment procedures

## Risks and Mitigations

### Risk 1: VS Code API Compatibility
**Risk:** Language Model Tool API changes or compatibility issues
**Mitigation:** 
- Use stable VS Code API version (1.80.0+)
- Test with multiple VS Code versions
- Monitor VS Code release notes for API changes

### Risk 2: Gravitycar API Changes
**Risk:** Backend API endpoints or response formats change
**Mitigation:**
- Use centralized operation mapping for easy updates
- Include version detection and compatibility checking
- Maintain backward compatibility when possible

### Risk 3: Tool Registration Failures
**Risk:** Tools not properly registered or discovered by language models
**Mitigation:**
- Comprehensive error logging in extension activation
- Fallback registration mechanisms
- Clear diagnostic information for troubleshooting

### Risk 4: Performance Issues
**Risk:** Long-running operations block language model interface
**Mitigation:**
- Implement reasonable timeouts for all operations
- Use asynchronous execution patterns
- Provide progress feedback for long operations

## Success Criteria

1. **Functionality**
   - All three tools register successfully in VS Code
   - API operations execute without URL pattern errors
   - Test runner handles all test types correctly
   - Server control commands work reliably

2. **Integration**
   - Tools automatically available in Copilot Chat without manual setup
   - No confirmation prompts required for tool execution
   - Consistent response formatting across all tools

3. **User Experience**
   - Natural language queries correctly route to appropriate tools
   - Error messages provide clear guidance for resolution
   - Documentation enables quick user onboarding

4. **Reliability**
   - Extension loads without errors in VS Code
   - Tools handle edge cases gracefully
   - Proper cleanup on extension deactivation

## Current Status

**✅ COMPLETED:**
- Extension foundation and structure
- All three tool implementations
- TypeScript compilation and build system
- Comprehensive documentation

**🔄 IN PROGRESS:**
- Extension testing and validation

**📋 PENDING:**
- User acceptance testing
- Extension packaging and distribution
- Performance optimization

## Next Steps

1. Test extension loading and tool registration in VS Code
2. Validate API operations against running Gravitycar backend
3. Test integration with GitHub Copilot Chat
4. Address any compatibility or performance issues
5. Package extension for distribution

## Implementation Notes

- Used VS Code Language Model Tool API (vscode.lm.registerTool)
- Implemented operation-based API mapping for URL safety
- Added comprehensive error handling and logging
- Designed for seamless integration with existing Gravitycar development workflow
- Focused on eliminating manual URL construction and command execution
