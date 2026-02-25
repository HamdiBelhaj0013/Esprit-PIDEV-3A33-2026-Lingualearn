<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Entity;

use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cache du feedback IA (Hugging Face) par tentative + exercice.
 * Évite de rappeler l'API pour la même erreur.
 */
#[ORM\Entity]
#[ORM\Table(name: 'exercise_ai_feedback')]
#[ORM\UniqueConstraint(name: 'uq_exercise_ai_feedback_user_attempt_exercise_prompt', columns: ['user_id', 'quiz_attempt_id', 'exercise_id', 'prompt_hash'])]
#[ORM\Index(columns: ['quiz_attempt_id'], name: 'idx_exercise_ai_feedback_attempt')]
#[ORM\Index(columns: ['user_id'], name: 'idx_exercise_ai_feedback_user')]
class ExerciseAiFeedback
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: QuizAttempt::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?QuizAttempt $quizAttempt = null;

    #[ORM\ManyToOne(targetEntity: Exercice::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Exercice $exercise = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isCorrect = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $studentAnswer = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $correctAnswer = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $aiExplanation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $aiCorrection = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $aiTip = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $aiExample = null;

    #[ORM\Column(length: 50)]
    private string $provider = 'huggingface';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(length: 64)]
    private ?string $promptHash = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
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

    public function getStudentAnswer(): ?string
    {
        return $this->studentAnswer;
    }

    public function setStudentAnswer(?string $studentAnswer): self
    {
        $this->studentAnswer = $studentAnswer;
        return $this;
    }

    public function getCorrectAnswer(): ?string
    {
        return $this->correctAnswer;
    }

    public function setCorrectAnswer(?string $correctAnswer): self
    {
        $this->correctAnswer = $correctAnswer;
        return $this;
    }

    public function getAiExplanation(): ?string
    {
        return $this->aiExplanation;
    }

    public function setAiExplanation(?string $aiExplanation): self
    {
        $this->aiExplanation = $aiExplanation;
        return $this;
    }

    public function getAiCorrection(): ?string
    {
        return $this->aiCorrection;
    }

    public function setAiCorrection(?string $aiCorrection): self
    {
        $this->aiCorrection = $aiCorrection;
        return $this;
    }

    public function getAiTip(): ?string
    {
        return $this->aiTip;
    }

    public function setAiTip(?string $aiTip): self
    {
        $this->aiTip = $aiTip;
        return $this;
    }

    public function getAiExample(): ?string
    {
        return $this->aiExample;
    }

    public function setAiExample(?string $aiExample): self
    {
        $this->aiExample = $aiExample;
        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function getPromptHash(): ?string
    {
        return $this->promptHash;
    }

    public function setPromptHash(?string $promptHash): self
    {
        $this->promptHash = $promptHash;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
