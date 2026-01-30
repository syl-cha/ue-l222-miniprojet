<?php

namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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

        $article = new Article();
        $article->setTitle("Article 1");
        $article->setContent("Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut viverra urna in nunc dapibus tincidunt et eu magna. Pellentesque venenatis varius orci sit amet condimentum. Quisque sit amet enim id nisl consectetur gravida. Curabitur efficitur felis rutrum ligula convallis convallis. Vestibulum eget dictum lorem. Vestibulum est justo, fermentum eu eleifend vestibulum, lacinia efficitur erat. Proin a ligula lorem. In iaculis dignissim sodales.");
        $article->setCategory($categorie);
        $article->setAuthor($admin);
        $manager->persist($article);

        $article = new Article();
        $article->setTitle("Article 2");
        $article->setContent("Morbi viverra facilisis aliquet. Phasellus suscipit arcu id felis tempor, in fringilla turpis dictum. Fusce porta finibus diam, vitae lacinia nibh finibus id. Donec facilisis nisl quis egestas sollicitudin. Aenean quis nulla est. Aliquam volutpat dolor eget ultricies maximus. Maecenas felis turpis, lacinia et purus in, viverra sagittis ex. Ut laoreet est sit amet ligula auctor, in venenatis magna interdum. Maecenas suscipit accumsan libero, auctor ullamcorper lectus porta semper.");
        $article->setCategory($categorie);
        $article->setAuthor($lambdaUser);
        $manager->persist($article);

        $manager->flush();
    }
}
