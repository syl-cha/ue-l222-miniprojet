# Miniprojet UE-L222

Ce fichier renseigne sur la mise en route de notre miniprojet en UE-L222.

## Mise en place

### Lancement du container *à la main*

On peut lancer le contrôleur à la main mais la méthode recommandée est d'utiliser le `makefile`

1. Générer un fichier `docker-compose.override.yml` avec :

```yml
services:
  app:
    volumes:
      # On lie le dossier courant (.) au dossier racine du serveur
      - ./:/var/www/html
    ports:
      # Vérifier que les ports sont dispo sinon les changer
      - "9001:80"   # Accès Web sur localhost:9001
      - "3308:3306" # Accès BDD sur localhost:3308
```

2. Les commandes disponibles pour piloter le container :

```bash
docker compose up -d # Démarrer le container
docker compose ps    # Vérifier si ça marche
docker compose down  # Arrêter le container
```

3. On accède au terminal lié au container en faisant (le nom du container s'appelle `blog_app`) :

```bash
docker exec -it blog-app bash
```
4. Depuis le terminal, on peut lancer toutes les commandes pour installer Symphony avec `composer`


### Utilisation du `makefile`

On peut tout faire à la main depuis le terminal mais l'utilisation du `makefile` simplifie grandement les manipulations.

1. Liste des commandes disponibles pour le container :

- `make up` : lancer le container (modification du `VirtualHost` pour désigner le dossier racine effectuée si première fois)
- `make down` : arrêter le container
- `make bash` : ouvrir le terminal du container
- `make logs` : affiche les logs (live)

2. Liste des commandes pour piloter Symfony :

Tout le nécessaire est renseigné dans `composer.json`...

- `make install` : lance l'installation 
  - exécute `composer` et installe tout
  - change les droits sur les dossiers dans `/var/html/www`
  - met à zéro la base de données
  - installes les assets (CSS, JS, etc)
- `make db-init` : créer la base de données et les tables
- `make db-reset` : ré-initialise la base de données (+ ajoute les fixtures) :warning: **PERTE DE DONNÉES** :warning:
- `make sf c=...` : lancer une commande Symfony (exemples : `make sf c=make:controller` ou `make sf c=make:entity`)
- `make cc` : vide le cache
