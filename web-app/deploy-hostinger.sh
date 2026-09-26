#!/usr/bin/env bash

set -euo pipefail

cd /home/u694356768/domains/oypoint.online/public_html

php_bin="/opt/alt/php84/usr/bin/php"
composer_bin="/usr/local/bin/composer2"

# Langkah di bawah ini aman dijalankan berulang setiap deploy: composer
# install, migrate, dan cache rebuild semuanya idempoten.
COMPOSER_ALLOW_SUPERUSER=1 "${php_bin}" -d memory_limit=-1 "${composer_bin}" \
    install --no-dev --optimize-autoloader --no-interaction

chmod -R 775 storage bootstrap/cache

"${php_bin}" artisan migrate --force

# Akun admin HANYA dibuat sekali — sipresti:create-admin --force akan
# mengganti kata sandi admin bila dijalankan ulang, jadi tidak boleh
# ikut jalan di setiap deploy.
admin_marker="storage/app/.admin_created"
if [[ ! -f "${admin_marker}" ]]; then
    "${php_bin}" artisan sipresti:create-admin --force
    touch "${admin_marker}"
fi

"${php_bin}" artisan config:cache
"${php_bin}" artisan route:cache
"${php_bin}" artisan view:cache
