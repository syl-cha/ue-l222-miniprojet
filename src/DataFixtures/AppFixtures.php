<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Faker\Factory;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher) {}

    public function load(ObjectManager $manager): void
    {
        $users = $this->loadUsers($manager);
        $this->loadCategoriesAndArticles($manager, $users);
    }

    private function loadUsers(ObjectManager $manager): array
    {
        // Création d'un utlisateur lambda
        $user = new User();
        $user->setUsername('lambda');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
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

        return ['lambda' => $user, 'admin' => $admin];
    }

    private function loadCategoriesAndArticles(ObjectManager $manager, array $users): void
    {
        /** @var User $admin */
        $admin = $users['admin'];
        /** @var User $lambdaUser */
        $lambdaUser = $users['lambda'];

        // $product = new Product();
        // $manager->persist($product);
        $categorie = new Category();
        $categorie->setName("Catégorie 1");
        $manager->persist($categorie);

        // $article->setAuthor($admin);

        $faker = Factory::create('fr_FR');

        /*** Création des catégories ***/
        $categoryNames = [
            'Technologie',
            'Culture',
            'Sport',
            'Économie',
            'Société',
            'Science',
            'Politique',
            'Santé',
            'Voyages',
            'Éducation'
        ];


        $categories = [];


        foreach ($categoryNames as $name) {
            $categorie = new Category();
            $categorie->setName($name);
            $manager->persist($categorie);
            $categories[] = $categorie;
        }


        /*** Création d'articles ***/
        for ($i = 1; $i <= 20; $i++) {
            $article = new Article();
            $article->setAuthor($lambdaUser);
            // Titre : 2 à 6 mots
            $article->setTitle(rtrim($faker->sentence($faker->numberBetween(2, 8)), '.'));
            // Contenu : plusieurs paragraphes + texte réaliste
            $article->setContent(
                $faker->realText($faker->numberBetween(400, 800))
            );

            $article->setCreatedAt($faker->dateTimeBetween('-2 years', 'now'));

            // Catégorie aléatoire parmi celles créées
            $article->setCategory($faker->randomElement($categories));

            $article->setImage("https://picsum.photos/seed/" . rand(1, 1000) . "/800/600");

            $article->setVueCount($faker->numberBetween(2, 45));

            $manager->persist($article);
        }

        /*** Enregistrement final en base ***/
        $manager->flush();
    }
}
