#!/bin/bash

# ==============================================================================
# Gravitycar Framework - Common Functions Library
# ==============================================================================
# 
# This file contains shared functions used by all CI/CD scripts.
# Source this file in other scripts to access common functionality.
#
# ==============================================================================

# Color codes for output
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly NC='\033[0m' # No Color

# Logging function
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
        "DEBUG") [[ "${VERBOSE:-false}" == "true" ]] && echo -e "[DEBUG] ${message}" ;;
    esac
    
    # Log to file if LOG_FILE is set
    if [[ -n "${LOG_FILE:-}" ]]; then
        echo "[$timestamp] [$level] $message" >> "$LOG_FILE"
    fi
}

# Error exit function
error_exit() {
    log "ERROR" "$1"
    exit 1
}

# Check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Retry function for unreliable operations
retry() {
    local retries=$1
    shift
    local command=("$@")
    
    for ((i=1; i<=retries; i++)); do
        if "${command[@]}"; then
            return 0
        else
            if [[ $i -lt $retries ]]; then
                log "WARN" "Command failed, retrying ($i/$retries)..."
                sleep 2
            fi
        fi
    done
    
    log "ERROR" "Command failed after $retries attempts"
    return 1
}