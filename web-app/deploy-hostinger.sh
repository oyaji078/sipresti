#!/usr/bin/env bash

set -euo pipefail

cd /home/u694356768/domains/oypoint.online/public_html

marker="storage/app/.deployment_complete"

if [[ -f "${marker}" ]]; then
    exit 0
fi

php_bin="/opt/alt/php84/usr/bin/php"
composer_bin="/usr/local/bin/composer2"

COMPOSER_ALLOW_SUPERUSER=1 "${php_bin}" -d memory_limit=-1 "${composer_bin}" \
    install --no-dev --optimize-autoloader --no-interaction

chmod -R 775 storage bootstrap/cache

"${php_bin}" artisan migrate --force
"${php_bin}" artisan sipresti:create-admin --force
"${php_bin}" artisan config:cache
"${php_bin}" artisan route:cache
"${php_bin}" artisan view:cache

touch "${marker}"
