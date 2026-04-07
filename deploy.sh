#!/bin/bash

# Зупиняємо скрипт, якщо якась команда видасть помилку
set -e

# Назва твого проекту (папки). Докер використовує її як префікс для волюмів.
PROJECT_NAME="blastr"

echo "========================================"
echo "🚀 Починаємо деплой BlastR..."
echo "========================================"

echo "⬇️ 1. Оновлюємо код з Git..."
git pull

echo "📦 2. Збираємо імейджі..."
# Docker розумний: якщо package.json чи composer.json не змінились,
# він використає кеш і проскочить цей крок за 2 секунди.
docker compose -f docker-compose.prod.yml build

echo "🛑 3. Оновлюємо волюмі з залежностями..."
docker compose -f docker-compose.prod.yml down
# Видаляємо старі волюмі vendor та build.
# При наступному up Docker автоматично заллє сюди свіжі файли з імейджа.
docker volume rm ${PROJECT_NAME}_vendor ${PROJECT_NAME}_node_build 2>/dev/null || true

echo "🟢 4. Запускаємо контейнери..."
docker compose up -d

echo "🗄 5. Виконуємо міграції..."
# Прапорець -T потрібен, щоб скрипт не сварився, якщо його запускати через крон або CI/CD
docker compose exec -T app php artisan migrate --force

echo "🧹 6. Очищуємо та прогріваємо кеш Laravel..."
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache

echo "🔄 7. Даємо команду воркерам перезапуститися..."
# queue:restart м'яко зупиняє поточні задачі (без обриву) і запускає нові процеси
docker compose exec -T app php artisan queue:restart

echo "========================================"
echo "✅ Деплой успішно завершено!"
echo "========================================"
