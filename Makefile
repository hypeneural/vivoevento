# ------------------------------------------------------------------------------
# Evento Vivo Makefile
# ------------------------------------------------------------------------------

.PHONY: help setup setup-web setup-landing dev api web landing queue reverb test test-api test-filter test-coverage test-web test-web-watch test-landing fresh seed migrate rollback lint lint-fix lint-web lint-landing type-check analyze docker-up docker-down docker-logs docker-reset routes clear cache tinker docs horizon telescope

API_DIR = apps/api
WEB_DIR = apps/web
LANDING_DIR = apps/landing

help: ## Mostra esta ajuda
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

# ------------------------------------------------------------------------------
# Setup
# ------------------------------------------------------------------------------

setup: ## Setup inicial completo (API + frontends)
	@echo "Setting up Evento Vivo..."
	cd $(API_DIR) && composer install
	cd $(API_DIR) && cp -n .env.example .env || true
	cd $(API_DIR) && php artisan key:generate
	cd $(API_DIR) && php artisan migrate --seed
	cd $(WEB_DIR) && npm install
	cd $(WEB_DIR) && cp -n .env.example .env || true
	cd $(LANDING_DIR) && npm install
	cd $(LANDING_DIR) && cp -n .env.example .env || true
	@echo "Setup complete."

setup-web: ## Setup apenas do painel admin
	cd $(WEB_DIR) && npm install
	cd $(WEB_DIR) && cp -n .env.example .env || true
	@echo "Admin frontend setup complete."

setup-landing: ## Setup apenas da landing page
	cd $(LANDING_DIR) && npm install
	cd $(LANDING_DIR) && cp -n .env.example .env || true
	@echo "Landing setup complete."

# ------------------------------------------------------------------------------
# Development
# ------------------------------------------------------------------------------

dev: ## Roda API + Horizon + Reverb + admin + landing em paralelo
	@echo "Starting Evento Vivo..."
	cd $(API_DIR) && php artisan serve & \
	cd $(API_DIR) && php artisan horizon & \
	cd $(API_DIR) && php artisan reverb:start & \
	cd $(WEB_DIR) && npm run dev & \
	cd $(LANDING_DIR) && npm run dev & \
	wait

api: ## Roda apenas o servidor Laravel
	cd $(API_DIR) && php artisan serve

web: ## Roda apenas o painel admin
	cd $(WEB_DIR) && npm run dev

landing: ## Roda apenas a landing page
	cd $(LANDING_DIR) && npm run dev

queue: ## Roda Horizon (filas)
	cd $(API_DIR) && php artisan horizon

reverb: ## Roda Reverb (WebSocket)
	cd $(API_DIR) && php artisan reverb:start

horizon: ## Abre dashboard do Horizon
	@echo "Horizon: http://localhost:8000/horizon"

telescope: ## Abre dashboard do Telescope
	@echo "Telescope: http://localhost:8000/telescope"

# ------------------------------------------------------------------------------
# Database
# ------------------------------------------------------------------------------

fresh: ## Reseta banco e roda seeders
	cd $(API_DIR) && php artisan migrate:fresh --seed

seed: ## Roda seeders
	cd $(API_DIR) && php artisan db:seed

migrate: ## Roda migrations
	cd $(API_DIR) && php artisan migrate

rollback: ## Rollback ultima migration
	cd $(API_DIR) && php artisan migrate:rollback

# ------------------------------------------------------------------------------
# Testing
# ------------------------------------------------------------------------------

test: ## Roda todos os testes (API + Web + Landing)
	cd $(API_DIR) && php artisan test
	cd $(WEB_DIR) && npm run test
	cd $(LANDING_DIR) && npm run test

test-api: ## Roda testes do backend
	cd $(API_DIR) && php artisan test

test-filter: ## Roda testes filtrados (uso: make test-filter F=Events)
	cd $(API_DIR) && php artisan test --filter=$(F)

test-coverage: ## Roda testes com coverage
	cd $(API_DIR) && php artisan test --coverage

test-web: ## Roda testes do painel admin
	cd $(WEB_DIR) && npm run test

test-web-watch: ## Roda testes do painel admin em watch mode
	cd $(WEB_DIR) && npm run test:watch

test-landing: ## Roda testes da landing
	cd $(LANDING_DIR) && npm run test

# ------------------------------------------------------------------------------
# Quality
# ------------------------------------------------------------------------------

lint: ## Roda linters (API + Web + Landing)
	cd $(API_DIR) && ./vendor/bin/pint
	cd $(WEB_DIR) && npm run lint
	cd $(LANDING_DIR) && npm run lint

lint-fix: ## Roda linters e corrige automaticamente
	cd $(API_DIR) && ./vendor/bin/pint --repair
	cd $(WEB_DIR) && npm run lint:fix
	cd $(LANDING_DIR) && npm run lint:fix

lint-web: ## Roda apenas lint do painel admin
	cd $(WEB_DIR) && npm run lint

lint-landing: ## Roda apenas lint da landing
	cd $(LANDING_DIR) && npm run lint

type-check: ## Verifica tipos TypeScript
	cd $(WEB_DIR) && npm run type-check
	cd $(LANDING_DIR) && npm run type-check

analyze: ## Roda analise estatica (se PHPStan instalado)
	cd $(API_DIR) && ./vendor/bin/phpstan analyse

# ------------------------------------------------------------------------------
# Docker
# ------------------------------------------------------------------------------

docker-up: ## Sobe containers Docker
	docker-compose up -d

docker-down: ## Para containers Docker
	docker-compose down

docker-logs: ## Mostra logs dos containers
	docker-compose logs -f

docker-reset: ## Remove volumes e recria containers
	docker-compose down -v
	docker-compose up -d

# ------------------------------------------------------------------------------
# Utilities
# ------------------------------------------------------------------------------

routes: ## Lista todas as rotas da API
	cd $(API_DIR) && php artisan route:list --path=api

clear: ## Limpa todos os caches
	cd $(API_DIR) && php artisan optimize:clear

cache: ## Gera caches de producao
	cd $(API_DIR) && php artisan optimize

tinker: ## Abre o Tinker
	cd $(API_DIR) && php artisan tinker

docs: ## Gera documentacao da API (futuro)
	@echo "API docs generation not yet configured"
