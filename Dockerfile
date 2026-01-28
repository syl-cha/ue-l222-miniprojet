# 1. On part de l'image de base que tu aimes
FROM bspcvtic/cvtic-lamp:latest

# 2. On met à jour et on installe SQLite3 pour PHP 8.1
# Le flag "-y" valide automatiquement les questions
RUN apt-get update --allow-releaseinfo-change && apt-get install -y php8.1-sqlite3

# 3. On active le mod_rewrite (souvent déjà actif, mais ça sécurise)
RUN a2enmod rewrite

# 4. On remplace la configuration Apache par la tienne
# On copie ton fichier local vhost.conf vers le dossier de config d'Apache
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# 5. On nettoie les fichiers temporaires d'installation (bonne pratique pour réduire la taille)
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# 6. Le dossier de travail
WORKDIR /var/www/html
