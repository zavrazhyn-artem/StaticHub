# =============================================================================
# Makefile – Local development + Production deployment automation
# =============================================================================
# Usage: make <target>
#
# Local (docker-compose.yml):
#   make init        – first-time setup (build → up → migrate → key → link)
#   make up          – start local containers
#   make down        – stop local containers
#
# Production (docker-compose.prod.yml):
#   make deploy      – full deploy pipeline
# =============================================================================

APP_NAME    ?= app
APP_VERSION ?= latest

# Production
COMPOSE_FILE  = docker-compose.prod.yml
COMPOSE       = docker compose -f $(COMPOSE_FILE)
APP_CONTAINER = app

# Local (uses docker-compose.yml – the default)
LOCAL_COMPOSE    = docker compose
LOCAL_CONTAINER  = app

# Colours for output
GREEN  = \033[0;32m
YELLOW = \033[0;33m
RED    = \033[0;31m
RESET  = \033[0m

.PHONY: help init up down build up-prod down-prod restart \
        deploy migrate migrate-fresh \
        cache-clear cache-warm \
        shell worker-shell \
        logs logs-worker logs-nginx \
        storage-link npm-build \
        ps pull

# ── Default target ────────────────────────────────────────────────────────────
help:
	@echo ""
	@echo "$(GREEN)Available targets:$(RESET)"
	@echo ""
	@echo "  $(YELLOW)Local development$(RESET)"
	@echo "  make init            First-time setup: .env check → npm build → docker build → up → migrate → key → storage:link"
	@echo "  make up              Start local containers (detached)"
	@echo "  make down            Stop local containers (volumes preserved)"
	@echo ""
	@echo "  $(YELLOW)Production deployment$(RESET)"
	@echo "  make deploy          Full deploy: build → up → migrate → cache warm"
	@echo "  make build           Build (or rebuild) production Docker images"
	@echo "  make up-prod         Start all production containers (detached)"
	@echo "  make down-prod       Stop and remove containers (volumes preserved)"
	@echo "  make restart         Restart app + worker + scheduler containers"
	@echo "  make pull            Pull latest base images before build"
	@echo ""
	@echo "  $(YELLOW)Database$(RESET)"
	@echo "  make migrate         Run pending migrations (safe for production)"
	@echo "  make migrate-fresh   !! DROP & recreate all tables (dev/staging only)"
	@echo ""
	@echo "  $(YELLOW)Cache$(RESET)"
	@echo "  make cache-clear     Clear config, route, view, and application cache"
	@echo "  make cache-warm      Compile and cache config, routes, and views"
	@echo ""
	@echo "  $(YELLOW)Assets$(RESET)"
	@echo "  make npm-build       Build Vite assets on the host"
	@echo "  make storage-link    Create public symlink to storage"
	@echo ""
	@echo "  $(YELLOW)Debugging$(RESET)"
	@echo "  make shell           Open a bash shell in the app container"
	@echo "  make worker-shell    Open a bash shell in the worker container"
	@echo "  make logs            Tail logs from all containers"
	@echo "  make logs-worker     Tail queue worker logs only"
	@echo "  make logs-nginx      Tail Nginx logs only"
	@echo "  make ps              Show running container status"
	@echo ""

# ── Local: first-time setup ───────────────────────────────────────────────────

init:
	@echo ""
	@echo "$(GREEN)=== Local environment setup ===$(RESET)"
	@echo ""

	@# 1. Ensure .env exists
	@if [ ! -f .env ]; then \
		echo "$(YELLOW)[1/7] .env not found – copying .env.example$(RESET)"; \
		cp .env.example .env; \
		echo "$(RED)>> Fill in secrets in .env, then re-run: make init$(RESET)"; \
		exit 1; \
	fi
	@echo "$(GREEN)[1/7] .env found$(RESET)"

	@# 2. Build Vite assets on the host so nginx has ./public/build to serve
	@echo "$(GREEN)[2/7] Building Vite assets (npm ci + npm run build)...$(RESET)"
	npm ci --no-audit --no-fund
	npm run build

	@# 3. Build Docker images (uses docker-compose.yml)
	@echo "$(GREEN)[3/7] Building Docker images...$(RESET)"
	$(LOCAL_COMPOSE) build

	@# 4. Start all containers
	@echo "$(GREEN)[4/7] Starting containers...$(RESET)"
	$(LOCAL_COMPOSE) up -d --remove-orphans

	@# 5. Wait until the app container accepts artisan commands
	@echo "$(GREEN)[5/7] Waiting for app container to be ready...$(RESET)"
	@attempt=0; \
	until $(LOCAL_COMPOSE) exec -T $(LOCAL_CONTAINER) php artisan --version > /dev/null 2>&1; do \
		attempt=$$((attempt + 1)); \
		if [ $$attempt -ge 30 ]; then \
			echo "$(RED)Timed out waiting for app container. Check: make logs$(RESET)"; \
			exit 1; \
		fi; \
		printf '.'; sleep 2; \
	done
	@echo ""

	@# 6. Generate APP_KEY only if not already set
	@echo "$(GREEN)[6/7] Checking APP_KEY...$(RESET)"
	@if grep -qE '^APP_KEY=\s*$$' .env; then \
		echo "  APP_KEY is empty – generating..."; \
		$(LOCAL_COMPOSE) exec -T $(LOCAL_CONTAINER) php artisan key:generate --ansi; \
	else \
		echo "  APP_KEY already set – skipping"; \
	fi

	@# 7. Migrate + storage:link
	@echo "$(GREEN)[7/7] Running migrations and storage:link...$(RESET)"
	$(LOCAL_COMPOSE) exec -T $(LOCAL_CONTAINER) php artisan migrate --force
	$(LOCAL_COMPOSE) exec -T $(LOCAL_CONTAINER) php artisan storage:link

	@echo ""
	@echo "$(GREEN)Local environment is ready!$(RESET)"
	@echo "  App:    http://localhost"
	@echo "  Health: http://localhost/up"
	@echo ""
	@echo "  Useful commands:"
	@echo "    make logs          – tail all container logs"
	@echo "    make shell         – bash shell in app container"
	@echo "    make down          – stop all containers"
	@echo ""

# ── Local: day-to-day ─────────────────────────────────────────────────────────

up:
	@echo "$(GREEN)Starting local containers...$(RESET)"
	$(LOCAL_COMPOSE) up -d --remove-orphans

down:
	@echo "$(YELLOW)Stopping local containers (volumes preserved)...$(RESET)"
	$(LOCAL_COMPOSE) down

# ── Build ─────────────────────────────────────────────────────────────────────

pull:
	@echo "$(GREEN)Pulling latest base images...$(RESET)"
	docker pull php:8.5-fpm-bookworm
	docker pull node:22-alpine
	docker pull nginx:1.27-alpine
	docker pull mysql:8.4
	docker pull redis:7-alpine

build:
	@echo "$(GREEN)Building production images...$(RESET)"
	$(COMPOSE) build --no-cache \
		--build-arg APP_NAME=$(APP_NAME) \
		--build-arg APP_VERSION=$(APP_VERSION)

# ── Containers ────────────────────────────────────────────────────────────────

up-prod:
	@echo "$(GREEN)Starting production containers...$(RESET)"
	$(COMPOSE) up -d --remove-orphans

down-prod:
	@echo "$(YELLOW)Stopping containers (volumes preserved)...$(RESET)"
	$(COMPOSE) down

restart:
	@echo "$(GREEN)Restarting app, worker, and scheduler...$(RESET)"
	$(COMPOSE) restart app worker scheduler

ps:
	$(COMPOSE) ps

# ── Full deploy pipeline ──────────────────────────────────────────────────────

deploy: build up-prod migrate cache-warm storage-link
	@echo ""
	@echo "$(GREEN)Deploy complete.$(RESET)"
	@echo "  App:    http://localhost (or your server IP)"
	@echo "  Health: http://localhost/up"
	@echo ""

# ── Database ─────────────────────────────────────────────────────────────────

migrate:
	@echo "$(GREEN)Running migrations...$(RESET)"
	$(COMPOSE) exec $(APP_CONTAINER) php artisan migrate --force

migrate-fresh:
	@echo "$(YELLOW)WARNING: This will destroy all data!$(RESET)"
	@read -p "Type 'yes' to continue: " confirm && [ "$$confirm" = "yes" ]
	$(COMPOSE) exec $(APP_CONTAINER) php artisan migrate:fresh --force

# ── Cache ─────────────────────────────────────────────────────────────────────

cache-clear:
	@echo "$(GREEN)Clearing all caches...$(RESET)"
	$(COMPOSE) exec $(APP_CONTAINER) php artisan optimize:clear

cache-warm:
	@echo "$(GREEN)Warming up caches (config + routes + views)...$(RESET)"
	$(COMPOSE) exec $(APP_CONTAINER) php artisan optimize
	$(COMPOSE) exec $(APP_CONTAINER) php artisan view:cache

# ── Assets ───────────────────────────────────────────────────────────────────

npm-build:
	@echo "$(GREEN)Building Vite assets for production...$(RESET)"
	npm ci --no-audit --no-fund
	npm run build

storage-link:
	@echo "$(GREEN)Creating storage symlink...$(RESET)"
	$(COMPOSE) exec $(APP_CONTAINER) php artisan storage:link

# ── Shell access ──────────────────────────────────────────────────────────────

shell:
	$(COMPOSE) exec $(APP_CONTAINER) bash

worker-shell:
	$(COMPOSE) exec worker bash

# ── Logs ─────────────────────────────────────────────────────────────────────

logs:
	$(COMPOSE) logs -f --tail=100

logs-worker:
	$(COMPOSE) logs -f --tail=100 worker

logs-nginx:
	$(COMPOSE) logs -f --tail=100 nginx
