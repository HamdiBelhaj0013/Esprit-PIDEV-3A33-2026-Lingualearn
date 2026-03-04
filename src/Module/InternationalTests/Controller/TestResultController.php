<?php

namespace App\Module\InternationalTests\Controller;

use App\Module\InternationalTests\Entity\TestResult;
use App\Module\InternationalTests\Form\TestResultType;
use App\Module\InternationalTests\Repository\TestResultRepository;
use App\Module\InternationalTests\Repository\MockTestRepository;
use App\Module\UserManagement\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/internationaltests/testresult')]
class TestResultController extends AbstractController
{
    #[Route('/', name: 'testresult_index', methods: ['GET'])]
    public function index(
        TestResultRepository $repository,
        MockTestRepository $mockTestRepository,
        UserRepository $userRepository
    ): Response {
        $testResults = $repository->findWithFilters();
        $mockTests   = $mockTestRepository->findAll();
        $users       = $userRepository->findAll();

        // Calcul des statistiques complet (toutes les clés attendues par le template)
        $baseStats = $repository->getStatistics();

        // Calcul avgAiScore depuis les résultats existants
        $aiScores = array_filter(
            array_map(fn(TestResult $r) => $r->getAiPredictedScore(), $testResults),
            fn($s) => $s !== null
        );
        $avgAiScore = count($aiScores) > 0
            ? round(array_sum($aiScores) / count($aiScores), 2)
            : 0;

        $statistics = array_merge($baseStats, [
            'avgAiScore'     => $avgAiScore,
            'totalMockTests' => count($mockTests),
        ]);

        return $this->render('internationaltests/testresult/index.html.twig', [
            'testResults' => $testResults,
            'mockTests'   => $mockTests,
            'users'       => $users,
            'statistics'  => $statistics,
        ]);
    }

    /**
     * Route AJAX pour recherche, filtre et tri
     */
    #[Route('/search', name: 'testresult_search', methods: ['GET'])]
    public function search(Request $request, TestResultRepository $repository): JsonResponse
    {
        $mockTestId = $request->query->get('mockTestId');
        $userId     = $request->query->get('userId');
        $sortBy     = $request->query->get('sortBy', 'dateTaken');
        $sortOrder  = $request->query->get('sortOrder', 'DESC');

        $testResults = $repository->findWithFilters(
            $mockTestId ? (int)$mockTestId : null,
            $userId     ? (int)$userId     : null,
            $sortBy,
            $sortOrder
        );

        $data = array_map(function(TestResult $result) {
            return [
                'id'               => $result->getId(),
                'userId'           => $result->getUser()?->getId(),
                'userEmail'        => $result->getUser()?->getEmail(),
                'mockTestId'       => $result->getMockTest()?->getId(),
                'mockTestTitle'    => $result->getMockTest()?->getTitle(),
                'overallScore'     => $result->getOverallScore(),
                'aiPredictedScore' => $result->getAiPredictedScore(),
                'dateTaken'        => $result->getDateTaken()?->format('Y-m-d H:i:s'),
            ];
        }, $testResults);

        return new JsonResponse([
            'success' => true,
            'count'   => count($data),
            'data'    => $data,
        ]);
    }

    #[Route('/new', name: 'testresult_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $testResult = new TestResult();
        $form = $this->createForm(TestResultType::class, $testResult);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $jsonInput = $form->get('aiWeaknessReport')->getData();
            if ($jsonInput) {
                $decoded = json_decode($jsonInput, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $testResult->setAiWeaknessReport($decoded);
                }
            }

            $em->persist($testResult);
            $em->flush();

            $this->addFlash('success', 'Test result created successfully!');
            return $this->redirectToRoute('testresult_index');
        }

        return $this->render('internationaltests/testresult/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'testresult_show', methods: ['GET'])]
    public function show(TestResult $testResult): Response
    {
        return $this->render('internationaltests/testresult/show.html.twig', [
            'testResult' => $testResult,
        ]);
    }

    #[Route('/{id}/edit', name: 'testresult_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TestResult $testResult, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TestResultType::class, $testResult);

        if ($testResult->getAiWeaknessReport()) {
            $form->get('aiWeaknessReport')->setData(
                json_encode($testResult->getAiWeaknessReport(), JSON_PRETTY_PRINT)
            );
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $jsonInput = $form->get('aiWeaknessReport')->getData();
            if ($jsonInput) {
                $decoded = json_decode($jsonInput, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $testResult->setAiWeaknessReport($decoded);
                }
            } else {
                $testResult->setAiWeaknessReport(null);
            }

            $em->flush();

            $this->addFlash('success', 'Test result updated successfully!');
            return $this->redirectToRoute('testresult_index');
        }

        return $this->render('internationaltests/testresult/edit.html.twig', [
            'testResult' => $testResult,
            'form'       => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'testresult_delete', methods: ['POST'])]
    public function delete(Request $request, TestResult $testResult, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$testResult->getId(), $request->request->get('_token'))) {
            $em->remove($testResult);
            $em->flush();

            $this->addFlash('success', 'Test result deleted successfully!');
        }

        return $this->redirectToRoute('testresult_index');
    }

    /**
     * Statistiques en temps réel
     */
    #[Route('/stats/refresh', name: 'testresult_stats_refresh', methods: ['GET'])]
    public function refreshStats(
        TestResultRepository $repository,
        MockTestRepository $mockTestRepository
    ): JsonResponse {
        $testResults = $repository->findWithFilters();
        $baseStats   = $repository->getStatistics();

        $aiScores = array_filter(
            array_map(fn(TestResult $r) => $r->getAiPredictedScore(), $testResults),
            fn($s) => $s !== null
        );
        $avgAiScore = count($aiScores) > 0
            ? round(array_sum($aiScores) / count($aiScores), 2)
            : 0;

        $statistics = array_merge($baseStats, [
            'avgAiScore'     => $avgAiScore,
            'totalMockTests' => $mockTestRepository->count([]),
        ]);

        return new JsonResponse([
            'success'    => true,
            'statistics' => $statistics,
        ]);
    }
}
