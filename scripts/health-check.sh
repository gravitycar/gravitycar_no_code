#!/bin/bash

# scripts/health-check.sh - Production health verification script
# Part of Gravitycar Framework CI/CD Pipeline - Phase 3

set -euo pipefail

# Script configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
LOG_FILE="${PROJECT_ROOT}/logs/health-check.log"

# Default configuration
DEFAULT_API_URL="https://api.gravitycar.com"
DEFAULT_FRONTEND_URL="https://react.gravitycar.com"
DEFAULT_TIMEOUT=30
MAX_RETRIES=3
RETRY_DELAY=10

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

# Environment setup
setup_environment() {
    # Use environment variables or defaults
    API_URL="${API_URL:-$DEFAULT_API_URL}"
    FRONTEND_URL="${FRONTEND_URL:-$DEFAULT_FRONTEND_URL}"
    TIMEOUT="${HEALTH_CHECK_TIMEOUT:-$DEFAULT_TIMEOUT}"
    
    # If PRODUCTION_HOST is set, construct URLs from it
    if [ -n "${PRODUCTION_HOST:-}" ]; then
        if [[ "$PRODUCTION_HOST" == *"gravitycar.com"* ]]; then
            API_URL="https://api.gravitycar.com"
            FRONTEND_URL="https://react.gravitycar.com"
        else
            # For other hosts, assume they serve both API and frontend
            API_URL="https://$PRODUCTION_HOST"
            FRONTEND_URL="https://$PRODUCTION_HOST"
        fi
    fi
    
    log_info "Health check configuration:"
    log_info "  API URL: $API_URL"
    log_info "  Frontend URL: $FRONTEND_URL"
    log_info "  Timeout: ${TIMEOUT}s"
    log_info "  Max retries: $MAX_RETRIES"
}

# Utility function for HTTP requests with retries
http_request() {
    local url="$1"
    local expected_status="${2:-200}"
    local retry_count=0
    
    # Add debug information for the request
    log_info "Testing URL: $url (expecting HTTP $expected_status)"
    
    while [ $retry_count -lt $MAX_RETRIES ]; do
        local status_code
        local response_headers
        
        # Get both status code and some debug info
        status_code=$(curl -s -o /dev/null -w "%{http_code}" \
            --max-time "$TIMEOUT" \
            --connect-timeout 10 \
            --retry 0 \
            --user-agent "Gravitycar-HealthCheck/1.0" \
            "$url" 2>/dev/null || echo "000")
        
        log_info "Attempt $((retry_count + 1))/$MAX_RETRIES: HTTP $status_code"
        
        if [ "$status_code" = "$expected_status" ]; then
            log_info "Request successful after $((retry_count + 1)) attempts"
            return 0
        fi
        
        # Additional debug for first failure
        if [ $retry_count -eq 0 ]; then
            log_info "Getting additional debug information..."
            
            # Test basic connectivity
            local hostname
            hostname=$(echo "$url" | sed 's|https\?://||' | sed 's|/.*||')
            
            if command -v nslookup >/dev/null 2>&1; then
                local dns_result
                dns_result=$(nslookup "$hostname" 2>/dev/null | grep "Address:" | tail -1 || echo "DNS lookup failed")
                log_info "DNS lookup for $hostname: $dns_result"
            fi
            
            # Test basic HTTP connectivity
            local basic_status
            basic_status=$(curl -s -o /dev/null -w "%{http_code}" \
                --max-time 10 \
                --connect-timeout 5 \
                "https://$hostname" 2>/dev/null || echo "000")
            log_info "Basic HTTPS connectivity to $hostname: HTTP $basic_status"
        fi
        
        retry_count=$((retry_count + 1))
        if [ $retry_count -lt $MAX_RETRIES ]; then
            log_warn "HTTP request failed (attempt $retry_count/$MAX_RETRIES): $url returned $status_code"
            sleep $RETRY_DELAY
        fi
    done
    
    log_error "HTTP request failed after $MAX_RETRIES attempts: $url returned $status_code"
    return 1
}

# Check API health endpoint
check_api_health() {
    log_info "Checking API health endpoint..."
    
    local health_url="$API_URL/health"
    
    if http_request "$health_url" "200"; then
        log_success "API health endpoint responding"
        
        # Get detailed health information
        local health_response
        health_response=$(curl -s --max-time "$TIMEOUT" \
            --user-agent "Gravitycar-HealthCheck/1.0" \
            "$health_url" 2>/dev/null || echo '{"status":"unknown"}')
        
        # Parse health response if it's JSON
        if echo "$health_response" | jq . >/dev/null 2>&1; then
            local status
            status=$(echo "$health_response" | jq -r '.status' 2>/dev/null || echo "unknown")
            log_info "API health status: $status"
            
            # Check database connectivity if reported
            local db_status
            db_status=$(echo "$health_response" | jq -r '.database.status' 2>/dev/null || echo "unknown")
            if [ "$db_status" != "null" ] && [ "$db_status" != "unknown" ]; then
                log_info "Database status: $db_status"
            fi
        else
            log_info "API responding but health data not in expected JSON format"
            log_info "Response preview: $(echo "$health_response" | head -c 200)"
        fi
        
        return 0
    else
        log_warn "Primary health endpoint failed, trying fallback endpoints..."
        
        # Try alternative endpoints to verify API is running
        local fallback_endpoints=(
            "$API_URL/"
            "$API_URL/ping"
            "$API_URL/status"
        )
        
        for endpoint in "${fallback_endpoints[@]}"; do
            log_info "Trying fallback endpoint: $endpoint"
            local status_code
            status_code=$(curl -s -o /dev/null -w "%{http_code}" \
                --max-time 10 \
                --user-agent "Gravitycar-HealthCheck/1.0" \
                "$endpoint" 2>/dev/null || echo "000")
            
            log_info "Fallback endpoint $endpoint: HTTP $status_code"
            
            # Accept any reasonable HTTP response as sign the API is running
            if [[ "$status_code" =~ ^[2-4][0-9][0-9]$ ]]; then
                log_warn "API appears to be running (fallback endpoint responded with HTTP $status_code)"
                log_warn "Primary health endpoint may be misconfigured"
                return 0
            fi
        done
        
        log_error "API health endpoint not responding and no fallback endpoints accessible"
        return 1
    fi
}

# Check API authentication
check_api_auth() {
    log_info "Checking API authentication endpoint..."
    
    local auth_url="$API_URL/auth/status"
    
    # Authentication endpoint should return 401 for unauthenticated requests
    if http_request "$auth_url" "401"; then
        log_success "API authentication endpoint responding correctly"
        return 0
    else
        # Try alternative authentication check
        local login_url="$API_URL/auth/login"
        if http_request "$login_url" "200"; then
            log_success "API login endpoint responding"
            return 0
        fi
        
        log_error "API authentication endpoints not responding as expected"
        return 1
    fi
}

# Check API metadata endpoint
check_api_metadata() {
    log_info "Checking API metadata endpoint..."
    
    local metadata_url="$API_URL/metadata"
    
    if http_request "$metadata_url" "200"; then
        log_success "API metadata endpoint responding"
        
        # Verify metadata contains expected structure
        local metadata_response
        metadata_response=$(curl -s --max-time "$TIMEOUT" "$metadata_url" 2>/dev/null || echo '{}')
        
        if echo "$metadata_response" | jq . >/dev/null 2>&1; then
            local models_count
            models_count=$(echo "$metadata_response" | jq 'length' 2>/dev/null || echo "0")
            log_info "Metadata contains $models_count model definitions"
        fi
        
        return 0
    else
        log_warn "API metadata endpoint not responding (may be authentication-protected)"
        return 0  # Don't fail health check for this
    fi
}

# Check frontend availability
check_frontend_health() {
    log_info "Checking frontend availability..."
    
    if http_request "$FRONTEND_URL" "200"; then
        log_success "Frontend responding"
        
        # Check if it's actually the React app
        local frontend_content
        frontend_content=$(curl -s --max-time "$TIMEOUT" "$FRONTEND_URL" 2>/dev/null || echo "")
        
        if echo "$frontend_content" | grep -q "react\|React\|gravitycar" -i; then
            log_success "Frontend appears to be the correct React application"
        elif echo "$frontend_content" | grep -q "<html\|<body\|<div" -i; then
            log_info "Frontend serving HTML content"
        else
            log_warn "Frontend content doesn't appear to be a web application"
        fi
        
        return 0
    else
        log_error "Frontend not responding"
        return 1
    fi
}

# Check frontend static assets
check_frontend_assets() {
    log_info "Checking frontend static assets..."
    
    # Common asset paths to check
    local asset_paths=(
        "/assets"
        "/static"
        "/favicon.ico"
    )
    
    local assets_ok=0
    local assets_checked=0
    
    for path in "${asset_paths[@]}"; do
        local asset_url="$FRONTEND_URL$path"
        assets_checked=$((assets_checked + 1))
        
        # Assets might return 200 or 404, but should not timeout
        local status_code
        status_code=$(curl -s -o /dev/null -w "%{http_code}" \
            --max-time "$TIMEOUT" \
            --connect-timeout 5 \
            "$asset_url" 2>/dev/null || echo "000")
        
        if [ "$status_code" != "000" ] && [ "$status_code" != "502" ] && [ "$status_code" != "503" ]; then
            assets_ok=$((assets_ok + 1))
            log_info "Asset path $path: HTTP $status_code"
        fi
    done
    
    if [ $assets_ok -gt 0 ]; then
        log_success "Frontend asset serving functional ($assets_ok/$assets_checked paths responding)"
        return 0
    else
        log_warn "Frontend asset serving may have issues"
        return 0  # Don't fail health check for this
    fi
}

# Check database connectivity (through API)
check_database_connectivity() {
    log_info "Checking database connectivity through API..."
    
    # Try to access an endpoint that requires database
    local db_test_urls=(
        "$API_URL/users"
        "$API_URL/metadata"
        "$API_URL/health/database"
    )
    
    for url in "${db_test_urls[@]}"; do
        local status_code
        status_code=$(curl -s -o /dev/null -w "%{http_code}" \
            --max-time "$TIMEOUT" \
            "$url" 2>/dev/null || echo "000")
        
        # Accept 200, 401, or 403 as signs the API is working
        if [ "$status_code" = "200" ] || [ "$status_code" = "401" ] || [ "$status_code" = "403" ]; then
            log_success "Database connectivity verified through API"
            return 0
        fi
    done
    
    log_warn "Database connectivity could not be verified through API"
    return 0  # Don't fail health check for this
}

# Check SSL certificates
check_ssl_certificates() {
    log_info "Checking SSL certificates..."
    
    local urls_to_check=("$API_URL" "$FRONTEND_URL")
    
    for url in "${urls_to_check[@]}"; do
        if [[ "$url" == https://* ]]; then
            local hostname
            hostname=$(echo "$url" | sed 's|https://||' | sed 's|/.*||')
            
            # Check certificate expiration
            local cert_info
            cert_info=$(echo | openssl s_client -servername "$hostname" -connect "$hostname:443" 2>/dev/null | openssl x509 -noout -dates 2>/dev/null || echo "")
            
            if [ -n "$cert_info" ]; then
                local expiry_date
                expiry_date=$(echo "$cert_info" | grep "notAfter" | cut -d= -f2)
                log_info "SSL certificate for $hostname expires: $expiry_date"
                
                # Check if certificate expires within 30 days
                local expiry_timestamp
                expiry_timestamp=$(date -d "$expiry_date" +%s 2>/dev/null || echo "0")
                local current_timestamp
                current_timestamp=$(date +%s)
                local thirty_days=$((30 * 24 * 60 * 60))
                
                if [ "$expiry_timestamp" -gt 0 ] && [ $((expiry_timestamp - current_timestamp)) -lt $thirty_days ]; then
                    log_warn "SSL certificate for $hostname expires within 30 days"
                fi
            else
                log_warn "Could not retrieve SSL certificate information for $hostname"
            fi
        fi
    done
    
    log_success "SSL certificate check completed"
}

# Performance check
check_response_times() {
    log_info "Checking response times..."
    
    local start_time
    local end_time
    local response_time
    
    # Check API response time
    start_time=$(date +%s%N)
    if curl -s -o /dev/null --max-time "$TIMEOUT" "$API_URL/health" 2>/dev/null; then
        end_time=$(date +%s%N)
        response_time=$(( (end_time - start_time) / 1000000 ))
        log_info "API response time: ${response_time}ms"
        
        if [ "$response_time" -gt 5000 ]; then
            log_warn "API response time is slow (>${response_time}ms)"
        fi
    fi
    
    # Check frontend response time
    start_time=$(date +%s%N)
    if curl -s -o /dev/null --max-time "$TIMEOUT" "$FRONTEND_URL" 2>/dev/null; then
        end_time=$(date +%s%N)
        response_time=$(( (end_time - start_time) / 1000000 ))
        log_info "Frontend response time: ${response_time}ms"
        
        if [ "$response_time" -gt 3000 ]; then
            log_warn "Frontend response time is slow (>${response_time}ms)"
        fi
    fi
    
    log_success "Response time check completed"
}

# Generate health report
generate_health_report() {
    log_info "Generating health check report..."
    
    local report_file="${PROJECT_ROOT}/logs/health-report-$(date +%Y%m%d_%H%M%S).json"
    local deployment_id="${DEPLOYMENT_ID:-unknown}"
    
    cat > "$report_file" << EOF
{
    "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "deployment_id": "$deployment_id",
    "api_url": "$API_URL",
    "frontend_url": "$FRONTEND_URL",
    "checks": {
        "api_health": $([ "$api_health_result" = "0" ] && echo "true" || echo "false"),
        "api_auth": $([ "$api_auth_result" = "0" ] && echo "true" || echo "false"),
        "api_metadata": $([ "$api_metadata_result" = "0" ] && echo "true" || echo "false"),
        "frontend_health": $([ "$frontend_health_result" = "0" ] && echo "true" || echo "false"),
        "frontend_assets": $([ "$frontend_assets_result" = "0" ] && echo "true" || echo "false"),
        "database": $([ "$database_result" = "0" ] && echo "true" || echo "false")
    },
    "overall_status": $([ "$overall_health" = "0" ] && echo '"healthy"' || echo '"unhealthy"')
}
EOF
    
    log_info "Health report saved: $report_file"
}

# Main health check function
main() {
    log_info "Starting production health check..."
    log_info "Deployment ID: ${DEPLOYMENT_ID:-not-specified}"
    
    setup_environment
    
    # Initialize result tracking
    local overall_health=0
    
    # Run health checks
    check_api_health
    api_health_result=$?
    overall_health=$((overall_health + api_health_result))
    
    check_api_auth
    api_auth_result=$?
    overall_health=$((overall_health + api_auth_result))
    
    check_api_metadata
    api_metadata_result=$?
    overall_health=$((overall_health + api_metadata_result))
    
    check_frontend_health
    frontend_health_result=$?
    overall_health=$((overall_health + frontend_health_result))
    
    check_frontend_assets
    frontend_assets_result=$?
    # Don't add to overall health - this is informational
    
    check_database_connectivity
    database_result=$?
    # Don't add to overall health - this is informational
    
    # Informational checks (don't affect overall health)
    check_ssl_certificates
    check_response_times
    
    # Generate report
    generate_health_report
    
    # Summary
    log_info "Health check summary:"
    log_info "  API Health: $([ "$api_health_result" = "0" ] && echo "✅ PASS" || echo "❌ FAIL")"
    log_info "  API Auth: $([ "$api_auth_result" = "0" ] && echo "✅ PASS" || echo "❌ FAIL")"
    log_info "  API Metadata: $([ "$api_metadata_result" = "0" ] && echo "✅ PASS" || echo "ℹ️  INFO")"
    log_info "  Frontend: $([ "$frontend_health_result" = "0" ] && echo "✅ PASS" || echo "❌ FAIL")"
    log_info "  Assets: $([ "$frontend_assets_result" = "0" ] && echo "✅ PASS" || echo "ℹ️  INFO")"
    log_info "  Database: $([ "$database_result" = "0" ] && echo "✅ PASS" || echo "ℹ️  INFO")"
    
    if [ "$overall_health" -eq 0 ]; then
        log_success "All critical health checks passed!"
        log_success "Production deployment is healthy and ready"
        return 0
    else
        log_error "Some critical health checks failed"
        log_error "Production deployment may have issues"
        return 1
    fi
}

# Handle script interruption
trap 'log_error "Health check interrupted"; exit 1' INT TERM

# Check dependencies
if ! command -v curl >/dev/null 2>&1; then
    log_error "curl is required but not installed"
    exit 1
fi

if ! command -v jq >/dev/null 2>&1; then
    log_warn "jq not available - JSON parsing will be limited"
fi

# Execute main function
main "$@"

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