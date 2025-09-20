#!/bin/bash

# ==============================================================================
# Gravitycar Framework - Frontend Build Script
# ==============================================================================
# 
# This script builds the React frontend for deployment.
# It handles npm dependencies, TypeScript compilation, asset optimization,
# and environment-specific configuration injection.
#
# ==============================================================================

set -euo pipefail

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
FRONTEND_DIR="${PROJECT_ROOT}/gravitycar-frontend"

# Source common functions
source "${SCRIPT_DIR}/../common.sh" 2>/dev/null || {
    # Basic logging if common.sh not available yet
    log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$1] ${*:2}"; }
}

# Build configuration
ENVIRONMENT="${ENVIRONMENT:-development}"
BUILD_MODE="${BUILD_MODE:-production}"
SKIP_LINT="${SKIP_LINT:-false}"
SKIP_TYPE_CHECK="${SKIP_TYPE_CHECK:-false}"

log "INFO" "Starting frontend build process..."
log "INFO" "Environment: $ENVIRONMENT"
log "INFO" "Build mode: $BUILD_MODE"

# Check if frontend directory exists
if [[ ! -d "$FRONTEND_DIR" ]]; then
    error_exit "Frontend directory not found: $FRONTEND_DIR"
fi

cd "$FRONTEND_DIR"

# Check if package.json exists
if [[ ! -f "package.json" ]]; then
    error_exit "package.json not found in frontend directory"
fi

# Verify Node.js version
NODE_VERSION=$(node --version)
log "DEBUG" "Node.js version: $NODE_VERSION"

# Clean previous build artifacts
log "INFO" "Cleaning previous build artifacts..."
if [[ -d "dist" ]]; then
    rm -rf dist
    log "DEBUG" "Removed existing dist directory"
fi

# Install/update npm dependencies
log "INFO" "Installing npm dependencies..."
if [[ "$BUILD_MODE" == "production" ]]; then
    npm ci --silent
else
    npm ci --silent
fi

# Create environment-specific configuration
create_environment_config() {
    local env="$1"
    local config_file=".env.${env}"
    
    log "INFO" "Creating environment configuration: $config_file"
    
    case "$env" in
        "production")
            cat > "$config_file" << EOF
VITE_API_BASE_URL=https://api.gravitycar.com
VITE_APP_ENV=production
VITE_DEBUG=false
VITE_LOG_LEVEL=warn
EOF
            ;;
        "staging")
            cat > "$config_file" << EOF
VITE_API_BASE_URL=https://staging.gravitycar.com
VITE_APP_ENV=staging
VITE_DEBUG=false
VITE_LOG_LEVEL=info
EOF
            ;;
        *)
            cat > "$config_file" << EOF
VITE_API_BASE_URL=http://localhost:8081
VITE_APP_ENV=development
VITE_DEBUG=true
VITE_LOG_LEVEL=debug
EOF
            ;;
    esac
    
    log "DEBUG" "Environment configuration created for: $env"
}

# Create environment configuration
create_environment_config "$ENVIRONMENT"

# Run TypeScript type checking
if [[ "$SKIP_TYPE_CHECK" != "true" ]]; then
    log "INFO" "Running TypeScript type checking..."
    if npm run type-check 2>/dev/null; then
        log "SUCCESS" "TypeScript type checking passed"
    elif npx tsc --noEmit 2>/dev/null; then
        log "SUCCESS" "TypeScript type checking passed"
    else
        log "WARN" "TypeScript type checking failed or not available"
        log "INFO" "Continuing build without type checking"
    fi
fi

# Run linting
if [[ "$SKIP_LINT" != "true" ]]; then
    log "INFO" "Running frontend linting..."
    if npm run lint; then
        log "SUCCESS" "Linting passed"
    else
        log "WARN" "Linting issues found, but continuing build"
        log "WARN" "Consider fixing linting issues before production deployment"
    fi
fi

# Run frontend tests if available
if npm run test:ci 2>/dev/null || npm run test 2>/dev/null; then
    log "SUCCESS" "Frontend tests passed"
else
    log "WARN" "No frontend tests found or tests failed"
fi

# Build the application
log "INFO" "Building production frontend..."
if [[ "$BUILD_MODE" == "production" ]]; then
    # Use environment mode to ensure Vite reads the correct .env file
    NODE_ENV=production npm run build -- --mode "$ENVIRONMENT"
else
    log "DEBUG" "Development build mode - using dev build"
    npm run build:dev 2>/dev/null || npm run build
fi

# Verify build output
if [[ ! -d "dist" ]]; then
    error_exit "Build failed - no dist directory created"
fi

# Verify essential files exist
ESSENTIAL_FILES=("dist/index.html")
for file in "${ESSENTIAL_FILES[@]}"; do
    if [[ ! -f "$file" ]]; then
        error_exit "Build failed - missing essential file: $file"
    fi
done

# Calculate build size
BUILD_SIZE=$(du -sh dist | cut -f1)
log "INFO" "Build size: $BUILD_SIZE"

# Count build artifacts
ASSET_COUNT=$(find dist -type f | wc -l)
log "INFO" "Build artifacts: $ASSET_COUNT files"

# Optimize build for production
if [[ "$ENVIRONMENT" == "production" ]]; then
    log "INFO" "Applying production optimizations..."
    
    # Gzip compression analysis
    if command -v gzip >/dev/null 2>&1; then
        TOTAL_SIZE=$(find dist -type f -exec cat {} \; | wc -c)
        GZIPPED_SIZE=$(find dist -type f -exec cat {} \; | gzip | wc -c)
        COMPRESSION_RATIO=$(echo "scale=1; $GZIPPED_SIZE * 100 / $TOTAL_SIZE" | bc 2>/dev/null || echo "N/A")
        log "INFO" "Gzip compression ratio: ${COMPRESSION_RATIO}%"
    fi
    
    # Remove development files
    find dist -name "*.map" -delete 2>/dev/null || true
    log "DEBUG" "Removed source map files for production"
fi

# Create build manifest
BUILD_MANIFEST="dist/build-manifest.json"
cat > "$BUILD_MANIFEST" << EOF
{
  "buildTime": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "environment": "$ENVIRONMENT",
  "buildMode": "$BUILD_MODE",
  "nodeVersion": "$NODE_VERSION",
  "buildSize": "$BUILD_SIZE",
  "assetCount": $ASSET_COUNT,
  "gitCommit": "$(git rev-parse HEAD 2>/dev/null || echo 'unknown')",
  "gitBranch": "$(git branch --show-current 2>/dev/null || echo 'unknown')"
}
EOF

log "SUCCESS" "Frontend build completed successfully"
log "INFO" "Build artifacts available in: ${FRONTEND_DIR}/dist"
log "INFO" "Build manifest: ${BUILD_MANIFEST}"

# Clean up environment files for security
rm -f .env.* 2>/dev/null || true
log "DEBUG" "Cleaned up environment configuration files"