#!/bin/bash

# React Development Server Management Tool
# Manages the React dev server on port 3000 with proper process control

REACT_DIR="/mnt/g/projects/gravitycar_no_code/gravitycar-frontend"
PID_FILE="/mnt/g/projects/gravitycar_no_code/.vscode/react-server.pid"
LOG_FILE="/mnt/g/projects/gravitycar_no_code/.vscode/react-server.log"
PORT=3000

# Function to get current timestamp
timestamp() {
    date '+%Y-%m-%d %H:%M:%S'
}

# Function to log messages
log() {
    echo "[$(timestamp)] $1" | tee -a "$LOG_FILE"
}

# Function to return JSON response
json_response() {
    local success="$1"
    local message="$2"
    local data="$3"
    
    echo "{"
    echo "  \"success\": $success,"
    echo "  \"message\": \"$message\","
    echo "  \"timestamp\": \"$(date -u +%Y-%m-%dT%H:%M:%S+00:00)\","
    if [ -n "$data" ]; then
        echo "  \"data\": $data,"
    fi
    echo "  \"port\": $PORT"
    echo "}"
}

# Function to check if process is running on port 3000
check_port_process() {
    local pid=$(lsof -ti:$PORT 2>/dev/null)
    if [ -n "$pid" ]; then
        echo "$pid"
        return 0
    else
        return 1
    fi
}

# Function to check if our managed process is running
check_managed_process() {
    if [ -f "$PID_FILE" ]; then
        local stored_pid=$(cat "$PID_FILE" 2>/dev/null)
        if [ -n "$stored_pid" ] && kill -0 "$stored_pid" 2>/dev/null; then
            echo "$stored_pid"
            return 0
        else
            # PID file exists but process is dead, clean it up
            rm -f "$PID_FILE"
            return 1
        fi
    fi
    return 1
}

# Function to stop React server
stop_server() {
    local stopped_processes=0
    local messages=()
    
    # Check for our managed process first
    if managed_pid=$(check_managed_process); then
        log "Stopping managed React server process (PID: $managed_pid)"
        if kill -TERM "$managed_pid" 2>/dev/null; then
            # Wait for graceful shutdown
            for i in {1..10}; do
                if ! kill -0 "$managed_pid" 2>/dev/null; then
                    break
                fi
                sleep 1
            done
            
            # Force kill if still running
            if kill -0 "$managed_pid" 2>/dev/null; then
                kill -KILL "$managed_pid" 2>/dev/null
            fi
            
            rm -f "$PID_FILE"
            messages+=("Stopped managed React server (PID: $managed_pid)")
            ((stopped_processes++))
        fi
    fi
    
    # Check for any other processes on port 3000
    if port_pid=$(check_port_process); then
        log "Found process on port $PORT (PID: $port_pid), stopping it"
        if kill -TERM "$port_pid" 2>/dev/null; then
            # Wait for graceful shutdown
            for i in {1..10}; do
                if ! kill -0 "$port_pid" 2>/dev/null; then
                    break
                fi
                sleep 1
            done
            
            # Force kill if still running
            if kill -0 "$port_pid" 2>/dev/null; then
                kill -KILL "$port_pid" 2>/dev/null
            fi
            
            messages+=("Stopped process on port $PORT (PID: $port_pid)")
            ((stopped_processes++))
        fi
    fi
    
    if [ $stopped_processes -eq 0 ]; then
        json_response true "No React server processes found running"
    else
        local message=$(IFS='; '; echo "${messages[*]}")
        json_response true "$message" "{\"stopped_processes\": $stopped_processes}"
    fi
}

# Function to start React server
start_server() {
    # First check if anything is already running
    if check_port_process >/dev/null; then
        json_response false "Port $PORT is already in use. Stop the existing server first."
        return 1
    fi
    
    # Check if React directory exists
    if [ ! -d "$REACT_DIR" ]; then
        json_response false "React directory not found: $REACT_DIR"
        return 1
    fi
    
    # Check if package.json exists
    if [ ! -f "$REACT_DIR/package.json" ]; then
        json_response false "package.json not found in React directory"
        return 1
    fi
    
    # Change to React directory
    cd "$REACT_DIR" || {
        json_response false "Failed to change to React directory"
        return 1
    }
    
    # Start the React server in background
    log "Starting React development server..."
    
    # Use nohup to detach from terminal and redirect output
    nohup npm run dev > "$LOG_FILE" 2>&1 &
    local server_pid=$!
    
    # Save PID for management
    echo "$server_pid" > "$PID_FILE"
    
    # Wait a moment and check if it started successfully
    sleep 3
    
    if kill -0 "$server_pid" 2>/dev/null; then
        # Wait a bit more for the server to bind to the port
        for i in {1..15}; do
            if check_port_process >/dev/null; then
                log "React server started successfully (PID: $server_pid)"
                json_response true "React development server started successfully" "{\"pid\": $server_pid, \"url\": \"http://localhost:$PORT\"}"
                return 0
            fi
            sleep 1
        done
        
        # Server process is running but not bound to port yet
        log "React server process started but not yet bound to port (PID: $server_pid)"
        json_response true "React server starting..." "{\"pid\": $server_pid, \"status\": \"starting\", \"url\": \"http://localhost:$PORT\"}"
    else
        # Process died immediately
        rm -f "$PID_FILE"
        log "React server failed to start"
        json_response false "React server failed to start. Check the log file: $LOG_FILE"
        return 1
    fi
}

# Function to get server status
get_status() {
    local port_pid=""
    local managed_pid=""
    local status_data="{"
    
    # Check managed process
    if managed_pid=$(check_managed_process); then
        status_data="$status_data\"managed_process\": {\"pid\": $managed_pid, \"running\": true},"
    else
        status_data="$status_data\"managed_process\": {\"running\": false},"
    fi
    
    # Check port
    if port_pid=$(check_port_process); then
        status_data="$status_data\"port_$PORT\": {\"pid\": $port_pid, \"occupied\": true},"
        
        # Check if it's our managed process
        if [ "$port_pid" = "$managed_pid" ]; then
            status_data="$status_data\"server_status\": \"running_managed\","
            status_data="$status_data\"url\": \"http://localhost:$PORT\""
        else
            status_data="$status_data\"server_status\": \"running_unmanaged\","
            status_data="$status_data\"url\": \"http://localhost:$PORT\""
        fi
    else
        status_data="$status_data\"port_$PORT\": {\"occupied\": false},"
        status_data="$status_data\"server_status\": \"stopped\""
    fi
    
    status_data="$status_data}"
    
    if [ -n "$port_pid" ]; then
        if [ "$port_pid" = "$managed_pid" ]; then
            json_response true "React server is running (managed)" "$status_data"
        else
            json_response true "React server is running (unmanaged)" "$status_data"
        fi
    else
        json_response true "React server is not running" "$status_data"
    fi
}

# Function to restart server
restart_server() {
    log "Restarting React development server..."
    stop_server > /dev/null 2>&1
    sleep 2
    start_server
}

# Function to show logs
show_logs() {
    if [ -f "$LOG_FILE" ]; then
        local lines="${1:-50}"
        local log_content=$(tail -n "$lines" "$LOG_FILE" | sed 's/"/\\"/g' | sed ':a;N;$!ba;s/\n/\\n/g')
        json_response true "Last $lines lines from React server log" "{\"log_content\": \"$log_content\", \"log_file\": \"$LOG_FILE\"}"
    else
        json_response false "Log file not found: $LOG_FILE"
    fi
}

# Function to show examples
show_examples() {
    cat << 'EOF'
{
  "React Server Management Examples": {
    "Start Server": {
      "action": "start"
    },
    "Stop Server": {
      "action": "stop"
    },
    "Restart Server": {
      "action": "restart"
    },
    "Check Status": {
      "action": "status"
    },
    "Show Recent Logs": {
      "action": "logs",
      "lines": 20
    },
    "Show Examples": {
      "action": "examples"
    }
  },
  "Server Info": {
    "port": 3000,
    "url": "http://localhost:3000",
    "directory": "/mnt/g/projects/gravitycar_no_code/gravitycar-frontend",
    "log_file": "/mnt/g/projects/gravitycar_no_code/.vscode/react-server.log",
    "pid_file": "/mnt/g/projects/gravitycar_no_code/.vscode/react-server.pid"
  }
}
EOF
}

# Main execution
main() {
    # Create log file if it doesn't exist
    touch "$LOG_FILE"
    
    # Read JSON input from stdin
    if [ -t 0 ]; then
        # No input provided, show examples
        show_examples
        exit 0
    fi
    
    # Read input
    input=$(cat)
    
    # Parse JSON input (basic parsing)
    if [ -z "$input" ] || [ "$input" = "{}" ]; then
        show_examples
        exit 0
    fi
    
    # Extract action from JSON (simple approach)
    action=$(echo "$input" | grep -o '"action"[[:space:]]*:[[:space:]]*"[^"]*"' | sed 's/.*"action"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/')
    lines=$(echo "$input" | grep -o '"lines"[[:space:]]*:[[:space:]]*[0-9]*' | sed 's/.*"lines"[[:space:]]*:[[:space:]]*\([0-9]*\).*/\1/')
    
    # Default to status if no action specified
    if [ -z "$action" ]; then
        action="status"
    fi
    
    case "$action" in
        "start")
            start_server
            ;;
        "stop")
            stop_server
            ;;
        "restart")
            restart_server
            ;;
        "status")
            get_status
            ;;
        "logs")
            show_logs "${lines:-50}"
            ;;
        "examples")
            show_examples
            ;;
        *)
            json_response false "Unknown action: $action. Available actions: start, stop, restart, status, logs, examples"
            exit 1
            ;;
    esac
}

# Handle errors
set +e  # Don't exit on errors, handle them gracefully
trap 'json_response false "Script error occurred"' ERR

# Run main function
main "$@"
