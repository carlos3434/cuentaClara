#!/bin/sh
set -e

# Render injects $PORT; fall back to 10000 (Render's default) for local `docker run`.
export PORT="${PORT:-10000}"

# --- Privileged bootstrap (root) -----------------------------------------
# The image starts as root so we can prepare a freshly mounted Render
# Persistent Disk: the mount is root-owned until we chown it, and www-data
# can't write there otherwise. We create the receipts dir, hand it to
# www-data, then re-exec this script as www-data so the app never runs as
# root. RECEIPTS_DISK_ROOT is the disk-backed path (e.g. /var/data/receipts);
# when unset (local/dev) there's nothing to prepare.
if [ "$(id -u)" = "0" ]; then
    if [ -n "${RECEIPTS_DISK_ROOT:-}" ]; then
        mkdir -p "$RECEIPTS_DISK_ROOT"
        chown -R www-data:www-data "$RECEIPTS_DISK_ROOT"
    fi
    exec gosu www-data "$0" "$@"
fi

# --- Everything below runs as www-data -----------------------------------

# With SQLite, make sure the database file exists before migrating. SQLite on
# Render is ephemeral (recreated every deploy), so production should run on a
# managed Postgres (DB_CONNECTION=pgsql) — there this block is skipped and the
# managed database persists across deploys.
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/app/database/database.sqlite}"
    if [ ! -f "$DB_FILE" ]; then
        mkdir -p "$(dirname "$DB_FILE")"
        touch "$DB_FILE"
    fi
fi

# Cache config/routes/views now that runtime env vars are present.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations on container start.
php artisan migrate --force

# Ensure a default admin exists (idempotent). Handy for the first deploy /
# a fresh database. Set ADMIN_EMAIL and ADMIN_PASSWORD in the environment to
# enable; skipped otherwise.
if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
    php artisan admin:make "$ADMIN_EMAIL" --name="${ADMIN_NAME:-Admin}" --password="$ADMIN_PASSWORD"
fi

# Link the public storage symlink (no-op if it already exists).
php artisan storage:link || true

echo "Starting Laravel on 0.0.0.0:${PORT}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
