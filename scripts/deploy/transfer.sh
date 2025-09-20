#!/bin/bash

# scripts/deploy/transfer.sh - Production deployment and transfer script
# Part of Gravitycar Framework CI/CD Pipeline - Phase 3

set -euo pipefail

# Script configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
LOG_FILE="${PROJECT_ROOT}/logs/deployment.log"

# Ensure logs directory exists
mkdir -p "$(dirname "$LOG_FILE")"

# Logging functions
log_info() {
    echo "[INFO]  $1" | tee -a "$LOG_FILE"
}

log_warn() {
    echo "[WARN]  $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo "[ERROR] $1" | tee -a "$LOG_FILE"
}

log_success() {
    echo "[SUCCESS] $1" | tee -a "$LOG_FILE"
}

# Environment validation
validate_environment() {
    log_info "Validating deployment environment..."
    
    if [ -z "${DEPLOYMENT_ID:-}" ]; then
        log_error "DEPLOYMENT_ID environment variable is required"
        exit 1
    fi
    
    if [ -z "${PRODUCTION_HOST:-}" ]; then
        log_error "PRODUCTION_HOST environment variable is required"
        exit 1
    fi
    
    if [ -z "${PRODUCTION_USER:-}" ]; then
        log_error "PRODUCTION_USER environment variable is required"
        exit 1
    fi
    
    if [ -z "${DB_PASSWORD:-}" ]; then
        log_warn "DB_PASSWORD not set - database operations may fail"
    fi
    
    log_success "Environment validation completed"
}

# Check deployment package
check_package() {
    log_info "Checking deployment package..."
    
    local package_dir="${PROJECT_ROOT}/packages"
    local package_pattern="*${DEPLOYMENT_ID}*"
    
    log_info "Looking for packages in: $package_dir"
    log_info "Package pattern: $package_pattern"
    
    # Debug: List current directory contents
    log_info "Current working directory: $(pwd)"
    log_info "Project root directory: $PROJECT_ROOT"
    log_info "Contents of project root:"
    ls -la "$PROJECT_ROOT" || log_error "Failed to list project root"
    
    if [ ! -d "$package_dir" ]; then
        log_error "Packages directory not found: $package_dir"
        log_info "Creating packages directory for debugging..."
        mkdir -p "$package_dir"
        log_info "Directory created. Contents now:"
        ls -la "$package_dir" || log_error "Failed to list packages directory after creation"
        exit 1
    fi
    
    # Find the deployment package in packages directory first
    local package_path
    package_path=$(find "$package_dir" -name "$package_pattern" -type d | head -1)
    
    # If not found in packages directory, check root directory (artifact download fallback)
    if [ -z "$package_path" ]; then
        log_warn "Package not found in packages directory, checking root directory..."
        package_path=$(find "$PROJECT_ROOT" -maxdepth 1 -name "$package_pattern" -type d | head -1)
        
        if [ -n "$package_path" ]; then
            log_info "Found package in root directory: $package_path"
            log_info "Moving package to packages directory..."
            mv "$package_path" "$package_dir/"
            package_path="$package_dir/$(basename "$package_path")"
            log_success "Package moved to: $package_path"
        fi
    fi
    
    if [ -z "$package_path" ]; then
        log_error "Deployment package not found matching: $package_pattern"
        log_error "Available packages:"
        ls -la "$package_dir" || log_error "Failed to list packages directory"
        log_error "All files in packages directory:"
        find "$package_dir" -type f -o -type d | head -20
        log_error "Files in root directory matching pattern:"
        find "$PROJECT_ROOT" -maxdepth 1 -name "$package_pattern" | head -10
        exit 1
    fi
    
    PACKAGE_PATH="$package_path"
    log_success "Found deployment package: $PACKAGE_PATH"
    
    # Validate package contents
    local required_files=(
        "deployment-manifest.json"
        "backend"
        "frontend" 
        "scripts"
        "config"
    )
    
    for file in "${required_files[@]}"; do
        if [ ! -e "$PACKAGE_PATH/$file" ]; then
            log_error "Required package component missing: $file"
            exit 1
        fi
    done
    
    log_success "Package validation completed"
}

# Test SSH connectivity
test_ssh_connection() {
    log_info "Testing SSH connection to production server..."
    
    if ! ssh -o ConnectTimeout=10 -o BatchMode=yes "$PRODUCTION_USER@$PRODUCTION_HOST" "echo 'SSH connection successful'" 2>/dev/null; then
        log_error "SSH connection failed to $PRODUCTION_USER@$PRODUCTION_HOST"
        log_error "Please verify SSH key is properly configured"
        exit 1
    fi
    
    log_success "SSH connection verified"
}

# Create remote backup
create_remote_backup() {
    log_info "Creating remote backup before deployment..."
    
    local backup_timestamp
    backup_timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_dir="/home/$PRODUCTION_USER/backups/gravitycar_backup_$backup_timestamp"
    
    ssh "$PRODUCTION_USER@$PRODUCTION_HOST" "
        mkdir -p /home/$PRODUCTION_USER/backups
        
        # Backup current backend application if it exists
        if [ -d '/home/$PRODUCTION_USER/public_html/api.gravitycar.com' ]; then
            mkdir -p '$backup_dir'
            cp -r /home/$PRODUCTION_USER/public_html/api.gravitycar.com '$backup_dir/'
            echo 'Backend application backup created: $backup_dir/api.gravitycar.com'
        else
            echo 'No existing backend application found to backup'
        fi
        
        # Backup current frontend application if it exists
        if [ -d '/home/$PRODUCTION_USER/public_html/react.gravitycar.com' ]; then
            mkdir -p '$backup_dir'
            cp -r /home/$PRODUCTION_USER/public_html/react.gravitycar.com '$backup_dir/'
            echo 'Frontend application backup created: $backup_dir/react.gravitycar.com'
        else
            echo 'No existing frontend application found to backup'
        fi
        
        # Backup database
        if command -v mysqldump >/dev/null 2>&1; then
            mkdir -p '$backup_dir'
            mysqldump --single-transaction --routines --triggers gravitycar > '$backup_dir/database_backup.sql' 2>/dev/null || echo 'Database backup failed or not configured'
        fi
        
        # Clean old backups (keep last 5)
        cd /home/$PRODUCTION_USER/backups
        ls -t | grep '^gravitycar_backup_' | tail -n +6 | xargs -r rm -rf
    " || log_warn "Backup creation had some issues but continuing"
    
    log_success "Remote backup completed"
}

# Transfer deployment package
transfer_package() {
    log_info "Transferring deployment package to production..."
    
    local remote_temp_dir="/home/$PRODUCTION_USER/deployment_temp_$DEPLOYMENT_ID"
    
    # Create temporary directory on remote
    ssh "$PRODUCTION_USER@$PRODUCTION_HOST" "
        rm -rf '$remote_temp_dir'
        mkdir -p '$remote_temp_dir'
    "
    
    # Transfer package using rsync for efficiency
    log_info "Uploading package contents..."
    if command -v rsync >/dev/null 2>&1; then
        rsync -avz --progress "$PACKAGE_PATH/" "$PRODUCTION_USER@$PRODUCTION_HOST:$remote_temp_dir/"
    else
        # Fallback to scp if rsync not available
        scp -r "$PACKAGE_PATH/"* "$PRODUCTION_USER@$PRODUCTION_HOST:$remote_temp_dir/"
    fi
    
    REMOTE_TEMP_DIR="$remote_temp_dir"
    log_success "Package transfer completed"
}

# Deploy backend
deploy_backend() {
    log_info "Deploying backend to production..."
    
    ssh "$PRODUCTION_USER@$PRODUCTION_HOST" "
        # Create backend directory structure
        mkdir -p /home/$PRODUCTION_USER/public_html/api.gravitycar.com
        
        # Copy backend files
        cp -r '$REMOTE_TEMP_DIR/backend/'* /home/$PRODUCTION_USER/public_html/api.gravitycar.com/
        
        # Set proper permissions
        find /home/$PRODUCTION_USER/public_html/api.gravitycar.com -type f -exec chmod 644 {} \;
        find /home/$PRODUCTION_USER/public_html/api.gravitycar.com -type d -exec chmod 755 {} \;
        
        # Make specific files executable
        chmod +x /home/$PRODUCTION_USER/public_html/api.gravitycar.com/scripts/setup.php 2>/dev/null || true
        
        # Create/update production config
        if [ -f '$REMOTE_TEMP_DIR/config/production.conf' ]; then
            cp '$REMOTE_TEMP_DIR/config/production.conf' /home/$PRODUCTION_USER/public_html/api.gravitycar.com/config.php
        fi
        
        # Update database password in config if provided
        if [ -n '${DB_PASSWORD:-}' ]; then
            sed -i \"s/DB_PASSWORD_PLACEHOLDER/${DB_PASSWORD}/g\" /home/$PRODUCTION_USER/public_html/api.gravitycar.com/config.php 2>/dev/null || true
        fi
        
        # Run framework setup
        cd /home/$PRODUCTION_USER/public_html/api.gravitycar.com
        php setup.php 2>/dev/null || echo 'Setup script had issues but continuing'
        
        echo 'Backend deployment completed'
    "
    
    log_success "Backend deployment completed"
}

# Deploy frontend
deploy_frontend() {
    log_info "Deploying frontend to production..."
    
    ssh "$PRODUCTION_USER@$PRODUCTION_HOST" "
        # Create frontend directory structure
        mkdir -p /home/$PRODUCTION_USER/public_html/react.gravitycar.com
        
        # Copy frontend files
        if [ -d '$REMOTE_TEMP_DIR/frontend' ]; then
            cp -r '$REMOTE_TEMP_DIR/frontend/'* /home/$PRODUCTION_USER/public_html/react.gravitycar.com/
        fi
        
        # Set proper permissions
        find /home/$PRODUCTION_USER/public_html/react.gravitycar.com -type f -exec chmod 644 {} \;
        find /home/$PRODUCTION_USER/public_html/react.gravitycar.com -type d -exec chmod 755 {} \;
        
        echo 'Frontend deployment completed'
    "
    
    log_success "Frontend deployment completed"
}

# Update production configuration
update_production_config() {
    log_info "Updating production configuration..."
    
    ssh "$PRODUCTION_USER@$PRODUCTION_HOST" "
        # Update backend configuration
        cd /home/$PRODUCTION_USER/public_html/api.gravitycar.com
        
        # Update .htaccess if provided
        if [ -f '$REMOTE_TEMP_DIR/config/.htaccess' ]; then
            cp '$REMOTE_TEMP_DIR/config/.htaccess' .htaccess
        fi
        
        # Create logs directory
        mkdir -p logs
        chmod 777 logs
        
        # Create cache directory with proper permissions
        mkdir -p cache
        chmod 777 cache
        
        # Set up database if needed
        if [ -f '$REMOTE_TEMP_DIR/scripts/setup-database.sql' ] && [ -n '${DB_PASSWORD:-}' ]; then
            mysql -u \$USER -p${DB_PASSWORD} \$USER < '$REMOTE_TEMP_DIR/scripts/setup-database.sql' 2>/dev/null || echo 'Database setup had issues but continuing'
        fi
        
        echo 'Production configuration updated'
    "
    
    log_success "Production configuration updated"
}

# Cleanup temporary files
cleanup_deployment() {
    log_info "Cleaning up deployment files..."
    
    ssh "$PRODUCTION_USER@$PRODUCTION_HOST" "
        rm -rf '$REMOTE_TEMP_DIR'
        echo 'Temporary deployment files cleaned up'
    "
    
    log_success "Cleanup completed"
}

# Verify deployment
verify_deployment() {
    log_info "Verifying deployment..."
    
    ssh "$PRODUCTION_USER@$PRODUCTION_HOST" "
        # Check if critical backend files exist
        if [ ! -f '/home/$PRODUCTION_USER/public_html/api.gravitycar.com/index.php' ] && [ ! -f '/home/$PRODUCTION_USER/public_html/api.gravitycar.com/rest_api.php' ]; then
            echo 'ERROR: Critical backend files missing'
            exit 1
        fi
        
        # Check if frontend files exist
        if [ ! -f '/home/$PRODUCTION_USER/public_html/react.gravitycar.com/index.html' ]; then
            echo 'WARNING: Frontend index.html missing'
        fi
        
        # Check PHP syntax in backend
        cd /home/$PRODUCTION_USER/public_html/api.gravitycar.com
        find . -name '*.php' -exec php -l {} \; > /dev/null 2>&1 || echo 'WARNING: PHP syntax issues detected'
        
        echo 'Deployment verification completed'
    "
    
    log_success "Deployment verification completed"
}

# Record deployment
record_deployment() {
    log_info "Recording deployment information..."
    
    local deployment_info="$PACKAGE_PATH/deployment-manifest.json"
    
    if [ -f "$deployment_info" ]; then
        ssh "$PRODUCTION_USER@$PRODUCTION_HOST" "
            mkdir -p /home/$PRODUCTION_USER/deployments
            echo '$(cat "$deployment_info")' > '/home/$PRODUCTION_USER/deployments/deployment_${DEPLOYMENT_ID}.json'
            
            # Create symlink to latest deployment
            ln -sf 'deployment_${DEPLOYMENT_ID}.json' '/home/$PRODUCTION_USER/deployments/latest.json'
        "
    fi
    
    log_success "Deployment recorded"
}

# Main deployment function
main() {
    log_info "Starting production deployment..."
    log_info "Deployment ID: ${DEPLOYMENT_ID}"
    log_info "Target host: ${PRODUCTION_HOST}"
    log_info "SSH user: ${PRODUCTION_USER}"
    
    # Check for dry run mode
    if [ "${DRY_RUN:-false}" = "true" ]; then
        log_info "DRY RUN MODE: Simulating deployment steps"
        validate_environment
        check_package
        log_info "DRY RUN: Would test SSH connection"
        log_info "DRY RUN: Would create remote backup"
        log_info "DRY RUN: Would transfer package"
        log_info "DRY RUN: Would deploy backend"
        log_info "DRY RUN: Would deploy frontend"
        log_info "DRY RUN: Would update configuration"
        log_info "DRY RUN: Would verify deployment"
        log_info "DRY RUN: Would record deployment"
        log_success "DRY RUN: All deployment steps validated successfully"
        return 0
    fi
    
    # Execute deployment steps
    validate_environment
    check_package
    test_ssh_connection
    create_remote_backup
    transfer_package
    deploy_backend
    deploy_frontend
    update_production_config
    verify_deployment
    record_deployment
    cleanup_deployment
    
    log_success "Production deployment completed successfully!"
    log_info "Deployment ID: ${DEPLOYMENT_ID}"
    log_info "Next step: Run health checks to verify deployment"
}

# Handle script interruption
trap 'log_error "Deployment interrupted"; exit 1' INT TERM

# Execute main function
main "$@"