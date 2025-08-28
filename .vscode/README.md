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

## Setup

1. Ensure Node.js is installed
2. Make sure the Gravitycar server is running (`php setup.php`)
3. Restart VSCode to load the tool configuration

## Documentation

See `/docs/implementation_notes/VSCode_Custom_Gravitycar_API_Tool.md` for complete documentation, examples, and usage instructions.

## Testing

Test the tool manually:
```bash
# Show examples
echo '{}' | node .vscode/tools/gravitycar-api.js

# Test API call
echo '{"method": "GET", "endpoint": "/Users", "useAuth": false}' | node .vscode/tools/gravitycar-api.js
```

## Security

- **Development Only**: Tool is designed for localhost development
- **Token Storage**: JWT tokens stored in `.vscode/gravitycar-token.json`
- **Add to .gitignore**: Ensure token file is not committed to version control
