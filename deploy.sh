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

echo "🛑 2. Зупиняємо worker/scheduler перед build (звільняємо CPU/RAM)..."
# Без цього BuildKit конкурує з queue workers за CPU/RAM і білд може
# повзти годинами або зависати під OOM. Workers під SIGTERM чекають
# поки поточний job закінчиться (типово до 30с), потім SIGKILL.
# Jobs що обірвались стануть reserved у Redis і підхопляться після деплою.
docker compose -f docker-compose.prod.yml stop -t 30 worker 2>/dev/null || true

echo "📦 3. Збираємо імейджі..."
# Docker розумний: якщо package.json чи composer.json не змінились,
# він використає кеш і проскочить цей крок за 2 секунди.
# Обмежуємо паралелізм BuildKit щоб не вичерпати RAM на сервері.

# Створюємо BuildKit builder з обмеженим паралелізмом (якщо ще не існує)
if ! docker buildx inspect blastr-builder >/dev/null 2>&1; then
  docker buildx create --name blastr-builder \
    --config docker/production/buildkitd.toml \
    --use
else
  docker buildx use blastr-builder
fi

COMPOSE_PARALLEL_LIMIT=1 docker compose -f docker-compose.prod.yml build

echo "🛑 3.1. Оновлюємо волюмі з залежностями..."
docker compose -f docker-compose.prod.yml down
# Видаляємо старі волюмі vendor та build.
# При наступному up Docker автоматично заллє сюди свіжі файли з імейджа.
docker volume rm ${PROJECT_NAME}_vendor ${PROJECT_NAME}_node_build 2>/dev/null || true

echo "🟢 4. Запускаємо інфраструктуру + app (без worker/scheduler)..."
# Піднімаємо тільки mysql/redis/app/nginx, щоб зробити migrate та прогріти кеш
# ДО того як worker і scheduler почнуть завантажувати Laravel bootstrap —
# інакше race між optimize:clear і booted(require routes-v7.php).
docker compose -f docker-compose.prod.yml up -d mysql redis app nginx

echo "🗄 5. Виконуємо міграції..."
# Прапорець -T потрібен, щоб скрипт не сварився, якщо його запускати через крон або CI/CD
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force

echo "🧹 6. Очищуємо та прогріваємо кеш Laravel..."
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
docker compose -f docker-compose.prod.yml exec -T app php artisan route:cache
docker compose -f docker-compose.prod.yml exec -T app php artisan view:cache

echo "🎯 7. Стартуємо worker і scheduler (з уже готовим кешем)..."
docker compose -f docker-compose.prod.yml up -d worker scheduler

echo "========================================"
echo "✅ Деплой успішно завершено!"
echo "========================================"
