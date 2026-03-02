<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Service;

use App\Module\ExercisesQuizzes\Entity\ExerciseAttempt;
use App\Module\ExercisesQuizzes\Entity\Exercice;
use App\Module\ExercisesQuizzes\Entity\QuizAttempt;
use App\Module\ExercisesQuizzes\Repository\ExerciceRepository;
use App\Module\ExercisesQuizzes\Repository\QuizAttemptRepository;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Métier "Deuxième chance" : choix aléatoire d'un exercice faux, mini-session, best score.
 */
final class SecondChanceService
{
    private const GRAPH_NAME = 'quiz_attempt';

    public function __construct(
        private QuizAttemptRepository $quizAttemptRepository,
        private ExerciceRepository $exerciceRepository,
        private EntityManagerInterface $em,
        private StateMachineFactoryInterface $stateMachineFactory,
    ) {
    }

    /**
     * Démarre la 2ème chance : applique start_second, choisit un exercice faux au hasard, le fixe sur l'attempt.
     */
    public function startSecondChance(QuizAttempt $attempt): ?Exercice
    {
        $sm = $this->stateMachineFactory->get($attempt, self::GRAPH_NAME);
        if (!$sm->can('start_second')) {
            return null;
        }

        $wrongIds = $attempt->getWrongExerciseIds();
        if ($wrongIds === []) {
            return null;
        }

        $chosenId = $wrongIds[array_rand($wrongIds)];
        $exercise = $this->exerciceRepository->find($chosenId);
        if (!$exercise) {
            return null;
        }

        $sm->apply('start_second');
        $attempt->setSecondChanceExercise($exercise);
        $this->quizAttemptRepository->save($attempt, true);
        return $exercise;
    }

    /**
     * Soumet la réponse de la mini-session (un seul exercice), recalcule le score, applique finish_second, met à jour bestScore.
     */
    public function submitSecondChance(QuizAttempt $attempt, string $givenAnswer): void
    {
        $exercise = $attempt->getSecondChanceExercise();
        if (!$exercise) {
            return;
        }

        $normalizedUser = Exercice::normalizeOption($givenAnswer);
        $normalizedCorrect = Exercice::normalizeOption((string) $exercise->getCorrectAnswer());
        $isCorrect = $normalizedUser !== '' && $normalizedUser === $normalizedCorrect;

        $points = $isCorrect ? 1 : 0;

        $secondAttempt = new ExerciseAttempt();
        $secondAttempt->setQuizAttempt($attempt);
        $secondAttempt->setExercise($exercise);
        $secondAttempt->setIsCorrect($isCorrect);
        $secondAttempt->setGivenAnswer($givenAnswer);
        $secondAttempt->setPoints($points);
        $this->em->persist($secondAttempt);
        $attempt->addExerciseAttempt($secondAttempt);
        $this->em->flush();

        $scoreSecond = $this->computeBestScoreFromAttempts($attempt);
        $attempt->setScore($scoreSecond);
        $bestScore = max($attempt->getBestScore(), $scoreSecond);
        $attempt->setBestScore($bestScore);
        $attempt->setFinishedAt(new \DateTimeImmutable());

        $sm = $this->stateMachineFactory->get($attempt, self::GRAPH_NAME);
        if ($sm->can('finish_second')) {
            $sm->apply('finish_second');
        }

        $this->quizAttemptRepository->save($attempt, true);
    }

    /**
     * Pour chaque exercice, prend le meilleur résultat (au moins une bonne réponse = réussi). Score 0-100.
     */
    private function computeBestScoreFromAttempts(QuizAttempt $attempt): int
    {
        $byExercise = [];
        foreach ($attempt->getExerciseAttempts() as $ea) {
            $ex = $ea->getExercise();
            if (!$ex) {
                continue;
            }
            $id = $ex->getId();
            if (!isset($byExercise[$id])) {
                $byExercise[$id] = false;
            }
            if ($ea->getIsCorrect()) {
                $byExercise[$id] = true;
            }
        }
        $total = count($byExercise);
        if ($total === 0) {
            return 0;
        }
        $correct = count(array_filter($byExercise));
        return (int) round(($correct / $total) * 100);
    }

    /**
     * Recalcule le bestScore d'un attempt à partir de ses ExerciseAttempts (pour cohérence).
     */
    public function recomputeBestScore(QuizAttempt $attempt): int
    {
        $score = $this->computeBestScoreFromAttempts($attempt);
        $best = max($score, $attempt->getBestScore());
        $attempt->setScore($score);
        $attempt->setBestScore($best);
        $this->quizAttemptRepository->save($attempt, true);
        return $best;
    }
}
