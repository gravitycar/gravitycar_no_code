#!/bin/bash

# ==============================================================================
# Gravitycar Framework - Package Creation Script
# ==============================================================================
# 
# This script creates deployment packages containing both frontend and backend
# build artifacts, ready for transfer to production servers.
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
}

# Package configuration
ENVIRONMENT="${ENVIRONMENT:-development}"
DEPLOYMENT_ID="${DEPLOYMENT_ID:-package-$(date +%Y%m%d-%H%M%S)}"
PACKAGE_DIR="${PROJECT_ROOT}/packages"
PACKAGE_NAME="gravitycar-${ENVIRONMENT}-${DEPLOYMENT_ID}"
PACKAGE_PATH="${PACKAGE_DIR}/${PACKAGE_NAME}"

log "INFO" "Starting package creation process..."
log "INFO" "Environment: $ENVIRONMENT"
log "INFO" "Deployment ID: $DEPLOYMENT_ID"
log "INFO" "Package name: $PACKAGE_NAME"

# Create package directory structure
create_package_structure() {
    log "INFO" "Creating package directory structure..."
    
    # Clean up any existing package directory
    if [[ -d "$PACKAGE_PATH" ]]; then
        rm -rf "$PACKAGE_PATH"
        log "DEBUG" "Cleaned up existing package directory"
    fi
    
    # Create package directories
    mkdir -p "$PACKAGE_PATH"/{backend,frontend,scripts,config,docs}
    
    log "DEBUG" "Package structure created at: $PACKAGE_PATH"
}

# Package backend files
package_backend() {
    log "INFO" "Packaging backend files..."
    
    cd "$PROJECT_ROOT"
    
    # Essential backend directories and files
    local backend_items=(
        "src/"
        "vendor/"
        "cache/"
        "composer.json"
        "composer.lock"
        "index.html"
        "rest_api.php"
        "setup.php"
        "build-metadata.json"
        ".htaccess"
    )
    
    for item in "${backend_items[@]}"; do
        if [[ -e "$item" ]]; then
            log "DEBUG" "Copying backend item: $item"
            cp -r "$item" "$PACKAGE_PATH/backend/"
        else
            log "WARN" "Backend item not found: $item"
        fi
    done
    
    # Create production config template
    if [[ ! -f "$PACKAGE_PATH/backend/config.php" ]]; then
        log "INFO" "Creating production config template..."
        cat > "$PACKAGE_PATH/backend/config.php.template" << 'EOF'
<?php
// Production Configuration Template
// Copy to config.php and fill in actual values

return [
    'app' => [
        'debug' => false,
        'environment' => 'production',
        'log_level' => 'warn'
    ],
    'database' => [
        'driver' => 'pdo_mysql',
        'host' => 'YOUR_DB_HOST',
        'port' => 3306,
        'dbname' => 'YOUR_DB_NAME',
        'user' => 'YOUR_DB_USER',
        'password' => 'YOUR_DB_PASSWORD',
        'charset' => 'utf8mb4'
    ],
    'external_services' => [
        'tmdb_api_key' => 'YOUR_TMDB_API_KEY'
    ]
];
EOF
        log "DEBUG" "Production config template created"
    fi
    
    log "SUCCESS" "Backend packaging completed"
}

# Package frontend files
package_frontend() {
    log "INFO" "Packaging frontend files..."
    
    local frontend_dist="$PROJECT_ROOT/gravitycar-frontend/dist"
    
    if [[ ! -d "$frontend_dist" ]]; then
        log "WARN" "Frontend dist directory not found. Building frontend first..."
        "$SCRIPT_DIR/build-frontend.sh"
    fi
    
    if [[ -d "$frontend_dist" ]]; then
        log "DEBUG" "Copying frontend build artifacts"
        cp -r "$frontend_dist"/* "$PACKAGE_PATH/frontend/"
        
        # Verify essential frontend files
        if [[ ! -f "$PACKAGE_PATH/frontend/index.html" ]]; then
            error_exit "Frontend packaging failed - index.html not found"
        fi
        
        log "SUCCESS" "Frontend packaging completed"
    else
        error_exit "Frontend dist directory still not available after build attempt"
    fi
}

# Package deployment scripts
package_scripts() {
    log "INFO" "Packaging deployment scripts..."
    
    # Scripts to include in deployment package
    local script_items=(
        "scripts/deploy"
        "scripts/health-check.sh"
        "scripts/notify.sh"
        "scripts/common.sh"
    )
    
    for item in "${script_items[@]}"; do
        if [[ -e "$PROJECT_ROOT/$item" ]]; then
            log "DEBUG" "Copying script: $item"
            cp -r "$PROJECT_ROOT/$item" "$PACKAGE_PATH/scripts/"
        fi
    done
    
    # Make scripts executable
    find "$PACKAGE_PATH/scripts" -name "*.sh" -exec chmod +x {} \;
    
    log "SUCCESS" "Scripts packaging completed"
}

# Package configuration files
package_configuration() {
    log "INFO" "Packaging configuration files..."
    
    # Copy environment-specific configurations
    if [[ -f "$PROJECT_ROOT/scripts/config/environments.conf" ]]; then
        cp "$PROJECT_ROOT/scripts/config/environments.conf" "$PACKAGE_PATH/config/"
        log "DEBUG" "Copied environment configuration"
    fi
    
    # Copy credential template (not actual credentials)
    if [[ -f "$PROJECT_ROOT/scripts/config/credentials.conf.example" ]]; then
        cp "$PROJECT_ROOT/scripts/config/credentials.conf.example" "$PACKAGE_PATH/config/"
        log "DEBUG" "Copied credential template"
    fi
    
    log "SUCCESS" "Configuration packaging completed"
}

# Package documentation
package_documentation() {
    log "INFO" "Packaging documentation..."
    
    # Essential documentation files
    local doc_items=(
        "README.md"
        "docs/production_deployment_guide.md"
        "docs/implementation_plans/ci_cd_pipeline_implementation_plan.md"
    )
    
    for item in "${doc_items[@]}"; do
        if [[ -f "$PROJECT_ROOT/$item" ]]; then
            log "DEBUG" "Copying documentation: $item"
            cp "$PROJECT_ROOT/$item" "$PACKAGE_PATH/docs/"
        fi
    done
    
    log "SUCCESS" "Documentation packaging completed"
}

# Create deployment manifest
create_deployment_manifest() {
    log "INFO" "Creating deployment manifest..."
    
    local manifest_file="$PACKAGE_PATH/deployment-manifest.json"
    
    # Calculate package size
    local package_size=$(du -sh "$PACKAGE_PATH" | cut -f1)
    
    # Count files
    local file_count=$(find "$PACKAGE_PATH" -type f | wc -l)
    
    cat > "$manifest_file" << EOF
{
  "packageInfo": {
    "name": "$PACKAGE_NAME",
    "environment": "$ENVIRONMENT",
    "deploymentId": "$DEPLOYMENT_ID",
    "packageSize": "$package_size",
    "fileCount": $file_count,
    "createdAt": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "createdBy": "$(whoami)",
    "createdOn": "$(hostname)"
  },
  "buildInfo": {
    "gitCommit": "$(git rev-parse HEAD 2>/dev/null || echo 'unknown')",
    "gitBranch": "$(git branch --show-current 2>/dev/null || echo 'unknown')",
    "gitDirty": $(git diff-index --quiet HEAD -- && echo 'false' || echo 'true' 2>/dev/null || echo 'unknown'),
    "phpVersion": "$(php -r 'echo PHP_VERSION;')",
    "nodeVersion": "$(node --version 2>/dev/null || echo 'N/A')"
  },
  "components": {
    "backend": $([ -d "$PACKAGE_PATH/backend" ] && echo 'true' || echo 'false'),
    "frontend": $([ -d "$PACKAGE_PATH/frontend" ] && echo 'true' || echo 'false'),
    "scripts": $([ -d "$PACKAGE_PATH/scripts" ] && echo 'true' || echo 'false'),
    "config": $([ -d "$PACKAGE_PATH/config" ] && echo 'true' || echo 'false'),
    "docs": $([ -d "$PACKAGE_PATH/docs" ] && echo 'true' || echo 'false')
  },
  "deployment": {
    "targetEnvironment": "$ENVIRONMENT",
    "requiresConfiguration": true,
    "requiresDatabase": true,
    "backupRecommended": true
  }
}
EOF
    
    log "DEBUG" "Deployment manifest created: $manifest_file"
}

# Create compressed archive
create_archive() {
    log "INFO" "Creating compressed archive..."
    
    cd "$PACKAGE_DIR"
    
    local archive_name="${PACKAGE_NAME}.tar.gz"
    
    if tar -czf "$archive_name" "$PACKAGE_NAME"; then
        local archive_size=$(du -sh "$archive_name" | cut -f1)
        log "SUCCESS" "Archive created: $archive_name ($archive_size)"
        
        # Create checksum
        if command -v sha256sum >/dev/null 2>&1; then
            sha256sum "$archive_name" > "${archive_name}.sha256"
            log "DEBUG" "SHA256 checksum created"
        fi
        
        return 0
    else
        error_exit "Failed to create archive"
    fi
}

# Validate package integrity
validate_package() {
    log "INFO" "Validating package integrity..."
    
    # Check essential files exist
    local essential_files=(
        "$PACKAGE_PATH/deployment-manifest.json"
        "$PACKAGE_PATH/backend/src"
        "$PACKAGE_PATH/backend/vendor"
        "$PACKAGE_PATH/frontend/index.html"
    )
    
    for file in "${essential_files[@]}"; do
        if [[ ! -e "$file" ]]; then
            error_exit "Package validation failed - missing: $file"
        fi
    done
    
    # Validate JSON files
    if command -v jq >/dev/null 2>&1; then
        if ! jq empty "$PACKAGE_PATH/deployment-manifest.json" >/dev/null 2>&1; then
            error_exit "Invalid JSON in deployment manifest"
        fi
    fi
    
    log "SUCCESS" "Package validation passed"
}

# Clean up old packages
cleanup_old_packages() {
    log "INFO" "Cleaning up old packages..."
    
    # Keep only the last 5 packages for each environment
    local retention_count=5
    
    cd "$PACKAGE_DIR"
    
    # Clean up old directories
    ls -dt gravitycar-${ENVIRONMENT}-* 2>/dev/null | tail -n +$((retention_count + 1)) | xargs rm -rf 2>/dev/null || true
    
    # Clean up old archives
    ls -dt gravitycar-${ENVIRONMENT}-*.tar.gz 2>/dev/null | tail -n +$((retention_count + 1)) | xargs rm -f 2>/dev/null || true
    ls -dt gravitycar-${ENVIRONMENT}-*.tar.gz.sha256 2>/dev/null | tail -n +$((retention_count + 1)) | xargs rm -f 2>/dev/null || true
    
    log "DEBUG" "Old package cleanup completed"
}

# Main execution
main() {
    # Create packages directory
    mkdir -p "$PACKAGE_DIR"
    
    # Execute packaging steps
    create_package_structure
    package_backend
    package_frontend
    package_scripts
    package_configuration
    package_documentation
    create_deployment_manifest
    validate_package
    create_archive
    cleanup_old_packages
    
    log "SUCCESS" "Package creation completed successfully!"
    log "INFO" "Package location: $PACKAGE_PATH"
    log "INFO" "Archive location: $PACKAGE_DIR/${PACKAGE_NAME}.tar.gz"
    log "INFO" "Deployment manifest: $PACKAGE_PATH/deployment-manifest.json"
    
    # Display package summary
    local total_size=$(du -sh "$PACKAGE_PATH" | cut -f1)
    local file_count=$(find "$PACKAGE_PATH" -type f | wc -l)
    log "INFO" "Package summary: $file_count files, $total_size total"
}

# Execute main function
main "$@"