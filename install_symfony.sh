#!/bin/bash
set -e

APP_DIR="/var/www/html"
TEMP_DIR="/tmp/symfony_install"

if [ ! -f "$APP_DIR/composer.json" ]; then
  echo "🚀 Symfony не найден — устанавливаем новый проект во временную папку..."
  rm -rf "$TEMP_DIR"
  mkdir -p "$TEMP_DIR"

  cd "$TEMP_DIR"
  composer create-project symfony/skeleton:"^7.0" . --no-interaction --prefer-dist --ignore-platform-req=ext-xml

  echo "📂 Копируем файлы Symfony в рабочую директорию..."
  cp -r "$TEMP_DIR"/. "$APP_DIR"/

  rm -rf "$TEMP_DIR"
else
  echo "✅ Symfony уже установлен."
fi

echo "📦 Установка зависимостей..."
cd "$APP_DIR"
composer install --no-interaction --prefer-dist --ignore-platform-req=ext-xml || true

echo "🔧 Кэширование конфигурации..."
php bin/console cache:clear || true

echo "✅ Контейнер Symfony готов к работе!"
exec php-fpm

