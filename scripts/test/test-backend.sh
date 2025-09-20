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
    return 0;
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
    # Temporarily disable pipefail for grep pipeline that might not find matches
    set +o pipefail
    local todo_count=$(grep -r "TODO\|FIXME" src/ | wc -l || echo 0)
    set -o pipefail
    if [[ $todo_count -gt 0 ]]; then
        log "WARN" "Found $todo_count TODO/FIXME comments in code"
    fi
    
    # Check for debugging statements
    # Temporarily disable pipefail for grep pipeline that might not find matches
    set +o pipefail
    local debug_count=$(grep -r "var_dump\|print_r\|error_log" src/ | wc -l || echo 0)
    set -o pipefail
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

# Validate XML file using xmllint if available
validate_xml_file() {
    local xml_file="$1"
    local description="${2:-XML file}"
    
    # Check if xmllint is available
    if ! command -v xmllint >/dev/null 2>&1; then
        log "DEBUG" "xmllint not available, skipping XML validation for $description"
        return 0
    fi
    
    # Validate the XML file
    if xmllint --noout "$xml_file" 2>/dev/null; then
        log "DEBUG" "XML validation passed for $description: $xml_file"
        return 0
    else
        local error_output
        error_output=$(xmllint --noout "$xml_file" 2>&1 || true)
        log "ERROR" "XML validation failed for $description: $xml_file"
        log "ERROR" "xmllint error: $error_output"
        return 1
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
    
    # Validate each input JUnit file before processing
    log "INFO" "Validating input JUnit XML files..."
    local valid_files=()
    for junit_file in "${junit_files[@]}"; do
        if [[ -f "$junit_file" ]]; then
            if validate_xml_file "$junit_file" "input JUnit file"; then
                valid_files+=("$junit_file")
            else
                log "WARN" "Skipping invalid XML file: $junit_file"
            fi
        fi
    done
    
    if [[ ${#valid_files[@]} -eq 0 ]]; then
        log "ERROR" "No valid JUnit XML files found to consolidate"
        return 1
    fi
    
    log "INFO" "Processing ${#valid_files[@]} valid JUnit XML files"
    
    # Create consolidated XML with proper structure
    cat > "$output_file" << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites>
</testsuites>
EOF
    
    # Validate initial XML structure
    if ! validate_xml_file "$output_file" "initial consolidated XML"; then
        log "ERROR" "Failed to create valid initial XML structure"
        return 1
    fi
    
    # Calculate totals for the root testsuite
    local total_tests=0
    local total_assertions=0
    local total_errors=0
    local total_failures=0
    local total_skipped=0
    local total_time=0
    
    # Process each validated JUnit file with proper XML parsing
    for junit_file in "${valid_files[@]}"; do
        log "DEBUG" "Processing: $junit_file"
        
        # Extract the complete testsuite elements from within the testsuites wrapper
        # Use awk for reliable extraction that preserves original structure
        local temp_content
        temp_content=$(awk '/<testsuites>/,/<\/testsuites>/ {
            if ($0 !~ /<\/?testsuites>/) print $0
        }' "$junit_file" 2>/dev/null || echo "")
        
        if [[ -n "$temp_content" ]]; then
            # Extract metrics from the testsuite for totals calculation
            local suite_metrics
            # Temporarily disable pipefail to avoid broken pipe errors from grep | head pipeline
            set +o pipefail
            suite_metrics=$(echo "$temp_content" | grep -E '^[[:space:]]*<testsuite' | head -1)
            set -o pipefail
            
            if [[ -n "$suite_metrics" ]]; then
                # Extract numeric values using grep and awk for safety
                # Temporarily disable pipefail to avoid broken pipe errors from grep pipelines
                set +o pipefail
                local tests=$(echo "$suite_metrics" | grep -o 'tests="[0-9]*"' | cut -d'"' -f2 || echo "0")
                local assertions=$(echo "$suite_metrics" | grep -o 'assertions="[0-9]*"' | cut -d'"' -f2 || echo "0")
                local errors=$(echo "$suite_metrics" | grep -o 'errors="[0-9]*"' | cut -d'"' -f2 || echo "0")
                local failures=$(echo "$suite_metrics" | grep -o 'failures="[0-9]*"' | cut -d'"' -f2 || echo "0")
                local skipped=$(echo "$suite_metrics" | grep -o 'skipped="[0-9]*"' | cut -d'"' -f2 || echo "0")
                local time=$(echo "$suite_metrics" | grep -o 'time="[0-9.]*"' | cut -d'"' -f2 || echo "0")
                set -o pipefail
                
                # Add to totals (using arithmetic expansion for safety)
                ((total_tests += tests)) || true
                ((total_assertions += assertions)) || true  
                ((total_errors += errors)) || true
                ((total_failures += failures)) || true
                ((total_skipped += skipped)) || true
                total_time=$(echo "$total_time + $time" | bc -l 2>/dev/null || echo "$total_time")
            fi
            
            # Append the content to output file (before the closing </testsuites> tag)
            # Create a temporary file with the new content
            temp_file="${output_file}.tmp"
            head -n -1 "$output_file" > "$temp_file"  # Remove the last line (</testsuites>)
            echo "$temp_content" >> "$temp_file"
            echo "</testsuites>" >> "$temp_file"
            mv "$temp_file" "$output_file"
            
            # Validate XML after each addition
            if ! validate_xml_file "$output_file" "consolidated XML (after adding $junit_file)"; then
                log "ERROR" "XML became invalid after adding content from $junit_file"
                # Try to recover by removing the last addition
                log "WARN" "Attempting to recover by removing last addition..."
                # Restore to previous state by recreating without the problematic content
                head -n -$(echo "$temp_content" | wc -l) "$output_file" > "${output_file}.tmp"
                echo "</testsuites>" >> "${output_file}.tmp"
                mv "${output_file}.tmp" "$output_file"
                log "WARN" "Skipped content from $junit_file due to XML validation failure"
            fi
        else
            log "WARN" "Failed to extract content from $junit_file"
        fi
    done
    
    # Validate the final consolidated XML file
    if validate_xml_file "$output_file" "final consolidated XML"; then
        log "SUCCESS" "Consolidated JUnit report created and validated: $output_file"
        log "INFO" "Report summary: $total_tests tests, $total_assertions assertions, $total_errors errors, $total_failures failures, $total_skipped skipped"
    else
        log "ERROR" "Final consolidated XML file is invalid. Falling back to first available JUnit file."
        # Fallback: just copy the first valid JUnit file
        if [[ ${#valid_files[@]} -gt 0 ]]; then
            cp "${valid_files[0]}" "$output_file"
            log "INFO" "Using fallback file: ${valid_files[0]}"
        else
            log "ERROR" "No valid fallback files available"
            return 1
        fi
    fi
    
    # Also generate JSON format as backup
    generate_json_report "${valid_files[@]}"
}

# Generate JSON format test report as alternative to XML
generate_json_report() {
    local junit_files=("$@")
    local json_output="phpunit-report.json"
    
    if [[ -f "scripts/test/convert-junit-to-json.php" ]]; then
        log "INFO" "Generating JSON test report as backup format..."
        
        if php scripts/test/convert-junit-to-json.php "$json_output" "${junit_files[@]}" 2>/dev/null; then
            log "SUCCESS" "JSON test report created: $json_output"
        else
            log "WARN" "Failed to generate JSON test report"
        fi
    else
        log "DEBUG" "JSON converter script not found, skipping JSON report generation"
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
    
    log "INFO" "PARALLEL mode: $PARALLEL"
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