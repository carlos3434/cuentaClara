#!/bin/sh
set -e

# Render injects $PORT; fall back to 10000 (Render's default) for local `docker run`.
export PORT="${PORT:-10000}"

# SQLite "for now": make sure the database file exists before migrating.
# On Render the filesystem is ephemeral, so this is recreated on every deploy.
DB_FILE="${DB_DATABASE:-/app/database/database.sqlite}"
if [ ! -f "$DB_FILE" ]; then
    mkdir -p "$(dirname "$DB_FILE")"
    touch "$DB_FILE"
fi

# Cache config/routes/views now that runtime env vars are present.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations on container start.
php artisan migrate --force

# Ensure a default admin exists (idempotent). Required on Render because the
# SQLite database is ephemeral and reset on every deploy. Set ADMIN_EMAIL and
# ADMIN_PASSWORD in the environment to enable.
if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
    php artisan admin:make "$ADMIN_EMAIL" --name="${ADMIN_NAME:-Admin}" --password="$ADMIN_PASSWORD"
fi

# Link the public storage symlink (no-op if it already exists).
php artisan storage:link || true

echo "Starting Laravel on 0.0.0.0:${PORT}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
