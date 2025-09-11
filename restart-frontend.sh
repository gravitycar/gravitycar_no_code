#!/bin/bash

# Quick script to restart the React frontend from the project root
# Usage: ./restart-frontend.sh

echo "🚀 Restarting Gravitycar React Frontend..."

./gravitycar-frontend/scripts/restart-dev-server.sh &
