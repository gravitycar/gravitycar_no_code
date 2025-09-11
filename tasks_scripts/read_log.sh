#!/bin/bash

# AI Agent Log Reader Script
# Purpose: Read specified number of lines from log files by index
# Arguments: [log_index] [number_of_lines]
# Default: log_index=0 (most recent), number_of_lines=20

LOG_INDEX=${1:-0}
LINES=${2:-20}

# Get sorted list of log files (most recent first)
LOG_FILES=($(ls -t logs/*.log 2>/dev/null | head -10))

# Check if any log files exist
if [ ${#LOG_FILES[@]} -eq 0 ]; then
    echo "❌ No log files found in logs/ directory"
    echo "📁 Available files:"
    ls -la logs/ 2>/dev/null || echo "   Directory not accessible"
    exit 1
fi

# Validate log index
if [ $LOG_INDEX -ge ${#LOG_FILES[@]} ]; then
    echo "❌ Log file index $LOG_INDEX is out of range"
    echo "📊 Available log files (${#LOG_FILES[@]} total):"
    for i in "${!LOG_FILES[@]}"; do
        echo "   Index $i: ${LOG_FILES[$i]}"
    done
    exit 1
fi

# Select the requested log file
SELECTED_FILE=${LOG_FILES[$LOG_INDEX]}

echo "📄 Reading $LINES lines from: $SELECTED_FILE"
echo "📅 File info: $(ls -lh "$SELECTED_FILE" | awk '{print $5, $6, $7, $8}')"
echo "🔢 Log file index: $LOG_INDEX of $((${#LOG_FILES[@]} - 1))"
echo "=========================================="

# Read the specified number of lines
if [ -f "$SELECTED_FILE" ]; then
    tail -n "$LINES" "$SELECTED_FILE"
else
    echo "❌ Error: File $SELECTED_FILE not found or not readable"
    exit 1
fi
