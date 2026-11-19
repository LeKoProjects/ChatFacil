#!/bin/sh
# Gera os caches do Laravel com as variáveis do .env de produção e inicia o processo.
set -e
php artisan config:cache --no-ansi -q
php artisan route:cache --no-ansi -q
php artisan view:cache --no-ansi -q
exec "$@"
