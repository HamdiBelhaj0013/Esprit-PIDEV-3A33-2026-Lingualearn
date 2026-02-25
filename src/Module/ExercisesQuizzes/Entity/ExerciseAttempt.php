<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'exercise_attempt')]
#[ORM\Index(columns: ['quiz_attempt_id'], name: 'idx_exercise_attempt_quiz_attempt')]
class ExerciseAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: QuizAttempt::class, inversedBy: 'exerciseAttempts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?QuizAttempt $quizAttempt = null;

    #[ORM\ManyToOne(targetEntity: Exercice::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Exercice $exercise = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isCorrect = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $givenAnswer = null;

    #[ORM\Column(type: 'integer')]
    private int $points = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    /** Temps passé sur cet exercice en secondes (optionnel, pour pénalité dans le calcul de mastery). */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $timeSpentSeconds = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuizAttempt(): ?QuizAttempt
    {
        return $this->quizAttempt;
    }

    public function setQuizAttempt(?QuizAttempt $quizAttempt): self
    {
        $this->quizAttempt = $quizAttempt;
        return $this;
    }

    public function getExercise(): ?Exercice
    {
        return $this->exercise;
    }

    public function setExercise(?Exercice $exercise): self
    {
        $this->exercise = $exercise;
        return $this;
    }

    public function getIsCorrect(): bool
    {
        return $this->isCorrect;
    }

    public function setIsCorrect(bool $isCorrect): self
    {
        $this->isCorrect = $isCorrect;
        return $this;
    }

    public function getGivenAnswer(): ?string
    {
        return $this->givenAnswer;
    }

    public function setGivenAnswer(?string $givenAnswer): self
    {
        $this->givenAnswer = $givenAnswer;
        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): self
    {
        $this->points = $points;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTimeSpentSeconds(): ?int
    {
        return $this->timeSpentSeconds;
    }

    public function setTimeSpentSeconds(?int $timeSpentSeconds): self
    {
        $this->timeSpentSeconds = $timeSpentSeconds;
        return $this;
    }
}
