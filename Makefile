SHELL := /bin/bash
.DEFAULT_GOAL := help

COMPOSE := docker compose
APP_SERVICE := app
NODE_SERVICE := node

.PHONY: help init build up down restart ps logs shell root-shell artisan composer npm \
        migrate seed fresh test test-backend test-frontend lint format analyse \
        build-assets doctor db redis clean

help: ## Lista os comandos disponiveis
	@echo "Gestao de Clinicas — comandos disponiveis:"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'
	@echo ""
	@echo "Exemplos:"
	@echo "  make init                        # prepara o projeto do zero (idempotente)"
	@echo "  make artisan cmd=\"migrate\"        # roda um comando artisan"
	@echo "  make composer cmd=\"require foo\"  # roda um comando composer"
	@echo "  make npm cmd=\"run build\"          # roda um comando npm"
	@echo "  make fresh                       # migrate:fresh, pede confirmacao"

init: ## Prepara o projeto (idempotente): .env, build, up, deps, key, migrate, storage:link
	@bash docker/scripts/bootstrap.sh

build: ## Constroi as imagens Docker
	$(COMPOSE) build

up: ## Sobe todos os containers em background
	$(COMPOSE) up -d

down: ## Para e remove os containers (mantem volumes)
	$(COMPOSE) down

restart: down up ## Reinicia todos os containers

ps: ## Lista o status dos containers
	$(COMPOSE) ps

logs: ## Acompanha os logs de todos os servicos (use service=<nome> para um so)
	$(COMPOSE) logs -f $(service)

shell: ## Abre um shell no container app como usuario nao-root
	$(COMPOSE) exec $(APP_SERVICE) bash

root-shell: ## Abre um shell no container app como root
	$(COMPOSE) exec -u root $(APP_SERVICE) bash

artisan: ## Executa um comando artisan. Uso: make artisan cmd="migrate:status"
	$(COMPOSE) exec $(APP_SERVICE) php artisan $(cmd)

composer: ## Executa um comando composer. Uso: make composer cmd="require foo/bar"
	$(COMPOSE) exec $(APP_SERVICE) composer $(cmd)

npm: ## Executa um comando npm no container node. Uso: make npm cmd="run build"
	$(COMPOSE) exec $(NODE_SERVICE) npm $(cmd)

migrate: ## Executa as migrations pendentes
	$(COMPOSE) exec $(APP_SERVICE) php artisan migrate

seed: ## Executa os seeders
	$(COMPOSE) exec $(APP_SERVICE) php artisan db:seed

fresh: ## migrate:fresh — APAGA E RECRIA todas as tabelas. Pede confirmacao.
	@read -p "Isto vai APAGAR todas as tabelas do banco de aplicacao. Continuar? [y/N] " ans; \
	if [ "$$ans" = "y" ] || [ "$$ans" = "Y" ]; then \
		$(COMPOSE) exec $(APP_SERVICE) php artisan migrate:fresh; \
	else \
		echo "Cancelado."; \
	fi

test: test-backend test-frontend ## Executa os testes de backend e frontend

test-backend: ## Executa os testes Pest
	$(COMPOSE) exec $(APP_SERVICE) php artisan test

test-frontend: ## Executa os testes Vitest
	$(COMPOSE) exec $(NODE_SERVICE) npm run test:run

lint: ## Executa Pint (verificacao) e ESLint
	$(COMPOSE) exec $(APP_SERVICE) ./vendor/bin/pint --test
	$(COMPOSE) exec $(NODE_SERVICE) npm run lint

format: ## Aplica formatacao (Pint + Prettier)
	$(COMPOSE) exec $(APP_SERVICE) ./vendor/bin/pint
	$(COMPOSE) exec $(NODE_SERVICE) npm run format

analyse: ## Executa Larastan/PHPStan e vue-tsc
	$(COMPOSE) exec $(APP_SERVICE) ./vendor/bin/phpstan analyse
	$(COMPOSE) exec $(NODE_SERVICE) npm run types

build-assets: ## Compila os assets de producao (vite build)
	$(COMPOSE) exec $(NODE_SERVICE) npm run build

doctor: ## Executa o diagnostico completo da infraestrutura
	@bash docker/scripts/doctor.sh

db: ## Abre um psql no banco de aplicacao
	$(COMPOSE) exec postgres psql -U $${DB_USERNAME:-gestao_clinicas} -d $${DB_DATABASE:-gestao_clinicas}

redis: ## Abre um redis-cli autenticado
	$(COMPOSE) exec redis sh -c 'redis-cli -a "$$REDIS_PASSWORD" --no-auth-warning'

clean: ## Remove containers. Pede confirmacao extra para apagar volumes (banco/redis/minio)
	$(COMPOSE) down
	@read -p "Também remover os volumes (dados de Postgres/Redis/MinIO serão perdidos)? [y/N] " ans; \
	if [ "$$ans" = "y" ] || [ "$$ans" = "Y" ]; then \
		read -p "Tem certeza? Esta ação é irreversível. Digite 'sim' para confirmar: " confirm; \
		if [ "$$confirm" = "sim" ]; then \
			$(COMPOSE) down -v; \
		else \
			echo "Cancelado."; \
		fi \
	fi
