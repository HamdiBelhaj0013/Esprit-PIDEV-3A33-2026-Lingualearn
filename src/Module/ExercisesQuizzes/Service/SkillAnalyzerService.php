<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Service;

use App\Module\ExercisesQuizzes\Entity\ExerciseAttempt;
use App\Module\ExercisesQuizzes\Entity\Exercice;
use App\Module\ExercisesQuizzes\Entity\QuizAttempt;
use App\Module\ExercisesQuizzes\Entity\SkillProfile;
use App\Module\ExercisesQuizzes\Repository\QuizAttemptRepository;
use App\Module\ExercisesQuizzes\Repository\SkillProfileRepository;
use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calcule le profil de maîtrise par compétence à partir des tentatives (Attempt/Result).
 * mastery 0..100 : normalizedScore/correctRate + pondération difficulté + pénalité temps si timeSpent existe.
 */
class SkillAnalyzerService
{
    private const MASTERY_MAX = 100;
    private const TIME_PENALTY_FACTOR = 0.02; // léger malus par 60s au-dessus d'un seuil

    public function __construct(
        private QuizAttemptRepository $quizAttemptRepository,
        private SkillProfileRepository $skillProfileRepository,
        private EntityManagerInterface $em,
    ) {}

    /**
     * Recalcule et persiste les SkillProfile pour l'utilisateur.
     *
     * @return array<string, array{mastery: int, attemptsCount: int}>
     */
    public function computeAndSaveProfiles(User $user): array
    {
        $attempts = $this->quizAttemptRepository->findFinishedForUserWithDetails($user);
        $bySkill = $this->aggregateBySkill($attempts);

        $now = new \DateTimeImmutable();
        foreach ($bySkill as $skillCode => $data) {
            $profile = $this->skillProfileRepository->findOneByUserAndSkill($user, $skillCode);
            if (!$profile) {
                $profile = new SkillProfile();
                $profile->setUser($user);
                $profile->setSkillCode($skillCode);
            }
            $profile->setMastery($data['mastery']);
            $profile->setAttemptsCount($data['attemptsCount']);
            $profile->setUpdatedAt($now);
            $this->skillProfileRepository->save($profile);
        }
        $this->em->flush();

        return $bySkill;
    }

    /**
     * Agrège les tentatives par skill et calcule mastery (0-100).
     *
     * @param QuizAttempt[] $attempts
     * @return array<string, array{mastery: int, attemptsCount: int}>
     */
    private function aggregateBySkill(array $attempts): array
    {
        $bySkill = [];

        foreach ($attempts as $quizAttempt) {
            $quizDurationSeconds = null;
            if ($quizAttempt->getFinishedAt() && $quizAttempt->getCreatedAt()) {
                $quizDurationSeconds = $quizAttempt->getFinishedAt()->getTimestamp() - $quizAttempt->getCreatedAt()->getTimestamp();
            }

            foreach ($quizAttempt->getExerciseAttempts() as $exerciseAttempt) {
                $exercise = $exerciseAttempt->getExercise();
                if (!$exercise instanceof Exercice) {
                    continue;
                }
                $skills = $exercise->getSkillCodes();
if ($skills === []) {
    continue;
}

                $normalizedScore = $exerciseAttempt->getIsCorrect() ? 100 : 0;
                $difficulty = max(1, min(5, $exercise->getDifficulty()));
                $weight = 1 + ($difficulty - 3) * 0.1; // pondération légère par difficulté
                $scoreWeighted = $normalizedScore * $weight;

                $timePenalty = 0;
                $timeSpent = $exerciseAttempt->getTimeSpentSeconds();
                if ($timeSpent !== null && $timeSpent > 60) {
                    $timePenalty = min(15, ($timeSpent - 60) * self::TIME_PENALTY_FACTOR);
                } elseif ($quizDurationSeconds !== null && $quizDurationSeconds > 0) {
                    $estimatedPerQuestion = (int) ceil($quizDurationSeconds / max(1, $quizAttempt->getExerciseAttempts()->count()));
                    if ($estimatedPerQuestion > 60) {
                        $timePenalty = min(10, ($estimatedPerQuestion - 60) * self::TIME_PENALTY_FACTOR * 0.5);
                    }
                }

                $masteryContribution = max(0, min(100, $scoreWeighted - $timePenalty));

                foreach ($skills as $skillCode) {
                    $skillCode = (string) $skillCode;
                    if (!isset($bySkill[$skillCode])) {
                        $bySkill[$skillCode] = ['totalScore' => 0, 'count' => 0];
                    }
                    $bySkill[$skillCode]['totalScore'] += $masteryContribution;
                    $bySkill[$skillCode]['count'] += 1;
                }
            }
        }

        $result = [];
        foreach ($bySkill as $skillCode => $data) {
            $count = $data['count'];
            $avg = $count > 0 ? $data['totalScore'] / $count : 0;
            $result[$skillCode] = [
                'mastery' => (int) round(min(self::MASTERY_MAX, max(0, $avg))),
                'attemptsCount' => $count,
            ];
        }

        return $result;
    }
}
