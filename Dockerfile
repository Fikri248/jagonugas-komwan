# Dockerfile
FROM php:8.2-apache

# Install extension PHP yang dibutuhkan
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Aktifkan Apache modules
RUN a2enmod rewrite headers reqtimeout

# === PHP Configuration untuk Large File Upload ===
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
# Set ServerName untuk hilangkan warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Set request timeout di Apache config
RUN echo "RequestReadTimeout header=60 body=600" >> /etc/apache2/conf-available/reqtimeout.conf && \
    a2enconf reqtimeout

# Set workdir & copy source code
WORKDIR /var/www/html
COPY . /var/www/html

# Permission basic
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

# Buat folder untuk uploads jika belum ada
RUN mkdir -p /var/www/html/uploads /var/www/html/assets/uploads \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/assets/uploads \
    && chmod -R 755 /var/www/html/uploads /var/www/html/assets/uploads

# Default env (bisa di-override di Azure App Settings)
ENV DB_HOST=db \
    DB_NAME=jagonugas_db \
    DB_USER=jagonugas_user \
    DB_PASS=secretpassword \
    DB_PORT=3306

EXPOSE 80

CMD ["apache2-foreground"]
