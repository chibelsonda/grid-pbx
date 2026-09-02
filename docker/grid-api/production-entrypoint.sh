#!/usr/bin/env sh
set -eu

prepare_application() {
    if [ -z "${APP_KEY:-}" ]; then
        echo "APP_KEY is required." >&2
        exit 1
    fi

    mkdir -p \
        resources/views \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache

    php artisan config:cache
    php artisan view:cache
    php artisan storage:link --force >/dev/null 2>&1 || true
}

case "${1:-web}" in
    web)
        prepare_application
        php-fpm -D
        exec nginx -g 'daemon off;'
        ;;
    worker)
        prepare_application
        exec php artisan queue:work redis --queue=sync,default --tries=3 --timeout=120 --sleep=2
        ;;
    scheduler)
        prepare_application
        exec php artisan schedule:work
        ;;
    *)
        exec "$@"
        ;;
esac
