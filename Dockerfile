# Dockerfile
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Aktifkan Apache modules
RUN a2enmod rewrite headers reqtimeout

# === PHP Configuration ===
RUN { \
    echo 'upload_max_filesize = 300M'; \
    echo 'post_max_size = 310M'; \
    echo 'max_execution_time = 600'; \
    echo 'max_input_time = 600'; \
    echo 'memory_limit = 512M'; \
    echo 'display_errors = Off'; \
    echo 'log_errors = On'; \
    echo 'error_log = /var/log/apache2/php_errors.log'; \
} > /usr/local/etc/php/conf.d/custom.ini

# === Apache Configuration ===
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Request timeout
RUN echo "RequestReadTimeout header=60 body=600" >> /etc/apache2/conf-available/reqtimeout.conf && \
    a2enconf reqtimeout

# Set workdir
WORKDIR /var/www/html

# Copy composer files first (for Docker layer caching)
COPY composer.json composer.lock* ./

# Install Composer dependencies (PRODUCTION)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --optimize-autoloader \
    --prefer-dist \
    && composer clear-cache

# Copy all source code
COPY . /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

# Create upload directories
RUN mkdir -p /var/www/html/uploads /var/www/html/assets/uploads \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/assets/uploads \
    && chmod -R 755 /var/www/html/uploads /var/www/html/assets/uploads

# Environment variables (can be overridden in Azure App Settings)
ENV DB_HOST=db \
    DB_NAME=jagonugas_db \
    DB_USER=jagonugas_user \
    DB_PASS=secretpassword \
    DB_PORT=3306

EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD curl -f http://localhost/ || exit 1

CMD ["apache2-foreground"]
