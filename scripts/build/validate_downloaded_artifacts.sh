#!/bin/bash

# ==============================================================================
# Gravitycar Framework - Downloaded Artifacts Validation Script
# ==============================================================================
# 
# This script validates that downloaded CI artifacts contain all required files
# and that frontend builds have correct API URLs before packaging.
#
# ==============================================================================

set -euo pipefail

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# Source common functions
source "${SCRIPT_DIR}/../common.sh" 2>/dev/null || {
    # Basic logging if common.sh not available yet
    log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$1] ${*:2}"; }
    error_exit() { log "ERROR" "$1"; exit 1; }
}

log "INFO" "Starting downloaded artifacts validation..."

# Print working directory
log "INFO" "Current working directory: $(pwd)"

# Print contents of working directory
log "INFO" "Contents of working directory:"
ls -la

# Print path to artifacts directory
artifacts_dir="$PROJECT_ROOT/artifacts"
log "INFO" "Artifacts directory path: $artifacts_dir"

# Print contents of artifacts directory
log "INFO" "Contents of artifacts directory:"
if [[ -d "$artifacts_dir" ]]; then
    ls -la "$artifacts_dir/"
else
    error_exit "Artifacts directory not found: $artifacts_dir"
fi

# Print contents of artifacts/frontend/dist/
frontend_dist_dir="$artifacts_dir/frontend/dist"
log "INFO" "Contents of artifacts/frontend/dist/:"
if [[ -d "$frontend_dist_dir" ]]; then
    ls -la "$frontend_dist_dir/"
else
    error_exit "Frontend dist directory not found: $frontend_dist_dir"
fi

# Print contents of artifacts/backend
backend_dir="$artifacts_dir/backend"
log "INFO" "Contents of artifacts/backend:"
if [[ -d "$backend_dir" ]]; then
    ls -la "$backend_dir/"
else
    error_exit "Backend artifacts directory not found: $backend_dir"
fi

# Validate backend artifacts
log "INFO" "Validating backend artifacts..."
backend_required_items=(
    "src/"
    "vendor/"
    "composer.json"
    "index.html"
    "rest_api.php"
    "build-metadata.json"
)

missing_backend_items=()
for item in "${backend_required_items[@]}"; do
    if [[ ! -e "$backend_dir/$item" ]]; then
        missing_backend_items+=("$item")
    else
        log "DEBUG" "✅ Found required backend item: $item"
    fi
done

if [[ ${#missing_backend_items[@]} -gt 0 ]]; then
    log "ERROR" "Missing required backend artifacts:"
    for item in "${missing_backend_items[@]}"; do
        log "ERROR" "  - $item"
    done
    error_exit "Backend artifact validation failed"
fi

log "SUCCESS" "All required backend artifacts found"

# Validate frontend artifacts
log "INFO" "Validating frontend artifacts..."

# Check for index-*.js file in assets
assets_dir="$frontend_dist_dir/assets"
if [[ ! -d "$assets_dir" ]]; then
    error_exit "Frontend assets directory not found: $assets_dir"
fi

# Find index-*.js file
index_js_files=($(ls "$assets_dir"/index-*.js 2>/dev/null || true))
if [[ ${#index_js_files[@]} -eq 0 ]]; then
    log "ERROR" "No index-*.js file found in: $assets_dir"
    log "INFO" "Available files in assets:"
    ls -la "$assets_dir/" || true
    error_exit "Frontend index-*.js file validation failed"
fi

# Use the first index-*.js file found
index_js_file="${index_js_files[0]}"
log "DEBUG" "Found frontend JavaScript file: $index_js_file"

# Check for api.gravitycar.com in the JavaScript file
log "INFO" "Checking API URL content in: $index_js_file"
if grep -q "api\.gravitycar\.com" "$index_js_file" 2>/dev/null; then
    api_count=$(grep -o "api\.gravitycar\.com" "$index_js_file" 2>/dev/null | wc -l)
else
    api_count=0
fi

if [[ "$api_count" -eq 0 ]]; then
    log "ERROR" "No occurrences of 'api.gravitycar.com' found in: $index_js_file"
    log "ERROR" "Frontend build may be using wrong API base URL"
    error_exit "Frontend API URL validation failed - api.gravitycar.com not found"
fi

log "SUCCESS" "Found $api_count occurrences of 'api.gravitycar.com' in frontend JavaScript"

# Check that localhost:8081 is NOT present
if grep -q "localhost:8081" "$index_js_file" 2>/dev/null; then
    localhost_count=$(grep -o "localhost:8081" "$index_js_file" 2>/dev/null | wc -l)
else
    localhost_count=0
fi

if [[ "$localhost_count" -gt 0 ]]; then
    log "ERROR" "Found $localhost_count occurrences of 'localhost:8081' in: $index_js_file"
    log "ERROR" "Frontend build contains development URLs that should not be in production"
    error_exit "Frontend API URL validation failed - localhost:8081 found"
fi

log "SUCCESS" "Confirmed 0 occurrences of 'localhost:8081' in frontend JavaScript"

# Final validation summary
log "INFO" "Artifact validation summary:"
log "INFO" "  ✅ Backend artifacts: All required files present"
log "INFO" "  ✅ Frontend JavaScript: $index_js_file"
log "INFO" "  ✅ API URLs: $api_count api.gravitycar.com, $localhost_count localhost:8081"
log "SUCCESS" "Downloaded artifacts validation completed successfully"