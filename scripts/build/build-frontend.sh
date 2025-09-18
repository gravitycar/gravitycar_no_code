#!/bin/bash

# ==============================================================================
# Gravitycar Framework - Frontend Build Script
# ==============================================================================
# 
# This script builds the React frontend for deployment.
# It handles npm dependencies, TypeScript compilation, and asset optimization.
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

log "INFO" "Starting frontend build process..."

# Check if frontend directory exists
if [[ ! -d "$FRONTEND_DIR" ]]; then
    log "ERROR" "Frontend directory not found: $FRONTEND_DIR"
    exit 1
fi

cd "$FRONTEND_DIR"

# Check if package.json exists
if [[ ! -f "package.json" ]]; then
    log "ERROR" "package.json not found in frontend directory"
    exit 1
fi

log "INFO" "Installing npm dependencies..."
npm ci --silent

log "INFO" "Running TypeScript checks..."
npm run type-check || {
    log "ERROR" "TypeScript compilation failed"
    exit 1
}

log "INFO" "Running frontend linting..."
npm run lint || {
    log "WARN" "Linting issues found, but continuing build"
}

log "INFO" "Building production frontend..."
npm run build

# Verify build output
if [[ ! -d "dist" ]]; then
    log "ERROR" "Build failed - no dist directory created"
    exit 1
fi

log "SUCCESS" "Frontend build completed successfully"
log "INFO" "Build artifacts available in: ${FRONTEND_DIR}/dist"