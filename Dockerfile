FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev libzip-dev \
    cron default-mysql-client supervisor nginx awscli \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Node.js 20 for Vite frontend build
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN COMPOSER_AUTH='{}' composer install --no-dev --optimize-autoloader

# Build frontend assets and remove any leftover Vite dev-server marker
ARG VITE_STORAGE_BASE_URL=https://crcmis-mis-storage.s3.ap-southeast-1.amazonaws.com
ENV VITE_STORAGE_BASE_URL=${VITE_STORAGE_BASE_URL}
RUN npm ci --prefer-offline 2>/dev/null || npm install \
    && npm run build \
    && rm -f /var/www/public/hot \
    && rm -rf /var/www/node_modules

# Ensure all storage/bootstrap directories exist and are writable at runtime
RUN mkdir -p \
        /var/www/storage/logs \
        /var/www/storage/app/public \
        /var/www/storage/framework/cache/data \
        /var/www/storage/framework/sessions \
        /var/www/storage/framework/views \
        /var/www/bootstrap/cache \
        /var/lib/nginx/body \
        /var/lib/nginx/proxy \
        /var/lib/nginx/fastcgi \
        /var/lib/nginx/uwsgi \
        /var/lib/nginx/scgi \
        /var/log/nginx \
        /var/run/nginx \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache \
    && chown -R www-data:www-data \
        /var/www/storage \
        /var/www/bootstrap/cache \
        /var/lib/nginx \
        /var/log/nginx \
        /var/run/nginx

RUN echo "expose_php = Off" > /usr/local/etc/php/conf.d/security.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "session.cookie_httponly = 1" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "session.cookie_secure = 1" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "session.use_strict_mode = 1" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "upload_max_filesize = 20M" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "post_max_size = 25M" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "max_execution_time = 120" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "allow_url_fopen = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "disable_functions = system,shell_exec,passthru,proc_open,popen,pcntl_exec" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "open_basedir = /var/www:/tmp:/usr/local/etc/php:/dev/stdin:/dev/stdout:/dev/stderr" >> /usr/local/etc/php/conf.d/security.ini

RUN echo "* * * * * root . /etc/environment; cd /var/www && php artisan schedule:run >> /var/log/cron.log 2>&1" > /etc/cron.d/laravel-scheduler \
    && chmod 0644 /etc/cron.d/laravel-scheduler

COPY docker/nginx-app.conf /etc/nginx/sites-enabled/default
RUN rm -f /etc/nginx/sites-enabled/default.conf 2>/dev/null; \
    rm -f /etc/nginx/conf.d/default.conf 2>/dev/null; true

COPY docker/supervisord.conf /etc/supervisor/conf.d/bugsaymis.conf

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

CMD ["/usr/local/bin/docker-entrypoint.sh"]
