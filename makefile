# Makefile

# --- Variables ---
DOCKER_COMP = docker compose
PHP_CONT = $(DOCKER_COMP) exec -u www-data app php
SYMFONY = $(PHP_CONT) bin/console

# --- Commandes Docker ---

## Démarre le projet (et reconstruit l'image si la config a changé)
up:
	$(DOCKER_COMP) up -d --build

## Arrête les conteneurs
down:
	$(DOCKER_COMP) down

## Affiche les logs en temps réel (utile pour débugger Apache/PHP)
logs:
	$(DOCKER_COMP) logs -f

## Accès terminal (bash) dans le conteneur
bash:
	$(DOCKER_COMP) exec -it app bash

# --- Commandes Projet (Symfony & Composer) ---

## Installation complète (Composer + Database + Assets)
install:
	# 1. Création automatique du .env.local si inexistant
	@if [ ! -f .env.local ]; then \
		echo "Création du fichier .env.local pour SQLite..."; \
		echo 'DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"' > .env.local; \
	fi
	# CRITIQUE : On s'assure que tout le monde peut lire ce fichier (fix pour Docker)
	chmod 644 .env.local

	# 2. Installation des dépendances
	$(DOCKER_COMP) exec -u 0 app composer install
	# On s'assure que www-data est propriétaire du dossier var ET du vendor
	$(DOCKER_COMP) exec -u 0 app chown -R www-data:www-data /var/www/html/var /var/www/html/vendor

	# 3. Base de données
	$(MAKE) db-reset

	# 4. Assets
	$(SYMFONY) importmap:install

## Réinitialise la BDD
db-reset:
	# Étape 1 : Suppression manuelle du fichier SQLite (Workaround pour le bug --if-exists)
	$(DOCKER_COMP) exec -u www-data app rm -f var/data.db

	# Étape 2 : Création de la nouvelle base (fichier vide)
	$(SYMFONY) doctrine:database:create

	# Étape 3 : Migrations
	$(SYMFONY) doctrine:migrations:migrate --no-interaction
	$(SYMFONY) doctrine:fixtures:load --no-interaction # Décommenter si tu as des fixtures

## Lance une commande Symfony arbitraire (ex: make sf c="make:controller")
sf:
	$(SYMFONY) $(c)

## Vide le cache
cc:
	$(SYMFONY) cache:clear
