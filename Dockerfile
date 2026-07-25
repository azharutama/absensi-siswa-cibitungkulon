FROM php:8.4-cli

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libonig-dev \
    nodejs \
    npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip gd bcmath intl xml opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
RUN npm install
RUN rm -f public/hot && npm run build

RUN mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views && \
    php artisan config:clear && \
    php artisan route:clear && \
    php artisan view:clear || true

EXPOSE 10000

CMD ["sh", "-c", "php artisan queue:work --sleep=3 --tries=3 --timeout=90 & exec php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
