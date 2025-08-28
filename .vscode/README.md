# VSCode Custom Tools for Gravitycar

This directory contains custom tools for enhancing the development experience with the Gravitycar framework.

## Available Tools

### gravitycar-api
Direct API communication tool for the Gravitycar framework server.

**Purpose**: Enables AI agents (like GitHub Copilot) to make real-time API calls to localhost:8081

**Files**:
- `settings.json` - Tool configuration
- `tools/gravitycar-api.js` - Node.js implementation script

**Usage**: The tool is automatically available in GitHub Copilot Chat after restarting VSCode.

### react-server
React development server management tool.

**Purpose**: Manages the React dev server lifecycle with proper process control

**Features**:
- Start/stop/restart React server on port 3000
- Detached background processes (no terminal blocking)
- Process monitoring and status checking
- Log management and viewing
- Automatic port conflict resolution

**Files**:
- `settings.json` - Tool configuration
- `tools/react-server.sh` - Bash implementation script
- `react-server.pid` - Process ID file (auto-generated)
- `react-server.log` - Server logs (auto-generated)

**Usage**: Available as `@react-server` in GitHub Copilot Chat

## Setup

1. Ensure Node.js is installed
2. Make sure the Gravitycar server is running (`php setup.php`)
3. Restart VSCode to load the tool configuration

## Documentation

See `/docs/implementation_notes/VSCode_Custom_Gravitycar_API_Tool.md` for complete documentation, examples, and usage instructions.

## Testing

Test the tools manually:
```bash
# Test Gravitycar API tool
echo '{}' | node .vscode/tools/gravitycar-api.js

# Test React server tool
echo '{"action": "status"}' | bash .vscode/tools/react-server.sh
```

## Security

- **Development Only**: Tools are designed for localhost development
- **Token Storage**: JWT tokens stored in `.vscode/gravitycar-token.json`
- **Add to .gitignore**: Ensure token and log files are not committed to version control
