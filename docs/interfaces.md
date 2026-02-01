## Interfaces

### Système de message d'alerte

Lorsque un utilisateur tente une opération interdite, il doit être averti par un système de message flash.

#### Modèle TWIG

On conçoit un système de pile qui accueillera tous les messages émits.

```twig
{% for label, messages in app.flashes %}
    {% for message in messages %}
        <div class="alert alert-{{ label }} alert-dismissible fade show" role="alert" data-timeout="5000">
            {{ message }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    {% endfor %}
{% endfor %}
```

#### Envoie d'un message 
, on utilise la méthode `addFlash` pour alerter l'utilisateur qu'il n'a pas le droit d'édition sur le fichier qu'il souhaite éditer.

```php
if (!$isAuthor && !$isAdmin) {
    $this->addFlash('danger', "Vous n'êtes pas autorisé à supprimer cet article.");
    ...
}
```

#### Listerners

On implémentes un listener spécifique dans `assets/apps.js` afin de fermer automatiquement les messages d'alerte après 10s.

```js
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.alert[data-timeout]').forEach((alertEl) => {
    setTimeout(
      () => {
        Alert.getOrCreateInstance(alertEl).close();
      },
      parseInt(alertEl.dataset.timeout, 10),
    );
  });
});
```

### Compteur de vues

On commence par ajouter une propriété `vue_count` à `Article`

```bash
❯ make sf c="make:entity Article"
docker compose exec -u www-data app php bin/console make:entity Article
 Your entity already exists! So let's add some new fields!

 New property name (press <return> to stop adding fields):
 > vue_count

 Field type (enter ? to see all types) [string]:
 > integer

 Can this field be null in the database (nullable) (yes/no) [no]:
 >

 updated: src/Entity/Article.php

 Add another property? Enter the property name (or press <return> to stop adding fields):
 >



  Success!


 Next: When you're ready, create a migration with php bin/console make:migration
 ```
 
 Ensuite on mets à jour `ArticleController` afin de prendre en compte ce nouveau champ.
 Dans la fonction `new`, on ajoute
 
 ```php
 $article->setVueCount(0);
 ```
 
 Dans la fonction `show`, on met à jour le compteur :
 
 ```php
// mise à jour des vues
$vueCount = $article->getVueCount();
$article->setVueCount($vueCount + 1);
// mise à jour de la BDD
$entityManager->flush();
```
Pour s'en servir, nous n'avons plus qu'à appeler la variable correspondante dans les modèles TWIG :

```twig
<i class="bi bi-eye-fill"></i> {{ article.vueCount | default(0) }}
```

Nous avons aussi créé une requête afin de retrrouver la liste des articles des plus vus dans `ArticleRepository` :

```php
public function findMostViewed(int $limit = 5): array
{
    return $this->createQueryBuilder('a')
        ->orderBy('a.vue_count', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        -getResult()
    ;
}
```
et dans `ArticleCOntroller`, nous utilisons cette requête en ajoutant la propriété `mostViewedArticles` à l'article afin de pouvoir afficher les articles les plus vus :

```php
'mostViewedArticles' => $articleRepository->findMostViewed(3)
```
Les fixtures pour les articles ont aussi été mises à jour :

```php
$article->setVueCount($faker->numberBetween(2, 45));
```

### Génération de données de test (Faker & Fixtures)

Pour remplir la base de données rapidement lors du développement, nous utilisons Faker au sein des fixtures Symfony.

Installation :

```bash
composer require --dev fakerphp/faker
```

Mise en œuvre : dans `src/DataFixtures/AppFixtures.php`, nous initialisons Faker pour générer des titres, du contenu et assigner des images aléatoires.

Note sur les images : Faker génère des URLs d'images (via `imageUrl()`). Pour les tests locaux, nous lions ces URLs au dossier public/uploads/articles ou utilisons des placeholders pour simuler la présence de fichiers physiques.

Exécution :

```bash
make db-reset
```

### Système de Pagination (KnpPaginatorBundle)

Pour éviter de charger une certaine quantité d'articles sur une seule page, nous avons intégré KnpPaginatorBundle.

- Configuration : dans le contrôleur (`ArticleController`), nous utilisons le service PaginatorInterface pour segmenter les résultats de la base de données.

- Utilisation : le bundle reçoit une requête Doctrine (Query) et retourne un objet PaginationInterface contenant uniquement les 6 articles de la page demandée.

- Affichage (Twig) : l'affichage des contrôles de navigation (Suivant/Précédent) se fait via :

```php
// Twig
{{ knp_pagination_render(articles) }}
```

### Gestion sécurisée des Images (Upload & Types MIME)

L'upload d'images est géré manuellement pour garantir un contrôle total sur la sécurité du serveur.

#### Validation par Contraintes

Nous utilisons le composant Validator de Symfony dans `ArticleType.php` pour filtrer les fichiers dès leur réception :

```php
'constraints' => [
    new File([
        'maxSize' => '2M',
        'mimeTypes' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG, PNG ou WEBP).',
        'maxSizeMessage' => 'Le fichier est trop lourd (2Mo max).',
    ])
],
```

#### Configuration sécurisée :

- Vérification de l'Identité (MIME) : Symfony n'analyse pas seulement l'extension (ex: .jpg), mais inspecte le contenu réel du fichier. Cela empêche un utilisateur malveillant d'uploader un script PHP renommé en image.

- Contrôle des Ressources (maxSize) : La limite à 2 Mo protège le serveur contre la saturation du disque dur et garantit des temps de chargement rapides pour les lecteurs du blog.

- Anonymisation et Unicité : Chaque fichier est renommé via uniqid() avant d'être déplacé dans le dossier public/uploads/articles. Cela évite les conflits de noms (écrasement d'une image existante) et masque le nom d'origine.

- Pré-requis technique : Pour que cette détection soit fiable, l'extension PHP fileinfo doit être activée sur le serveur. Elle permet à PHP de "lire" la signature réelle du fichier.