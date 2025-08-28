# VSCode React Server Management Tool

**Date**: August 28, 2025  
**Purpose**: Custom VSCode tool for managing the React development server with proper process control

## Overview

This custom tool solves the common problem of managing React development servers in VSCode where servers run in the foreground, block terminals, and require manual process management. The tool provides background process management, automatic port conflict resolution, and comprehensive server lifecycle control.

## Problems Solved

### 1. **Terminal Blocking**
- **Problem**: `npm run dev` runs in foreground, blocking the terminal
- **Solution**: Automatically detaches server process using `nohup` and background execution

### 2. **Process Management**
- **Problem**: Hard to track and stop React server processes
- **Solution**: PID file management and automatic process tracking

### 3. **Port Conflicts**
- **Problem**: Port 3000 conflicts when multiple servers or zombie processes exist
- **Solution**: Intelligent port checking and cleanup of stale processes

### 4. **Status Visibility**
- **Problem**: Unclear if server is running and on which port
- **Solution**: Comprehensive status reporting with process details

## Installation

The tool consists of:

1. **`.vscode/settings.json`** - Tool configuration
2. **`.vscode/tools/react-server.sh`** - Bash script implementation
3. **`.vscode/react-server.pid`** - Process ID storage (auto-generated)
4. **`.vscode/react-server.log`** - Server output logs (auto-generated)

## Tool Configuration

In `.vscode/settings.json`:

```json
{
  "github.copilot.chat.tools": {
    "react-server": {
      "name": "React Dev Server",
      "description": "Manage the React development server on port 3000. Commands: start, stop, restart, status. Automatically detaches from terminal and manages background processes.",
      "command": "bash",
      "args": [".vscode/tools/react-server.sh"],
      "input": "json"
    }
  }
}
```

## Usage

The tool accepts JSON input with an `action` parameter:

### Available Actions

- **`start`** - Start the React development server
- **`stop`** - Stop the React development server
- **`restart`** - Stop and start the server
- **`status`** - Check server status and port information
- **`logs`** - View recent server logs
- **`examples`** - Show usage examples

### Basic Parameters

- **`action`** (string, required): The action to perform
- **`lines`** (number, optional): Number of log lines to show (for logs action)

## Examples

### Start Server
```json
{
  "action": "start"
}
```

**Response:**
```json
{
  "success": true,
  "message": "React development server started successfully",
  "timestamp": "2025-08-28T15:44:38+00:00",
  "data": {
    "pid": 461300,
    "url": "http://localhost:3000"
  },
  "port": 3000
}
```

### Stop Server
```json
{
  "action": "stop"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Stopped managed React server (PID: 461300)",
  "timestamp": "2025-08-28T15:44:55+00:00",
  "data": {
    "stopped_processes": 1
  },
  "port": 3000
}
```

### Check Status
```json
{
  "action": "status"
}
```

**Response (Running):**
```json
{
  "success": true,
  "message": "React server is running (managed)",
  "timestamp": "2025-08-28T15:43:30+00:00",
  "data": {
    "managed_process": {
      "pid": 460746,
      "running": true
    },
    "port_3000": {
      "pid": 460759,
      "occupied": true
    },
    "server_status": "running_managed",
    "url": "http://localhost:3000"
  },
  "port": 3000
}
```

**Response (Stopped):**
```json
{
  "success": true,
  "message": "React server is not running",
  "timestamp": "2025-08-28T15:44:25+00:00",
  "data": {
    "managed_process": {
      "running": false
    },
    "port_3000": {
      "occupied": false
    },
    "server_status": "stopped"
  },
  "port": 3000
}
```

### Restart Server
```json
{
  "action": "restart"
}
```

### View Logs
```json
{
  "action": "logs",
  "lines": 20
}
```

**Response:**
```json
{
  "success": true,
  "message": "Last 20 lines from React server log",
  "timestamp": "2025-08-28T15:43:59+00:00",
  "data": {
    "log_content": "  VITE v7.1.3  ready in 1432 ms\\n\\n  ➜  Local:   http://localhost:3000/\\n  ➜  Network: http://172.27.14.27:3000/",
    "log_file": "/mnt/g/projects/gravitycar_no_code/.vscode/react-server.log"
  },
  "port": 3000
}
```

### Show Examples
```json
{
  "action": "examples"
}
```

## Advanced Features

### 1. **Process Tracking**
- **Managed Processes**: Tracks processes started by the tool
- **Unmanaged Processes**: Detects external processes on port 3000
- **PID File Management**: Stores process IDs for reliable tracking
- **Zombie Cleanup**: Automatically cleans up dead process references

### 2. **Port Management**
- **Port Conflict Detection**: Checks if port 3000 is already in use
- **Multiple Process Handling**: Can stop both managed and unmanaged processes
- **Graceful Shutdown**: Uses SIGTERM first, then SIGKILL if necessary
- **Wait Logic**: Allows time for graceful shutdown before force killing

### 3. **Background Execution**
- **Detached Processes**: Uses `nohup` to detach from terminal
- **Output Redirection**: Captures all output to log files
- **No Terminal Blocking**: Returns control to terminal immediately
- **Process Monitoring**: Verifies successful startup

### 4. **Logging System**
- **Timestamped Logs**: All actions logged with timestamps
- **Dual Output**: Logs to both file and terminal
- **Error Capture**: Captures both stdout and stderr
- **Log Rotation**: Prevents log files from growing too large

## Error Handling

### Common Scenarios

#### Port Already in Use
```json
{
  "success": false,
  "message": "Port 3000 is already in use. Stop the existing server first.",
  "timestamp": "2025-08-28T15:44:00+00:00",
  "port": 3000
}
```

#### React Directory Not Found
```json
{
  "success": false,
  "message": "React directory not found: /path/to/gravitycar-frontend",
  "timestamp": "2025-08-28T15:44:00+00:00",
  "port": 3000
}
```

#### Server Failed to Start
```json
{
  "success": false,
  "message": "React server failed to start. Check the log file: /path/to/react-server.log",
  "timestamp": "2025-08-28T15:44:00+00:00",
  "port": 3000
}
```

## File Structure

```
.vscode/
├── settings.json           # Tool configuration
├── tools/
│   └── react-server.sh    # Tool implementation
├── react-server.pid       # Process ID storage (auto-generated)
├── react-server.log       # Server logs (auto-generated)
└── README.md              # Documentation
```

## Configuration

### Default Settings
- **Port**: 3000 (fixed for Google OAuth compatibility)
- **React Directory**: `/mnt/g/projects/gravitycar_no_code/gravitycar-frontend`
- **PID File**: `.vscode/react-server.pid`
- **Log File**: `.vscode/react-server.log`
- **Timeout**: 10 seconds for process operations

### Customization
To customize settings, edit the variables at the top of `react-server.sh`:

```bash
REACT_DIR="/path/to/your/react/app"
PID_FILE="/path/to/pid/file"
LOG_FILE="/path/to/log/file"
PORT=3000
```

## Server Status Types

### 1. **stopped**
- No processes running on port 3000
- No managed processes active

### 2. **running_managed**
- Server started by this tool
- Process tracked in PID file
- Port 3000 occupied by our process

### 3. **running_unmanaged**
- External process on port 3000
- Not started by this tool
- Can still be stopped by tool

## Integration with Development Workflow

### Typical Usage Patterns

1. **Start Development Session**
   ```json
   {"action": "start"}
   ```

2. **Check if Server is Running**
   ```json
   {"action": "status"}
   ```

3. **Restart After Changes**
   ```json
   {"action": "restart"}
   ```

4. **Debug Issues**
   ```json
   {"action": "logs", "lines": 50}
   ```

5. **End Development Session**
   ```json
   {"action": "stop"}
   ```

### AI Agent Integration

This tool enables AI agents to:

1. **Automatically Start Services**: Start React server when beginning frontend work
2. **Monitor Server Health**: Check status before making recommendations
3. **Troubleshoot Issues**: View logs to diagnose problems
4. **Clean Environment**: Stop servers when switching contexts
5. **Provide Status Updates**: Report current server state

## Security Considerations

### Local Development Only
- Tool is designed for localhost development
- No remote server support
- No network exposure beyond localhost

### Process Security
- Only manages processes on port 3000
- Uses standard Unix signals for process control
- Respects process ownership and permissions

### File Security
- PID and log files stored in `.vscode/` directory
- Files excluded from version control
- No sensitive data stored in files

## Troubleshooting

### Common Issues

#### "React directory not found"
- Verify the path in `REACT_DIR` variable
- Ensure React project exists and has `package.json`

#### "Server failed to start"
- Check log file for detailed error messages
- Verify Node.js and npm are installed
- Check for missing dependencies

#### "Port already in use"
- Use `action: "stop"` to clean up existing processes
- Check for other applications using port 3000
- Use `lsof -i:3000` to identify processes

#### Process tracking issues
- Clean up stale PID files manually if needed
- Restart VSCode to reset tool state
- Use `action: "status"` to verify current state

## Future Enhancements

### Planned Improvements
1. **Multi-port Support**: Support for different ports
2. **Environment Variables**: React environment configuration
3. **Health Checks**: HTTP endpoint health monitoring
4. **Build Integration**: Development vs production build management
5. **Hot Reload Monitoring**: Track file changes and reload status

### Scalability Considerations
1. **Multiple Projects**: Support for multiple React projects
2. **Configuration Files**: External configuration management
3. **Plugin Architecture**: Extensible action system
4. **Integration APIs**: Hooks for other development tools

## Testing

The tool has been tested with:
- ✅ Start/stop/restart operations
- ✅ Process management and PID tracking
- ✅ Port conflict detection and resolution
- ✅ Background process execution
- ✅ Log capture and viewing
- ✅ Error handling and edge cases
- ✅ JSON input/output formatting

## Conclusion

This React server management tool provides a robust solution for managing React development servers in VSCode environments. It eliminates common terminal blocking issues, provides comprehensive process control, and integrates seamlessly with AI-assisted development workflows.

The tool's background process management, automatic port conflict resolution, and comprehensive logging make it an essential utility for efficient React development within the Gravitycar framework.
