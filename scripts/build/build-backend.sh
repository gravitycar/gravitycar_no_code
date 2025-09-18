#!/bin/bash

# ==============================================================================
# Gravitycar Framework - Backend Build Script
# ==============================================================================
# 
# This script prepares the PHP backend for deployment.
# It handles composer dependencies, autoloader optimization, and validation.
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

log "INFO" "Starting backend build process..."

cd "$PROJECT_ROOT"

# Check if composer.json exists
if [[ ! -f "composer.json" ]]; then
    log "ERROR" "composer.json not found in project root"
    exit 1
fi

log "INFO" "Installing composer dependencies (production mode)..."
composer install --no-dev --optimize-autoloader --no-interaction

log "INFO" "Validating PHP syntax..."
find src -name "*.php" -exec php -l {} \; | grep -v "No syntax errors" || {
    log "ERROR" "PHP syntax errors found"
    exit 1
}

log "INFO" "Checking PHP configuration..."
php -m | grep -E "(pdo_mysql|pdo_sqlite|curl|json|mbstring)" > /dev/null || {
    log "ERROR" "Required PHP extensions not found"
    exit 1
}

log "INFO" "Validating configuration files..."
if [[ -f "config.php" ]]; then
    php -r "
        try {
            require 'config.php';
            echo 'Configuration file syntax is valid\n';
        } catch (Exception \$e) {
            echo 'Configuration file error: ' . \$e->getMessage() . '\n';
            exit(1);
        }
    "
else
    log "WARN" "config.php not found - will need to be created in production"
fi

log "SUCCESS" "Backend build completed successfully"
log "INFO" "Composer autoloader optimized for production"