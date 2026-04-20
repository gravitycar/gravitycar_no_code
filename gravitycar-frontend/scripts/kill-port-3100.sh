#!/bin/bash

# Script to kill any process running on port 3100
# This is useful when restarting the React development server

echo "🔍 Checking for processes on port 3100..."

# Find processes using port 3100
PIDS=$(lsof -ti :3100 2>/dev/null)

if [ -z "$PIDS" ]; then
    echo "✅ Port 3100 is free - no processes to kill"
else
    echo "🔥 Found processes on port 3100: $PIDS"
    echo "🔥 Killing processes..."
    
    # Kill the processes
    echo "$PIDS" | xargs kill -9 2>/dev/null
    
    # Wait a moment for processes to die
    sleep 1
    
    # Check if any are still running
    REMAINING=$(lsof -ti :3100 2>/dev/null)
    if [ -z "$REMAINING" ]; then
        echo "✅ All processes on port 3100 have been killed"
    else
        echo "⚠️  Some processes may still be running on port 3100: $REMAINING"
        echo "🔥 Trying force kill..."
        echo "$REMAINING" | xargs kill -KILL 2>/dev/null
        sleep 1
    fi
fi

echo "🎯 Port 3100 should now be available"
