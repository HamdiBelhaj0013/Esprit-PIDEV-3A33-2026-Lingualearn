<?php

namespace App\Module\Forum\Controller;

use App\Module\Forum\Entity\ForumReply;
use App\Module\Forum\Entity\ForumPost;
use App\Module\Forum\Form\ForumReplyType;
use App\Module\Forum\Repository\ForumReplyRepository;
use App\Module\Forum\Repository\ForumPostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/forum/reply')]
class ForumReplyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ForumReplyRepository $repository,
        private ForumPostRepository $postRepository
    ) {}

    /**
     * ⭐ INDEX
     */
    #[Route('/', name: 'admin_forum_reply_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = [
            'keyword' => $request->query->get('keyword'),
            'authorId' => $request->query->get('authorId'),
            'postId' => $request->query->get('postId'),
            'isActive' => $request->query->get('isActive'),
            'isBestAnswer' => $request->query->get('isBestAnswer'),
            'sortField' => $request->query->get('sortField', 'repliedAt'),
            'sortOrder' => $request->query->get('sortOrder', 'DESC'),
        ];

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $replies = $this->repository->findWithPagination($page, $limit, $filters);
        $totalReplies = $this->repository->countByFilters($filters);
        $totalPages = ceil($totalReplies / $limit);

        $stats = $this->repository->getStatistics();
        $posts = $this->postRepository->findAll();

        return $this->render('Forum/backend/forum_reply/index.html.twig', [
            'replies' => $replies,
            'posts' => $posts,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalReplies' => $totalReplies,
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    /**
     * ⭐ NEW - DOIT ÊTRE AVANT /{id}
     */
    #[Route('/new', name: 'admin_forum_reply_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $reply = new ForumReply();

        $postId = $request->query->get('postId');
        if ($postId) {
            $post = $this->postRepository->find($postId);
            if ($post) {
                $reply->setPost($post);
            }
        }

        $form = $this->createForm(ForumReplyType::class, $reply);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->repository->save($reply, true);
            $this->addFlash('success', 'La réponse a été créée avec succès !');
            return $this->redirectToRoute('admin_forum_reply_index');
        }

        return $this->render('Forum/backend/forum_reply/new.html.twig', [
            'reply' => $reply,
            'form' => $form,
        ]);
    }

    /**
     * ⭐ SEARCH/AJAX - DOIT ÊTRE AVANT /{id}
     */
    #[Route('/search/ajax', name: 'admin_forum_reply_search_ajax', methods: ['GET'])]
    public function searchAjax(Request $request): JsonResponse
    {
        $keyword = $request->query->get('keyword', '');

        if (strlen($keyword) < 2) {
            return $this->json([
                'success' => false,
                'message' => 'Veuillez entrer au moins 2 caractères'
            ]);
        }

        $replies = $this->repository->searchByKeyword($keyword);

        $results = array_map(function($reply) {
            return [
                'id' => $reply->getId(),
                'content' => substr($reply->getContent(), 0, 100) . '...',
                'postTitle' => $reply->getPost()->getTitle(),
                'authorId' => $reply->getAuthorId(),
                'repliedAt' => $reply->getRepliedAt()->format('Y-m-d H:i'),
                'isActive' => $reply->isActive(),
                'isBestAnswer' => $reply->isBestAnswer(),
            ];
        }, $replies);

        return $this->json([
            'success' => true,
            'results' => $results,
            'count' => count($results)
        ]);
    }

    /**
     * ⭐ BULK ACTION - DOIT ÊTRE AVANT /{id}
     */
    #[Route('/bulk/action', name: 'admin_forum_reply_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        $action = $request->request->get('action');
        $replyIds = $request->request->all('reply_ids');

        if (empty($replyIds)) {
            $this->addFlash('error', 'Aucune réponse sélectionnée');
            return $this->redirectToRoute('admin_forum_reply_index');
        }

        $replies = $this->repository->findBy(['id' => $replyIds]);

        switch ($action) {
            case 'delete':
                foreach ($replies as $reply) {
                    $this->repository->remove($reply);
                }
                $this->entityManager->flush();
                $this->addFlash('success', count($replies) . ' réponse(s) supprimée(s)');
                break;

            case 'activate':
                foreach ($replies as $reply) {
                    $reply->setIsActive(true);
                }
                $this->entityManager->flush();
                $this->addFlash('success', count($replies) . ' réponse(s) activée(s)');
                break;

            case 'deactivate':
                foreach ($replies as $reply) {
                    $reply->setIsActive(false);
                }
                $this->entityManager->flush();
                $this->addFlash('success', count($replies) . ' réponse(s) désactivée(s)');
                break;

            default:
                $this->addFlash('error', 'Action non reconnue');
        }

        return $this->redirectToRoute('admin_forum_reply_index');
    }

    /**
     * ⭐ EXPORT CSV - DOIT ÊTRE AVANT /{id}
     */
    #[Route('/export/csv', name: 'admin_forum_reply_export_csv', methods: ['GET'])]
    public function exportCsv(): Response
    {
        $replies = $this->repository->findAll();

        $csv = "ID;Post;Auteur ID;Contenu;Date de réponse;Statut;Meilleure réponse\n";

        foreach ($replies as $reply) {
            $csv .= sprintf(
                "%d;%s;%d;%s;%s;%s;%s\n",
                $reply->getId(),
                str_replace(';', ',', $reply->getPost()->getTitle()),
                $reply->getAuthorId(),
                str_replace([';', "\n", "\r"], [',', ' ', ' '], substr($reply->getContent(), 0, 100)),
                $reply->getRepliedAt()->format('Y-m-d H:i:s'),
                $reply->isActive() ? 'Actif' : 'Inactif',
                $reply->isBestAnswer() ? 'Oui' : 'Non'
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="forum_replies_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    /**
     * ⭐ EDIT
     */
    #[Route('/{id}/edit', name: 'admin_forum_reply_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ForumReply $reply): Response
    {
        $form = $this->createForm(ForumReplyType::class, $reply);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'La réponse a été modifiée avec succès !');
            return $this->redirectToRoute('admin_forum_reply_index');
        }

        return $this->render('Forum/backend/forum_reply/edit.html.twig', [
            'reply' => $reply,
            'form' => $form,
        ]);
    }

    /**
     * ⭐ DELETE
     */
    #[Route('/{id}', name: 'admin_forum_reply_delete', methods: ['POST'])]
    public function delete(Request $request, ForumReply $reply): Response
    {
        if ($this->isCsrfTokenValid('delete' . $reply->getId(), $request->request->get('_token'))) {
            $this->repository->remove($reply, true);
            $this->addFlash('success', 'La réponse a été supprimée avec succès !');
        }

        return $this->redirectToRoute('admin_forum_reply_index');
    }

    /**
     * ⭐ TOGGLE STATUS
     */
    #[Route('/{id}/toggle-status', name: 'admin_forum_reply_toggle_status', methods: ['POST'])]
    public function toggleStatus(ForumReply $reply): JsonResponse
    {
        try {
            $this->repository->toggleStatus($reply);

            return $this->json([
                'success' => true,
                'isActive' => $reply->isActive(),
                'message' => $reply->isActive() ? 'Réponse activée' : 'Réponse désactivée'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut'
            ], 500);
        }
    }

    /**
     * ⭐ MARK BEST ANSWER
     */
    #[Route('/{id}/mark-best-answer', name: 'admin_forum_reply_mark_best_answer', methods: ['POST'])]
    public function markBestAnswer(ForumReply $reply): JsonResponse
    {
        try {
            $this->repository->markAsBestAnswer($reply);

            return $this->json([
                'success' => true,
                'message' => 'Marquée comme meilleure réponse'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du marquage'
            ], 500);
        }
    }

    /**
     * ⭐ GET REPLIES BY POST - DOIT ÊTRE AVANT /{id}
     */
    #[Route('/post/{id}/replies', name: 'admin_forum_reply_by_post', methods: ['GET'])]
    public function getRepliesByPost(ForumPost $post): JsonResponse
    {
        $replies = $this->repository->findByPost($post, false);

        $results = array_map(function($reply) {
            return [
                'id' => $reply->getId(),
                'content' => $reply->getContent(),
                'authorId' => $reply->getAuthorId(),
                'repliedAt' => $reply->getRepliedAt()->format('Y-m-d H:i'),
                'isActive' => $reply->isActive(),
                'isBestAnswer' => $reply->isBestAnswer(),
            ];
        }, $replies);

        return $this->json([
            'success' => true,
            'postTitle' => $post->getTitle(),
            'replies' => $results,
            'count' => count($results)
        ]);
    }
}
