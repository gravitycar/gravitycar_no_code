#!/bin/bash

# Script to kill the Vite dev server running on port 3100

PORT=3100

echo "🔍 Checking for processes on port $PORT..."

port_in_use() {
    ss -tlnp "sport = :$PORT" 2>/dev/null | grep -q ":$PORT"
}

if ! port_in_use; then
    echo "✅ Port $PORT is free - no processes to kill"
    exit 0
fi

echo "🔥 Port $PORT is in use - killing processes..."

# Kill by port directly (most reliable in WSL2)
fuser -k "${PORT}/tcp" 2>/dev/null

# Also kill any lingering vite/node processes by name
pkill -f "vite" 2>/dev/null
pkill -f "node.*vite" 2>/dev/null

sleep 1

if port_in_use; then
    echo "⚠️  Port $PORT still in use - trying with elevated signal..."
    fuser -k -KILL "${PORT}/tcp" 2>/dev/null
    sleep 1
fi

if port_in_use; then
    echo "❌ Could not free port $PORT - it may be reserved by Windows/Hyper-V"
    echo "   Try running in PowerShell: netsh interface ipv4 show excludedportrange protocol=tcp"
    exit 1
fi

echo "✅ Port $PORT is now free"
