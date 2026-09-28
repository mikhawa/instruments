DC      = docker compose
PHP     = $(DC) exec php
CONSOLE = $(PHP) php bin/console

export UID := $(shell id -u)
export GID := $(shell id -g)

.DEFAULT_GOAL := help
.PHONY: help build up down restart logs sh console composer db-reset test npm front-build

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

build: ## Construit les images
	$(DC) build --pull

up: ## Démarre l'environnement (installe Symfony au premier lancement)
	$(DC) up -d --build

down: ## Arrête l'environnement
	$(DC) down

restart: down up ## Redémarre l'environnement

logs: ## Affiche les logs (make logs s=php)
	$(DC) logs -f $(s)

sh: ## Ouvre un shell dans le conteneur PHP
	$(PHP) bash

console: ## Lance une commande Symfony (make console c="cache:clear")
	$(CONSOLE) $(c)

composer: ## Lance Composer (make composer c="require api")
	$(PHP) composer $(c)

db-reset: ## Recrée la base et joue les migrations
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

test: ## Lance les tests PHPUnit
	$(PHP) php bin/phpunit

npm: ## Lance npm dans le front React (make npm c="install react-router")
	$(DC) exec node npm $(c)

front-build: ## Compile le front React pour la production (frontend/dist)
	$(DC) run --rm --no-deps node npm run build
