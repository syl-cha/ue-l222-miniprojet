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

#[Route('/article')]
class ArticleController extends AbstractController
{
    #[Route('/', name: 'article_index', methods: ['GET'])]
    public function index(ArticleRepository $articleRepository): Response
    {
        return $this->render('article/index.html.twig', [
            'articles' => $articleRepository->findAll(),
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
    public function show(Article $article): Response
    {
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
    public function articlesByCategory(Category $category, ArticleRepository $articleRepository): Response
    {
        // Récupère les articles liés à cette catégorie
        $articles = $articleRepository->findBy(['Category' => $category], ['createdAt' => 'DESC']);

        return $this->render('article/articles_by_category.html.twig', [
            'category' => $category,
            'articles' => $articles,
        ]);
    }
}
