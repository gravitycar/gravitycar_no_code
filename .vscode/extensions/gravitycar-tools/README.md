# Gravitycar VS Code Extension

This VS Code extension provides Language Model Tools for seamless interaction with the Gravitycar Framework API, eliminating URL pattern confusion and providing automatic tool availability.

## Installation

1. **Compile the extension:**
   ```bash
   cd .vscode/extensions/gravitycar-tools
   npm install
   npm run compile
   ```

2. **Install the extension in VS Code:**
   - Open VS Code
   - Go to Extensions (Ctrl+Shift+X)
   - Click "..." menu → "Install from VSIX..."
   - Select the compiled extension (you can package it with `vsce package` if needed)
   
   OR for development:
   - Open the extension folder in VS Code
   - Press F5 to launch Extension Development Host
   - The extension will be active in the new VS Code window

## Available Tools

### 1. Gravitycar API Tool (`gravitycar_api_call`)
Makes API calls to the local Gravitycar backend server (localhost:8081) with predefined operations:

**Health Operations:**
- `health_ping` - Basic health check
- `health_detailed` - Detailed health information

**Authentication:**
- `auth_login` - Authenticate user

**User Operations:**
- `get_users` - Get all users
- `get_user_by_id` - Get specific user (requires data.id)
- `create_user` - Create new user (requires data with user fields)
- `update_user` - Update user (requires data.id and update fields)
- `delete_user` - Delete user (requires data.id)

**Movie Operations:**
- `get_movies` - Get all movies
- `get_movie_by_id` - Get specific movie (requires data.id)
- `create_movie` - Create new movie (requires data with movie fields)
- `update_movie` - Update movie (requires data.id and update fields)
- `delete_movie` - Delete movie (requires data.id)

**Movie Quote Operations:**
- `get_movie_quotes` - Get all movie quotes
- `get_movie_quote_by_id` - Get specific quote (requires data.id)
- `create_movie_quote` - Create new quote (requires data with quote fields)
- `update_movie_quote` - Update quote (requires data.id and update fields)
- `delete_movie_quote` - Delete quote (requires data.id)

**Custom Operations:**
- `custom_get` - Custom GET request (requires data.endpoint)
- `custom_post` - Custom POST request (requires data.endpoint)
- `custom_put` - Custom PUT request (requires data.endpoint)
- `custom_delete` - Custom DELETE request (requires data.endpoint)

### 2. Gravitycar Test Runner (`gravitycar_test_runner`)
Run PHPUnit tests with various configurations:

**Test Types:**
- `unit` - Run unit tests
- `integration` - Run integration tests
- `feature` - Run feature tests
- `all` - Run all tests

**Options:**
- `test_path` - Specific test file or directory
- `coverage` - Include coverage report
- `verbose` - Verbose output

### 3. Gravitycar Server Control (`gravitycar_server_control`)
Control development servers and check status:

**Actions:**
- `restart_apache` - Restart Apache server
- `restart_frontend` - Restart frontend development server
- `status` - Check server status
- `logs` - Show recent Gravitycar logs
- `health_check` - Check both servers

## Usage Examples

These tools work automatically in VS Code's language model interface (GitHub Copilot Chat) without needing manual invocation or confirmation prompts.

**Example API call:**
```
Use gravitycar_api_call to get all users
```

**Example test run:**
```
Use gravitycar_test_runner to run unit tests with coverage
```

**Example server control:**
```
Use gravitycar_server_control to restart apache server
```

## Benefits

- **No URL Pattern Mistakes:** Tools know the correct Gravitycar API endpoints
- **Automatic Availability:** Tools work like built-in VS Code tools (searchResults, runCommands)
- **No Confirmation Prompts:** Direct execution without user confirmation
- **Proper Error Handling:** Consistent error responses and logging
- **Type Safety:** Full TypeScript support with proper input validation

## Development

To modify the tools:

1. Edit source files in `src/`
2. Run `npm run compile` to rebuild
3. Restart the Extension Development Host (F5) to test changes

## Troubleshooting

- Ensure Gravitycar backend is running on localhost:8081
- Check VS Code output panel for extension logs
- Verify all dependencies are installed with `npm install`
