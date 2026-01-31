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


## Description des fonctionnalités

### Utlisateurs

Dans la version originale, le blog n'a pas d'utilisateurs. Nous décidons d'implémenter cette fonctionnalité afin de :
- gérer un système d'auteur pour les articles (droit d'éditier et de création) ;
- gérer les utilisateurs eux-même avec un rôle d'administateur du blog (création/modification/effacement des utilisateurs).

#### Création et règles d'accès

##### Installation de la classe `User`

On utilise `make:user` [[source](https://symfony.com/doc/6.4/security.html#the-user)]: on choisit de positionner les utilisateurs dans la BDD, de travailler avec des `username` et de hasher les mots de passe.

```bash
❯ make sf c=make:user
docker compose exec -u www-data app php bin/console make:user

 The name of the security user class (e.g. User) [User]:
 >

 Do you want to store user data in the database (via Doctrine)? (yes/no) [yes]:
 >

 Enter a property name that will be the unique "display" name for the user (e.g. email, username, uuid) [email]:
 > username

 Will this app need to hash/check user passwords? Choose No if passwords are not needed or will be checked/hashed by some other system (e.g. a single sign-on server).

 Does this app need to hash/check user passwords? (yes/no) [yes]:
 >

 created: src/Entity/User.php
 created: src/Repository/UserRepository.php
 updated: src/Entity/User.php
 updated: config/packages/security.yaml


  Success!


 Next Steps:
   - Review your new App\Entity\User class.
   - Use make:entity to add more fields to your User entity and then run make:migration.
   - Create a way to authenticate! See https://symfony.com/doc/current/security.html
```

On ajoute des propriétés `fisrtName` et `lastName` :

```bash
❯ make sf c=make:entity
docker compose exec -u www-data app php bin/console make:entity

 Class name of the entity to create or update (e.g. TinyElephant):
 > User

 Your entity already exists! So let's add some new fields!

 New property name (press <return> to stop adding fields):
 > firstName

 Field type (enter ? to see all types) [string]:
 >

 Field length [255]:
 >

 Can this field be null in the database (nullable) (yes/no) [no]:
 >
 
 New property name (press <return> to stop adding fields):
 > lastName

 Field type (enter ? to see all types) [string]:
 >

 Field length [255]:
 >

 Can this field be null in the database (nullable) (yes/no) [no]:
 >

 updated: src/Entity/User.php

 Add another property? Enter the property name (or press <return> to stop adding fields):
 >



  Success!


 Next: When you're ready, create a migration with php bin/console make:migration
```

On crée la table :

```bash
❯ make sf c=make:migration
docker compose exec -u www-data app php bin/console make:migration


 [WARNING] You have 1 available migrations to execute.


 Are you sure you wish to continue? (yes/no) [yes]:
 >

 created: migrations/Version20260130092433.php


  Success!


 Review the new migration then run it with php bin/console doctrine:migrations:migrate
 ```
 
 On met à jour la base de données avec la commande du `makefile` :
 
 ```bash
 ❯ make db-reset
 --- Dropping old DB ---
 docker compose exec -u www-data app sh -c 'rm -f var/data.db'
 --- Creating new DB ---
 docker compose exec -u www-data app php bin/console doctrine:database:create
 Created database /app/var/data.db for connection named default
 --- Running Migrations ---
 docker compose exec -u www-data app php bin/console doctrine:migrations:migrate --no-interaction
 [notice] Migrating up to DoctrineMigrations\Version20260130092433
 [notice] finished in 39.2ms, used 20M memory, 2 migrations executed, 5 sql queries
 
 
  [OK] Successfully migrated to version: DoctrineMigrations\Version20260130092433
 ```

##### Règles d'accès

À présent que nous avons ce concept d'utilisateur, nous pouvons utiliser les [rôles](https://symfony.com/doc/6.4/security.html#roles)
afin de restreindre des partie du sites à des personnes authentifiées.

Plusieurs manières de procéder :

1. **Directement dans le contrôleur** — Dans les méthodes correspondant aux routes, on peut insérer :
```php
$this->denyAccessUnlessGranted('ROLE_USER');
```
ou directement au dessus de la déclaration de la classe pour gérer toutes les routes d'un coup :
```php
#[IsGranted('ROLE_USER')]
```
Cela emmènera directement l'utilisateur sur la page de login lorsqu'il souhaitera consulter des pages derrière ces routes.

2. **Déclaration dans `security.yml`** — Plus efficace, on peut déclarer des parties du site innaccessible sans authentification
en renseignant des chemin grâce à des expressions régulières.

Dans le fichier `security.yml`, on renseigne :

```yaml
security:
    ...
  access_control:
    - { path: "^/.*(new|edit)", roles: ROLE_USER }
```

Ainsi, tous les chemin contenant `new` ou `edit` devront prendre en compte l'authentification.

### Gestion des utilisateurs depuis l'application

Un utilisateur particulier `admin` avec le rôle `ROLE_ADMIN` sera le seul habilité à gérer les utilisateurs via l'application direcment,
via une interface dédiée.

#### Modification de `security.yml`

```yaml
  role_hierarchy:
    ROLE_ADMIN: [ROLE_USER]
```

#### Ajouter la création d'un admin dans les fixtures

Pour créer un  l'utilisateur `admin`, on utilise les fixtures
 
 ```php
 $admin = new User();
        $admin->setUsername('admin');
        $admin->setFirstName('Admin');
        $admin->setLastName('ISTRATEUR');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin')); // mdp = admin
        $manager->persist($admin);
```


#### Création du controleur pour l'authentification

On commence par créer un formulaire pour l'identification [[source](https://symfony.com/doc/6.4/security.html#form-login)]:

```bash
❯ make sf c=make:security:form-login
docker compose exec -u www-data app php bin/console make:security:form-login

 Choose a name for the controller class (e.g. SecurityController) [SecurityController]:
 > LoginController

 Do you want to generate a '/logout' URL? (yes/no) [yes]:
 >

 Do you want to generate PHPUnit tests? [Experimental] (yes/no) [no]:
 >

 created: src/Controller/LoginController.php
 created: templates/login/login.html.twig
 updated: config/packages/security.yaml


  Success!


 Next: Review and adapt the login template: login/login.html.twig to suit your needs.
 ```
 
On ajoute un bouton de connexion dans la barre de navigation :

```html
<div class="d-flex align-items-center">
    {% if app.user %}
        <span class="navbar-text me-3">
            Bonjour, {{ app.user.firstName }} {{ app.user.lastName | upper }}
        </span>
        <a class="btn btn-secondary" href="{{ path('app_logout') }}" title="Déconnexion">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    {% else %}
        <a class="btn btn-secondary" href="{{ path('app_login') }}" title="Connexion">
            <i class="bi bi-box-arrow-in-right"></i>
        </a>
    {% endif %}
</div>
```

#### Utilisateurs et articles

À présent que nous avons des utilisateurs, nous devons définir une propriété pour l'article afin de le lié à son auteur.

Nous allons créer une nouvelle propriété `author` qui sera une relation `ManyToOne` avec `User` (un article a un seul auteur
mais un auteur peut produire plusieurs articles).

```bash
❯ make sf c="make:entity Article"
docker compose exec -u www-data app php bin/console make:entity Article
 Your entity already exists! So let's add some new fields!

 New property name (press <return> to stop adding fields):
 > author

 Field type (enter ? to see all types) [string]:
 > relation

 What class should this entity be related to?:
 > User

What type of relationship is this?
 ------------ --------------------------------------------------------------------
  Type         Description
 ------------ --------------------------------------------------------------------
  ManyToOne    Each Article relates to (has) one User.
               Each User can relate to (can have) many Article objects.

  OneToMany    Each Article can relate to (can have) many User objects.
               Each User relates to (has) one Article.

  ManyToMany   Each Article can relate to (can have) many User objects.
               Each User can also relate to (can also have) many Article objects.

  OneToOne     Each Article relates to (has) exactly one User.
               Each User also relates to (has) exactly one Article.
 ------------ --------------------------------------------------------------------

 Relation type? [ManyToOne, OneToMany, ManyToMany, OneToOne]:
 > ManyToOne

 Is the Article.author property allowed to be null (nullable)? (yes/no) [yes]:
 > no

 Do you want to add a new property to User so that you can access/update Article objects from it - e.g. $user->getArticles()? (yes/no) [yes]:
 > yes

 A new property will also be added to the User class so that you can access the related Article objects from it.

 New field name inside User [articles]:
 >

 Do you want to activate orphanRemoval on your relationship?
 A Article is "orphaned" when it is removed from its related User.
 e.g. $user->removeArticle($article)

 NOTE: If a Article may *change* from one User to another, answer "no".

 Do you want to automatically delete orphaned App\Entity\Article objects (orphanRemoval)? (yes/no) [no]:
 >

 updated: src/Entity/Article.php
 updated: src/Entity/User.php

 Add another property? Enter the property name (or press <return> to stop adding fields):
 >



  Success!


 Next: When you're ready, create a migration with php bin/console make:migration
 ```
 
 ENsuite nous devons mettre à jour la base de données :
 ```bash
 make sf c="make:migration"
 make sf c="doctrine:migrations:migrate"
 ```
 
 Le nom de l'utilisateur peut-être à présent à jouté aux articles et dans les listes d'articles :
 
 ```bash
{% if article.author %}
    <p class="lead fst-italic">
        Par {{ article.author.firstName }} {{ article.author.lastName | upper }}
    </p>
{% endif %}
  ```
  
Nous devons aussi mettre à jour `ArticleController` afin de lié l'article à l'utilisateur connecté :

```php
$article->setAuthor($this->getUser());
```

#### Ajout d'utilsateurs

##### Utilisateurs par défaut

En utilisant les fixtures, nous pouvons ajouter des utilisateurs par défaut à la base de données :

```php
private function loadUsers(ObjectManager $manager): void
{
    // Création d'un utlisateur lambda
    $user = new User();
    $user->setUsername('lambda');
    $user->setPassword($this->passwordHasher->hashPassword($user, '0000')); // mdp = 0000
    $user->setFirstName('Éric');
    $user->setLastName('Lambda');
    $manager->persist($user);

    // Création de l'admin
    $admin = new User();
    $admin->setUsername('admin');
    $admin->setFirstName('Admin');
    $admin->setLastName('ISTRATEUR');
    $admin->setRoles(['ROLE_ADMIN']);
    $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin')); // mdp = admin
    $manager->persist($admin);

    $manager->flush();
}
```

On oublie pas de mettre à jour la base de données avec `make db-reset` du `makefile`.


##### Dans l'application

Nous créons à présent un contrôleur dédié à la création, édition, suppression des utilisateurs dans l'application via `make:crud User`.

```bash
❯ make sf c="make:crud User"
docker compose exec -u www-data app php bin/console make:crud User

 Choose a name for your controller class (e.g. UserController) [UserController]:
 >

 Do you want to generate PHPUnit tests? [Experimental] (yes/no) [no]:
 >

 created: src/Controller/UserController.php
 created: src/Form/User1Type.php
 created: templates/user/_delete_form.html.twig
 created: templates/user/_form.html.twig
 created: templates/user/edit.html.twig
 created: templates/user/index.html.twig
 created: templates/user/new.html.twig
 created: templates/user/show.html.twig


  Success!


 Next: Check your new CRUD by going to /user/
 ```
 Pour restreindre l'accès au processus liés à la gestion des utilisateurs, il faut ajouter juste avant la définition de la class :
 
 ```php
 #[IsGranted('ROLE_ADMIN')]
 ```
 
 Un formulaire est créé automatiquement. Il faut ensuite adapter ce formulaire `Form/UserType` afin qu'il prenne en compte 
 les mots de passe correctement et qu'il propose des champs pour les rôles automatiquement dans le formulaire :

```php
$builder
    ->add('username')
    ->add('firstName')
    ->add('lastName')
    // Champ pour le mot de passe (non mappé à l'entité directement)
    ->add('plainPassword', PasswordType::class, [
        'label' => 'Mot de passe',
        'mapped' => false,
        'required' => false, // Mettre à true pour la création
        'attr' => ['autocomplete' => 'new-password'],
    ])
    ->add('roles', ChoiceType::class, [
        'choices' => [
            'Utilisateur' => 'ROLE_USER',
            'Administrateur' => 'ROLE_ADMIN',
        ],
        'multiple' => true, // Permet de sélectionner plusieurs rôles
        'expanded' => true, // Affiche comme des cases à cocher
        'label' => 'Rôles'
    ])
;
```


##### Création d'un formulaire de recherche
1. Création d'un formulaire via make :
❯ make sf c="make:form"

    docker compose exec -u www-data app php bin/console make:form

    The name of the form class (e.g. AgreeablePizzaType):
    > SearchType

    The name of Entity or fully qualified model class name that the new form will be bound to (empty for none):
    > Article

    created: src/Form/SearchType.php


2. On adapte notre formulaire à celui d'une barre de recherche :

    public function buildForm(FormBuilderInterface $builder, array $options): void
        {
            $builder
                ->add('query', TextType::class, [
                    'attr' => [
                        'placeholder' => 'Rechercher un article...'
                    ]
                ]); 
        }

        public function configureOptions(OptionsResolver $resolver): void
        {
            $resolver->setDefaults([
                'method' => 'GET',
                'csrf_protection' => false
            ]);
        }
    }

3. On crée notre requête dans le Repository :

    public function searchFunction(string $recherche):array {
        return 
            $this->createQueryBuilder('a') //createQueryBuilder crée une requête SQL à l'aide de Doctrine
            ->where('a.title LIKE :recherche')
            ->orWhere('a.content LIKE :recherche')
            ->setParameter('recherche', '%' . $recherche . '%')
            ->getQuery() //transforme le querybuilder en querydoctrine
            ->getResult(); //execute la requête
    }

4. On l'implémente dans notre ArticleController.php

On ajoute : Response après notre fonction pour dire qu'elle doit retoruner une page HTML

    #[Route('/search', name: 'article_index', methods: ['GET'])]
    public function search(Request $request, ArticleRepository $articleRepository): Response 
    {
        $recherche = $request->query->get('query', '');
        
        if (!empty(trim($recherche))) {
            $articles = $articleRepository->searchFunction($recherche);
        } else {
            $articles = $articleRepository->findAll();
        }
        
        return $this->render('article/search.html.twig', [
            'articles' => $articles,
            'query' => $recherche,
        ]);
    }