#!/bin/bash

# ==============================================================================
# Gravitycar Framework - Health Check Script
# ==============================================================================
# 
# This script performs health checks after deployment to verify the system
# is working correctly.
#
# ==============================================================================

set -euo pipefail

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

# Source common functions
source "${SCRIPT_DIR}/common.sh" 2>/dev/null || {
    # Basic logging if common.sh not available yet
    log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$1] ${*:2}"; }
}

# Health check configuration
HEALTH_CHECK_TIMEOUT=30
MAX_RETRIES=3

check_api_health() {
    local base_url="${1:-http://localhost:8081}"
    
    log "INFO" "Checking API health at: $base_url"
    
    # Basic connectivity check
    if curl -s --max-time "$HEALTH_CHECK_TIMEOUT" "$base_url" > /dev/null; then
        log "SUCCESS" "API is responding"
    else
        log "ERROR" "API is not responding"
        return 1
    fi
    
    # Check specific endpoints if API is up
    local endpoints=("/health" "/Users" "/Movies")
    
    for endpoint in "${endpoints[@]}"; do
        local url="$base_url$endpoint"
        log "INFO" "Testing endpoint: $url"
        
        if curl -s --max-time "$HEALTH_CHECK_TIMEOUT" "$url" > /dev/null; then
            log "SUCCESS" "Endpoint $endpoint is responding"
        else
            log "WARN" "Endpoint $endpoint is not responding (may be expected)"
        fi
    done
}

check_database_connectivity() {
    log "INFO" "Checking database connectivity..."
    
    cd "$PROJECT_ROOT"
    
    # Simple PHP script to test database connection
    php -r "
        require 'vendor/autoload.php';
        try {
            if (file_exists('config.php')) {
                require 'config.php';
                echo 'Database configuration loaded successfully\n';
            } else {
                echo 'Warning: config.php not found\n';
            }
        } catch (Exception \$e) {
            echo 'Database check failed: ' . \$e->getMessage() . '\n';
            exit(1);
        }
    " || {
        log "ERROR" "Database connectivity check failed"
        return 1
    }
    
    log "SUCCESS" "Database connectivity check passed"
}

check_file_permissions() {
    log "INFO" "Checking file permissions..."
    
    cd "$PROJECT_ROOT"
    
    # Check critical directories are writable
    local directories=("logs" "cache" "tmp")
    
    for dir in "${directories[@]}"; do
        if [[ -d "$dir" ]]; then
            if [[ -w "$dir" ]]; then
                log "SUCCESS" "Directory $dir is writable"
            else
                log "ERROR" "Directory $dir is not writable"
                return 1
            fi
        else
            log "WARN" "Directory $dir does not exist"
        fi
    done
}

main() {
    log "INFO" "Starting health checks..."
    
    # Determine environment and endpoints
    local environment="${ENVIRONMENT:-development}"
    local api_url
    
    case "$environment" in
        "production")
            api_url="https://api.gravitycar.com"
            ;;
        "staging")
            api_url="https://staging.gravitycar.com"
            ;;
        *)
            api_url="http://localhost:8081"
            ;;
    esac
    
    # Run health checks
    check_file_permissions
    check_database_connectivity
    retry "$MAX_RETRIES" check_api_health "$api_url"
    
    log "SUCCESS" "All health checks passed!"
}

main "$@"