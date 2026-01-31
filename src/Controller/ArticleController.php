<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\User;
use App\Entity\Category;
use App\Form\ArticleType;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/article')]
class ArticleController extends AbstractController
{
    #[Route('/', name: 'article_index', methods: ['GET'])]
    public function index(ArticleRepository $articleRepository, PaginatorInterface $paginator, Request $request): Response
    {
        $query = $articleRepository->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery();

        // On crée la pagination
        $pagination = $paginator->paginate(
            $query, // On pagine la requête triée
            $request->query->getInt('page', 1), // Le numéro de page dans l'URL
            6 // Nombre d'articles par page
        );

        return $this->render('article/index.html.twig', [
            'articles' => $pagination, // On envoie $pagination
            'mostViewedArticles' => $articleRepository->findMostViewed(3),
        ]);
    }

    #[Route('/search', name: 'article_search', methods: ['GET'])]
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

    #[Route('/new', name: 'article_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $article = new Article();
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();
            $article->setAuthor($user);
            $article->setVueCount(0);
            $entityManager->persist($article);
            $entityManager->flush();

            return $this->redirectToRoute('article_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('article/new.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'article_show', methods: ['GET'])]
    public function show(Article $article, EntityManagerInterface $entityManager): Response
    {
        // mise à jour des vues
        $vueCount = $article->getVueCount();
        $article->setVueCount($vueCount + 1);
        // mise à jour de la BDD
        $entityManager->flush();
        return $this->render('article/show.html.twig', [
            'article' => $article,
        ]);
    }

    #[Route('/{id}/edit', name: 'article_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Article $article, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        $isAuthor = $article->getAuthor()?->getId() === $currentUser?->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        if (!$isAuthor && !$isAdmin) {
            $this->addFlash('danger', "Vous n'êtes pas autorisé à éditer cet article.");

            return $this->redirectToRoute('article_index');
        }

        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('article_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('article/edit.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'article_delete', methods: ['POST'])]
    public function delete(Request $request, Article $article, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();

        $isAuthor = $article->getAuthor()?->getId() === $currentUser?->getId();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        if (!$isAuthor && !$isAdmin) {
            $this->addFlash('danger', "Vous n'êtes pas autorisé à supprimer cet article.");

            return $this->redirectToRoute('article_index');
        }

        if ($this->isCsrfTokenValid('delete' . $article->getId(), $request->request->get('_token'))) {
            $entityManager->remove($article);
            $entityManager->flush();
        }

        return $this->redirectToRoute('article_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/category/{id}/articles', name: 'articles_by_category')]
    public function articlesByCategory(Category $category, ArticleRepository $articleRepository, PaginatorInterface $paginator, Request $request): Response
    {
        // Récupère les articles liés à cette catégorie
        $data = $articleRepository->findBy(['Category' => $category], ['createdAt' => 'DESC']);

        $articles = $paginator->paginate(
            $data,
            $request->query->getInt('page', 1),
            6
        );

        return $this->render('article/articles_by_category.html.twig', [
            'category' => $category,
            'articles' => $articles,
        ]);
    }
}
