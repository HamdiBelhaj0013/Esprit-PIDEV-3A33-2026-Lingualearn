<?php

namespace App\Module\Forum\Controller;

use App\Module\Forum\Entity\ForumPost;
use App\Module\Forum\Form\ForumPostType;
use App\Module\Forum\Repository\ForumPostRepository;
use App\Module\UserManagement\Repository\UserRepository;
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
        private ForumPostRepository    $repository,
        private UserRepository         $userRepository,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // INDEX
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/', name: 'admin_forum_post_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = [
            'keyword'            => $request->query->get('keyword'),
            'authorId'           => $request->query->get('authorId'),
            'platformLanguageId' => $request->query->get('platformLanguageId'),
            'isActive'           => $request->query->get('isActive'),
            'sortField'          => $request->query->get('sortField', 'postedAt'),
            'sortOrder'          => $request->query->get('sortOrder', 'DESC'),
        ];

        $page  = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $posts      = $this->repository->findWithPagination($page, $limit, $filters);
        $totalPosts = $this->repository->countByFilters($filters);
        $totalPages = ceil($totalPosts / $limit);
        $stats      = $this->repository->getStatistics();

        return $this->render('Forum/backend/forum_post/index.html.twig', [
            'posts'       => $posts,
            'currentPage' => $page,
            'totalPages'  => $totalPages,
            'totalPosts'  => $totalPosts,
            'filters'     => $filters,
            'stats'       => $stats,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NEW  (must stay before /{id})
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/new', name: 'admin_forum_post_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $post = new ForumPost();

        // Automatically set the author to the currently logged-in user
        $currentUser = $this->getUser();
        if ($currentUser) {
            $post->setAuthorId($currentUser->getId());
        }

        $form = $this->createForm(ForumPostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ensure author is set
            if (!$post->getAuthorId() && $currentUser) {
                $post->setAuthorId($currentUser->getId());
            }

            $this->repository->save($post, true);
            $this->addFlash('success', 'Post created successfully!');
            return $this->redirectToRoute('admin_forum_post_index');
        }

        return $this->render('Forum/backend/forum_post/new.html.twig', [
            'post'        => $post,
            'form'        => $form,
            'currentUser' => $currentUser,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SEARCH / AJAX  (must stay before /{id})
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/search/ajax', name: 'admin_forum_post_search_ajax', methods: ['GET'])]
    public function searchAjax(Request $request): JsonResponse
    {
        $keyword = $request->query->get('keyword', '');

        if (strlen($keyword) < 2) {
            return $this->json(['success' => false, 'message' => 'Please enter at least 2 characters']);
        }

        $posts = $this->repository->searchByKeyword($keyword);

        $results = array_map(fn($post) => [
            'id'         => $post->getId(),
            'title'      => $post->getTitle(),
            'content'    => substr($post->getContent(), 0, 100) . '...',
            'authorId'   => $post->getAuthorId(),
            'postedAt'   => $post->getPostedAt()->format('Y-m-d H:i'),
            'isActive'   => $post->isActive(),
            'viewCount'  => $post->getViewCount(),
            'replyCount' => $post->getReplyCount(),
        ], $posts);

        return $this->json(['success' => true, 'results' => $results, 'count' => count($results)]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BULK ACTION  (must stay before /{id})
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/bulk/action', name: 'admin_forum_post_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        $action  = $request->request->get('action');
        $postIds = $request->request->all('post_ids');

        if (empty($postIds)) {
            $this->addFlash('error', 'No posts selected.');
            return $this->redirectToRoute('admin_forum_post_index');
        }

        $posts = $this->repository->findBy(['id' => $postIds]);

        match ($action) {
            'delete' => (function () use ($posts) {
                foreach ($posts as $p) { $this->repository->remove($p); }
                $this->entityManager->flush();
                $this->addFlash('success', count($posts) . ' post(s) deleted.');
            })(),
            'activate' => (function () use ($posts) {
                foreach ($posts as $p) { $p->setIsActive(true); }
                $this->entityManager->flush();
                $this->addFlash('success', count($posts) . ' post(s) activated.');
            })(),
            'deactivate' => (function () use ($posts) {
                foreach ($posts as $p) { $p->setIsActive(false); }
                $this->entityManager->flush();
                $this->addFlash('success', count($posts) . ' post(s) deactivated.');
            })(),
            default => $this->addFlash('error', 'Unknown action.'),
        };

        return $this->redirectToRoute('admin_forum_post_index');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXPORT CSV  (must stay before /{id})
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/export/csv', name: 'admin_forum_post_export_csv', methods: ['GET'])]
    public function exportCsv(): Response
    {
        $posts = $this->repository->findAll();

        // Build a name lookup so CSV shows real names
        $users   = $this->userRepository->findAll();
        $userMap = [];
        foreach ($users as $u) {
            $userMap[$u->getId()] = trim($u->getFirstName() . ' ' . $u->getLastName());
        }

        $csv = "ID;Title;Author;Language;Content;Date;Status;Views;Replies\n";

        foreach ($posts as $post) {
            $authorLabel = $userMap[$post->getAuthorId()] ?? 'User #' . $post->getAuthorId();
            $csv .= sprintf(
                "%d;%s;%s;%d;%s;%s;%s;%d;%d\n",
                $post->getId(),
                str_replace(';', ',', $post->getTitle()),
                $authorLabel,
                $post->getPlatformLanguageId(),
                str_replace([';', "\n", "\r"], [',', ' ', ' '], substr($post->getContent(), 0, 100)),
                $post->getPostedAt()->format('Y-m-d H:i:s'),
                $post->isActive() ? 'Active' : 'Inactive',
                $post->getViewCount(),
                $post->getReplyCount()
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="forum_posts_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHOW  (must stay after /new, /search/ajax, /bulk/action, /export/csv)
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{id}', name: 'admin_forum_post_show', methods: ['GET'])]
    public function show(ForumPost $post): Response
    {
        // Build user map so the show template can display real author names
        $users   = $this->userRepository->findAll();
        $userMap = [];
        foreach ($users as $u) {
            $userMap[$u->getId()] = [
                'name'    => trim($u->getFirstName() . ' ' . $u->getLastName()),
                'isAdmin' => in_array('ROLE_ADMIN', $u->getRoles(), true),
            ];
        }

        return $this->render('Forum/backend/forum_post/show.html.twig', [
            'post'    => $post,
            'userMap' => $userMap,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EDIT
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{id}/edit', name: 'admin_forum_post_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ForumPost $post): Response
    {
        $form = $this->createForm(ForumPostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Post updated successfully!');
            return $this->redirectToRoute('admin_forum_post_index');
        }

        // Get the author user object for display
        $author = $this->userRepository->find($post->getAuthorId());

        return $this->render('Forum/backend/forum_post/edit.html.twig', [
            'post'   => $post,
            'form'   => $form,
            'author' => $author,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{id}', name: 'admin_forum_post_delete', methods: ['POST'])]
    public function delete(Request $request, ForumPost $post): Response
    {
        if ($this->isCsrfTokenValid('delete' . $post->getId(), $request->request->get('_token'))) {
            $this->repository->remove($post, true);
            $this->addFlash('success', 'Post deleted successfully!');
        }

        return $this->redirectToRoute('admin_forum_post_index');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TOGGLE STATUS
    // ─────────────────────────────────────────────────────────────────────────
    #[Route('/{id}/toggle-status', name: 'admin_forum_post_toggle_status', methods: ['POST'])]
    public function toggleStatus(ForumPost $post): JsonResponse
    {
        try {
            $this->repository->toggleStatus($post);
            return $this->json([
                'success'  => true,
                'isActive' => $post->isActive(),
                'message'  => $post->isActive() ? 'Post activated' : 'Post deactivated',
            ]);
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => 'Could not change status.'], 500);
        }
    }
}
