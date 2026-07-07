FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev libzip-dev libfreetype6-dev libjpeg-dev \
    cron default-mysql-client supervisor nginx awscli \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Node.js 20 for Vite frontend build
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

# OpenTelemetry boots and its shutdown handler tries to export even during
# build-time artisan calls (composer's post-autoload-dump package:discover).
# No ADOT sidecar exists during the build, and the failed export crashes the
# step with a non-zero exit — not just a logged warning. ECS's runtime env
# vars (from SSM) override this default once the container actually starts.
ENV OTEL_SDK_DISABLED=true

RUN COMPOSER_AUTH='{}' composer install --no-dev --optimize-autoloader

# Build frontend assets with production VITE env vars injected as build args.
# All ARG values have safe defaults for local builds; CI passes production values.
ARG VITE_STORAGE_BASE_URL=https://mis.crc.pshs.edu.ph/media
ARG VITE_PUSHER_HOST=localhost
ARG VITE_PUSHER_PORT=9601
ARG VITE_PUSHER_SCHEME=http
ARG VITE_PUSHER_APP_KEY=bugsaymis-app-key
ARG VITE_PUSHER_APP_CLUSTER=mt1
ARG VITE_VAPID_PUBLIC_KEY=

ENV VITE_STORAGE_BASE_URL=${VITE_STORAGE_BASE_URL}
ENV VITE_PUSHER_HOST=${VITE_PUSHER_HOST}
ENV VITE_PUSHER_PORT=${VITE_PUSHER_PORT}
ENV VITE_PUSHER_SCHEME=${VITE_PUSHER_SCHEME}
ENV VITE_PUSHER_APP_KEY=${VITE_PUSHER_APP_KEY}
ENV VITE_PUSHER_APP_CLUSTER=${VITE_PUSHER_APP_CLUSTER}
ENV VITE_VAPID_PUBLIC_KEY=${VITE_VAPID_PUBLIC_KEY}

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
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "upload_max_filesize = 20M" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "post_max_size = 25M" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "max_execution_time = 120" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "allow_url_fopen = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "open_basedir = /var/www:/tmp:/usr/local/etc/php:/dev/stdin:/dev/stdout:/dev/stderr" >> /usr/local/etc/php/conf.d/security.ini

# disable_functions is FPM-only: the Laravel scheduler (cron -> schedule:run) spawns
# every task through Symfony Process, which needs proc_open in the CLI SAPI.
RUN echo "[www]" > /usr/local/etc/php-fpm.d/zz-security.conf \
    && echo "php_admin_value[disable_functions] = system,shell_exec,passthru,proc_open,popen,pcntl_exec" >> /usr/local/etc/php-fpm.d/zz-security.conf

# The php:8.4-fpm default pool is pm.max_children=5 — a couple of slow
# requests (large base64 uploads, long streams) starve the whole app and
# spike ALB p99 latency. Sized for the 2 vCPU / 4GB Fargate task (shared
# with nginx + soketi + adot): ~24 workers × ~100MB RSS + siblings fits 4GB
# with headroom; max_requests recycles workers to cap leaks. listen.backlog
# matches nginx's listen backlog so bursts queue instead of erroring.
RUN { \
        echo "[www]"; \
        echo "pm = dynamic"; \
        echo "pm.max_children = 24"; \
        echo "pm.start_servers = 8"; \
        echo "pm.min_spare_servers = 4"; \
        echo "pm.max_spare_servers = 12"; \
        echo "pm.max_requests = 500"; \
        echo "listen.backlog = 4096"; \
    } > /usr/local/etc/php-fpm.d/zz-pool-sizing.conf

# OPcache ships enabled but with stock sizing: 10k max files (the app + no-dev
# vendor tree exceeds this → cache thrash) and 128M memory. validate_timestamps
# is OFF because containers are immutable and deploys are blue-green — code
# NEVER changes in a running task. Consequence: hot-patching a file inside a
# live container will not take effect; a full deploy is required (already the rule).
RUN { \
        echo "opcache.memory_consumption = 256"; \
        echo "opcache.max_accelerated_files = 32531"; \
        echo "opcache.interned_strings_buffer = 32"; \
        echo "opcache.validate_timestamps = 0"; \
    } > /usr/local/etc/php/conf.d/zz-opcache-tuning.ini

RUN echo "* * * * * root . /etc/environment; cd /var/www && php artisan schedule:run >> /var/log/cron.log 2>&1" > /etc/cron.d/laravel-scheduler \
    && chmod 0644 /etc/cron.d/laravel-scheduler

COPY docker/nginx-main.conf /etc/nginx/nginx.conf
COPY docker/nginx-app.conf /etc/nginx/sites-enabled/default
RUN rm -f /etc/nginx/sites-enabled/default.conf 2>/dev/null; \
    rm -f /etc/nginx/conf.d/default.conf 2>/dev/null; true

COPY docker/supervisord.conf /etc/supervisor/conf.d/bugsaymis.conf
COPY docker/supervisord-worker.conf /etc/supervisor/conf.d/bugsaymis-worker.conf

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

CMD ["/usr/local/bin/docker-entrypoint.sh"]
