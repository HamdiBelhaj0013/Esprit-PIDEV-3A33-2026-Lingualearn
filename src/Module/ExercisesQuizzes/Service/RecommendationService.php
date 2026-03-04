<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Service;

use App\Module\ExercisesQuizzes\Entity\RecommendationSession;
use App\Module\ExercisesQuizzes\Repository\ExerciceRepository;
use App\Module\ExercisesQuizzes\Repository\RecommendationSessionRepository;
use App\Module\ExercisesQuizzes\Repository\SkillProfileRepository;
use App\Module\UserManagement\Entity\User;

/**
 * Détecte les compétences faibles (mastery < seuil), trie par priorité, sélectionne les exercices recommandés (déterministe en DB).
 */
class RecommendationService
{
    private const WEAK_MASTERY_THRESHOLD = 60;
    private const RECOMMENDED_EXERCISES_LIMIT = 10;
    private const RECENTLY_MASTERED_THRESHOLD = 85;

    public function __construct(
        private SkillProfileRepository $skillProfileRepository,
        private ExerciceRepository $exerciceRepository,
        private RecommendationSessionRepository $recommendationSessionRepository,
    ) {}

    /**
     * Construit une session de recommandations : weak skills + IDs d'exercices recommandés (sans feedback IA).
     */
    public function buildRecommendationSession(User $user): RecommendationSession
    {
        $profiles = $this->skillProfileRepository->findByUser($user);
        $weakSkillCodes = $this->getWeakSkillsSortedByPriority($user, $profiles);

        $excludeIds = $this->getRecentlyMasteredExerciseIds($user, $profiles);
        $recommendedIds = $this->selectRecommendedExerciseIds($weakSkillCodes, $excludeIds);

        $session = new RecommendationSession();
        $session->setUser($user);
        $session->setWeakSkillCodes($weakSkillCodes);
        $session->setRecommendedExerciseIds($recommendedIds);
        $this->recommendationSessionRepository->save($session, true);

        return $session;
    }

    /**
     * @param \App\Module\ExercisesQuizzes\Entity\SkillProfile[] $profiles
     * @return string[]
     */
    private function getWeakSkillsSortedByPriority(User $user, array $profiles): array
    {
        $weak = [];
        foreach ($profiles as $profile) {
            if ($profile->getMastery() < self::WEAK_MASTERY_THRESHOLD) {
                $weak[] = [
                    'skillCode' => $profile->getSkillCode(),
                    'mastery' => $profile->getMastery(),
                    'attemptsCount' => $profile->getAttemptsCount(),
                ];
            }
        }

        usort($weak, function ($a, $b) {
            if ($a['mastery'] !== $b['mastery']) {
                return $a['mastery'] <=> $b['mastery'];
            }
            return $b['attemptsCount'] <=> $a['attemptsCount'];
        });

        return array_map(fn ($w) => $w['skillCode'], $weak);
    }

    /**
     * Exercices déjà bien maîtrisés (skill avec mastery >= seuil) : on exclut leurs IDs pour varier.
     *
     * @param \App\Module\ExercisesQuizzes\Entity\SkillProfile[] $profiles
     * @return int[]
     */
    private function getRecentlyMasteredExerciseIds(User $user, array $profiles): array
    {
        $strongSkills = [];
        foreach ($profiles as $profile) {
            if ($profile->getMastery() >= self::RECENTLY_MASTERED_THRESHOLD) {
                $strongSkills[] = $profile->getSkillCode();
            }
        }
        if ($strongSkills === []) {
            return [];
        }

        $exercises = $this->exerciceRepository->findBySkillCodes($strongSkills, null, [], 50);

        // on filtre les null au cas où getId() retourne ?int
        return array_values(array_filter(
            array_map(fn ($e) => $e->getId(), $exercises),
            fn ($id) => $id !== null
        ));
    }

    /**
     * Sélection déterministe : facile, moyen, challenge par skill faible.
     *
     * @param string[] $weakSkillCodes
     * @param int[] $excludeIds
     * @return int[]
     */
    private function selectRecommendedExerciseIds(array $weakSkillCodes, array $excludeIds): array
    {
        if ($weakSkillCodes === []) {
            $all = $this->exerciceRepository->findBy(
                ['enabled' => true],
                ['id' => 'ASC'],
                self::RECOMMENDED_EXERCISES_LIMIT
            );

            return array_values(array_filter(
                array_map(fn ($e) => $e->getId(), $all),
                fn ($id) => $id !== null
            ));
        }

        $ids = [];
        $byDifficulty = [
            1 => 3,
            2 => 3,
            3 => 4,
        ];

        foreach ($byDifficulty as $maxDiff => $limit) {
            $found = $this->exerciceRepository->findBySkillCodes($weakSkillCodes, $maxDiff, $excludeIds, $limit);
            foreach ($found as $e) {
                $id = $e->getId();
                if ($id !== null && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }

        if (count($ids) < self::RECOMMENDED_EXERCISES_LIMIT) {
            $extra = $this->exerciceRepository->findBySkillCodes(
                $weakSkillCodes,
                null,
                array_merge($excludeIds, $ids),
                self::RECOMMENDED_EXERCISES_LIMIT - count($ids)
            );

            foreach ($extra as $e) {
                $id = $e->getId();
                if ($id !== null && !in_array($id, $ids, true)) {
                    $ids[] = $id;
                }
            }
        }

        return array_slice($ids, 0, self::RECOMMENDED_EXERCISES_LIMIT);
    }
}
