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
use Symfony\Component\HttpFoundation\File\Exception\FileException;
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

        // --- DÉBUT LOGIQUE IMAGE ---
            // On récupère le fichier depuis le champ "non-mappé" du formulaire
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                // On génère un nom unique pour éviter les doublons (ex: 65b8f.jpg)
                $newFilename = uniqid().'.'.$imageFile->guessExtension();

                // On déplace le fichier dans le dossier configuré (public/uploads/articles)
                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    // On met à jour le champ "image" de l'article avec le nom du fichier
                    $article->setImage($newFilename);
                } catch (FileException $e) {
                    // On ajoute un message d'erreur qui s'affichera sur la page
                    $this->addFlash('danger', "Une erreur est survenue lors de l'upload de l'image.");
                    $logger->error($e->getMessage());
                    // On arrête le processus et on réaffiche le formulaire
                    return $this->render('article/new.html.twig', [
                        'article' => $article,
                        'form' => $form,
                    ]);
                }
            }

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
        // On stocke le nom de l'image actuelle
        $oldImage = $article->getImage();

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

            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                // Un nouveau fichier est uploadé -> on traite comme pour le 'new'
                $newFilename = uniqid().'.'.$imageFile->guessExtension();
                $imageFile->move($this->getParameter('images_directory'), $newFilename);
                $article->setImage($newFilename);

                // Supprime l'ancien fichier physique pour ne pas encombrer le serveur
                if ($oldImage && !str_contains($oldImage, 'http')) {
                     unlink($this->getParameter('images_directory').'/'.$oldImage);
                }
            } else {
                // Aucun nouveau fichier -> on remet l'ancien nom pour ne pas perdre l'image
                $article->setImage($oldImage);
            }

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
            $data, // La source : la requête (Query) ou le tableau contenant tous les articles
            $request->query->getInt('page', 1), // Le numéro de page actuelle : récupéré dans l'URL (?page=X), 1 par défaut
            6 // La limite : nombre d'éléments à afficher par page
        );

        return $this->render('article/articles_by_category.html.twig', [
            'category' => $category,
            'articles' => $articles,
        ]);
    }
}
