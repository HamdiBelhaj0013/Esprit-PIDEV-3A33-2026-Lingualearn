<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Module\ExercisesQuizzes\Entity\QuizAttempt;
use App\Module\ExercisesQuizzes\Repository\ExerciceRepository;
use App\Module\ExercisesQuizzes\Repository\QuizAttemptRepository;
use App\Module\ExercisesQuizzes\Service\SecondChanceService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use SM\Factory\FactoryInterface as StateMachineFactoryInterface;
use SM\StateMachine\StateMachineInterface;

final class SecondChanceServiceTest extends TestCase
{
    public function testStartSecondChanceReturnsNullIfNoWrongIds(): void
    {
        $quizAttemptRepository = $this->createMock(QuizAttemptRepository::class);
        $exerciceRepository = $this->createMock(ExerciceRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $smFactory = $this->createMock(StateMachineFactoryInterface::class);

        $attempt = $this->createMock(QuizAttempt::class);
        $attempt->method('getWrongExerciseIds')->willReturn([]);

        $sm = $this->createMock(StateMachineInterface::class);
        $sm->method('can')->with('start_second')->willReturn(true);

        $smFactory->method('get')->willReturn($sm);

        $service = new SecondChanceService($quizAttemptRepository, $exerciceRepository, $em, $smFactory);

        self::assertNull($service->startSecondChance($attempt));
    }
}

