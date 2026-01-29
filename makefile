# Makefile (Linux, Mac, Windows)

# --- Variables ---
# Détection basique pour docker compose (V2) vs docker-compose (V1)
# Si "docker compose" ne marche pas sous Windows : make up DOCKER_COMP="docker-compose"
DOCKER_COMP = docker compose

# Commandes internes au conteneur
PHP_CONT = $(DOCKER_COMP) exec -u www-data app php
SYMFONY = $(PHP_CONT) bin/console
# On utilise 'sh -c' pour exécuter des scripts shell complexes DANS le conteneur
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

## Installation complète (Fonctionne sur tout OS)
install:
	@echo "--- 1. Vérification/Création du .env.local (Inside Container) ---"
	# On exécute la logique 'if file exists' DANS le conteneur Linux. L'hôte Windows ne voit qu'une commande Docker.
	$(DOCKER_SHELL) 'if [ ! -f .env.local ]; then echo "DATABASE_URL=\"sqlite:///%kernel.project_dir%/var/data.db\"" > .env.local; echo ".env.local created"; else echo ".env.local already exists"; fi'

	@echo "--- 2. Correction des permissions (Inside Container) ---"
	# On fixe les droits en root à l'intérieur
	$(ROOT_SHELL) 'chmod 644 .env.local'
	
	@echo "--- 3. Installation Composer ---"
	$(ROOT_SHELL) 'composer install'
	# Correction propriétaire dossier var/vendor
	$(ROOT_SHELL) 'chown -R www-data:www-data /var/www/html/var /var/www/html/vendor'
	
	@echo "--- 4. Reset Base de données ---"
	$(MAKE) db-reset
	
	@echo "--- 5. Installation Assets ---"
	$(SYMFONY) importmap:install

## Réinitialise la BDD (Compatible tout OS)
db-reset:
	# Suppression fichier .db via commande interne (évite rm sur l'hôte)
	$(DOCKER_SHELL) 'rm -f var/data.db'
	$(SYMFONY) doctrine:database:create
	$(SYMFONY) doctrine:migrations:migrate --no-interaction
	# $(SYMFONY) doctrine:fixtures:load --no-interaction

## Lance une commande Symfony (ex: make sf c="make:controller")
sf:
	$(SYMFONY) $(c)

## Vide le cache
cc:
	$(SYMFONY) cache:clear