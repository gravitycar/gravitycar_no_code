#!/bin/bash

# ==============================================================================
# Gravitycar Framework - Backend Build Script
# ==============================================================================
# 
# This script prepares the PHP backend for deployment.
# It handles composer dependencies, autoloader optimization, validation,
# and environment-specific configuration.
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

# Build configuration
ENVIRONMENT="${ENVIRONMENT:-development}"
SKIP_TESTS="${SKIP_TESTS:-false}"
SKIP_VALIDATION="${SKIP_VALIDATION:-false}"
OPTIMIZE_AUTOLOADER="${OPTIMIZE_AUTOLOADER:-true}"

log "INFO" "Starting backend build process..."
log "INFO" "Environment: $ENVIRONMENT"
log "INFO" "Optimize autoloader: $OPTIMIZE_AUTOLOADER"

cd "$PROJECT_ROOT"

# Check if composer.json exists
if [[ ! -f "composer.json" ]]; then
    error_exit "composer.json not found in project root"
fi

# Verify PHP version and extensions
check_php_environment() {
    log "INFO" "Checking PHP environment..."
    
    # Check PHP version
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    log "DEBUG" "PHP version: $PHP_VERSION"
    
    # Check if we have PHP 8.2+
    if ! php -r "exit(version_compare(PHP_VERSION, '8.2.0', '>=') ? 0 : 1);"; then
        error_exit "PHP 8.2+ is required. Current version: $PHP_VERSION"
    fi
    
    # Check required PHP extensions
    local required_extensions=("PDO" "pdo_mysql" "pdo_sqlite" "json" "mbstring" "openssl")
    local missing_extensions=()
    
    for ext in "${required_extensions[@]}"; do
        if ! php -m | grep -q "^$ext$"; then
            missing_extensions+=("$ext")
        fi
    done
    
    if [[ ${#missing_extensions[@]} -gt 0 ]]; then
        error_exit "Missing required PHP extensions: ${missing_extensions[*]}"
    fi
    
    log "SUCCESS" "PHP environment check passed"
}

# Install and optimize composer dependencies
install_dependencies() {
    log "INFO" "Installing composer dependencies..."
    
    # Backup existing vendor directory if it exists
    if [[ -d "vendor" ]]; then
        log "DEBUG" "Backing up existing vendor directory"
        mv vendor vendor.backup.$(date +%s) 2>/dev/null || true
    fi
    
    local composer_flags="--no-interaction --prefer-dist"
    
    if [[ "$ENVIRONMENT" == "production" ]]; then
        composer_flags="$composer_flags --no-dev --no-scripts"
        log "INFO" "Installing production dependencies (no dev packages)"
    else
        log "INFO" "Installing all dependencies (including dev packages)"
    fi
    
    if [[ "$OPTIMIZE_AUTOLOADER" == "true" ]]; then
        composer_flags="$composer_flags --optimize-autoloader"
        log "DEBUG" "Autoloader optimization enabled"
    fi
    
    if ! composer install $composer_flags; then
        error_exit "Composer install failed"
    fi
    
    log "SUCCESS" "Composer dependencies installed successfully"
}

# Validate PHP syntax across the codebase
validate_php_syntax() {
    if [[ "$SKIP_VALIDATION" == "true" ]]; then
        log "INFO" "Skipping PHP syntax validation (--skip-validation specified)"
        return 0
    fi
    
    log "INFO" "Validating PHP syntax..."
    
    # Add environment debugging - use INFO level so it shows in CI
    log "INFO" "PHP CLI version: $(php --version | head -1)"
    log "INFO" "Current working directory: $(pwd)"
    log "INFO" "Looking for PHP files in: $(pwd)/src"
    log "INFO" "Directory exists: $([ -d "src" ] && echo "YES" || echo "NO")"
    
    # Count files first
    local total_files=$(find src -name "*.php" 2>/dev/null | wc -l)
    log "INFO" "Found $total_files PHP files to validate"
    
    if [[ $total_files -eq 0 ]]; then
        log "WARNING" "No PHP files found in src/ directory!"
        log "INFO" "Directory listing:"
        ls -la src/ 2>/dev/null || log "ERROR" "Cannot list src/ directory"
        return 0
    fi
    
    local error_count=0
    local file_count=0
    local error_files=()
    local show_debug_limit=5
    
    # Create temporary file list for more robust processing
    local temp_file_list="/tmp/php_files_list.txt"
    find src -name "*.php" > "$temp_file_list" 2>/dev/null
    
    # Check if we have any files to process
    if [[ ! -s "$temp_file_list" ]]; then
        log "WARNING" "No PHP files found in temp file list!"
        rm -f "$temp_file_list"
        return 0
    fi
    
    log "INFO" "Processing files from temporary list..."
    
    # Temporarily disable exit-on-error to properly handle syntax check failures
    set +e
    
    # Process each file
    while IFS= read -r file; do
        ((file_count++))
        
        # Show debug info for first few files and all errors
        if [[ $file_count -le $show_debug_limit ]]; then
            log "INFO" "Checking syntax: $file"
        elif [[ $file_count -eq $((show_debug_limit + 1)) ]]; then
            log "INFO" "... (continuing validation, will show errors only)"
        fi
        
        # Capture both stdout and stderr for detailed error reporting
        local syntax_output
        if ! syntax_output=$(php -l "$file" 2>&1); then
            log "ERROR" "Syntax error in: $file"
            log "ERROR" "Details: $syntax_output"
            error_files+=("$file")
            ((error_count++))
        else
            # Show success for first few files only
            if [[ $file_count -le $show_debug_limit ]]; then
                log "INFO" "✅ $file - OK"
            fi
        fi
    done < "$temp_file_list"
    
    # Re-enable exit-on-error
    set -e
    
    # Clean up temporary file
    rm -f "$temp_file_list"
    
    log "INFO" "Checked $file_count PHP files"
    
    if [[ $error_count -gt 0 ]]; then
        log "ERROR" "==============================================="
        log "ERROR" "PHP SYNTAX VALIDATION FAILED"
        log "ERROR" "==============================================="
        log "ERROR" "Total files checked: $file_count"
        log "ERROR" "Files with errors: $error_count"
        log "ERROR" ""
        log "ERROR" "Failed files:"
        for failed_file in "${error_files[@]}"; do
            log "ERROR" "  - $failed_file"
        done
        log "ERROR" ""
        log "ERROR" "Run 'php -l <filename>' on each failed file for details"
        log "ERROR" "==============================================="
        error_exit "Found $error_count PHP syntax errors"
    fi
    
    log "SUCCESS" "PHP syntax validation passed"
}

# Validate configuration files
validate_configuration() {
    log "INFO" "Validating configuration files..."
    
    # Check main config file
    if [[ -f "config.php" ]]; then
        log "DEBUG" "Validating config.php"
        php -r "
            try {
                require 'config.php';
                echo 'Configuration file syntax is valid\n';
            } catch (Exception \$e) {
                echo 'Configuration file error: ' . \$e->getMessage() . '\n';
                exit(1);
            } catch (ParseError \$e) {
                echo 'Configuration file parse error: ' . \$e->getMessage() . '\n';
                exit(1);
            }
        " || error_exit "config.php validation failed"
    else
        log "WARN" "config.php not found - will need to be created in production"
    fi
    
    # Validate composer.json
    if ! composer validate --no-check-all --no-check-publish; then
        log "WARN" "composer.json validation issues found"
    fi
    
    log "SUCCESS" "Configuration validation completed"
}

# Generate autoloader optimization
optimize_autoloader() {
    if [[ "$OPTIMIZE_AUTOLOADER" != "true" ]]; then
        return 0
    fi
    
    log "INFO" "Optimizing autoloader for production..."
    
    # Generate optimized autoloader
    composer dump-autoload --optimize --no-dev --classmap-authoritative
    
    # Verify autoloader works
    if php -r "require 'vendor/autoload.php'; echo 'Autoloader test passed\n';"; then
        log "SUCCESS" "Autoloader optimization completed"
    else
        error_exit "Autoloader optimization failed"
    fi
}

# Clean up development files for production
cleanup_for_production() {
    if [[ "$ENVIRONMENT" != "production" ]]; then
        return 0
    fi
    
    log "INFO" "Cleaning up development files for production..."
    
    # Remove development and test files
    local cleanup_patterns=(
        "Tests/"
        "tests/"
        "phpunit.xml"
        ".phpunit.cache/"
        "coverage/"
        "*.md"
        ".git/"
        ".gitignore"
        "tmp/"
    )
    
    for pattern in "${cleanup_patterns[@]}"; do
        if [[ -e "$pattern" ]]; then
            log "DEBUG" "Removing: $pattern"
            rm -rf "$pattern"
        fi
    done
    
    log "DEBUG" "Production cleanup completed"
}

# Generate build metadata
generate_build_metadata() {
    log "INFO" "Generating build metadata..."
    
    local metadata_file="build-metadata.json"
    
    cat > "$metadata_file" << EOF
{
  "buildTime": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "environment": "$ENVIRONMENT",
  "phpVersion": "$PHP_VERSION",
  "composerVersion": "$(composer --version 2>/dev/null | cut -d' ' -f3 || echo 'unknown')",
  "optimizedAutoloader": $OPTIMIZE_AUTOLOADER,
  "gitCommit": "$(git rev-parse HEAD 2>/dev/null || echo 'unknown')",
  "gitBranch": "$(git branch --show-current 2>/dev/null || echo 'unknown')",
  "buildHost": "$(hostname)",
  "buildUser": "$(whoami)"
}
EOF
    
    log "DEBUG" "Build metadata saved to: $metadata_file"
}

# Run cache rebuild if needed
rebuild_cache() {
    log "INFO" "Rebuilding framework cache..."
    
    if [[ -f "setup.php" ]]; then
        if php setup.php; then
            log "SUCCESS" "Framework cache rebuilt successfully"
        else
            log "WARN" "Cache rebuild failed, but continuing build"
        fi
    else
        log "DEBUG" "setup.php not found, skipping cache rebuild"
    fi
}

# Main execution
main() {
    check_php_environment
    install_dependencies
    validate_php_syntax
    validate_configuration
    optimize_autoloader
    rebuild_cache
    generate_build_metadata
    cleanup_for_production
    
    log "SUCCESS" "Backend build completed successfully"
    log "INFO" "Build metadata available in: build-metadata.json"
    
    # Display summary
    local vendor_size=$(du -sh vendor 2>/dev/null | cut -f1 || echo "N/A")
    log "INFO" "Vendor directory size: $vendor_size"
    
    if [[ "$ENVIRONMENT" == "production" ]]; then
        log "INFO" "Production build optimizations applied"
    fi
}

# Execute main function
main "$@"