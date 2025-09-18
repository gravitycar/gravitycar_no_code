#!/bin/bash

# ==============================================================================
# Gravitycar Framework - Backend Testing Script
# ==============================================================================
# 
# This script runs backend-specific tests including PHPUnit test suites,
# code quality checks, and validation tests.
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

# Test configuration
SKIP_UNIT="${SKIP_UNIT:-false}"
SKIP_INTEGRATION="${SKIP_INTEGRATION:-false}"
SKIP_FEATURE="${SKIP_FEATURE:-false}"
COVERAGE="${COVERAGE:-true}"
PARALLEL="${PARALLEL:-false}"
TEST_FILTER="${TEST_FILTER:-}"

log "INFO" "Starting backend testing process..."

cd "$PROJECT_ROOT"

# Check if PHPUnit is available
if [[ ! -f "vendor/bin/phpunit" ]]; then
    error_exit "PHPUnit not found. Run composer install first."
fi

# Ensure PHPUnit has execute permissions (important for CI environments)
if [[ ! -x "vendor/bin/phpunit" ]]; then
    log "INFO" "Setting execute permissions on PHPUnit"
    chmod +x vendor/bin/phpunit
fi

# Set up test environment variables for SQLite
setup_test_environment() {
    log "INFO" "Setting up test environment..."
    
    export DB_CONNECTION=sqlite
    export DB_DATABASE=":memory:"
    export APP_ENV=testing
    
    # Ensure coverage directory exists
    mkdir -p coverage
    
    log "DEBUG" "Test environment configured for SQLite in-memory database"
    
    # Create coverage directory
    mkdir -p coverage
}

# Run PHPUnit test suite with specific configuration
run_phpunit_suite() {
    local suite_name="$1"
    local options="${2:-}"
    
    log "INFO" "Running $suite_name test suite..."
    
    local phpunit_cmd="vendor/bin/phpunit"
    local phpunit_args="--testsuite=$suite_name"
    
    # Add filter if specified
    if [[ -n "$TEST_FILTER" ]]; then
        phpunit_args="$phpunit_args --filter=$TEST_FILTER"
    fi
    
    # Add coverage options for Unit tests only (to avoid conflicts)
    if [[ "$suite_name" == "Unit" && "$COVERAGE" == "true" ]]; then
        phpunit_args="$phpunit_args --coverage-html=coverage/html --coverage-text=coverage/coverage.txt --coverage-xml=coverage/xml"
    fi
    
    # Add JUnit logging
    phpunit_args="$phpunit_args --log-junit=coverage/junit-${suite_name,,}.xml"
    
    # Add TestDox output
    phpunit_args="$phpunit_args --testdox-html=coverage/testdox-${suite_name,,}.html"
    
    # Add any additional options
    if [[ -n "$options" ]]; then
        phpunit_args="$phpunit_args $options"
    fi
    
    # Execute PHPUnit
    local exit_code=0
    $phpunit_cmd $phpunit_args || exit_code=$?
    
    # Check if tests actually passed despite warnings
    if [[ $exit_code -ne 0 ]]; then
        # Check if it's just warnings/skips by looking at the output
        local last_output_file="coverage/junit-${suite_name,,}.xml"
        if [[ -f "$last_output_file" ]] && grep -q 'failures="0"' "$last_output_file" && grep -q 'errors="0"' "$last_output_file"; then
            log "SUCCESS" "$suite_name tests passed (ignoring warnings/skips)"
            return 0
        else
            log "ERROR" "$suite_name tests failed"
            return $exit_code
        fi
    else
        log "SUCCESS" "$suite_name tests passed"
        return 0
    fi
}

# Run unit tests
run_unit_tests() {
    if [[ "$SKIP_UNIT" == "true" ]]; then
        log "INFO" "Skipping unit tests (--skip-unit specified)"
        return 0
    fi
    
    run_phpunit_suite "Unit"
}

# Run integration tests
run_integration_tests() {
    if [[ "$SKIP_INTEGRATION" == "true" ]]; then
        log "INFO" "Skipping integration tests (--skip-integration specified)"
        return 0
    fi
    
    run_phpunit_suite "Integration"
}

# Run feature tests
run_feature_tests() {
    if [[ "$SKIP_FEATURE" == "true" ]]; then
        log "INFO" "Skipping feature tests (--skip-feature specified)"
        return 0
    fi
    
    run_phpunit_suite "Feature"
}

# Run code quality checks
run_code_quality_checks() {
    log "INFO" "Running code quality checks..."
    
    # PHP syntax check
    log "DEBUG" "Checking PHP syntax..."
    local syntax_errors=0
    while IFS= read -r -d '' file; do
        if ! php -l "$file" >/dev/null 2>&1; then
            log "ERROR" "Syntax error in: $file"
            ((syntax_errors++))
        fi
    done < <(find src -name "*.php" -print0)
    
    if [[ $syntax_errors -gt 0 ]]; then
        log "ERROR" "Found $syntax_errors PHP syntax errors"
        return 1
    fi
    
    log "SUCCESS" "PHP syntax check passed"
    
    # Check for basic code quality issues
    log "DEBUG" "Checking for code quality issues..."
    
    # Check for TODO/FIXME comments
    local todo_count=$(grep -r "TODO\|FIXME" src/ | wc -l || echo 0)
    if [[ $todo_count -gt 0 ]]; then
        log "WARN" "Found $todo_count TODO/FIXME comments in code"
    fi
    
    # Check for debugging statements
    local debug_count=$(grep -r "var_dump\|print_r\|error_log" src/ | wc -l || echo 0)
    if [[ $debug_count -gt 0 ]]; then
        log "WARN" "Found $debug_count potential debugging statements"
    fi
    
    log "SUCCESS" "Code quality checks completed"
}

# Generate test coverage report summary
generate_coverage_summary() {
    if [[ "$COVERAGE" != "true" || ! -f "coverage/coverage.txt" ]]; then
        return 0
    fi
    
    log "INFO" "Test coverage summary:"
    
    # Extract key metrics from coverage report
    local coverage_file="coverage/coverage.txt"
    
    if [[ -f "$coverage_file" ]]; then
        # Show last few lines which typically contain the summary
        tail -n 10 "$coverage_file" | while IFS= read -r line; do
            if [[ "$line" =~ [0-9]+\.[0-9]+% ]]; then
                log "INFO" "  $line"
            fi
        done
    fi
    
    # Check if HTML coverage report was generated
    if [[ -d "coverage/html" ]]; then
        log "INFO" "HTML coverage report available at: coverage/html/index.html"
    fi
}

# Consolidate JUnit XML reports into single file for CI
consolidate_junit_reports() {
    log "INFO" "Consolidating JUnit XML reports..."
    
    local junit_files=(coverage/junit-*.xml)
    local output_file="phpunit-report.xml"
    
    # Check if any JUnit files exist
    if [[ ! -f "${junit_files[0]}" ]]; then
        log "WARN" "No JUnit XML files found to consolidate"
        return 0
    fi
    
    # Initialize counters for aggregated totals
    local total_tests=0
    local total_failures=0
    local total_errors=0
    local total_skipped=0
    local total_time=0
    
    # Create consolidated XML header
    cat > "$output_file" << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
EOF
    
    # Process each JUnit file
    for junit_file in "${junit_files[@]}"; do
        if [[ -f "$junit_file" ]]; then
            log "DEBUG" "Processing: $junit_file"
            
            # Extract testsuite elements (not the wrapper testsuites)
            # Use xmllint if available for proper XML parsing, otherwise fallback to sed
            if command -v xmllint >/dev/null 2>&1; then
                # Proper XML extraction using xmllint
                xmllint --xpath "//testsuite" "$junit_file" 2>/dev/null >> "$output_file" || {
                    # Fallback if xpath fails - extract between testsuite tags
                    sed -n '/<testsuite/,/<\/testsuite>/p' "$junit_file" >> "$output_file"
                }
            else
                # Fallback: Extract testsuite elements carefully
                awk '/<testsuite[[:space:]]/{flag=1} flag{print} /<\/testsuite>/{flag=0}' "$junit_file" >> "$output_file"
            fi
        fi
    done
    
    # Close the consolidated XML
    echo "</testsuites>" >> "$output_file"
    
    # Validate the generated XML
    if command -v xmllint >/dev/null 2>&1; then
        if xmllint --noout "$output_file" 2>/dev/null; then
            log "SUCCESS" "Consolidated JUnit report created: $output_file"
        else
            log "ERROR" "Generated XML is invalid, falling back to simple concatenation"
            # Fallback: create simple valid XML
            cat > "$output_file" << EOF
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
  <testsuite name="consolidated" tests="0" failures="0" errors="0" skipped="0" time="0">
    <!-- Consolidated test results - see individual junit-*.xml files for details -->
  </testsuite>
</testsuites>
EOF
        fi
    else
        log "SUCCESS" "Consolidated JUnit report created: $output_file (xmllint not available for validation)"
    fi
}

# Validate test database functionality
validate_test_database() {
    log "INFO" "Validating test database functionality..."
    
    # Test SQLite in-memory database
    php -r "
        try {
            \$pdo = new PDO('sqlite::memory:');
            \$pdo->exec('CREATE TABLE test (id INTEGER PRIMARY KEY, name TEXT)');
            \$pdo->exec('INSERT INTO test (name) VALUES (\"test\")');
            \$result = \$pdo->query('SELECT COUNT(*) FROM test')->fetchColumn();
            if (\$result == 1) {
                echo 'SQLite in-memory database test: PASSED\n';
            } else {
                echo 'SQLite in-memory database test: FAILED\n';
                exit(1);
            }
        } catch (Exception \$e) {
            echo 'SQLite test failed: ' . \$e->getMessage() . '\n';
            exit(1);
        }
    " || {
        error_exit "Test database validation failed"
    }
    
    log "SUCCESS" "Test database validation passed"
}

# Parallel test execution (experimental)
run_parallel_tests() {
    if [[ "$PARALLEL" != "true" ]]; then
        return 0
    fi
    
    log "INFO" "Running tests in parallel mode..."
    
    # Run different test suites in parallel
    (
        run_unit_tests
        echo $? > /tmp/unit_result
    ) &
    
    (
        run_integration_tests
        echo $? > /tmp/integration_result
    ) &
    
    (
        run_feature_tests
        echo $? > /tmp/feature_result
    ) &
    
    # Wait for all parallel jobs to complete
    wait
    
    # Check results
    local unit_result=$(cat /tmp/unit_result 2>/dev/null || echo 1)
    local integration_result=$(cat /tmp/integration_result 2>/dev/null || echo 1)
    local feature_result=$(cat /tmp/feature_result 2>/dev/null || echo 1)
    
    # Cleanup temp files
    rm -f /tmp/unit_result /tmp/integration_result /tmp/feature_result
    
    if [[ $unit_result -eq 0 && $integration_result -eq 0 && $feature_result -eq 0 ]]; then
        log "SUCCESS" "All parallel tests passed"
        return 0
    else
        log "ERROR" "Some parallel tests failed"
        return 1
    fi
}

# Main execution
main() {
    setup_test_environment
    validate_test_database
    run_code_quality_checks
    
    # Choose execution mode
    if [[ "$PARALLEL" == "true" ]]; then
        run_parallel_tests
    else
        # Sequential execution
        local test_results=()
        
        run_unit_tests && test_results+=("UNIT:PASS") || test_results+=("UNIT:FAIL")
        run_integration_tests && test_results+=("INTEGRATION:PASS") || test_results+=("INTEGRATION:FAIL")
        run_feature_tests && test_results+=("FEATURE:PASS") || test_results+=("FEATURE:FAIL")
        
        # Check overall results
        local failed_tests=0
        for result in "${test_results[@]}"; do
            if [[ "$result" == *":FAIL" ]]; then
                ((failed_tests++))
            fi
        done
        
        # Summary
        log "INFO" "Backend testing summary:"
        for result in "${test_results[@]}"; do
            log "INFO" "  $result"
        done
        
        if [[ $failed_tests -eq 0 ]]; then
            log "SUCCESS" "All backend tests passed!"
        else
            log "ERROR" "$failed_tests backend test suite(s) failed"
            return 1
        fi
    fi
    
    generate_coverage_summary
    consolidate_junit_reports
    
    log "SUCCESS" "Backend testing completed successfully"
}

# Execute main function
main "$@"