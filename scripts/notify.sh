#!/bin/bash

# scripts/notify.sh - Deployment notification script
# Part of Gravitycar Framework CI/CD Pipeline - Phase 3

set -euo pipefail

# Script configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
LOG_FILE="${PROJECT_ROOT}/logs/notifications.log"

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

# Default configuration
DEFAULT_FROM_EMAIL="deployments@gravitycar.com"
DEFAULT_TO_EMAIL="admin@gravitycar.com"
DEFAULT_SMTP_HOST="smtp.gmail.com"
DEFAULT_SMTP_PORT="587"

# Get notification configuration
setup_notification_config() {
    # Email configuration from environment or defaults
    FROM_EMAIL="${NOTIFICATION_FROM_EMAIL:-$DEFAULT_FROM_EMAIL}"
    TO_EMAIL="${NOTIFICATION_TO_EMAIL:-$DEFAULT_TO_EMAIL}"
    SMTP_HOST="${SMTP_HOST:-$DEFAULT_SMTP_HOST}"
    SMTP_PORT="${SMTP_PORT:-$DEFAULT_SMTP_PORT}"
    SMTP_USER="${SMTP_USER:-$FROM_EMAIL}"
    SMTP_PASSWORD="${EMAIL_PASSWORD:-}"
    
    # Deployment information from environment
    DEPLOYMENT_ID="${DEPLOYMENT_ID:-unknown}"
    DEPLOYMENT_STATUS="${DEPLOYMENT_STATUS:-unknown}"
    DEPLOYMENT_MESSAGE="${DEPLOYMENT_MESSAGE:-No message provided}"
    DEPLOYED_BY="${DEPLOYED_BY:-unknown}"
    GIT_REF="${GIT_REF:-unknown}"
    GIT_SHA="${GIT_SHA:-unknown}"
    ENVIRONMENT="${ENVIRONMENT:-production}"
    
    log_info "Notification configuration:"
    log_info "  From: $FROM_EMAIL"
    log_info "  To: $TO_EMAIL"
    log_info "  SMTP: $SMTP_HOST:$SMTP_PORT"
    log_info "  Deployment ID: $DEPLOYMENT_ID"
    log_info "  Status: $DEPLOYMENT_STATUS"
}

# Send console notification
send_console_notification() {
    log_info "Sending console notification..."
    
    echo ""
    echo "========================================"
    echo "   DEPLOYMENT NOTIFICATION"
    echo "========================================"
    echo ""
    echo "Status: $DEPLOYMENT_MESSAGE"
    echo "Deployment ID: $DEPLOYMENT_ID"
    echo "Environment: $ENVIRONMENT"
    echo "Deployed by: $DEPLOYED_BY"
    echo "Git reference: $GIT_REF"
    echo "Timestamp: $(date)"
    echo ""
    
    case "$DEPLOYMENT_STATUS" in
        "SUCCESS")
            echo "✅ SUCCESS: Deployment completed successfully!"
            echo "🌐 API: https://api.gravitycar.com"
            echo "🖥️  Frontend: https://react.gravitycar.com"
            ;;
        "DRY_RUN")
            echo "🔍 DRY RUN: All steps validated successfully"
            ;;
        *)
            echo "❌ ISSUE: Check logs for details"
            ;;
    esac
    
    echo ""
    echo "========================================"
    echo ""
    
    log_success "Console notification sent"
}

# Save notification to log file
save_notification_log() {
    log_info "Saving notification to log file..."
    
    local notification_log="${PROJECT_ROOT}/logs/deployment-notifications.log"
    local timestamp
    timestamp=$(date -u "+%Y-%m-%d %H:%M:%S UTC")
    
    cat >> "$notification_log" << EOF

========================================
Deployment Notification - $timestamp
========================================
Deployment ID: $DEPLOYMENT_ID
Status: $DEPLOYMENT_STATUS
Message: $DEPLOYMENT_MESSAGE
Environment: $ENVIRONMENT
Deployed By: $DEPLOYED_BY
Git Reference: $GIT_REF
Git SHA: $GIT_SHA
========================================

EOF
    
    log_success "Notification saved to log file"
}

# Main notification function
main() {
    log_info "Starting deployment notification..."
    
    setup_notification_config
    
    # Always send console notification
    send_console_notification
    
    # Always save to log file
    save_notification_log
    
    log_success "Notification process completed"
    return 0
}

# Handle script interruption
trap 'log_error "Notification interrupted"; exit 1' INT TERM

# Execute main function
main "$@"

main() {
    # Determine status from exit codes or environment
    local status="success"
    
    # If this script is called after a failure, the status should be passed as an argument
    if [[ $# -gt 0 ]]; then
        status="$1"
    fi
    
    send_notification "$status"
}

main "$@"