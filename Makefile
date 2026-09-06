SHELL := /bin/bash
.DEFAULT_GOAL := help

COMPOSE := docker compose
COMPOSE_PROD := docker compose -f compose.yaml -f compose.prod.yaml

APP_SERVICE := app
NODE_SERVICE := node

.PHONY: \
	help \
	init \
	build \
	up \
	down \
	restart \
	ps \
	logs \
	shell \
	root-shell \
	artisan \
	composer \
	npm \
	migrate \
	seed \
	fresh \
	test \
	test-backend \
	test-frontend \
	lint \
	format \
	analyse \
	build-assets \
	doctor \
	db \
	redis \
	clean \
	prod-build \
	prod-up \
	prod-down \
	prod-restart \
	prod-ps \
	prod-logs \
	prod-shell \
	prod-root-shell \
	prod-artisan \
	prod-composer \
	prod-npm \
	prod-migrate \
	prod-seed \
	prod-config-cache \
	prod-route-cache \
	prod-view-cache \
	prod-optimize \
	prod-optimize-clear \
	prod-build-assets \
	prod-db \
	prod-redis \
	prod-status \
	prod-deploy \
	prod-queue-restart

help: ## Lista os comandos disponiveis
	@echo "Gestao de Clinicas — comandos disponiveis:"
	@echo ""
	@echo "DESENVOLVIMENTO"
	@echo "  make init                         Prepara o projeto do zero"
	@echo "  make build                        Constroi as imagens"
	@echo "  make up                           Sobe os containers"
	@echo "  make down                         Para os containers"
	@echo "  make restart                      Reinicia os containers"
	@echo "  make ps                           Lista containers"
	@echo "  make logs                         Acompanha logs"
	@echo "  make logs service=app             Logs de um servico"
	@echo "  make shell                        Shell no container app"
	@echo "  make artisan cmd=\"migrate\"        Executa Artisan"
	@echo "  make composer cmd=\"install\"       Executa Composer"
	@echo "  make npm cmd=\"run build\"          Executa NPM"
	@echo "  make migrate                      Executa migrations"
	@echo "  make seed                         Executa seeders"
	@echo "  make fresh                        Recria banco com confirmacao"
	@echo "  make test                         Executa todos os testes"
	@echo "  make lint                         Executa verificacoes de lint"
	@echo "  make format                       Formata o projeto"
	@echo "  make analyse                      Executa analise estatica"
	@echo "  make doctor                       Diagnostico da infraestrutura"
	@echo ""
	@echo "PRODUCAO"
	@echo "  make prod-build                   Constroi imagens de producao"
	@echo "  make prod-up                      Sobe ambiente de producao"
	@echo "  make prod-down                    Para ambiente de producao"
	@echo "  make prod-restart                 Reinicia ambiente de producao"
	@echo "  make prod-ps                      Lista containers de producao"
	@echo "  make prod-status                  Status detalhado da producao"
	@echo "  make prod-logs                    Logs de producao"
	@echo "  make prod-logs service=app        Logs de um servico"
	@echo "  make prod-shell                   Shell no app de producao"
	@echo "  make prod-artisan cmd=\"about\"     Executa Artisan em producao"
	@echo "  make prod-migrate                 Executa migrations com --force"
	@echo "  make prod-seed                    Executa seeders com --force"
	@echo "  make prod-build-assets            Compila assets de producao"
	@echo "  make prod-optimize                Cria caches Laravel"
	@echo "  make prod-optimize-clear          Limpa caches Laravel"
	@echo "  make prod-deploy                  Executa fluxo padrao de deploy"
	@echo "  make prod-queue-restart           Reinicia so os workers de fila"
	@echo ""

# =========================================================
# DESENVOLVIMENTO
# =========================================================

init: ## Prepara o projeto do zero
	@bash docker/scripts/bootstrap.sh

build: ## Constroi as imagens Docker
	$(COMPOSE) build

up: ## Sobe todos os containers em background
	$(COMPOSE) up -d

down: ## Para e remove containers mantendo volumes
	$(COMPOSE) down

restart: down up ## Reinicia todos os containers

ps: ## Lista o status dos containers
	$(COMPOSE) ps

logs: ## Acompanha logs. Use service=<nome>
	$(COMPOSE) logs -f $(service)

shell: ## Abre shell no container app
	$(COMPOSE) exec $(APP_SERVICE) bash

root-shell: ## Abre shell root no container app
	$(COMPOSE) exec -u root $(APP_SERVICE) bash

artisan: ## Executa Artisan. Uso: make artisan cmd="migrate:status"
	$(COMPOSE) exec $(APP_SERVICE) php artisan $(cmd)

composer: ## Executa Composer. Uso: make composer cmd="require foo/bar"
	$(COMPOSE) exec $(APP_SERVICE) composer $(cmd)

npm: ## Executa NPM. Uso: make npm cmd="run build"
	$(COMPOSE) exec $(NODE_SERVICE) npm $(cmd)

migrate: ## Executa migrations pendentes
	$(COMPOSE) exec $(APP_SERVICE) php artisan migrate

seed: ## Executa seeders
	$(COMPOSE) exec $(APP_SERVICE) php artisan db:seed

fresh: ## migrate:fresh com confirmacao
	@read -p "Isto vai APAGAR todas as tabelas do banco de aplicacao. Continuar? [y/N] " ans; \
	if [ "$$ans" = "y" ] || [ "$$ans" = "Y" ]; then \
		$(COMPOSE) exec $(APP_SERVICE) php artisan migrate:fresh; \
	else \
		echo "Cancelado."; \
	fi

test: test-backend test-frontend ## Executa testes backend e frontend

test-backend: ## Executa testes Pest
	$(COMPOSE) exec $(APP_SERVICE) php artisan test

test-frontend: ## Executa testes Vitest
	$(COMPOSE) exec $(NODE_SERVICE) npm run test:run

lint: ## Executa Pint e ESLint
	$(COMPOSE) exec $(APP_SERVICE) ./vendor/bin/pint --test
	$(COMPOSE) exec $(NODE_SERVICE) npm run lint

format: ## Aplica Pint e Prettier
	$(COMPOSE) exec $(APP_SERVICE) ./vendor/bin/pint
	$(COMPOSE) exec $(NODE_SERVICE) npm run format

analyse: ## Executa PHPStan/Larastan e vue-tsc
	$(COMPOSE) exec $(APP_SERVICE) ./vendor/bin/phpstan analyse
	$(COMPOSE) exec $(NODE_SERVICE) npm run types

build-assets: ## Compila assets com Vite
	$(COMPOSE) exec $(NODE_SERVICE) npm run build

doctor: ## Executa diagnostico da infraestrutura
	@bash docker/scripts/doctor.sh

db: ## Abre psql no banco
	$(COMPOSE) exec postgres sh -c 'psql -U "$${POSTGRES_USER:-gestao_clinicas}" -d "$${POSTGRES_DB:-gestao_clinicas}"'

redis: ## Abre redis-cli autenticado
	$(COMPOSE) exec redis sh -c 'redis-cli -a "$$REDIS_PASSWORD" --no-auth-warning'

clean: ## Remove containers e opcionalmente volumes
	$(COMPOSE) down
	@read -p "Tambem remover os volumes? Dados de Postgres/Redis/MinIO serao perdidos. [y/N] " ans; \
	if [ "$$ans" = "y" ] || [ "$$ans" = "Y" ]; then \
		read -p "Digite 'sim' para confirmar: " confirm; \
		if [ "$$confirm" = "sim" ]; then \
			$(COMPOSE) down -v; \
		else \
			echo "Cancelado."; \
		fi \
	fi

# =========================================================
# PRODUCAO
# =========================================================

prod-build: ## Constroi imagens de producao
	$(COMPOSE_PROD) build

prod-up: ## Sobe ambiente de producao
	$(COMPOSE_PROD) up -d

prod-down: ## Para ambiente mantendo volumes
	$(COMPOSE_PROD) down

prod-restart: ## Reinicia ambiente de producao
	$(COMPOSE_PROD) down
	$(COMPOSE_PROD) up -d

prod-ps: ## Lista containers de producao
	$(COMPOSE_PROD) ps

prod-status: ## Exibe status e health dos containers
	@echo "=== Containers ==="
	@$(COMPOSE_PROD) ps
	@echo ""
	@echo "=== Docker ==="
	@docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

prod-logs: ## Acompanha logs. Use service=<nome>
	$(COMPOSE_PROD) logs -f $(service)

prod-shell: ## Shell no container app de producao
	$(COMPOSE_PROD) exec $(APP_SERVICE) bash

prod-root-shell: ## Shell root no container app
	$(COMPOSE_PROD) exec -u root $(APP_SERVICE) bash

prod-artisan: ## Artisan em producao. Uso: make prod-artisan cmd="about"
	$(COMPOSE_PROD) exec $(APP_SERVICE) php artisan $(cmd)

prod-composer: ## Composer em producao
	$(COMPOSE_PROD) exec $(APP_SERVICE) composer $(cmd)

prod-npm: ## NPM em producao
	$(COMPOSE_PROD) exec $(NODE_SERVICE) npm $(cmd)

prod-migrate: ## Executa migrations em producao
	$(COMPOSE_PROD) exec $(APP_SERVICE) php artisan migrate --force

prod-seed: ## Executa seeders em producao
	$(COMPOSE_PROD) exec $(APP_SERVICE) php artisan db:seed --force

prod-build-assets: ## Compila os assets para producao
	$(COMPOSE_PROD) exec $(NODE_SERVICE) npm run build

prod-config-cache: ## Gera cache de configuracao
	$(COMPOSE_PROD) exec $(APP_SERVICE) php artisan config:cache

prod-route-cache: ## Gera cache de rotas
	$(COMPOSE_PROD) exec $(APP_SERVICE) php artisan route:cache

prod-view-cache: ## Gera cache das views
	$(COMPOSE_PROD) exec $(APP_SERVICE) php artisan view:cache

prod-optimize: ## Executa otimizacoes Laravel
	$(COMPOSE_PROD) exec $(APP_SERVICE) php artisan optimize

prod-optimize-clear: ## Limpa todos os caches Laravel
	$(COMPOSE_PROD) exec $(APP_SERVICE) php artisan optimize:clear

prod-db: ## Abre psql no banco de producao
	$(COMPOSE_PROD) exec postgres sh -c 'psql -U "$${POSTGRES_USER:-gestao_clinicas}" -d "$${POSTGRES_DB:-gestao_clinicas}"'

prod-redis: ## Abre redis-cli de producao
	$(COMPOSE_PROD) exec redis sh -c 'redis-cli -a "$$REDIS_PASSWORD" --no-auth-warning'

prod-deploy: ## Fluxo padrao de deploy da aplicacao (build, up, migrations, otimizacao, health check, rollback automatico em falha)
	@bash scripts/deploy.sh

prod-queue-restart: ## Reinicia os workers de fila sem refazer todo o deploy
	$(COMPOSE_PROD) exec $(APP_SERVICE) php artisan queue:restart
