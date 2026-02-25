<?php

namespace App\Module\PedagogicalContent\Controller;
use App\Service\HuggingFaceSummarizer;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Module\PedagogicalContent\Entity\Lesson;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Vich\UploaderBundle\Storage\StorageInterface;

#[IsGranted('ROLE_USER')]
class LessonResourceFrontController extends AbstractController
{
    public function __construct(
        private readonly StorageInterface $storage,
    ) {}

    // =========================
    // 1) PAGE QUI AFFICHE LE PDF (iframe)
    // =========================
    #[Route('/lessons/{id}/resource', name: 'lesson_resource_view', methods: ['GET'])]
    public function view(Lesson $lesson): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Accès interdit côté étudiant.');
        }

        if (!$lesson->getResourceName()) {
            throw new NotFoundHttpException('Aucune ressource pour cette leçon.');
        }

        return $this->render('pedagogical_content/lesson/resource_view.html.twig', [
            'lesson' => $lesson,
            'pdfUrl' => $this->generateUrl('lesson_resource_file', ['id' => $lesson->getId()]),
        ]);
    }

    // =========================
    // 2) FICHIER PDF EN INLINE (affichage navigateur)
    // =========================
    #[Route('/lessons/{id}/resource/file', name: 'lesson_resource_file', methods: ['GET'])]
    public function resourceFile(Lesson $lesson): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Accès interdit côté étudiant.');
        }

        if (!$lesson->getResourceName()) {
            throw new NotFoundHttpException('Aucune ressource pour cette leçon.');
        }

        $absolutePath = $this->storage->resolvePath($lesson, 'resourceFile');
        if (!$absolutePath || !is_file($absolutePath)) {
            throw new NotFoundHttpException('Fichier introuvable sur le disque.');
        }

        $response = new BinaryFileResponse($absolutePath);

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $lesson->getResourceName()
        );

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
    #[Route('/learn/lesson/{id}/summary', name: 'lesson_summary', methods: ['GET'])]
public function summary(Lesson $lesson, HuggingFaceSummarizer $summarizer): JsonResponse
{
    if ($this->isGranted('ROLE_ADMIN')) {
        throw new AccessDeniedException('Accès interdit côté étudiant.');
    }

    $text = trim((string) $lesson->getContent());
    if ($text === '') {
        return new JsonResponse(['error' => 'Contenu de leçon vide'], 400);
    }

    try {
        $summary = $summarizer->summarize($text);
        return new JsonResponse(['summary' => $summary]);
    } catch (\Throwable $e) {
        return new JsonResponse([
            'error' => 'Erreur Summary (HF)',
            'details' => $e->getMessage(),
        ], 500);
    }
}
}