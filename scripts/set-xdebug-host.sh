#!/bin/bash
# Updates XDEBUG_CLIENT_HOST in .env to the current WSL2 IP.
# Run this after WSL2 restarts, then: docker compose up -d app
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/../.env"
WSL_IP=$(hostname -I | awk '{print $1}')

if [ -z "$WSL_IP" ]; then
    echo "ERROR: Could not detect WSL2 IP" >&2
    exit 1
fi

if grep -q "^XDEBUG_CLIENT_HOST=" "$ENV_FILE"; then
    sed -i "s/^XDEBUG_CLIENT_HOST=.*/XDEBUG_CLIENT_HOST=$WSL_IP/" "$ENV_FILE"
else
    echo "XDEBUG_CLIENT_HOST=$WSL_IP" >> "$ENV_FILE"
fi

echo "Set XDEBUG_CLIENT_HOST=$WSL_IP in .env"
echo "Now restart the app container: docker compose up -d app"
