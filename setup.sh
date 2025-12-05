#!/bin/bash

echo "🚀 Настройка Order Service..."

# Запуск контейнеров
echo "🐳 Запуск Docker контейнеров..."
docker compose up -d

# Ожидание запуска контейнеров
echo "⏳ Ожидание запуска контейнеров..."
sleep 15

# Проверка наличия Symfony
if [ ! -f "composer.json" ]; then
    echo "📦 Установка Symfony через Docker..."
    docker compose exec app /usr/local/bin/install_symfony.sh || true
    sleep 5
fi

# Установка зависимостей
echo "📦 Установка зависимостей Symfony..."
docker compose exec app composer require \
    doctrine/doctrine-bundle \
    doctrine/doctrine-migrations-bundle \
    doctrine/orm \
    symfony/validator \
    enqueue/rdkafka \
    --no-interaction --ignore-platform-req=ext-xml || true

# Ожидание запуска PostgreSQL
echo "⏳ Ожидание запуска PostgreSQL..."
sleep 10

# Создание .env.local файла если его нет
if [ ! -f ".env.local" ]; then
    echo "📝 Создание .env.local файла..."
    docker compose exec app cp .env .env.local || true
fi

# Настройка базы данных в .env.local
echo "🔧 Настройка базы данных..."
docker compose exec app bash -c 'cat >> .env.local << EOF

###> doctrine/doctrine-bundle ###
DATABASE_URL="postgresql://order_user:order_password@postgres:5432/order_db?serverVersion=15&charset=utf8"
###< doctrine/doctrine-bundle ###

###> kafka ###
KAFKA_BROKER=kafka:9092
KAFKA_TOPIC_ORDER_EVENTS=order-events
KAFKA_TOPIC_ORDER_COMMANDS=order-commands
KAFKA_TOPIC_INVENTORY_COMMANDS=inventory-commands
KAFKA_TOPIC_BALANCE_COMMANDS=balance-commands
###< kafka ###

###> elasticsearch ###
ELASTICSEARCH_HOST=http://elasticsearch:9200
###< elasticsearch ###
EOF'

# Создание базы данных
echo "🗄️ Создание базы данных..."
docker compose exec app php bin/console doctrine:database:create --if-not-exists || true

# Запуск миграций (если есть)
echo "🗄️ Запуск миграций..."
docker compose exec app php bin/console doctrine:schema:update --force || true

echo "✅ Настройка завершена!"
echo ""
echo "Приложение доступно по адресу: http://localhost:8082"
echo ""
echo "Полезные команды:"
echo "docker compose exec app bash  # Вход в контейнер"
echo "docker compose logs -f        # Просмотр логов"
echo "docker compose down           # Остановка контейнеров"

