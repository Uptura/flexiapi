# FlexiAPI Dockerfile
FROM php:8.2-cli
WORKDIR /app
RUN docker-php-ext-install pdo pdo_mysql
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
EXPOSE 8000
# Start PHP built-in web server using the "public" folder as document root
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]