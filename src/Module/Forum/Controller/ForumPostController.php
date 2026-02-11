<?php

namespace App\Module\Forum\Controller;

use App\Module\Forum\Entity\ForumPost;
use App\Module\Forum\Form\ForumPostType;
use App\Module\Forum\Repository\ForumPostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/forum/post')]
class ForumPostController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ForumPostRepository $repository
    ) {}

    /**
     * ⭐ INDEX - Liste tous les posts
     */
    #[Route('/', name: 'admin_forum_post_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = [
            'keyword' => $request->query->get('keyword'),
            'authorId' => $request->query->get('authorId'),
            'platformLanguageId' => $request->query->get('platformLanguageId'),
            'isActive' => $request->query->get('isActive'),
            'sortField' => $request->query->get('sortField', 'postedAt'),
            'sortOrder' => $request->query->get('sortOrder', 'DESC'),
        ];

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $posts = $this->repository->findWithPagination($page, $limit, $filters);
        $totalPosts = $this->repository->countByFilters($filters);
        $totalPages = ceil($totalPosts / $limit);
        $stats = $this->repository->getStatistics();

        return $this->render('Forum/backend/forum_post/index.html.twig', [
            'posts' => $posts,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalPosts' => $totalPosts,
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    /**
     * ⭐ NEW - DOIT ÊTRE AVANT /{id}
     */
    #[Route('/new', name: 'admin_forum_post_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $post = new ForumPost();
        $form = $this->createForm(ForumPostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->repository->save($post, true);
            $this->addFlash('success', 'Le post a été créé avec succès !');
            return $this->redirectToRoute('admin_forum_post_index');
        }

        return $this->render('Forum/backend/forum_post/new.html.twig', [
            'post' => $post,
            'form' => $form,
        ]);
    }

    /**
     * ⭐ SEARCH/AJAX - DOIT ÊTRE AVANT /{id}
     */
    #[Route('/search/ajax', name: 'admin_forum_post_search_ajax', methods: ['GET'])]
    public function searchAjax(Request $request): JsonResponse
    {
        $keyword = $request->query->get('keyword', '');
        
        if (strlen($keyword) < 2) {
            return $this->json([
                'success' => false,
                'message' => 'Veuillez entrer au moins 2 caractères'
            ]);
        }

        $posts = $this->repository->searchByKeyword($keyword);

        $results = array_map(function($post) {
            return [
                'id' => $post->getId(),
                'title' => $post->getTitle(),
                'content' => substr($post->getContent(), 0, 100) . '...',
                'authorId' => $post->getAuthorId(),
                'postedAt' => $post->getPostedAt()->format('Y-m-d H:i'),
                'isActive' => $post->isActive(),
                'viewCount' => $post->getViewCount(),
                'replyCount' => $post->getReplyCount(),
            ];
        }, $posts);

        return $this->json([
            'success' => true,
            'results' => $results,
            'count' => count($results)
        ]);
    }

    /**
     * ⭐ BULK ACTION - DOIT ÊTRE AVANT /{id}
     */
    #[Route('/bulk/action', name: 'admin_forum_post_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        $action = $request->request->get('action');
        $postIds = $request->request->all('post_ids');

        if (empty($postIds)) {
            $this->addFlash('error', 'Aucun post sélectionné');
            return $this->redirectToRoute('admin_forum_post_index');
        }

        $posts = $this->repository->findBy(['id' => $postIds]);

        switch ($action) {
            case 'delete':
                foreach ($posts as $post) {
                    $this->repository->remove($post);
                }
                $this->entityManager->flush();
                $this->addFlash('success', count($posts) . ' post(s) supprimé(s)');
                break;

            case 'activate':
                foreach ($posts as $post) {
                    $post->setIsActive(true);
                }
                $this->entityManager->flush();
                $this->addFlash('success', count($posts) . ' post(s) activé(s)');
                break;

            case 'deactivate':
                foreach ($posts as $post) {
                    $post->setIsActive(false);
                }
                $this->entityManager->flush();
                $this->addFlash('success', count($posts) . ' post(s) désactivé(s)');
                break;

            default:
                $this->addFlash('error', 'Action non reconnue');
        }

        return $this->redirectToRoute('admin_forum_post_index');
    }

    /**
     * ⭐ EXPORT CSV - DOIT ÊTRE AVANT /{id}
     */
    #[Route('/export/csv', name: 'admin_forum_post_export_csv', methods: ['GET'])]
    public function exportCsv(): Response
    {
        $posts = $this->repository->findAll();

        $csv = "ID;Titre;Auteur ID;Langue;Contenu;Date de publication;Statut;Vues;Réponses\n";
        
        foreach ($posts as $post) {
            $csv .= sprintf(
                "%d;%s;%d;%d;%s;%s;%s;%d;%d\n",
                $post->getId(),
                str_replace(';', ',', $post->getTitle()),
                $post->getAuthorId(),
                $post->getPlatformLanguageId(),
                str_replace([';', "\n", "\r"], [',', ' ', ' '], substr($post->getContent(), 0, 100)),
                $post->getPostedAt()->format('Y-m-d H:i:s'),
                $post->isActive() ? 'Actif' : 'Inactif',
                $post->getViewCount(),
                $post->getReplyCount()
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="forum_posts_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    /**
     * ⭐ SHOW - Afficher un post (APRÈS /new, /search/ajax, /bulk/action, /export/csv)
     */
    #[Route('/{id}', name: 'admin_forum_post_show', methods: ['GET'])]
    public function show(ForumPost $post): Response
    {
        return $this->render('Forum/backend/forum_post/show.html.twig', [
            'post' => $post,
        ]);
    }

    /**
     * ⭐ EDIT
     */
    #[Route('/{id}/edit', name: 'admin_forum_post_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ForumPost $post): Response
    {
        $form = $this->createForm(ForumPostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Le post a été modifié avec succès !');
            return $this->redirectToRoute('admin_forum_post_index');
        }

        return $this->render('Forum/backend/forum_post/edit.html.twig', [
            'post' => $post,
            'form' => $form,
        ]);
    }

    /**
     * ⭐ DELETE
     */
    #[Route('/{id}', name: 'admin_forum_post_delete', methods: ['POST'])]
    public function delete(Request $request, ForumPost $post): Response
    {
        if ($this->isCsrfTokenValid('delete' . $post->getId(), $request->request->get('_token'))) {
            $this->repository->remove($post, true);
            $this->addFlash('success', 'Le post a été supprimé avec succès !');
        }

        return $this->redirectToRoute('admin_forum_post_index');
    }

    /**
     * ⭐ TOGGLE STATUS
     */
    #[Route('/{id}/toggle-status', name: 'admin_forum_post_toggle_status', methods: ['POST'])]
    public function toggleStatus(ForumPost $post): JsonResponse
    {
        try {
            $this->repository->toggleStatus($post);

            return $this->json([
                'success' => true,
                'isActive' => $post->isActive(),
                'message' => $post->isActive() ? 'Post activé' : 'Post désactivé'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut'
            ], 500);
        }
    }
}