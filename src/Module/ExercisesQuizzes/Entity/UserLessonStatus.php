<?php

namespace App\Module\ExercisesQuizzes\Entity;

use App\Module\ExercisesQuizzes\Repository\UserLessonStatusRepository;
use App\Module\UserManagement\Entity\User;
use App\Module\PedagogicalContent\Entity\Lesson;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserLessonStatusRepository::class)]
class UserLessonStatus
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "L'utilisateur est obligatoire.")]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Lesson::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "La leçon est obligatoire.")]
    private ?Lesson $lesson = null;

    #[ORM\Column]
    private bool $isCompleted = false;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: "Le score doit être positif ou zéro.")]
    private int $bestQuizScore = 0;

    /** Dernier score obtenu à ce quiz (mis à jour à chaque tentative). */
    #[ORM\Column]
    #[Assert\PositiveOrZero(message: "Le score doit être positif ou zéro.")]
    private int $lastQuizScore = 0;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    // -----------------------------
    // Getters et Setters
    // -----------------------------

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

    public function getLesson(): ?Lesson
    {
        return $this->lesson;
    }

    public function setLesson(?Lesson $lesson): self
    {
        $this->lesson = $lesson;
        return $this;
    }

    public function getIsCompleted(): bool
    {
        return $this->isCompleted;
    }

    public function setIsCompleted(bool $isCompleted): self
    {
        $this->isCompleted = $isCompleted;
        if ($isCompleted && $this->completedAt === null) {
            $this->completedAt = new \DateTime();
        }
        return $this;
    }

    public function getBestQuizScore(): int
    {
        return $this->bestQuizScore;
    }

    public function setBestQuizScore(int $bestQuizScore): self
    {
        $this->bestQuizScore = $bestQuizScore;
        return $this;
    }

    public function getLastQuizScore(): int
    {
        return $this->lastQuizScore;
    }

    public function setLastQuizScore(int $lastQuizScore): self
    {
        $this->lastQuizScore = $lastQuizScore;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }
}
