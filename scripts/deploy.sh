#!/bin/bash

# ==============================================================================
# Gravitycar Framework - Main Deployment Orchestrator
# ==============================================================================
# 
# This script orchestrates the complete CI/CD pipeline for the Gravitycar Framework.
# It handles building, testing, packaging, and deploying both frontend and backend
# components to production or staging environments.
#
# Usage:
#   ./scripts/deploy.sh [OPTIONS]
#
# Options:
#   --environment=ENV    Target environment (development|staging|production)
#   --confirm           Skip manual confirmation prompts
#   --dry-run           Show what would be done without executing
#   --verbose           Enable verbose logging
#   --skip-tests        Skip test execution (not recommended for production)
#   --skip-build        Skip build process (use existing build artifacts)
#   --help              Show this help message
#
# Examples:
#   ./scripts/deploy.sh --environment=production --confirm
#   ./scripts/deploy.sh --environment=staging --dry-run --verbose
#   ./scripts/deploy.sh --help
#
# ==============================================================================

set -euo pipefail

# Script configuration
readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
readonly LOG_DIR="${PROJECT_ROOT}/logs"
readonly CONFIG_DIR="${SCRIPT_DIR}/config"

# Default values
ENVIRONMENT=""
CONFIRM_DEPLOYMENT=false
DRY_RUN=false
VERBOSE=false
SKIP_TESTS=false
SKIP_BUILD=false
DEPLOYMENT_ID="deploy-$(date +%Y%m%d-%H%M%S)"
LOG_FILE="${LOG_DIR}/deployment-${DEPLOYMENT_ID}.log"

# Color codes for output
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly NC='\033[0m' # No Color

# ==============================================================================
# Utility Functions
# ==============================================================================

log() {
    local level="$1"
    shift
    local message="$*"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    # Console output with colors
    case "$level" in
        "INFO")  echo -e "${BLUE}[INFO]${NC}  ${message}" ;;
        "WARN")  echo -e "${YELLOW}[WARN]${NC}  ${message}" ;;
        "ERROR") echo -e "${RED}[ERROR]${NC} ${message}" ;;
        "SUCCESS") echo -e "${GREEN}[SUCCESS]${NC} ${message}" ;;
        "DEBUG") [[ "$VERBOSE" == "true" ]] && echo -e "[DEBUG] ${message}" ;;
    esac
    
    # Log file output (always, without colors)
    echo "[$timestamp] [$level] $message" >> "$LOG_FILE"
}

error_exit() {
    log "ERROR" "$1"
    log "ERROR" "Deployment failed. Check log file: $LOG_FILE"
    exit 1
}

check_prerequisites() {
    log "INFO" "Checking prerequisites..."
    
    # Check if we're in the correct directory
    if [[ ! -f "$PROJECT_ROOT/composer.json" ]]; then
        error_exit "Not in Gravitycar project root directory"
    fi
    
    # Check required commands
    local required_commands=("php" "composer" "npm" "git")
    for cmd in "${required_commands[@]}"; do
        if ! command -v "$cmd" &> /dev/null; then
            error_exit "Required command not found: $cmd"
        fi
    done
    
    # Check PHP version
    local php_version=$(php -r "echo PHP_VERSION;")
    log "DEBUG" "PHP version: $php_version"
    
    # Check if vendor directory exists
    if [[ ! -d "$PROJECT_ROOT/vendor" ]]; then
        log "WARN" "Composer dependencies not installed. Running composer install..."
        cd "$PROJECT_ROOT"
        composer install --no-dev --optimize-autoloader
    fi
    
    log "SUCCESS" "Prerequisites check completed"
}

load_environment_config() {
    local env="$1"
    local config_file="$CONFIG_DIR/environments.conf"
    
    log "INFO" "Loading configuration for environment: $env"
    
    if [[ ! -f "$config_file" ]]; then
        log "WARN" "Environment config file not found: $config_file"
        log "INFO" "Using default configuration"
        return 0
    fi
    
    # Source environment-specific configuration
    if grep -q "^\[$env\]" "$config_file"; then
        log "DEBUG" "Found configuration section for $env"
        # Here you would parse the config file and set variables
        # For now, we'll use defaults
    else
        log "WARN" "No configuration found for environment: $env"
    fi
}

confirm_deployment() {
    if [[ "$CONFIRM_DEPLOYMENT" == "true" ]]; then
        return 0
    fi
    
    echo ""
    log "WARN" "You are about to deploy to: $ENVIRONMENT"
    log "WARN" "This will update the live application and may cause downtime."
    echo ""
    
    read -p "Are you sure you want to continue? (type 'DEPLOY' to confirm): " confirmation
    
    if [[ "$confirmation" != "DEPLOY" ]]; then
        log "INFO" "Deployment cancelled by user"
        exit 0
    fi
    
    log "INFO" "Deployment confirmed by user"
}

run_stage() {
    local stage_name="$1"
    local stage_script="$2"
    
    log "INFO" "Starting stage: $stage_name"
    
    if [[ "$DRY_RUN" == "true" ]]; then
        log "INFO" "[DRY RUN] Would execute: $stage_script"
        return 0
    fi
    
    if [[ ! -f "$stage_script" ]]; then
        error_exit "Stage script not found: $stage_script"
    fi
    
    # Make script executable
    chmod +x "$stage_script"
    
    # Execute stage script with environment variables
    export ENVIRONMENT="$ENVIRONMENT"
    export DEPLOYMENT_ID="$DEPLOYMENT_ID"
    export VERBOSE="$VERBOSE"
    export DRY_RUN="$DRY_RUN"
    export LOG_FILE="$LOG_FILE"
    
    if "$stage_script"; then
        log "SUCCESS" "Stage completed: $stage_name"
    else
        error_exit "Stage failed: $stage_name"
    fi
}

# ==============================================================================
# Main Pipeline Stages
# ==============================================================================

stage_build() {
    if [[ "$SKIP_BUILD" == "true" ]]; then
        log "INFO" "Skipping build stage (--skip-build specified)"
        return 0
    fi
    
    log "INFO" "=== BUILD STAGE ==="
    
    # Frontend build
    run_stage "Frontend Build" "$SCRIPT_DIR/build/build-frontend.sh"
    
    # Backend build
    run_stage "Backend Build" "$SCRIPT_DIR/build/build-backend.sh"
    
    # Package creation
    run_stage "Package Creation" "$SCRIPT_DIR/build/package.sh"
}

stage_test() {
    if [[ "$SKIP_TESTS" == "true" ]]; then
        log "WARN" "Skipping test stage (--skip-tests specified)"
        log "WARN" "This is NOT recommended for production deployments!"
        return 0
    fi
    
    log "INFO" "=== TEST STAGE ==="
    
    # Run comprehensive test suite
    run_stage "Test Execution" "$SCRIPT_DIR/test/run-tests.sh"
}

stage_deploy() {
    log "INFO" "=== DEPLOY STAGE ==="
    
    case "$ENVIRONMENT" in
        "development")
            log "INFO" "Deploying to development environment"
            # Local deployment logic here
            ;;
        "staging")
            log "INFO" "Deploying to staging environment"
            run_stage "Staging Deployment" "$SCRIPT_DIR/deploy/deploy-staging.sh"
            ;;
        "production")
            log "INFO" "Deploying to production environment"
            run_stage "Production Transfer" "$SCRIPT_DIR/deploy/transfer.sh"
            run_stage "Production Setup" "$SCRIPT_DIR/deploy/setup-production.sh"
            ;;
        *)
            error_exit "Unknown environment: $ENVIRONMENT"
            ;;
    esac
}

stage_verify() {
    log "INFO" "=== VERIFICATION STAGE ==="
    
    # Health checks
    run_stage "Health Verification" "$SCRIPT_DIR/health-check.sh"
    
    # Send notifications
    run_stage "Notifications" "$SCRIPT_DIR/notify.sh"
}

# ==============================================================================
# Main Execution
# ==============================================================================

show_help() {
    cat << EOF
Gravitycar Framework - Deployment Orchestrator

Usage: $0 [OPTIONS]

Options:
  --environment=ENV    Target environment (development|staging|production)
  --confirm           Skip manual confirmation prompts
  --dry-run           Show what would be done without executing
  --verbose           Enable verbose logging
  --skip-tests        Skip test execution (not recommended for production)
  --skip-build        Skip build process (use existing build artifacts)
  --help              Show this help message

Examples:
  $0 --environment=production --confirm
  $0 --environment=staging --dry-run --verbose
  $0 --help

Environment Options:
  development  - Local development deployment
  staging      - Staging server deployment (future enhancement)
  production   - Production server deployment

For more information, see: docs/implementation_plans/ci_cd_pipeline_implementation_plan.md
EOF
}

parse_arguments() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --environment=*)
                ENVIRONMENT="${1#*=}"
                shift
                ;;
            --confirm)
                CONFIRM_DEPLOYMENT=true
                shift
                ;;
            --dry-run)
                DRY_RUN=true
                shift
                ;;
            --verbose)
                VERBOSE=true
                shift
                ;;
            --skip-tests)
                SKIP_TESTS=true
                shift
                ;;
            --skip-build)
                SKIP_BUILD=true
                shift
                ;;
            --help)
                show_help
                exit 0
                ;;
            *)
                error_exit "Unknown argument: $1"
                ;;
        esac
    done
    
    # Validate required arguments
    if [[ -z "$ENVIRONMENT" ]]; then
        error_exit "Environment must be specified. Use --environment=production|staging|development"
    fi
    
    if [[ ! "$ENVIRONMENT" =~ ^(development|staging|production)$ ]]; then
        error_exit "Invalid environment: $ENVIRONMENT. Must be one of: development, staging, production"
    fi
}

main() {
    # Parse command line arguments
    parse_arguments "$@"
    
    # Set up logging
    mkdir -p "$LOG_DIR"
    touch "$LOG_FILE"
    
    log "INFO" "=============================================="
    log "INFO" "Gravitycar Framework Deployment Started"
    log "INFO" "=============================================="
    log "INFO" "Deployment ID: $DEPLOYMENT_ID"
    log "INFO" "Environment: $ENVIRONMENT"
    log "INFO" "Log file: $LOG_FILE"
    log "INFO" "Dry run: $DRY_RUN"
    log "INFO" "Skip tests: $SKIP_TESTS"
    log "INFO" "Skip build: $SKIP_BUILD"
    log "INFO" "=============================================="
    
    # Check prerequisites
    check_prerequisites
    
    # Load environment configuration
    load_environment_config "$ENVIRONMENT"
    
    # Confirm deployment for production
    if [[ "$ENVIRONMENT" == "production" ]]; then
        confirm_deployment
    fi
    
    # Execute pipeline stages
    local start_time=$(date +%s)
    
    stage_build
    stage_test
    stage_deploy
    stage_verify
    
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    
    log "SUCCESS" "=============================================="
    log "SUCCESS" "Deployment Completed Successfully!"
    log "SUCCESS" "=============================================="
    log "SUCCESS" "Environment: $ENVIRONMENT"
    log "SUCCESS" "Duration: ${duration} seconds"
    log "SUCCESS" "Log file: $LOG_FILE"
    log "SUCCESS" "=============================================="
}

# Execute main function with all arguments
main "$@"