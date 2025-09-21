#!/bin/bash

# Enhanced frontend build script for CI environments
# Ensures absolutely clean builds with no cached artifacts

set -euo pipefail

echo "🏗️ Starting enhanced CI frontend build..."

# Get to the frontend directory
cd "${PROJECT_ROOT:-$(pwd)}/gravitycar-frontend"

# Set default environment for CI builds
export ENVIRONMENT="${ENVIRONMENT:-production}"

# Environment info
echo "Node version: $(node --version)"
echo "NPM version: $(npm --version)"
echo "Environment: $ENVIRONMENT"
echo "PWD: $(pwd)"

# Aggressive cleanup for CI
echo "🧹 Aggressive cleanup for CI environment..."
rm -rf dist/ .env.* node_modules/.cache/ node_modules/.vite/ 2>/dev/null || true
npm cache clean --force 2>/dev/null || true

# Create environment file
echo "📝 Creating environment configuration..."
if [ "$ENVIRONMENT" = "production" ]; then
    cat > .env.production << 'ENVEOF'
VITE_API_BASE_URL=https://api.gravitycar.com
VITE_APP_ENV=production
VITE_DEBUG=false
VITE_LOG_LEVEL=warn
ENVEOF
    echo "✅ Created .env.production with api.gravitycar.com"
    cat .env.production
else
    echo "⚠️ Not production environment, using default settings"
fi

# Install dependencies fresh
echo "📦 Installing npm dependencies..."
npm ci --cache /tmp/.npm-cache --prefer-offline=false

# Build with explicit environment
echo "🔨 Building with explicit production mode..."
NODE_ENV=production npm run build -- --mode production

# Verify build
echo "🔍 Verifying build results..."
if [ ! -d "dist" ]; then
    echo "❌ Build failed - no dist directory"
    exit 1
fi

ls -la dist/
ls -la dist/assets/

# Check for correct API URL
if ls dist/assets/index-*.js 1> /dev/null 2>&1; then
    js_file=$(ls dist/assets/index-*.js | head -1)
    echo "🔍 Checking API URLs in $js_file:"
    
    # Use more robust counting that handles grep exit codes properly
    if grep -q "api\.gravitycar\.com" "$js_file" 2>/dev/null; then
        api_count=$(grep -o "api\.gravitycar\.com" "$js_file" 2>/dev/null | wc -l)
    else
        api_count=0
    fi
    
    if grep -q "localhost:8081" "$js_file" 2>/dev/null; then
        localhost_count=$(grep -o "localhost:8081" "$js_file" 2>/dev/null | wc -l)
    else
        localhost_count=0
    fi
    
    echo "  api.gravitycar.com: $api_count"
    echo "  localhost:8081: $localhost_count"
    
    if [ "$api_count" -gt 0 ] && [ "$localhost_count" -eq 0 ]; then
        echo "✅ Build successful - correct API URLs"
    else
        echo "❌ Build failed - wrong API URLs"
        echo "Expected: api.gravitycar.com found, localhost:8081 not found"
        echo "Actual: api.gravitycar.com=$api_count, localhost:8081=$localhost_count"
        exit 1
    fi
else
    echo "❌ No JavaScript files found"
    exit 1
fi

echo "✅ Enhanced CI frontend build completed successfully"
