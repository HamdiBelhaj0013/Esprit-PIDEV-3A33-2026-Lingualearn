<?php

namespace App\Module\InternationalTests\Controller;

use App\Module\InternationalTests\Entity\TestQuestion;
use App\Module\InternationalTests\Form\TestQuestionType;
use App\Module\InternationalTests\Repository\TestQuestionRepository;
use App\Module\InternationalTests\Repository\MockTestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Module\InternationalTests\Service\GeminiEmbeddingService;
use App\Module\InternationalTests\Service\QuestionSimilarityService;

#[Route('/internationaltests/testquestion')]
class TestQuestionController extends AbstractController
{
    // ── Seuil de blocage : 75% de similarité ──────────────────────
    private const BLOCK_THRESHOLD = 0.75;

    public function __construct(
        private GeminiEmbeddingService    $embeddingService,
        private QuestionSimilarityService $similarityService
    ) {}

    #[Route('/', name: 'testquestion_index', methods: ['GET'])]
    public function index(TestQuestionRepository $repository, MockTestRepository $mockTestRepository): Response
    {
        return $this->render('internationaltests/testquestion/index.html.twig', [
            'testQuestions' => $repository->findWithFilters(),
            'categories'    => $repository->findAllSectionCategories(),
            'mockTests'     => $mockTestRepository->findAll(),
            'statistics'    => $repository->getStatistics(),
        ]);
    }

    #[Route('/search', name: 'testquestion_search', methods: ['GET'])]
    public function search(Request $request, TestQuestionRepository $repository): JsonResponse
    {
        $isActive = $request->query->get('isActive');

        $testQuestions = $repository->findWithFilters(
            $request->query->get('search'),
            $request->query->get('section'),
            $request->query->get('mockTest') ? (int) $request->query->get('mockTest') : null,
            $isActive === 'active' ? true : ($isActive === 'inactive' ? false : null),
            $request->query->get('sortBy', 'createdAt'),
            $request->query->get('sortOrder', 'DESC')
        );

        $data = array_map(fn(TestQuestion $q) => [
            'id'            => $q->getId(),
            'questionText'  => substr($q->getQuestionText(), 0, 80) . (strlen($q->getQuestionText()) > 80 ? '...' : ''),
            'questionType'  => $q->getQuestionType(),
            'points'        => $q->getPoints(),
            'mockTestTitle' => $q->getMockTest()?->getTitle(),
            'mockTestId'    => $q->getMockTest()?->getId(),
            'optionsCount'  => count($q->getOptions()),
            'isActive'      => $q->isActive(),
            'createdAt'     => $q->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $testQuestions);

        return new JsonResponse(['success' => true, 'count' => count($data), 'data' => $data]);
    }

    #[Route('/{id}/toggle-status', name: 'testquestion_toggle_status', methods: ['POST'])]
    public function toggleStatus(TestQuestion $testQuestion, EntityManagerInterface $em): JsonResponse
    {
        try {
            $testQuestion->setIsActive(!$testQuestion->isActive());
            $em->flush();
            return new JsonResponse([
                'success'  => true,
                'isActive' => $testQuestion->isActive(),
                'message'  => $testQuestion->isActive() ? 'Activated' : 'Deactivated',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/bulk-toggle', name: 'testquestion_bulk_toggle', methods: ['POST'])]
    public function bulkToggle(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data   = json_decode($request->getContent(), true);
            $ids    = $data['ids'] ?? [];
            $action = $data['action'] ?? null;

            if (empty($ids) || !in_array($action, ['activate', 'deactivate'])) {
                return new JsonResponse(['success' => false, 'message' => 'Invalid parameters'], 400);
            }

            $isActive = $action === 'activate';
            $qb = $em->createQueryBuilder();
            $qb->update(TestQuestion::class, 'q')
                ->set('q.isActive', ':isActive')
                ->where($qb->expr()->in('q.id', ':ids'))
                ->setParameter('isActive', $isActive)
                ->setParameter('ids', $ids);

            return new JsonResponse([
                'success' => true,
                'updated' => $qb->getQuery()->execute(),
                'message' => sprintf('%d question(s) %s', count($ids), $isActive ? 'activated' : 'deactivated'),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Métier Avancé #4 : Détection de doublons ────────────────────────────

    /**
     * Route AJAX — vérifie la similarité en temps réel (pendant la saisie).
     * Utilisée uniquement pour l'AFFICHAGE d'un warning dans le formulaire.
     * Le blocage réel se fait côté serveur dans new() et edit().
     */
    #[Route('/check-similarity', name: 'testquestion_check_similarity', methods: ['POST'])]
    public function checkSimilarity(Request $request): JsonResponse
    {
        $data         = json_decode($request->getContent(), true);
        $questionText = trim($data['questionText'] ?? '');
        $excludeId    = isset($data['excludeId']) ? (int) $data['excludeId'] : null;

        if (strlen($questionText) < 10) {
            return new JsonResponse([
                'success'      => false,
                'hasDuplicate' => false,
                'message'      => 'Question too short to check.',
            ]);
        }

        $result = $this->similarityService->checkForDuplicates($questionText, $excludeId);

        // On filtre les résultats selon le seuil de BLOCAGE (75%)
        $blockingDuplicates = array_filter(
            $result['duplicates'] ?? [],
            fn($d) => ($d['similarityRaw'] ?? 0) >= self::BLOCK_THRESHOLD
        );

        return new JsonResponse([
            'success'            => true,
            'hasDuplicate'       => !empty($blockingDuplicates),
            'duplicates'         => array_values($blockingDuplicates),
            'checkedCount'       => $result['checkedCount'],
            'threshold'          => self::BLOCK_THRESHOLD * 100,
            'error'              => $result['error'] ?? null,
        ]);
    }

    #[Route('/new', name: 'testquestion_new', methods: ['GET', 'POST'])]
    public function new(
        Request                $request,
        EntityManagerInterface $em,
        MockTestRepository     $mockTestRepo
    ): Response {
        $testQuestion = new TestQuestion();

        // Pre-select mockTest if passed via ?mockTest=X
        $mockTestId = $request->query->get('mockTest');
        $mockTest   = null;
        if ($mockTestId) {
            $mockTest = $mockTestRepo->find((int) $mockTestId);
            if ($mockTest) {
                $testQuestion->setMockTest($mockTest);
            }
        }

        $form = $this->createForm(TestQuestionType::class, $testQuestion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleFormData($form, $testQuestion);

            // ── Métier Avancé #4 : Vérification doublon BLOQUANTE côté serveur ──
            $duplicateCheck = $this->checkDuplicateBeforeSave($testQuestion->getQuestionText(), null);
            if ($duplicateCheck !== null) {
                // Doublon détecté → on ne sauvegarde PAS, on renvoie le formulaire avec l'alerte
                return $this->render('internationaltests/testquestion/new.html.twig', [
                    'form'             => $form->createView(),
                    'mockTest'         => $mockTest,
                    'testCategory'     => $mockTest ? $mockTest->getTestCategory() : 'QCM',
                    'duplicateWarning' => $duplicateCheck, // ← transmis au template
                ]);
            }

            // ── Pas de doublon → génération embedding + sauvegarde ──
            $embedding = $this->embeddingService->generateEmbedding($testQuestion->getQuestionText());
            if ($embedding) {
                $testQuestion->setEmbedding($embedding);
            }

            $em->persist($testQuestion);
            $em->flush();

            $this->addFlash('success', 'Question créée avec succès !');

            if ($testQuestion->getMockTest()) {
                return $this->redirectToRoute('mocktest_show', [
                    'id' => $testQuestion->getMockTest()->getId(),
                ]);
            }
            return $this->redirectToRoute('testquestion_index');
        }

        return $this->render('internationaltests/testquestion/new.html.twig', [
            'form'             => $form->createView(),
            'mockTest'         => $mockTest,
            'testCategory'     => $mockTest ? $mockTest->getTestCategory() : 'QCM',
            'duplicateWarning' => null,
        ]);
    }

    #[Route('/{id}', name: 'testquestion_show', methods: ['GET'])]
    public function show(TestQuestion $testQuestion): Response
    {
        return $this->render('internationaltests/testquestion/show.html.twig', [
            'testQuestion' => $testQuestion,
        ]);
    }

    #[Route('/{id}/edit', name: 'testquestion_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TestQuestion $testQuestion, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TestQuestionType::class, $testQuestion);

        // Pre-fill options textarea
        if (!empty($testQuestion->getOptions())) {
            $lines = array_map(fn($o) => is_array($o) ? ($o['text'] ?? '') : $o, $testQuestion->getOptions());
            $form->get('options')->setData(implode("\n", $lines));
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleFormData($form, $testQuestion);

            // ── Métier Avancé #4 : Vérification doublon BLOQUANTE (en excluant la question actuelle) ──
            $duplicateCheck = $this->checkDuplicateBeforeSave($testQuestion->getQuestionText(), $testQuestion->getId());
            if ($duplicateCheck !== null) {
                return $this->render('internationaltests/testquestion/edit.html.twig', [
                    'form'             => $form->createView(),
                    'testQuestion'     => $testQuestion,
                    'testCategory'     => $testQuestion->getMockTest()?->getTestCategory() ?? 'QCM',
                    'duplicateWarning' => $duplicateCheck,
                ]);
            }

            // ── Pas de doublon → régénérer l'embedding + sauvegarde ──
            $embedding = $this->embeddingService->generateEmbedding($testQuestion->getQuestionText());
            if ($embedding) {
                $testQuestion->setEmbedding($embedding);
            }

            $em->flush();

            $this->addFlash('success', 'Question mise à jour avec succès !');
            return $this->redirectToRoute('mocktest_show', [
                'id' => $testQuestion->getMockTest()->getId(),
            ]);
        }

        return $this->render('internationaltests/testquestion/edit.html.twig', [
            'form'             => $form->createView(),
            'testQuestion'     => $testQuestion,
            'testCategory'     => $testQuestion->getMockTest()?->getTestCategory() ?? 'QCM',
            'duplicateWarning' => null,
        ]);
    }

    #[Route('/{id}/delete', name: 'testquestion_delete', methods: ['POST'])]
    public function delete(Request $request, TestQuestion $testQuestion, EntityManagerInterface $em): Response
    {
        $mockTestId = $testQuestion->getMockTest()?->getId();

        if ($this->isCsrfTokenValid('delete' . $testQuestion->getId(), $request->request->get('_token'))) {
            $em->remove($testQuestion);
            $em->flush();
            $this->addFlash('success', 'Question supprimée avec succès !');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        if ($mockTestId) {
            return $this->redirectToRoute('mocktest_show', ['id' => $mockTestId]);
        }
        return $this->redirectToRoute('testquestion_index');
    }

    #[Route('/stats/refresh', name: 'testquestion_stats_refresh', methods: ['GET'])]
    public function refreshStats(TestQuestionRepository $repository): JsonResponse
    {
        return new JsonResponse(['success' => true, 'statistics' => $repository->getStatistics()]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Vérifie les doublons AVANT la sauvegarde.
     * Retourne un tableau d'info sur le doublon si bloquant (>75%), null sinon.
     * 
     * Si l'API Gemini est indisponible (timeout), on laisse passer la sauvegarde
     * pour ne pas bloquer l'utilisateur à cause d'une erreur externe.
     */
    private function checkDuplicateBeforeSave(string $questionText, ?int $excludeId): ?array
    {
        if (strlen(trim($questionText)) < 10) {
            return null; // Question trop courte, pas de vérification
        }

        $result = $this->similarityService->checkForDuplicates($questionText, $excludeId);

        // Si l'API est indisponible, on laisse passer (fail-open)
        if (isset($result['error']) && !empty($result['error'])) {
            return null;
        }

        // Filtrer selon le seuil de BLOCAGE 75%
        $blockingDuplicates = array_filter(
            $result['duplicates'] ?? [],
            fn($d) => ($d['similarityRaw'] ?? 0) >= self::BLOCK_THRESHOLD
        );

        if (empty($blockingDuplicates)) {
            return null; // Pas de doublon → sauvegarde autorisée
        }

        // Retourner le doublon le plus similaire pour l'affichage
        $topDuplicate = reset($blockingDuplicates);
        return [
            'similarity'    => $topDuplicate['similarity'],       // ex: 82.5
            'questionText'  => $topDuplicate['questionText'],     // texte de la question existante
            'mockTestTitle' => $topDuplicate['mockTestTitle'],    // nom du test
            'questionId'    => $topDuplicate['id'],               // id pour lien éventuel
            'count'         => count($blockingDuplicates),        // nb total de doublons
        ];
    }

    private function handleFormData($form, TestQuestion $testQuestion): void
    {
        $type = $testQuestion->getMockTest()?->getTestCategory() ?? 'QCM';

        $questionType = match(strtolower($type)) {
            'writing'   => TestQuestion::TYPE_WRITING,
            'speaking'  => TestQuestion::TYPE_SPEAKING,
            'listening' => TestQuestion::TYPE_LISTENING,
            default     => TestQuestion::TYPE_QCM,
        };
        $testQuestion->setQuestionType($questionType);
        $testQuestion->setSectionCategory($type);

        if ($questionType === TestQuestion::TYPE_QCM) {
            $raw = $form->get('options')->getData();
            $testQuestion->setOptions($this->parseOptions($raw));

            if (empty($testQuestion->getCorrectAnswer())) {
                $testQuestion->setCorrectAnswer('');
            }
        } else {
            $testQuestion->setOptions([]);
            $testQuestion->setCorrectAnswer('N/A');
        }
    }

    private function parseOptions(?string $raw): array
    {
        if (empty(trim((string) $raw))) {
            return [];
        }
        $raw = trim($raw);

        if (str_starts_with($raw, '{')) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        if (str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
                $result = [];
                foreach (array_values($decoded) as $index => $value) {
                    if (isset($letters[$index])) {
                        $result[$letters[$index]] = $value;
                    }
                }
                return $result;
            }
        }

        $lines = array_values(array_filter(
            array_map('trim', explode("\n", str_replace("\r\n", "\n", $raw))),
            fn($l) => $l !== ''
        ));

        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $result = [];
        foreach ($lines as $index => $value) {
            if (isset($letters[$index])) {
                $result[$letters[$index]] = $value;
            }
        }
        return $result;
    }
}
