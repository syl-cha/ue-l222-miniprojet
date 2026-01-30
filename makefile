# Makefile - (Linux, Mac, Windows Fixed)

# --- Variables ---
DOCKER_COMP = docker compose
PHP_CONT = $(DOCKER_COMP) exec -u www-data app php
SYMFONY = $(PHP_CONT) bin/console

# Commandes Shell Docker
# On utilise 'sh -c' pour passer des commandes complexes
DOCKER_SHELL = $(DOCKER_COMP) exec -u www-data app sh -c
ROOT_SHELL = $(DOCKER_COMP) exec -u 0 app sh -c

# --- Commandes Docker ---

## Démarre le projet
up:
	$(DOCKER_COMP) up -d --build

## Arrête les conteneurs
down:
	$(DOCKER_COMP) down

## Affiche les logs
logs:
	$(DOCKER_COMP) logs -f

## Accès terminal (bash)
bash:
	$(DOCKER_COMP) exec -it app bash

# --- Commandes Projet ---

## Installation complète (Safe for Windows)
install:
	@echo "--- 1. Check/Create .env.local (Inside Container) ---"
	$(DOCKER_SHELL) 'if [ ! -f .env.local ]; then echo "DATABASE_URL=\"sqlite:///%kernel.project_dir%/var/data.db\"" > .env.local; echo ".env.local created"; else echo ".env.local already exists"; fi'

	@echo "--- 2. Permissions Fix ---"
	$(ROOT_SHELL) 'chmod 644 .env.local'

	@echo "--- 3. Composer Install ---"
	$(ROOT_SHELL) 'composer install'
	$(ROOT_SHELL) 'chown -R www-data:www-data /var/www/html/var /var/www/html/vendor'

	@echo "--- 4. Database Reset ---"
	$(MAKE) db-reset

	@echo "--- 5. Assets Install ---"
	$(SYMFONY) importmap:install

## Réinitialise la BDD
db-reset:
	@echo "--- Dropping old DB ---"
	$(DOCKER_SHELL) 'rm -f var/data.db'
	@echo "--- Creating new DB ---"
	$(SYMFONY) doctrine:database:create
	@echo "--- Running Migrations ---"
	$(SYMFONY) doctrine:migrations:migrate --no-interaction
	@echo "--- Running Fixtures ---"
	$(SYMFONY) doctrine:fixtures:load --no-interaction

## Lance une commande Symfony (ex: make sf c="make:controller")
sf:
	$(SYMFONY) $(c)

## Vide le cache
cc:
	$(SYMFONY) cache:clear
