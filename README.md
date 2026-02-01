# Miniprojet UE-L222

Ce fichier renseigne sur le processus de développement de notre miniprojet en UE-L222.

## Installation

### Prérequis

- Docker doit être installé : Docker engine et Docker Compose à minima ou Docker Desktop ([instructions](https://docs.docker.com/desktop/))
- `git` doit être installé (voir [instructions](https://git-scm.com/install/) d'installation)

### Étapes

Dans un terminal :

1. Créer un dossier accueillant le projet :
```bash
mkdir projet_blog && cd projet_blog
```
2. Cloner le projet dans le dossier :

  * via SSH
```bash
git clone git@github.com:syl-cha/ue-l222-miniprojet.git .
```
* ou via HTTPS :
```bash
git clone https://github.com/syl-cha/ue-l222-miniprojet.git .
```
3. Construire le container associé :
```bash
make up
```
4. Mettre en place le projet :
```bash
make install
```

## Processus de développement

- [Mise en place](./docs/mise-en-place.md)
- [Fonctionnalités](./docs/fonctionnalites.md)
- [Interfaces](./docs/interfaces.md)
