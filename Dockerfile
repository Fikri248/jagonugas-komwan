# Dockerfile
FROM php:8.2-apache

# Install extension PHP yang dibutuhkan
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Aktifkan mod_rewrite (jika ada routing)
RUN a2enmod rewrite

# Set workdir & copy source code
WORKDIR /var/www/html
COPY . /var/www/html

# Jika pakai folder public sebagai webroot, uncomment ini:
# RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

# Permission basic
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

# Default env (bisa di-override di docker-compose / Azure)
ENV DB_HOST=db \
    DB_NAME=jagonugas_db \
    DB_USER=jagonugas_user \
    DB_PASS=secretpassword \
    DB_PORT=3306

EXPOSE 80

CMD ["apache2-foreground"]
