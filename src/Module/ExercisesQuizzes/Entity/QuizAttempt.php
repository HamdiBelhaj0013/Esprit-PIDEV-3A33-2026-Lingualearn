<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Entity;

use App\Module\PedagogicalContent\Entity\Lesson;
use App\Module\UserManagement\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'quiz_attempt')]
#[ORM\Index(columns: ['user_id', 'quiz_id'], name: 'idx_quiz_attempt_user_quiz')]
class QuizAttempt
{
    public const STATE_IN_PROGRESS = 'in_progress';
    public const STATE_FINISHED_FIRST = 'finished_first';
    public const STATE_SECOND_CHANCE_AVAILABLE = 'second_chance_available';
    public const STATE_SECOND_CHANCE_IN_PROGRESS = 'second_chance_in_progress';
    public const STATE_FINISHED_SECOND = 'finished_second';
    public const STATE_LOCKED = 'locked';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Quiz::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Quiz $quiz = null;

    #[ORM\ManyToOne(targetEntity: Lesson::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Lesson $lesson = null;

    /** Score de la tentative (0-100). */
    #[ORM\Column(type: 'integer')]
    private int $score = 0;

    /** État du cycle de vie (state machine). */
    #[ORM\Column(length: 50)]
    private string $state = self::STATE_IN_PROGRESS;

    #[ORM\Column(type: 'smallint')]
    private int $attemptNumber = 1;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    /** Meilleur score connu (max des tentatives). */
    #[ORM\Column(type: 'integer')]
    private int $bestScore = 0;

    /** Exercice choisi pour la 2ème chance (aléatoire parmi les faux). */
    #[ORM\ManyToOne(targetEntity: Exercice::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Exercice $secondChanceExercise = null;

    /** @var Collection<int, ExerciseAttempt> */
    #[ORM\OneToMany(mappedBy: 'quizAttempt', targetEntity: ExerciseAttempt::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $exerciseAttempts;

    public function __construct()
    {
        $this->exerciseAttempts = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getQuiz(): ?Quiz
    {
        return $this->quiz;
    }

    public function setQuiz(?Quiz $quiz): self
    {
        $this->quiz = $quiz;
        return $this;
    }

    public function getLesson(): ?Lesson
    {
        return $this->lesson;
    }

    public function setLesson(?Lesson $lesson): self
    {
        $this->lesson = $lesson;
        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = $score;
        return $this;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;
        return $this;
    }

    public function getAttemptNumber(): int
    {
        return $this->attemptNumber;
    }

    public function setAttemptNumber(int $attemptNumber): self
    {
        $this->attemptNumber = $attemptNumber;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): self
    {
        $this->finishedAt = $finishedAt;
        return $this;
    }

    public function getBestScore(): int
    {
        return $this->bestScore;
    }

    public function setBestScore(int $bestScore): self
    {
        $this->bestScore = $bestScore;
        return $this;
    }

    public function getSecondChanceExercise(): ?Exercice
    {
        return $this->secondChanceExercise;
    }

    public function setSecondChanceExercise(?Exercice $exercise): self
    {
        $this->secondChanceExercise = $exercise;
        return $this;
    }

    /** @return Collection<int, ExerciseAttempt> */
    public function getExerciseAttempts(): Collection
    {
        return $this->exerciseAttempts;
    }

    public function addExerciseAttempt(ExerciseAttempt $ea): self
    {
        if (!$this->exerciseAttempts->contains($ea)) {
            $this->exerciseAttempts->add($ea);
            $ea->setQuizAttempt($this);
        }
        return $this;
    }

    /** Nombre d'exercices faux (réponses incorrectes) dans cette tentative. */
    public function countWrongExercises(): int
    {
        $n = 0;
        foreach ($this->exerciseAttempts as $ea) {
            if (!$ea->getIsCorrect()) {
                $n++;
            }
        }
        return $n;
    }

    /** IDs des exercices ayant reçu une réponse fausse. */
    public function getWrongExerciseIds(): array
    {
        $ids = [];
        foreach ($this->exerciseAttempts as $ea) {
            if (!$ea->getIsCorrect() && $ea->getExercise()) {
                $ids[] = $ea->getExercise()->getId();
            }
        }
        return $ids;
    }
}
