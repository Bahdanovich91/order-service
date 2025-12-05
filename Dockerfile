FROM php:8.3-fpm

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libjpeg-dev \
    libfreetype6-dev \
    librdkafka-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Установка PHP-расширений
RUN docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd zip intl xml dom

# Установка PECL и rdkafka
RUN pecl install rdkafka \
    && docker-php-ext-enable rdkafka

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Создание пользователя приложения
RUN useradd -G www-data,root -u 1000 -m developer

WORKDIR /var/www/html

# Установка Symfony, если его нет
COPY ./install_symfony.sh /usr/local/bin/install_symfony.sh
RUN chmod +x /usr/local/bin/install_symfony.sh

USER developer

EXPOSE 9000

CMD ["/usr/local/bin/install_symfony.sh"]
