#!/bin/bash
set -e

echo "=== Gravitycar Docker Entrypoint ==="

# -------------------------------------------------------
# 1. Ensure writable directories exist
# -------------------------------------------------------
echo "[entrypoint] Ensuring logs/ and cache/ directories exist..."
mkdir -p /var/www/html/logs /var/www/html/cache
chown -R www-data:www-data /var/www/html/logs /var/www/html/cache

# -------------------------------------------------------
# 2. Install Composer dependencies if vendor/ is missing
#    (Volume mount from host overwrites the image layer,
#     so vendor/ will be empty on first run)
# -------------------------------------------------------
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "[entrypoint] vendor/autoload.php not found. Running composer install..."
    cd /var/www/html
    composer install --no-interaction --optimize-autoloader
    echo "[entrypoint] Composer install complete."
else
    echo "[entrypoint] vendor/ already present, skipping composer install."
fi

# -------------------------------------------------------
# 3. Wait for MySQL to be truly ready (not just pinging,
#    but accepting connections on the target database)
# -------------------------------------------------------
echo "[entrypoint] Waiting for MySQL to accept connections..."
MAX_RETRIES=30
RETRY_COUNT=0
until mysql -h "${DB_HOST:-db}" -P "${DB_PORT:-3306}" \
            -u "${DB_USER:-gravitycar}" -p"${DB_PASSWORD:-gravitycar_secret}" \
            -e "SELECT 1" "${DB_NAME:-gravitycar_nc}" > /dev/null 2>&1; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
        echo "[entrypoint] WARNING: MySQL not ready after ${MAX_RETRIES} attempts. Proceeding anyway."
        break
    fi
    echo "[entrypoint] MySQL not ready yet (attempt ${RETRY_COUNT}/${MAX_RETRIES}), waiting 2s..."
    sleep 2
done

# -------------------------------------------------------
# 4. Run setup.php on first launch (marker file approach)
#    setup.php is idempotent, but we only want to run it
#    automatically on the very first docker-compose up.
# -------------------------------------------------------
SETUP_MARKER="/var/www/html/.docker-setup-complete"
if [ ! -f "$SETUP_MARKER" ]; then
    echo "[entrypoint] First-run detected. Running setup.php..."
    cd /var/www/html
    php setup.php && touch "$SETUP_MARKER"
    echo "[entrypoint] Setup complete."
else
    echo "[entrypoint] Setup already run (marker file exists). Skipping."
    echo "[entrypoint] To re-run setup, delete .docker-setup-complete and restart."
fi

# -------------------------------------------------------
# 5. Fix permissions for Apache
# -------------------------------------------------------
chown -R www-data:www-data /var/www/html/logs /var/www/html/cache

echo "=== Entrypoint complete. Starting Apache... ==="

# -------------------------------------------------------
# 6. Execute the CMD (apache2-foreground by default)
# -------------------------------------------------------
exec "$@"
