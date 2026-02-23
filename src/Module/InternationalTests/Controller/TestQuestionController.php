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
        $testQuestions = $repository->findWithFilters();
        $categories = $repository->findAllSectionCategories();
        $mockTests = $mockTestRepository->findAll();
        $statistics = $repository->getStatistics();

        return $this->render('internationaltests/testquestion/index.html.twig', [
            'testQuestions' => $testQuestions,
            'categories' => $categories,
            'mockTests' => $mockTests,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Route AJAX pour recherche, filtre et tri
     */
    #[Route('/search', name: 'testquestion_search', methods: ['GET'])]
    public function search(Request $request, TestQuestionRepository $repository): JsonResponse
    {
        $searchTerm = $request->query->get('search');
        $sectionCategory = $request->query->get('section');
        $mockTestId = $request->query->get('mockTest');
        $isActive = $request->query->get('isActive');
        $sortBy = $request->query->get('sortBy', 'createdAt');
        $sortOrder = $request->query->get('sortOrder', 'DESC');

        // Convertir le filtre isActive
        $isActiveFilter = null;
        if ($isActive === 'active') {
            $isActiveFilter = true;
        } elseif ($isActive === 'inactive') {
            $isActiveFilter = false;
        }

        $testQuestions = $repository->findWithFilters(
            $searchTerm,
            $sectionCategory,
            $mockTestId ? (int)$mockTestId : null,
            $isActiveFilter,
            $sortBy,
            $sortOrder
        );

        // Formatter les données pour AJAX
        $data = array_map(function(TestQuestion $question) {
            return [
                'id' => $question->getId(),
                'questionText' => substr($question->getQuestionText(), 0, 80) . (strlen($question->getQuestionText()) > 80 ? '...' : ''),
                'sectionCategory' => $question->getSectionCategory(),
                'points' => $question->getPoints(),
                'mockTestTitle' => $question->getMockTest()?->getTitle(),
                'mockTestId' => $question->getMockTest()?->getId(),
                'optionsCount' => count($question->getOptions()),
                'isActive' => $question->isActive(),
                'createdAt' => $question->getCreatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $testQuestions);

        return new JsonResponse([
            'success' => true,
            'count' => count($data),
            'data' => $data
        ]);
    }

    /**
     * Toggle le statut actif/inactif d'une question
     */
    #[Route('/{id}/toggle-status', name: 'testquestion_toggle_status', methods: ['POST'])]
    public function toggleStatus(TestQuestion $testQuestion, EntityManagerInterface $em): JsonResponse
    {
        try {
            $testQuestion->setIsActive(!$testQuestion->isActive());
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'isActive' => $testQuestion->isActive(),
                'message' => $testQuestion->isActive() ? 'Question activated successfully' : 'Question deactivated successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error toggling status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activation/Désactivation en masse
     */
    #[Route('/bulk-toggle', name: 'testquestion_bulk_toggle', methods: ['POST'])]
    public function bulkToggle(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $ids = $data['ids'] ?? [];
            $action = $data['action'] ?? null;

            if (empty($ids) || !in_array($action, ['activate', 'deactivate'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Invalid parameters'
                ], 400);
            }

            $isActive = $action === 'activate';
            
            $qb = $em->createQueryBuilder();
            $qb->update(TestQuestion::class, 'q')
                ->set('q.isActive', ':isActive')
                ->where($qb->expr()->in('q.id', ':ids'))
                ->setParameter('isActive', $isActive)
                ->setParameter('ids', $ids);

            $updated = $qb->getQuery()->execute();

            return new JsonResponse([
                'success' => true,
                'updated' => $updated,
                'message' => sprintf('%d question(s) %s successfully', $updated, $isActive ? 'activated' : 'deactivated')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/new', name: 'testquestion_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $testQuestion = new TestQuestion();
        $form = $this->createForm(TestQuestionType::class, $testQuestion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $optionsJson = $form->get('options')->getData();
            $optionsArray = json_decode($optionsJson, true);
            if ($optionsArray !== null) {
                $testQuestion->setOptions($optionsArray);
            }

            $em->persist($testQuestion);
            $em->flush();

            $this->addFlash('success', 'Test question created successfully!');
            return $this->redirectToRoute('testquestion_index');
        }

        return $this->render('internationaltests/testquestion/new.html.twig', [
            'form' => $form->createView(),
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
        $form->get('options')->setData(json_encode($testQuestion->getOptions(), JSON_PRETTY_PRINT));

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $optionsJson = $form->get('options')->getData();
            $optionsArray = json_decode($optionsJson, true);
            if ($optionsArray !== null) {
                $testQuestion->setOptions($optionsArray);
            }

            $em->flush();

            $this->addFlash('success', 'Test question updated successfully!');
            return $this->redirectToRoute('testquestion_index');
        }

        return $this->render('internationaltests/testquestion/edit.html.twig', [
            'testQuestion' => $testQuestion,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'testquestion_delete', methods: ['POST'])]
    public function delete(Request $request, TestQuestion $testQuestion, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$testQuestion->getId(), $request->request->get('_token'))) {
            $em->remove($testQuestion);
            $em->flush();
            
            $this->addFlash('success', 'Test question deleted successfully!');
        }

        return $this->redirectToRoute('testquestion_index');
    }

    /**
     * Statistiques en temps réel
     */
    #[Route('/stats/refresh', name: 'testquestion_stats_refresh', methods: ['GET'])]
    public function refreshStats(TestQuestionRepository $repository): JsonResponse
    {
        $statistics = $repository->getStatistics();

        return new JsonResponse([
            'success' => true,
            'statistics' => $statistics
        ]);
    }
}