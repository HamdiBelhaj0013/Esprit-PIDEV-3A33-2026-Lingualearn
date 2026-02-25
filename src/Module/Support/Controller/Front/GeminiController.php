<?php

namespace App\Module\Support\Controller\Front;

use App\Module\Support\Service\GeminiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/support/gemini')]
class GeminiController extends AbstractController
{
    public function __construct(
        private GeminiService $geminiService
    ) {}

    #[Route('/correct', name: 'app_gemini_correct', methods: ['POST'])]
    public function correct(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $text = $data['text'] ?? '';

        if (empty(trim($text))) {
            return $this->json(['error' => 'Texte vide'], 400);
        }

        try {
            $corrected = $this->geminiService->correctText($text);

            if (empty($corrected)) {
                return $this->json(['error' => 'Gemini n\'a pas répondu'], 500);
            }

            return $this->json(['corrected' => $corrected]);

        } catch (\Throwable $e) {
            return $this->json(['error' => 'Erreur Gemini : ' . $e->getMessage()], 500);
        }
    }

    #[Route('/translate', name: 'app_gemini_translate', methods: ['POST'])]
    public function translate(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $text   = $data['text']   ?? '';
        $target = $data['target'] ?? 'en';

        if (empty(trim($text))) {
            return $this->json(['error' => 'Texte vide'], 400);
        }

        try {
            $translated = $this->geminiService->translateText($text, $target);

            if (empty($translated)) {
                return $this->json(['error' => 'Gemini n\'a pas répondu'], 500);
            }

            return $this->json(['translated' => $translated]);

        } catch (\Throwable $e) {
            return $this->json(['error' => 'Erreur Gemini : ' . $e->getMessage()], 500);
        }
    }
}