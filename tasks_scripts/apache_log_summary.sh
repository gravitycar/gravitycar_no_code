#!/bin/bash

# AI Agent Apache Error Log Summary Script
# Purpose: Provide a quick summary of Apache error logs

APACHE_LOG_DIR="/var/log/apache2"

# Check if running with appropriate permissions
if [ ! -r "$APACHE_LOG_DIR/error.log" ]; then
    echo "❌ Permission denied - Apache logs require sudo access"
    echo "💡 Tip: Run this script with sudo or ensure user is in 'adm' group"
    exit 1
fi

echo "🚨 Apache Error Log Summary"
echo "============================"

# Current log size and info
echo "📊 Current log status:"
if [ -f "$APACHE_LOG_DIR/error.log" ]; then
    LOG_INFO=$(sudo ls -lh "$APACHE_LOG_DIR/error.log" | awk '{print $5 " (" $6 " " $7 " " $8 ")"}')
    echo "   Size: $LOG_INFO"
    LINE_COUNT=$(sudo wc -l < "$APACHE_LOG_DIR/error.log" 2>/dev/null)
    echo "   Lines: $LINE_COUNT"
else
    echo "   Current log not found"
fi

echo

# Recent error counts by type
echo "📈 Recent error patterns (last 100 lines):"
if [ -f "$APACHE_LOG_DIR/error.log" ]; then
    ERROR_TYPES=$(sudo tail -n 100 "$APACHE_LOG_DIR/error.log" 2>/dev/null | grep -o '\[.*:.*\]' | sort | uniq -c | sort -nr | head -5)
    if [ -n "$ERROR_TYPES" ]; then
        echo "$ERROR_TYPES" | sed 's/^/   /'
    else
        echo "   No error patterns found in recent entries"
    fi
else
    echo "   Cannot analyze - log file not accessible"
fi

echo

# Available log files
echo "📁 Available log files:"
LOG_FILES=$(sudo find "$APACHE_LOG_DIR" -name "error.log*" -type f 2>/dev/null | wc -l)
echo "   Total error log files: $LOG_FILES"

GZIPPED=$(sudo find "$APACHE_LOG_DIR" -name "error.log*.gz" -type f 2>/dev/null | wc -l)
echo "   Gzipped archives: $GZIPPED"

echo

# Latest entries
echo "⏰ Latest entries (last 3 lines):"
if [ -f "$APACHE_LOG_DIR/error.log" ]; then
    LATEST=$(sudo tail -n 3 "$APACHE_LOG_DIR/error.log" 2>/dev/null)
    if [ -n "$LATEST" ]; then
        echo "$LATEST" | sed 's/^/   /'
    else
        echo "   No recent entries found"
    fi
else
    echo "   Cannot read current log file"
fi
