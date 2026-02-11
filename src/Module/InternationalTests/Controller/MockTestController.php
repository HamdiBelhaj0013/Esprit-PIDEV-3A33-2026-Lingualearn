<?php

namespace App\Module\InternationalTests\Controller;

use App\Module\InternationalTests\Entity\MockTest;
use App\Module\InternationalTests\Form\MockTestType;
use App\Module\InternationalTests\Repository\MockTestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/internationaltests/mocktest')]
class MockTestController extends AbstractController
{
    #[Route('/', name: 'mocktest_index', methods: ['GET'])]
    public function index(MockTestRepository $repository): Response
    {
        $mockTests = $repository->findWithFilters();
        $testTypes = $repository->findAllTestTypes();
        $statistics = $repository->getStatistics();

        return $this->render('internationaltests/mocktest/index.html.twig', [
            'mockTests' => $mockTests,
            'testTypes' => $testTypes,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Route AJAX pour recherche, filtre et tri
     */
    #[Route('/search', name: 'mocktest_search', methods: ['GET'])]
    public function search(Request $request, MockTestRepository $repository): JsonResponse
    {
        $searchTerm = $request->query->get('search');
        $testType = $request->query->get('testType');
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

        $mockTests = $repository->findWithFilters(
            $searchTerm,
            $testType,
            $isActiveFilter,
            $sortBy,
            $sortOrder
        );

        // Formatter les données pour AJAX
        $data = array_map(function(MockTest $test) {
            return [
                'id' => $test->getId(),
                'title' => $test->getTitle(),
                'testType' => $test->getTestType(),
                'durationMinutes' => $test->getDurationMinutes(),
                'platformLanguageId' => $test->getPlatformLanguageId(),
                'questionsCount' => $test->getTestQuestions()->count(),
                'isActive' => $test->isActive(),
                'createdAt' => $test->getCreatedAt()?->format('Y-m-d H:i:s'),
                'updatedAt' => $test->getUpdatedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $mockTests);

        return new JsonResponse([
            'success' => true,
            'count' => count($data),
            'data' => $data
        ]);
    }

    /**
     * Toggle le statut actif/inactif d'un test
     */
    #[Route('/{id}/toggle-status', name: 'mocktest_toggle_status', methods: ['POST'])]
    public function toggleStatus(MockTest $mockTest, EntityManagerInterface $em): JsonResponse
    {
        try {
            $mockTest->setIsActive(!$mockTest->isActive());
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'isActive' => $mockTest->isActive(),
                'message' => $mockTest->isActive() ? 'Test activated successfully' : 'Test deactivated successfully'
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
    #[Route('/bulk-toggle', name: 'mocktest_bulk_toggle', methods: ['POST'])]
    public function bulkToggle(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $ids = $data['ids'] ?? [];
            $action = $data['action'] ?? null; // 'activate' ou 'deactivate'

            if (empty($ids) || !in_array($action, ['activate', 'deactivate'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Invalid parameters'
                ], 400);
            }

            $isActive = $action === 'activate';
            
            $qb = $em->createQueryBuilder();
            $qb->update(MockTest::class, 'm')
                ->set('m.isActive', ':isActive')
                ->where($qb->expr()->in('m.id', ':ids'))
                ->setParameter('isActive', $isActive)
                ->setParameter('ids', $ids);

            $updated = $qb->getQuery()->execute();

            return new JsonResponse([
                'success' => true,
                'updated' => $updated,
                'message' => sprintf('%d test(s) %s successfully', $updated, $isActive ? 'activated' : 'deactivated')
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/new', name: 'mocktest_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $mockTest = new MockTest();
        $form = $this->createForm(MockTestType::class, $mockTest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($mockTest);
            $em->flush();

            $this->addFlash('success', 'Mock test created successfully!');
            return $this->redirectToRoute('mocktest_index');
        }

        return $this->render('internationaltests/mocktest/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'mocktest_show', methods: ['GET'])]
    public function show(MockTest $mockTest): Response
    {
        return $this->render('internationaltests/mocktest/show.html.twig', [
            'mockTest' => $mockTest,
        ]);
    }

    #[Route('/{id}/edit', name: 'mocktest_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, MockTest $mockTest, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(MockTestType::class, $mockTest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            
            $this->addFlash('success', 'Mock test updated successfully!');
            return $this->redirectToRoute('mocktest_index');
        }

        return $this->render('internationaltests/mocktest/edit.html.twig', [
            'mockTest' => $mockTest,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'mocktest_delete', methods: ['POST'])]
    public function delete(Request $request, MockTest $mockTest, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$mockTest->getId(), $request->request->get('_token'))) {
            $em->remove($mockTest);
            $em->flush();
            
            $this->addFlash('success', 'Mock test deleted successfully!');
        }

        return $this->redirectToRoute('mocktest_index');
    }

    /**
     * Statistiques en temps réel
     */
    #[Route('/stats/refresh', name: 'mocktest_stats_refresh', methods: ['GET'])]
    public function refreshStats(MockTestRepository $repository): JsonResponse
    {
        $statistics = $repository->getStatistics();

        return new JsonResponse([
            'success' => true,
            'statistics' => $statistics
        ]);
    }
}