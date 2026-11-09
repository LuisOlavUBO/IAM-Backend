#!/bin/sh
set -e

# Fail fast if a critical variable is missing
for var in APP_KEY DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD; do
    eval "value=\${$var:-}"
    if [ -z "$value" ]; then
        echo "ERROR: missing environment variable $var" >&2
        exit 1
    fi
done

echo "Optimizing Laravel for production..."

# Discovers the packages (the build used --no-scripts)
php artisan package:discover --ansi

# Caches config, routes, views and events with this deployment's environment variables
php artisan optimize

# Migrations run only when explicitly asked (RUN_MIGRATIONS=true). The default is to run them by hand,
# like the iam:* commands, which never run here: their secrets must not end up in the logs.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

# The caches were written as root: PHP-FPM workers (www-data) must be able to rewrite them
chown -R www-data:www-data storage bootstrap/cache

# Hands control to the container command (php-fpm) as PID 1
exec "$@"
