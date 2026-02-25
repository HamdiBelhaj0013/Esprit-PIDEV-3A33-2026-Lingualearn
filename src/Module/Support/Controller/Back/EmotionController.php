<?php

namespace App\Module\Support\Controller\Back;

use App\Module\Support\Entity\Reclamation;
use App\Module\Support\Service\OllamaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/support/emotion')]
#[IsGranted('ROLE_ADMIN')]
class EmotionController extends AbstractController
{
    public function __construct(
        private OllamaService $ollamaService
    ) {}

    #[Route('/{id}/analyze', name: 'admin_emotion_analyze', methods: ['POST'])]
    public function analyze(Reclamation $reclamation): JsonResponse
    {
        $text   = $reclamation->getSubject() . ' ' . $reclamation->getMessageBody();
        $result = $this->ollamaService->analyzeEmotion($text);

        return $this->json($result);
    }

    #[Route('/status', name: 'admin_ollama_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return $this->json(['available' => $this->ollamaService->isAvailable()]);
    }
}
