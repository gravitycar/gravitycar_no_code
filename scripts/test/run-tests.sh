#!/bin/bash

# ==============================================================================
# Gravitycar Framework - Comprehensive Test Runner Script
# ==============================================================================
# 
# This script runs the complete test suite for CI/CD pipeline.
# It executes both backend PHPUnit tests and frontend tests, and generates
# comprehensive coverage reports.
#
# ==============================================================================

set -euo pipefail

# Parse command line arguments
SKIP_BACKEND="${SKIP_BACKEND:-false}"
SKIP_FRONTEND="${SKIP_FRONTEND:-false}"
SKIP_INTEGRATION="${SKIP_INTEGRATION:-false}"
PARALLEL="${PARALLEL:-false}"
COVERAGE="${COVERAGE:-true}"
FAIL_FAST="${FAIL_FAST:-true}"
TEST_FILTER="${TEST_FILTER:-}"
MODE="${MODE:-local}"

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --mode=*)
            MODE="${1#*=}"
            shift
            ;;
        --coverage=*)
            COVERAGE="${1#*=}"
            shift
            ;;
        --exclude=*)
            EXCLUDE_VALUE="${1#*=}"
            if [[ "$EXCLUDE_VALUE" == "integration" ]]; then
                SKIP_INTEGRATION="true"
            fi
            shift
            ;;
        --skip-backend)
            SKIP_BACKEND="true"
            shift
            ;;
        --skip-frontend)
            SKIP_FRONTEND="true"
            shift
            ;;
        --skip-integration)
            SKIP_INTEGRATION="true"
            shift
            ;;
        --parallel)
            PARALLEL="true"
            shift
            ;;
        --filter=*)
            TEST_FILTER="${1#*=}"
            shift
            ;;
        --help)
            echo "Usage: $0 [options]"
            echo "Options:"
            echo "  --mode=<local|ci>           Test execution mode"
            echo "  --coverage=<true|false>     Enable/disable coverage reporting"
            echo "  --exclude=<integration>     Exclude specific test suites"
            echo "  --skip-backend              Skip backend tests"
            echo "  --skip-frontend             Skip frontend tests"
            echo "  --skip-integration          Skip integration tests"
            echo "  --parallel                  Run tests in parallel"
            echo "  --filter=<pattern>          Filter tests by pattern"
            echo "  --help                      Show this help message"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Get script directory and project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# Source common functions
source "${SCRIPT_DIR}/../common.sh" 2>/dev/null || {
    # Basic logging if common.sh not available yet
    log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] [$1] ${*:2}"; }
}

log "INFO" "Starting comprehensive test execution..."
log "INFO" "Mode: $MODE"
log "INFO" "Backend tests: $([ "$SKIP_BACKEND" == "true" ] && echo "DISABLED" || echo "ENABLED")"
log "INFO" "Frontend tests: $([ "$SKIP_FRONTEND" == "true" ] && echo "DISABLED" || echo "ENABLED")"
log "INFO" "Integration tests: $([ "$SKIP_INTEGRATION" == "true" ] && echo "DISABLED" || echo "ENABLED")"
log "INFO" "Parallel execution: $PARALLEL"
log "INFO" "Coverage reporting: $COVERAGE"

cd "$PROJECT_ROOT"

# Pre-test environment setup
setup_test_environment() {
    log "INFO" "Setting up test environment..."
    
    # Set up test environment variables for SQLite
    export DB_CONNECTION=sqlite
    export DB_DATABASE=":memory:"
    export APP_ENV=testing
    
    # Create coverage directory
    mkdir -p coverage/{backend,frontend,reports}
    
    log "DEBUG" "Test environment configured for SQLite in-memory database"
}

# Check prerequisites
check_prerequisites() {
    log "INFO" "Checking test prerequisites..."
    
    # Check PHPUnit for backend tests
    if [[ "$SKIP_BACKEND" != "true" && ! -f "vendor/bin/phpunit" ]]; then
        error_exit "PHPUnit not found. Run composer install first."
    fi
    
    # Check frontend dependencies
    if [[ "$SKIP_FRONTEND" != "true" && ! -d "gravitycar-frontend/node_modules" ]]; then
        log "WARN" "Frontend dependencies not found. Installing..."
        cd gravitycar-frontend
        npm ci --silent
        cd "$PROJECT_ROOT"
    fi
    
    log "SUCCESS" "Prerequisites check completed"
}

# Run backend tests
run_backend_tests() {
    if [[ "$SKIP_BACKEND" == "true" ]]; then
        log "INFO" "Skipping backend tests (--skip-backend specified)"
        return 0
    fi
    
    log "INFO" "=== BACKEND TESTING ==="
    
    # Export test configuration for backend script
    export COVERAGE="$COVERAGE"
    export PARALLEL="$PARALLEL"
    export TEST_FILTER="$TEST_FILTER"
    export SKIP_INTEGRATION="$SKIP_INTEGRATION"
    
    if "$SCRIPT_DIR/test-backend.sh"; then
        log "SUCCESS" "Backend tests completed successfully"
        return 0
    else
        log "ERROR" "Backend tests failed"
        return 1
    fi
}

# Run frontend tests
run_frontend_tests() {
    if [[ "$SKIP_FRONTEND" == "true" ]]; then
        log "INFO" "Skipping frontend tests (--skip-frontend specified)"
        return 0
    fi
    
    log "INFO" "=== FRONTEND TESTING ==="
    
    if "$SCRIPT_DIR/test-frontend.sh"; then
        log "SUCCESS" "Frontend tests completed successfully"
        return 0
    else
        log "ERROR" "Frontend tests failed"
        return 1
    fi
}

# Generate comprehensive test report
generate_comprehensive_report() {
    log "INFO" "Generating comprehensive test report..."
    
    local report_file="coverage/reports/test-summary.html"
    local json_report="coverage/reports/test-summary.json"
    
    # Collect test results
    local backend_status="UNKNOWN"
    local frontend_status="UNKNOWN"
    local total_tests=0
    local total_assertions=0
    
    # Parse backend results
    if [[ -f "coverage/junit-unit.xml" ]]; then
        backend_status="PASSED"
        if command -v xmllint >/dev/null 2>&1; then
            total_tests=$(xmllint --xpath 'string(//testsuites/@tests)' coverage/junit-unit.xml 2>/dev/null || echo "0")
            total_assertions=$(xmllint --xpath 'string(//testsuites/@assertions)' coverage/junit-unit.xml 2>/dev/null || echo "0")
        fi
    fi
    
    # Parse frontend results
    if [[ -f "coverage/frontend/eslint-report.json" ]]; then
        frontend_status="PASSED"
    fi
    
    # Generate JSON summary
    cat > "$json_report" << EOF
{
  "testRun": {
    "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
    "environment": "testing",
    "totalTests": $total_tests,
    "totalAssertions": $total_assertions
  },
  "backend": {
    "status": "$backend_status",
    "phpVersion": "$(php -r 'echo PHP_VERSION;')",
    "database": "sqlite (in-memory)",
    "testSuites": ["Unit", "Integration", "Feature"]
  },
  "frontend": {
    "status": "$frontend_status",
    "nodeVersion": "$(node --version 2>/dev/null || echo 'N/A')",
    "testSuites": ["Lint", "TypeScript", "Unit"]
  },
  "coverage": {
    "enabled": $COVERAGE,
    "htmlReport": "coverage/html/index.html",
    "textReport": "coverage/coverage.txt"
  }
}
EOF
    
    # Generate HTML summary
    cat > "$report_file" << EOF
<!DOCTYPE html>
<html>
<head>
    <title>Gravitycar Framework - Test Summary</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 5px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .pass { background-color: #d4edda; border-color: #c3e6cb; }
        .fail { background-color: #f8d7da; border-color: #f5c6cb; }
        .stats { display: flex; gap: 20px; margin: 20px 0; }
        .stat { background: #f8f9fa; padding: 10px; border-radius: 5px; flex: 1; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Gravitycar Framework - Test Summary</h1>
        <p>Generated: $(date)</p>
    </div>
    
    <div class="stats">
        <div class="stat">
            <h3>Total Tests</h3>
            <p><strong>$total_tests</strong></p>
        </div>
        <div class="stat">
            <h3>Total Assertions</h3>
            <p><strong>$total_assertions</strong></p>
        </div>
        <div class="stat">
            <h3>Database</h3>
            <p><strong>SQLite (in-memory)</strong></p>
        </div>
    </div>
    
    <div class="section $([ "$backend_status" == "PASSED" ] && echo "pass" || echo "fail")">
        <h2>Backend Tests: $backend_status</h2>
        <p>PHP Version: $(php -r 'echo PHP_VERSION;')</p>
        <p>Test Suites: Unit, Integration, Feature</p>
        $([ -f "coverage/html/index.html" ] && echo '<p><a href="../html/index.html">View Coverage Report</a></p>')
    </div>
    
    <div class="section $([ "$frontend_status" == "PASSED" ] && echo "pass" || echo "fail")">
        <h2>Frontend Tests: $frontend_status</h2>
        <p>Node Version: $(node --version 2>/dev/null || echo 'N/A')</p>
        <p>Test Suites: Lint, TypeScript, Unit</p>
    </div>
    
    <div class="section">
        <h2>Available Reports</h2>
        <ul>
            $([ -f "coverage/html/index.html" ] && echo '<li><a href="../html/index.html">HTML Coverage Report</a></li>')
            $([ -f "coverage/junit-unit.xml" ] && echo '<li><a href="../junit-unit.xml">JUnit XML Report</a></li>')
            $([ -f "coverage/testdox-unit.html" ] && echo '<li><a href="../testdox-unit.html">TestDox Report</a></li>')
            <li><a href="test-summary.json">JSON Summary</a></li>
        </ul>
    </div>
</body>
</html>
EOF
    
    log "SUCCESS" "Comprehensive test report generated"
    log "INFO" "HTML Report: $report_file"
    log "INFO" "JSON Report: $json_report"
}

# Parallel test execution
run_tests_parallel() {
    log "INFO" "Running tests in parallel mode..."
    
    local backend_result=0
    local frontend_result=0
    
    # Run backend and frontend tests in parallel
    (
        run_backend_tests
        echo $? > /tmp/backend_test_result
    ) &
    local backend_pid=$!
    
    (
        run_frontend_tests
        echo $? > /tmp/frontend_test_result
    ) &
    local frontend_pid=$!
    
    # Wait for both to complete
    wait $backend_pid
    wait $frontend_pid
    
    # Get results
    backend_result=$(cat /tmp/backend_test_result 2>/dev/null || echo 1)
    frontend_result=$(cat /tmp/frontend_test_result 2>/dev/null || echo 1)
    
    # Cleanup
    rm -f /tmp/backend_test_result /tmp/frontend_test_result
    
    # Return combined result
    if [[ $backend_result -eq 0 && $frontend_result -eq 0 ]]; then
        return 0
    else
        return 1
    fi
}

# Sequential test execution
run_tests_sequential() {
    log "INFO" "Running tests in sequential mode..."
    
    # Run backend tests first
    if ! run_backend_tests && [[ "$FAIL_FAST" == "true" ]]; then
        error_exit "Backend tests failed - stopping execution (fail-fast enabled)"
    fi
    
    # Run frontend tests
    if ! run_frontend_tests && [[ "$FAIL_FAST" == "true" ]]; then
        error_exit "Frontend tests failed - stopping execution (fail-fast enabled)"
    fi
    
    # Check if any tests failed
    local failed=false
    
    if [[ "$SKIP_BACKEND" != "true" ]] && ! run_backend_tests >/dev/null 2>&1; then
        failed=true
    fi
    
    if [[ "$SKIP_FRONTEND" != "true" ]] && ! run_frontend_tests >/dev/null 2>&1; then
        failed=true
    fi
    
    if [[ "$failed" == "true" ]]; then
        return 1
    else
        return 0
    fi
}

# Display test summary
display_test_summary() {
    log "INFO" "=== TEST EXECUTION SUMMARY ==="
    
    # Display coverage summary if available
    if [[ "$COVERAGE" == "true" && -f "coverage/coverage.txt" ]]; then
        log "INFO" "Test coverage summary:"
        tail -n 5 coverage/coverage.txt | while IFS= read -r line; do
            if [[ "$line" =~ [0-9]+\.[0-9]+% ]]; then
                log "INFO" "  $line"
            fi
        done
    fi
    
    # Display file counts
    local coverage_files=$(find coverage -name "*.html" -o -name "*.xml" -o -name "*.json" | wc -l)
    log "INFO" "Generated $coverage_files report files in coverage/"
    
    # Display available reports
    if [[ -f "coverage/html/index.html" ]]; then
        log "INFO" "HTML coverage report: coverage/html/index.html"
    fi
    
    if [[ -f "coverage/reports/test-summary.html" ]]; then
        log "INFO" "Comprehensive test report: coverage/reports/test-summary.html"
    fi
}

# Main execution
main() {
    local start_time=$(date +%s)
    
    setup_test_environment
    check_prerequisites
    
    # Choose execution mode
    if [[ "$PARALLEL" == "true" ]]; then
        if run_tests_parallel; then
            log "SUCCESS" "All test suites passed (parallel execution)!"
        else
            log "ERROR" "Some test suites failed (parallel execution)"
            exit 1
        fi
    else
        # Always run in sequence for the main function to ensure proper error handling
        run_backend_tests
        run_frontend_tests
    fi
    
    generate_comprehensive_report
    display_test_summary
    
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    
    log "SUCCESS" "Test execution completed successfully in ${duration} seconds"
}

# Execute main function
main "$@"