#!/bin/bash

# Quick script to restart Apache web server from the project root
# Usage: ./restart-apache.sh

echo "🔄 Restarting Apache web server..."

# Check if Apache is running and get the service name
if systemctl is-active --quiet apache2; then
    SERVICE_NAME="apache2"
elif systemctl is-active --quiet httpd; then
    SERVICE_NAME="httpd"
else
    echo "❌ Apache service not found or not running"
    echo "ℹ️  Trying common service names..."
    
    # Try to start apache2 first (Ubuntu/Debian)
    if sudo systemctl restart apache2 2>/dev/null; then
        echo "✅ Apache2 restarted successfully"
        exit 0
    fi
    
    # Try httpd (CentOS/RHEL/Fedora)
    if sudo systemctl restart httpd 2>/dev/null; then
        echo "✅ Apache (httpd) restarted successfully"
        exit 0
    fi
    
    echo "❌ Failed to restart Apache. Please check your Apache installation."
    exit 1
fi

# Restart the detected service
echo "🔄 Restarting $SERVICE_NAME..."
if sudo systemctl restart $SERVICE_NAME; then
    echo "✅ $SERVICE_NAME restarted successfully"
    echo "🌐 Apache web server should now be available at http://localhost:8081"
    
    # Check if it's actually running
    sleep 2
    if systemctl is-active --quiet $SERVICE_NAME; then
        echo "✅ $SERVICE_NAME is running"
    else
        echo "⚠️  $SERVICE_NAME may not have started correctly"
    fi
else
    echo "❌ Failed to restart $SERVICE_NAME"
    exit 1
fi
