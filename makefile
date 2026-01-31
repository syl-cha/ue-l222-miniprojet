# Makefile - version Windows/Docker/Symfony

# --- Variables ---
DOCKER_COMP = docker compose
PHP_CONT = $(DOCKER_COMP) exec -u www-data app php
SYMFONY = $(PHP_CONT) bin/console

# --- Commandes Docker ---

## Demarre le projet (et reconstruit l'image si la config a change)
up:
	$(DOCKER_COMP) up -d --build

## Arrete les conteneurs
down:
	$(DOCKER_COMP) down

## Affiche les logs en temps réel (utile pour debuggage)
logs:
	$(DOCKER_COMP) logs -f

## Acces terminal (bash) dans le conteneur
bash:
	$(DOCKER_COMP) exec -it app bash

# --- Commandes Projet (Symfony & Composer) ---

## Installation complete (Composer + Database + Assets)
install:
	@echo "Step 1: Creating .env.local inside container..."
	$(DOCKER_COMP) exec -u www-data app sh -c 'if [ ! -f .env.local ]; then \
		echo "DATABASE_URL=\"sqlite:///%kernel.project_dir%/var/data.db\"" > .env.local; \
	fi'

	@echo "Step 2: Set permissions..."
	$(DOCKER_COMP) exec -u 0 app chmod 644 .env.local

	@echo "Step 3: Installing dependencies..."
	$(DOCKER_COMP) exec -u 0 app composer install
	$(DOCKER_COMP) exec -u 0 app chown -R www-data:www-data /var/www/html/var /var/www/html/vendor

	@echo "Step 4: Resetting database..."
	$(MAKE) db-reset

	@echo "--- 5. Assets & Theme Install ---"
	$(DOCKER_COMP) exec -u 0 app sh -c 'mkdir -p assets/vendor && chown -R www-data:www-data assets/vendor'
	$(DOCKER_COMP) exec -u www-data app sh -c 'curl -L https://bootswatch.com/5/litera/bootstrap.min.css -o assets/vendor/bootstrap-theme.css'
	$(SYMFONY) importmap:install

## Reinitialise la BDD
db-reset:
	@echo "Deleting old SQLite database if exists..."
	$(DOCKER_COMP) exec -u www-data app rm -f var/data.db

	@echo "Creating new database..."
	$(SYMFONY) doctrine:database:create

	@echo "Running migrations..."
	$(SYMFONY) doctrine:migrations:migrate --no-interaction

	@echo "Loading fixtures..."
	$(SYMFONY) doctrine:fixtures:load --no-interaction

## Lance une commande Symfony arbitraire (ex: make sf c="make:controller")
sf:
	$(SYMFONY) $(c)

## Vide le cache
cc:
	$(SYMFONY) cache:clear