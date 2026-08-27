FROM php:8.3-cli-alpine

RUN apk add --no-cache curl git unzip $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY grid-api-switch /grid-api-switch
COPY grid-api /app

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8081

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8081"]
