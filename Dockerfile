# Laravel PHP Backend Dockerfile for Render
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    nginx \
    supervisor \
    oniguruma-dev \
    postgresql-dev \
    mysql-client \
    mariadb-client \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Copy application files
COPY . .

# Create necessary directories and set permissions
RUN mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy nginx configuration
RUN echo 'server { \
    listen 80; \
    server_name _; \
    root /var/www/html/public; \
    index index.php; \
    \
    add_header X-Frame-Options "SAMEORIGIN"; \
    add_header X-Content-Type-Options "nosniff"; \
    \
    charset utf-8; \
    \
    location / { \
        try_files $uri $uri/ /index.php?$query_string; \
    } \
    \
    location = /favicon.ico { access_log off; log_not_found off; } \
    location = /robots.txt  { access_log off; log_not_found off; } \
    \
    error_page 404 /index.php; \
    \
    location ~ \.php$ { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name; \
        include fastcgi_params; \
    } \
    \
    location ~ /\.(?!well-known).* { \
        deny all; \
    } \
}' > /etc/nginx/conf.d/default.conf

# Copy supervisor configuration
RUN echo '[supervisord] \
nodaemon=true \
user=root \
\
[program:php-fpm] \
command=php-fpm \
stdout_logfile=/dev/stdout \
stdout_logfile_maxbytes=0 \
stderr_logfile=/dev/stderr \
stderr_logfile_maxbytes=0 \
autorestart=true \
\
[program:nginx] \
command=nginx -g "daemon off;" \
stdout_logfile=/dev/stdout \
stdout_logfile_maxbytes=0 \
stderr_logfile=/dev/stderr \
stderr_logfile_maxbytes=0 \
autorestart=true \
\
[program:laravel-worker] \
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600 \
directory=/var/www/html \
autorestart=true \
stdout_logfile=/dev/stdout \
stdout_logfile_maxbytes=0 \
stderr_logfile=/dev/stderr \
stderr_logfile_maxbytes=0 \
user=www-data' > /etc/supervisor/conf.d/supervisord.conf

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
  CMD curl -f http://localhost/up || exit 1

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
