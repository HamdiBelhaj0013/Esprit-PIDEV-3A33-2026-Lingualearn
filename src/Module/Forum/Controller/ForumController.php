<?php

namespace App\Module\Forum\Controller;

use App\Module\Forum\Repository\ForumPostRepository;
use App\Module\Forum\Repository\ForumReplyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/forum')]
class ForumController extends AbstractController
{
    public function __construct(
        private ForumPostRepository $postRepository,
        private ForumReplyRepository $replyRepository
    ) {}

    /**
     * Page d'accueil du Forum
     */
    #[Route('/', name: 'admin_forum_index', methods: ['GET'])]
    public function index(): Response
    {
        $stats = [
            'totalPosts' => $this->postRepository->count([]),
            'activePosts' => $this->postRepository->count(['isActive' => true]),
            'inactivePosts' => $this->postRepository->count(['isActive' => false]),
            'totalReplies' => $this->replyRepository->count([]),
            'activeReplies' => $this->replyRepository->count(['isActive' => true]),
            'bestAnswers' => $this->replyRepository->count(['isBestAnswer' => true]),
        ];

        return $this->render('Forum/backend/index.html.twig', [
            'stats' => $stats,
        ]);
    }
}