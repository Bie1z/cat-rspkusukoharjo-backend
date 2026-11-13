FROM php:8.2-cli

# Install dependencies sistem
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Salin seluruh file proyek
COPY . .

# Install vendor dependencies
RUN composer install --no-dev --optimize-autoloader

# Beri izin folder storage
RUN chmod -R 777 storage bootstrap/cache

# Port default Railway
EXPOSE 8080

# Jalankan server Laravel langsung menggunakan built-in server PHP
CMD php artisan serve --host=0.0.0.0 --port=8080