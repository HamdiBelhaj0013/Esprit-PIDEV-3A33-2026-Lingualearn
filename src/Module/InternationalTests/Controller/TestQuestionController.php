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

#[Route('/internationaltests/testquestion')]
class TestQuestionController extends AbstractController
{
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
            $em->persist($testQuestion);
            $em->flush();

            $this->addFlash('success', 'Question créée avec succès !');

            // Redirect back to mockTest show if we came from there
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
            $em->flush();

            $this->addFlash('success', 'Question mise à jour avec succès !');
            return $this->redirectToRoute('mocktest_show', [
                'id' => $testQuestion->getMockTest()->getId(),
            ]);
        }

        return $this->render('internationaltests/testquestion/edit.html.twig', [
            'form'         => $form->createView(),
            'testQuestion' => $testQuestion,
            'testCategory' => $testQuestion->getMockTest()?->getTestCategory() ?? 'QCM',
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

    private function handleFormData($form, TestQuestion $testQuestion): void
    {
        $type = $testQuestion->getMockTest()?->getTestCategory() ?? 'QCM';

        // Sync questionType on entity from parent mockTest's testCategory
        $questionType = match(strtolower($type)) {
            'writing'   => TestQuestion::TYPE_WRITING,
            'speaking'  => TestQuestion::TYPE_SPEAKING,
            'listening' => TestQuestion::TYPE_LISTENING,
            default     => TestQuestion::TYPE_QCM,
        };
        $testQuestion->setQuestionType($questionType);

        // Set sectionCategory = testCategory (since we removed the field from the form)
        $testQuestion->setSectionCategory($type);

        if ($questionType === TestQuestion::TYPE_QCM) {
            // Parse options
            $raw = $form->get('options')->getData();
            $testQuestion->setOptions($this->parseOptions($raw));

            // correctAnswer is already mapped from the form field
            if (empty($testQuestion->getCorrectAnswer())) {
                $testQuestion->setCorrectAnswer('');
            }
        } else {
            // Writing / Speaking / Listening — no options, no correctAnswer
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

        // Try JSON object format: {"A": "Option A", "B": "Option B"}
        if (str_starts_with($raw, '{')) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // Try JSON array format: ["Option A", "Option B", "Option C", "Option D"]
        if (str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Convert to associative array with A, B, C, D keys
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

        // Plain text: one per line
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", str_replace("\r\n", "\n", $raw))),
            fn($l) => $l !== ''
        ));

        // Convert to associative array with A, B, C, D keys
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
