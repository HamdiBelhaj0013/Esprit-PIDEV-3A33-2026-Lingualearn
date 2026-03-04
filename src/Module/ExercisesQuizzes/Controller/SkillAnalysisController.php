<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Controller;

use App\Module\ExercisesQuizzes\Repository\ExerciceRepository;
use App\Module\ExercisesQuizzes\Repository\RecommendationSessionRepository;
use App\Module\ExercisesQuizzes\Repository\SkillProfileRepository;
use App\Module\ExercisesQuizzes\Service\AiCoachService;
use App\Module\ExercisesQuizzes\Service\RecommendationService;
use App\Module\ExercisesQuizzes\Service\SkillAnalyzerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Page "Analyse de compétences" : mastery par skill, points faibles, exercices recommandés, feedback IA.
 */
#[IsGranted('ROLE_USER')]
#[Route('/learn/skills', name: 'learn_skills_')]
class SkillAnalysisController extends AbstractController
{
    public function __construct(
        private SkillAnalyzerService $skillAnalyzerService,
        private RecommendationService $recommendationService,
        private AiCoachService $aiCoachService,
        private SkillProfileRepository $skillProfileRepository,
        private RecommendationSessionRepository $recommendationSessionRepository,
        private ExerciceRepository $exerciceRepository,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $this->skillAnalyzerService->computeAndSaveProfiles($user);

        $session = $this->recommendationService->buildRecommendationSession($user);
        try {
            $this->aiCoachService->generateAndAttachFeedback($session);
        } catch (\Throwable) {
            // Déjà géré dans AiCoachService : session sans aiFeedback
        }

        $profiles = $this->skillProfileRepository->findByUser($user);
        $weakCodes = $session->getWeakSkillCodes();
        $recommendedIds = $session->getRecommendedExerciseIds();
        $recommendedExercises = $recommendedIds !== []
    ? $this->exerciceRepository->findBy(['id' => $recommendedIds])
    : [];
        usort($recommendedExercises, fn ($a, $b) => (array_search($a->getId(), $recommendedIds, true) ?: 0) <=> (array_search($b->getId(), $recommendedIds, true) ?: 0));

        $aiFeedback = $session->getAiFeedback();
        $aiUnavailable = ($aiFeedback === null || $aiFeedback === '') && $weakCodes !== [];

        return $this->render('exercises_quizzes/skills/analysis.html.twig', [
            'profiles' => $profiles,
            'weakSkillCodes' => $weakCodes,
            'recommendedExercises' => $recommendedExercises,
            'aiFeedback' => $aiFeedback ?: null,
            'aiUnavailable' => $aiUnavailable,
        ]);
    }
}
