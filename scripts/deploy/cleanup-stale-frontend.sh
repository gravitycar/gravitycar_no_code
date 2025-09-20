#!/bin/bash

# Quick fix script to clean up stale frontend files on production
# This addresses the issue where old JavaScript files with localhost:8081 
# are still present alongside new files with correct production API URLs

set -euo pipefail

PRODUCTION_HOST="${PRODUCTION_HOST:-dog.gravitycar.com}"
PRODUCTION_USER="${PRODUCTION_USER:-gravityc}"

echo "🧹 Cleaning up stale frontend files on production..."
echo "Host: $PRODUCTION_HOST"
echo "User: $PRODUCTION_USER"

# This command will remove the old JavaScript file that contains localhost:8081
ssh "$PRODUCTION_USER@$PRODUCTION_HOST" "
    cd ~/public_html/react.gravitycar.com
    
    echo 'Current files before cleanup:'
    ls -la
    
    echo ''
    echo 'Checking for localhost:8081 references:'
    grep -l 'localhost:8081' assets/*.js 2>/dev/null || echo 'No files found with localhost:8081'
    
    echo ''
    echo 'Removing files with localhost:8081 references...'
    # Remove any JavaScript files that contain localhost:8081
    for file in assets/*.js; do
        if [ -f \"\$file\" ] && grep -q 'localhost:8081' \"\$file\"; then
            echo \"Removing stale file: \$file\"
            rm \"\$file\"
        fi
    done
    
    echo ''
    echo 'Files after cleanup:'
    ls -la
    
    echo ''
    echo 'Remaining localhost references (should only be api.gravitycar.com now):'
    grep -l 'localhost' assets/*.js 2>/dev/null || echo 'No files found with localhost references'
    grep -l 'api.gravitycar.com' assets/*.js 2>/dev/null || echo 'No files found with api.gravitycar.com'
"

echo "✅ Cleanup completed!"