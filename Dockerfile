# Production Dockerfile for WD Parcel Send Receiver PWA
# Multi-tenant parcel management system with PHP 8.1 + Apache

FROM php:8.1-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libpq-dev \
    cron \
    supervisor \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    mbstring \
    xml \
    zip \
    bcmath \
    gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers ssl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (for better caching)
COPY composer.json composer.lock* ./
COPY Admins/composer.json Admins/composer.lock* ./Admins/
COPY customer-app/composer.json customer-app/composer.lock* ./customer-app/
COPY outlet-app/composer.json outlet-app/composer.lock* ./outlet-app/

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction || composer update --no-dev --optimize-autoloader --no-interaction
RUN cd Admins && (composer install --no-dev --optimize-autoloader --no-interaction || composer update --no-dev --optimize-autoloader --no-interaction)
RUN cd customer-app && (composer install --no-dev --optimize-autoloader --no-interaction || composer update --no-dev --optimize-autoloader --no-interaction)
RUN cd outlet-app && (composer install --no-dev --optimize-autoloader --no-interaction || composer update --no-dev --optimize-autoloader --no-interaction)

# Copy application code
COPY . .

# Copy Apache virtual host configuration for multi-tenant setup
COPY apache-vhosts.conf /etc/apache2/sites-available/000-default.conf

# Create required directories with proper permissions
RUN mkdir -p \
    /var/www/html/logs \
    /var/www/html/outlet-app/logs \
    /var/www/html/outlet-app/sessions \
    /var/www/html/outlet-app/cache \
    /var/www/html/outlet-app/assets/barcodes \
    /var/www/html/customer-app/assets/barcodes \
    /var/www/html/Admins/super_admin/logs \
    /var/www/html/Admins/super_admin/storage \
    && chown -R www-data:www-data \
    /var/www/html/logs \
    /var/www/html/outlet-app/logs \
    /var/www/html/outlet-app/sessions \
    /var/www/html/outlet-app/cache \
    /var/www/html/outlet-app/assets/barcodes \
    /var/www/html/customer-app/assets/barcodes \
    /var/www/html/Admins/super_admin/logs \
    /var/www/html/Admins/super_admin/storage \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 \
    /var/www/html/outlet-app/sessions \
    /var/www/html/outlet-app/cache \
    /var/www/html/outlet-app/assets/barcodes \
    /var/www/html/customer-app/assets/barcodes

# PHP configuration for production
RUN echo 'memory_limit = 256M' >> /usr/local/etc/php/conf.d/docker-php-prod.ini && \
    echo 'upload_max_filesize = 50M' >> /usr/local/etc/php/conf.d/docker-php-prod.ini && \
    echo 'post_max_size = 50M' >> /usr/local/etc/php/conf.d/docker-php-prod.ini && \
    echo 'max_execution_time = 300' >> /usr/local/etc/php/conf.d/docker-php-prod.ini && \
    echo 'max_input_vars = 3000' >> /usr/local/etc/php/conf.d/docker-php-prod.ini && \
    echo 'session.gc_maxlifetime = 1800' >> /usr/local/etc/php/conf.d/docker-php-prod.ini && \
    echo 'display_errors = Off' >> /usr/local/etc/php/conf.d/docker-php-prod.ini && \
    echo 'log_errors = On' >> /usr/local/etc/php/conf.d/docker-php-prod.ini && \
    echo 'error_log = /var/log/php_errors.log' >> /usr/local/etc/php/conf.d/docker-php-prod.ini

# Supervisor configuration for background tasks
COPY <<EOF /etc/supervisor/conf.d/supervisord.conf
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:apache2]
command=/usr/sbin/apache2ctl -D FOREGROUND
autostart=true
autorestart=true
stderr_logfile=/var/log/supervisor/apache2.err.log
stdout_logfile=/var/log/supervisor/apache2.out.log

[program:cleanup-cron]
command=/bin/bash -c "while true; do php /var/www/html/outlet-app/api/cleanup_expired_subscriptions.php && php /var/www/html/outlet-app/api/cleanup_push_subscriptions_cron.php && sleep 3600; done"
autostart=true
autorestart=true
stderr_logfile=/var/log/supervisor/cleanup.err.log
stdout_logfile=/var/log/supervisor/cleanup.out.log
EOF

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Expose port
EXPOSE 80

# Start supervisor (runs Apache + background tasks)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]