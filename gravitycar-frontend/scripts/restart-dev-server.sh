#!/bin/bash

# Script to cleanly restart the React development server on port 3000
# This script:
# 1. Kills any existing processes on port 3000
# 2. Starts the React dev server on port 3000

echo "🚀 Restarting React development server..."
echo "📍 Target port: 3000"
echo "📂 Working directory: $(pwd)"

# Change to the gravitycar-frontend directory if not already there
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRONTEND_DIR="$(dirname "$SCRIPT_DIR")"

if [ "$(basename "$PWD")" != "gravitycar-frontend" ]; then
    echo "📁 Changing to frontend directory: $FRONTEND_DIR"
    cd "$FRONTEND_DIR" || {
        echo "❌ Error: Could not change to frontend directory"
        exit 1
    }
fi

# Kill any processes on port 3000
echo ""
echo "🔥 Step 1: Cleaning up port 3000..."
./scripts/kill-port-3000.sh

echo ""
echo "🏗️  Step 2: Starting React development server..."
echo "⏳ This may take a moment..."

# Start the development server
npm run dev

echo ""
echo "🎉 React development server startup complete!"
echo "🌐 If successful, the server should be available at: http://localhost:3000"
