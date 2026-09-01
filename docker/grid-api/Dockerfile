FROM php:8.3-cli-alpine

RUN apk add --no-cache curl freetype git libjpeg-turbo libpng libwebp unzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd pdo_mysql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY grid-api-switch /grid-api-switch
COPY grid-api /app

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

EXPOSE 8081

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8081"]
