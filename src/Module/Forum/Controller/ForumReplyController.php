<?php

namespace App\Module\Forum\Controller;

use App\Module\Forum\Entity\ForumReply;
use App\Module\Forum\Entity\ForumPost;
use App\Module\Forum\Form\ForumReplyType;
use App\Module\Forum\Repository\ForumReplyRepository;
use App\Module\Forum\Repository\ForumPostRepository;
use App\Module\UserManagement\Repository\UserRepository;
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
        private ForumReplyRepository   $repository,
        private ForumPostRepository    $postRepository,
        private UserRepository         $userRepository,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // INDEX
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/', name: 'admin_forum_reply_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = [
            'keyword'     => $request->query->get('keyword'),
            'authorId'    => $request->query->get('authorId'),
            'postId'      => $request->query->get('postId'),
            'isActive'    => $request->query->get('isActive'),
            'isBestAnswer'=> $request->query->get('isBestAnswer'),
            'sortField'   => $request->query->get('sortField', 'repliedAt'),
            'sortOrder'   => $request->query->get('sortOrder', 'DESC'),
        ];

        $page  = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $replies      = $this->repository->findWithPagination($page, $limit, $filters);
        $totalReplies = $this->repository->countByFilters($filters);
        $totalPages   = ceil($totalReplies / $limit);
        $stats        = $this->repository->getStatistics();
        $posts        = $this->postRepository->findAll();
        $users        = $this->userRepository->findBy(['status' => 'active'], ['lastName' => 'ASC']);

        return $this->render('Forum/backend/forum_reply/index.html.twig', [
            'replies'      => $replies,
            'posts'        => $posts,
            'users'        => $users,
            'currentPage'  => $page,
            'totalPages'   => $totalPages,
            'totalReplies' => $totalReplies,
            'filters'      => $filters,
            'stats'        => $stats,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NEW  (must stay before /{id})
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/new', name: 'admin_forum_reply_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $reply = new ForumReply();

        // Automatically set the author to the currently logged-in user
        $currentUser = $this->getUser();
        if ($currentUser && method_exists($currentUser, 'getId')) {
    $reply->setAuthorId($currentUser->getId());
}

        // Pre-select post if coming from a post page (but admin can still change it)
        $preselectedPostId = $request->query->get('postId');
        if ($preselectedPostId) {
            $post = $this->postRepository->find($preselectedPostId);
            if ($post) {
                $reply->setPost($post);
            }
        }

        $form = $this->createForm(ForumReplyType::class, $reply);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ensure author is set
            if ($currentUser && method_exists($currentUser, 'getId')) {
    $reply->setAuthorId($currentUser->getId());
}

            $this->repository->save($reply, true);
            $this->addFlash('success', 'Reply created successfully!');
            return $this->redirectToRoute('admin_forum_reply_index');
        }

        // Always get all posts for the dropdown
        $posts = $this->postRepository->findAll();

        return $this->render('Forum/backend/forum_reply/new.html.twig', [
            'reply'       => $reply,
            'form'        => $form,
            'currentUser' => $currentUser,
            'posts'       => $posts,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SEARCH / AJAX  (must stay before /{id})
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/search/ajax', name: 'admin_forum_reply_search_ajax', methods: ['GET'])]
    public function searchAjax(Request $request): JsonResponse
    {
        $keyword = $request->query->get('keyword', '');

        if (strlen($keyword) < 2) {
            return $this->json(['success' => false, 'message' => 'Please enter at least 2 characters']);
        }

        $replies = $this->repository->searchByKeyword($keyword);

        $results = array_map(fn($reply) => [
            'id'          => $reply->getId(),
            'content'     => substr($reply->getContent(), 0, 100) . '...',
            'postTitle'   => $reply->getPost()->getTitle(),
            'authorId'    => $reply->getAuthorId(),
            'repliedAt'   => $reply->getRepliedAt()->format('Y-m-d H:i'),
            'isActive'    => $reply->isActive(),
            'isBestAnswer'=> $reply->isBestAnswer(),
        ], $replies);

        return $this->json(['success' => true, 'results' => $results, 'count' => count($results)]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BULK ACTION  (must stay before /{id})
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/bulk/action', name: 'admin_forum_reply_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        $action   = $request->request->get('action');
        $replyIds = $request->request->all('reply_ids');

        if (empty($replyIds)) {
            $this->addFlash('error', 'No replies selected.');
            return $this->redirectToRoute('admin_forum_reply_index');
        }

        $replies = $this->repository->findBy(['id' => $replyIds]);

        match ($action) {
            'delete' => (function () use ($replies) {
                foreach ($replies as $r) { $this->repository->remove($r); }
                $this->entityManager->flush();
                $this->addFlash('success', count($replies) . ' reply/replies deleted.');
            })(),
            'activate' => (function () use ($replies) {
                foreach ($replies as $r) { $r->setIsActive(true); }
                $this->entityManager->flush();
                $this->addFlash('success', count($replies) . ' reply/replies activated.');
            })(),
            'deactivate' => (function () use ($replies) {
                foreach ($replies as $r) { $r->setIsActive(false); }
                $this->entityManager->flush();
                $this->addFlash('success', count($replies) . ' reply/replies deactivated.');
            })(),
            default => $this->addFlash('error', 'Unknown action.'),
        };

        return $this->redirectToRoute('admin_forum_reply_index');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXPORT CSV  (must stay before /{id})
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/export/csv', name: 'admin_forum_reply_export_csv', methods: ['GET'])]
    public function exportCsv(): Response
    {
        $replies = $this->repository->findAll();

        // Build a name lookup from users so CSV shows real names
        $users    = $this->userRepository->findAll();
        $userMap  = [];
        foreach ($users as $u) {
            $userMap[$u->getId()] = trim($u->getFirstName() . ' ' . $u->getLastName());
        }

        $csv = "ID;Post;Author;Content;Date;Status;Best Answer\n";

        foreach ($replies as $reply) {
            $authorLabel = $userMap[$reply->getAuthorId()] ?? 'User #' . $reply->getAuthorId();
            $csv .= sprintf(
                "%d;%s;%s;%s;%s;%s;%s\n",
                $reply->getId(),
                str_replace(';', ',', $reply->getPost()->getTitle()),
                $authorLabel,
                str_replace([';', "\n", "\r"], [',', ' ', ' '], substr($reply->getContent(), 0, 100)),
                $reply->getRepliedAt()->format('Y-m-d H:i:s'),
                $reply->isActive()    ? 'Active'     : 'Inactive',
                $reply->isBestAnswer() ? 'Yes'        : 'No'
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="forum_replies_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EDIT
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{id}/edit', name: 'admin_forum_reply_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ForumReply $reply): Response
    {
        $form = $this->createForm(ForumReplyType::class, $reply);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Reply updated successfully!');
            return $this->redirectToRoute('admin_forum_reply_index');
        }

        // Get the author user object for display
        $author = $this->userRepository->find($reply->getAuthorId());

        return $this->render('Forum/backend/forum_reply/edit.html.twig', [
            'reply'  => $reply,
            'form'   => $form,
            'author' => $author,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{id}', name: 'admin_forum_reply_delete', methods: ['POST'])]
    public function delete(Request $request, ForumReply $reply): Response
    {
        if ($this->isCsrfTokenValid('delete' . $reply->getId(), $request->request->get('_token'))) {
            $this->repository->remove($reply, true);
            $this->addFlash('success', 'Reply deleted successfully!');
        }

        return $this->redirectToRoute('admin_forum_reply_index');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TOGGLE STATUS
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{id}/toggle-status', name: 'admin_forum_reply_toggle_status', methods: ['POST'])]
    public function toggleStatus(ForumReply $reply): JsonResponse
    {
        try {
            $this->repository->toggleStatus($reply);
            return $this->json([
                'success'  => true,
                'isActive' => $reply->isActive(),
                'message'  => $reply->isActive() ? 'Reply activated' : 'Reply deactivated',
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Could not change status.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MARK BEST ANSWER
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{id}/mark-best-answer', name: 'admin_forum_reply_mark_best_answer', methods: ['POST'])]
    public function markBestAnswer(ForumReply $reply): JsonResponse
    {
        try {
            $this->repository->markAsBestAnswer($reply);
            return $this->json(['success' => true, 'message' => 'Marked as best answer.']);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Could not mark as best answer.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET REPLIES BY POST  (must stay before /{id})
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/post/{id}/replies', name: 'admin_forum_reply_by_post', methods: ['GET'])]
    public function getRepliesByPost(ForumPost $post): JsonResponse
    {
        $replies = $this->repository->findByPost($post, false);

        $results = array_map(fn($reply) => [
            'id'          => $reply->getId(),
            'content'     => $reply->getContent(),
            'authorId'    => $reply->getAuthorId(),
            'repliedAt'   => $reply->getRepliedAt()->format('Y-m-d H:i'),
            'isActive'    => $reply->isActive(),
            'isBestAnswer'=> $reply->isBestAnswer(),
        ], $replies);

        return $this->json([
            'success'   => true,
            'postTitle' => $post->getTitle(),
            'replies'   => $results,
            'count'     => count($results),
        ]);
    }
}
