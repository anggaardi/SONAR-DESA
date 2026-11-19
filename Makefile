# Daily commands (PRD Arsitektur 7.3). Everything runs in containers; the host only needs Docker.
.DEFAULT_GOAL := help
SHELL := /bin/bash

export UID := $(shell id -u)
export GID := $(shell id -g)

DC   := docker compose -f compose.dev.yml
APP  := $(DC) exec app
RUN  := $(DC) run --rm --no-deps app
NODE := $(DC) run --rm --no-deps vite

.PHONY: help setup up down restart logs ps sh tinker migrate fresh test test-php test-js lint format build-prod

help: ## Show available commands
	@grep -E '^[a-z-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

setup: ## First run: .env, images, dependencies, key, database
	@test -f .env || cp .env.example .env
	$(DC) build
	$(RUN) composer install --no-interaction
	$(NODE) npm ci --no-audit --no-fund
	@# Only on first run, so re-running setup after a pull keeps the existing key.
	@grep -q '^APP_KEY=base64:' .env || $(RUN) php artisan key:generate --ansi
	$(DC) up -d db
	$(RUN) php artisan migrate --seed --force
	@echo "Setup done. Run 'make up' and open http://localhost:$${APP_PORT:-8080}"

up: ## Start all dev services
	$(DC) up -d
	@echo "App: http://localhost:$${APP_PORT:-8080}"

down: ## Stop all dev services
	$(DC) down

restart: ## Restart PHP services (after .env changes)
	$(DC) restart app queue scheduler

logs: ## Follow logs of all services
	$(DC) logs -f --tail=100

ps: ## Service status
	$(DC) ps

sh: ## Shell in the app container
	$(APP) sh

tinker: ## Laravel tinker
	$(APP) php artisan tinker

migrate: ## Run new migrations
	$(APP) php artisan migrate

fresh: ## Rebuild the database with seed data
	$(APP) php artisan migrate:fresh --seed

test: test-php test-js ## All tests

test-php: ## Pest on the Postgres "testing" database
	$(APP) php artisan test --compact $(ARGS)

test-js: ## Vitest for resources/js/lib
	$(NODE) npm run test

lint: ## Pint, Larastan, neutral language, ESLint, vue-tsc (same as CI)
	sh scripts/check-neutral-language.sh
	$(APP) vendor/bin/pint --test
	$(APP) vendor/bin/phpstan analyse --memory-limit=1G --no-progress
	$(NODE) npm run lint
	$(NODE) npm run typecheck

format: ## Auto-fix PHP and JS formatting
	$(APP) vendor/bin/pint
	$(NODE) npm run format

build-prod: ## Build production images locally (same as the server)
	docker compose -f compose.prod.yml --env-file .env.example build --build-arg GIT_COMMIT=$$(git rev-parse --short HEAD 2>/dev/null || echo dev)
