#!/usr/bin/env sh
set -e
# Minimal entrypoint for FrankenPHP
# If composer dependencies not yet installed (e.g. volume mount), install
if [ ! -f vendor/autoload.php ]; then
  composer install --no-dev --optimize-autoloader --no-interaction
fi
exec "$@"
