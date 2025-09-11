#!/bin/bash

# AI Agent Apache Error Log Search Script
# Purpose: Search for text in Apache error logs (including gzipped archives)
# Arguments: [search_term]

SEARCH_TERM="$1"
APACHE_LOG_DIR="/var/log/apache2"

if [ -z "$SEARCH_TERM" ]; then
    echo "❌ Usage: $0 <search_term>"
    echo "💡 Example: $0 'Permission denied'"
    exit 1
fi

# Check if running with appropriate permissions
if [ ! -r "$APACHE_LOG_DIR/error.log" ]; then
    echo "❌ Permission denied - Apache logs require sudo access"
    echo "💡 Tip: Run this script with sudo or ensure user is in 'adm' group"
    exit 1
fi

echo "🔍 Searching for: '$SEARCH_TERM' in Apache error logs"
echo "===================================================="

# Search current log
echo "📄 Current log (error.log):"
CURRENT_MATCHES=$(sudo grep -n "$SEARCH_TERM" "$APACHE_LOG_DIR/error.log" 2>/dev/null)
if [ -n "$CURRENT_MATCHES" ]; then
    echo "$CURRENT_MATCHES" | sed 's/^/   /'
else
    echo "   No matches in current log"
fi

echo

# Search recent archived logs (non-gzipped)
echo "📄 Recent archived logs:"
FOUND_ARCHIVED=false
for i in {1..5}; do
    LOG_FILE="$APACHE_LOG_DIR/error.log.$i"
    if [ -f "$LOG_FILE" ]; then
        MATCHES=$(sudo grep -n "$SEARCH_TERM" "$LOG_FILE" 2>/dev/null)
        if [ -n "$MATCHES" ]; then
            echo "   error.log.$i:"
            echo "$MATCHES" | sed 's/^/      /'
            FOUND_ARCHIVED=true
        fi
    fi
done

if [ "$FOUND_ARCHIVED" = false ]; then
    echo "   No matches in recent archived logs"
fi

echo

# Search gzipped logs
echo "📦 Gzipped archived logs:"
FOUND_GZIPPED=false
for i in {1..5}; do
    LOG_FILE="$APACHE_LOG_DIR/error.log.$i.gz"
    if [ -f "$LOG_FILE" ]; then
        MATCHES=$(sudo zgrep -n "$SEARCH_TERM" "$LOG_FILE" 2>/dev/null)
        if [ -n "$MATCHES" ]; then
            echo "   error.log.$i.gz:"
            echo "$MATCHES" | sed 's/^/      /'
            FOUND_GZIPPED=true
        fi
    fi
done

if [ "$FOUND_GZIPPED" = false ]; then
    echo "   No matches in gzipped logs"
fi

echo
echo "✅ Search completed"
