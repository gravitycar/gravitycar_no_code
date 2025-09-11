#!/bin/bash

# AI Agent Apache Error Log Reader Script
# Purpose: Read specified number of lines from Apache error logs by index
# Arguments: [log_index] [number_of_lines]
# Default: log_index=0 (most recent), number_of_lines=20
# Handles both plain text and gzipped log files

LOG_INDEX=${1:-0}
LINES=${2:-20}
APACHE_LOG_DIR="/var/log/apache2"

# Check if running with appropriate permissions
if [ ! -r "$APACHE_LOG_DIR/error.log" ]; then
    echo "❌ Permission denied - Apache logs require sudo access"
    echo "💡 Tip: Run this script with sudo or ensure user is in 'adm' group"
    exit 1
fi

# Get sorted list of error log files (most recent first)
# Include both .log and .log.gz files, sorted by modification time
LOG_FILES=($(sudo find "$APACHE_LOG_DIR" -name "error.log*" -type f | xargs sudo ls -t 2>/dev/null))

# Check if any log files exist
if [ ${#LOG_FILES[@]} -eq 0 ]; then
    echo "❌ No Apache error log files found in $APACHE_LOG_DIR"
    echo "📁 Directory contents:"
    sudo ls -la "$APACHE_LOG_DIR" 2>/dev/null || echo "   Directory not accessible"
    exit 1
fi

# Validate log index
if [ $LOG_INDEX -ge ${#LOG_FILES[@]} ]; then
    echo "❌ Log file index $LOG_INDEX is out of range"
    echo "📊 Available Apache error log files (${#LOG_FILES[@]} total):"
    for i in "${!LOG_FILES[@]}"; do
        FILE_INFO=$(sudo ls -lh "${LOG_FILES[$i]}" 2>/dev/null | awk '{print $5, $6, $7, $8}')
        if [[ "${LOG_FILES[$i]}" == *.gz ]]; then
            echo "   Index $i: $(basename "${LOG_FILES[$i]}") [$FILE_INFO] (gzipped)"
        else
            echo "   Index $i: $(basename "${LOG_FILES[$i]}") [$FILE_INFO] (current)"
        fi
    done
    exit 1
fi

# Select the requested log file
SELECTED_FILE=${LOG_FILES[$LOG_INDEX]}
FILE_NAME=$(basename "$SELECTED_FILE")

echo "🚨 Reading $LINES lines from Apache error log: $FILE_NAME"
FILE_INFO=$(sudo ls -lh "$SELECTED_FILE" 2>/dev/null | awk '{print $5, $6, $7, $8}')
echo "📅 File info: $FILE_INFO"
echo "🔢 Log file index: $LOG_INDEX of $((${#LOG_FILES[@]} - 1))"

# Check if file is gzipped
if [[ "$SELECTED_FILE" == *.gz ]]; then
    echo "📦 File is gzipped - decompressing for reading"
fi

echo "=========================================="

# Read the specified number of lines - handle both regular and gzipped files
if [ -f "$SELECTED_FILE" ]; then
    if [[ "$SELECTED_FILE" == *.gz ]]; then
        # For gzipped files, use zcat and tail
        sudo zcat "$SELECTED_FILE" | tail -n "$LINES"
    else
        # For regular files, use tail directly
        sudo tail -n "$LINES" "$SELECTED_FILE"
    fi
else
    echo "❌ Error: File $SELECTED_FILE not found or not readable"
    exit 1
fi
