# ──────────────────────────────────────────────────────────────
# Evento Vivo — Makefile
# ──────────────────────────────────────────────────────────────

.PHONY: help setup setup-web dev api web queue reverb test test-web fresh lint lint-web type-check docs docker-up docker-down seed horizon telescope

API_DIR = apps/api
WEB_DIR = apps/web

help: ## Mostra esta ajuda
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

# ──────────────────────────────────────────────────────────────
# Setup
# ──────────────────────────────────────────────────────────────

setup: ## Setup inicial completo (API + Web)
	@echo "🚀 Setting up Evento Vivo..."
	cd $(API_DIR) && composer install
	cd $(API_DIR) && cp -n .env.example .env || true
	cd $(API_DIR) && php artisan key:generate
	cd $(API_DIR) && php artisan migrate --seed
	cd $(WEB_DIR) && npm install
	cd $(WEB_DIR) && cp -n .env.example .env || true
	@echo "✅ Setup complete!"

setup-web: ## Setup apenas do frontend
	cd $(WEB_DIR) && npm install
	cd $(WEB_DIR) && cp -n .env.example .env || true
	@echo "✅ Frontend setup complete!"

# ──────────────────────────────────────────────────────────────
# Development
# ──────────────────────────────────────────────────────────────

dev: ## Roda API + Horizon + Reverb + Frontend em paralelo
	@echo "🎉 Starting Evento Vivo..."
	cd $(API_DIR) && php artisan serve & \
	cd $(API_DIR) && php artisan horizon & \
	cd $(API_DIR) && php artisan reverb:start & \
	cd $(WEB_DIR) && npm run dev & \
	wait

api: ## Roda apenas o servidor Laravel
	cd $(API_DIR) && php artisan serve

web: ## Roda apenas o frontend Vite
	cd $(WEB_DIR) && npm run dev

queue: ## Roda Horizon (filas)
	cd $(API_DIR) && php artisan horizon

reverb: ## Roda Reverb (WebSocket)
	cd $(API_DIR) && php artisan reverb:start

horizon: ## Abre dashboard do Horizon
	@echo "Horizon: http://localhost:8000/horizon"

telescope: ## Abre dashboard do Telescope
	@echo "Telescope: http://localhost:8000/telescope"

# ──────────────────────────────────────────────────────────────
# Database
# ──────────────────────────────────────────────────────────────

fresh: ## Reseta banco e roda seeders
	cd $(API_DIR) && php artisan migrate:fresh --seed

seed: ## Roda seeders
	cd $(API_DIR) && php artisan db:seed

migrate: ## Roda migrations
	cd $(API_DIR) && php artisan migrate

rollback: ## Rollback última migration
	cd $(API_DIR) && php artisan migrate:rollback

# ──────────────────────────────────────────────────────────────
# Testing
# ──────────────────────────────────────────────────────────────

test: ## Roda todos os testes (API + Web)
	cd $(API_DIR) && php artisan test
	cd $(WEB_DIR) && npm run test

test-api: ## Roda testes do backend
	cd $(API_DIR) && php artisan test

test-filter: ## Roda testes filtrados (uso: make test-filter F=Events)
	cd $(API_DIR) && php artisan test --filter=$(F)

test-coverage: ## Roda testes com coverage
	cd $(API_DIR) && php artisan test --coverage

test-web: ## Roda testes do frontend
	cd $(WEB_DIR) && npm run test

test-web-watch: ## Roda testes do frontend em watch mode
	cd $(WEB_DIR) && npm run test:watch

# ──────────────────────────────────────────────────────────────
# Quality
# ──────────────────────────────────────────────────────────────

lint: ## Roda linters (API + Web)
	cd $(API_DIR) && ./vendor/bin/pint
	cd $(WEB_DIR) && npm run lint

lint-fix: ## Roda linters e corrige automaticamente
	cd $(API_DIR) && ./vendor/bin/pint --repair
	cd $(WEB_DIR) && npm run lint:fix

lint-web: ## Roda apenas lint do frontend
	cd $(WEB_DIR) && npm run lint

type-check: ## Verifica tipos TypeScript
	cd $(WEB_DIR) && npm run type-check

analyze: ## Roda análise estática (se PHPStan instalado)
	cd $(API_DIR) && ./vendor/bin/phpstan analyse

# ──────────────────────────────────────────────────────────────
# Docker
# ──────────────────────────────────────────────────────────────

docker-up: ## Sobe containers Docker
	docker-compose up -d

docker-down: ## Para containers Docker
	docker-compose down

docker-logs: ## Mostra logs dos containers
	docker-compose logs -f

docker-reset: ## Remove volumes e recria containers
	docker-compose down -v
	docker-compose up -d

# ──────────────────────────────────────────────────────────────
# Utilities
# ──────────────────────────────────────────────────────────────

routes: ## Lista todas as rotas da API
	cd $(API_DIR) && php artisan route:list --path=api

clear: ## Limpa todos os caches
	cd $(API_DIR) && php artisan optimize:clear

cache: ## Gera caches de produção
	cd $(API_DIR) && php artisan optimize

tinker: ## Abre o Tinker
	cd $(API_DIR) && php artisan tinker

docs: ## Gera documentação da API (futuro)
	@echo "📚 API docs generation not yet configured"
