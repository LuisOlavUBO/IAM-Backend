#!/bin/sh
set -e

# storage/ and bootstrap/cache/ come from the bind mount: PHP-FPM (www-data) needs to write to them.
# On Docker Desktop for Windows chown may have no effect; startup is not aborted because of it.
if [ -d storage ] && [ -d bootstrap/cache ]; then
    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || echo "Warning: could not change the owner of storage/ (normal on Windows)"
    chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
fi

# Runs the container command: php-fpm by default, or whatever is passed (e.g. php artisan ...).
# Migrations and iam:* commands are never run here: they are run by hand.
exec "$@"
