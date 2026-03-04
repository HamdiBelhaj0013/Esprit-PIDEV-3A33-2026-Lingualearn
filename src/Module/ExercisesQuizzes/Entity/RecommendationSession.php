<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Entity;

use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace d'une session de recommandations : compétences faibles + exercices recommandés + feedback IA optionnel.
 */
#[ORM\Entity]
#[ORM\Table(name: 'recommendation_session')]
#[ORM\Index(columns: ['user_id'], name: 'idx_recommendation_session_user')]
class RecommendationSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** Codes des compétences faibles (mastery < seuil), triés par priorité. */
    #[ORM\Column(type: 'json')]
    private array $weakSkillCodes = [];

    /** IDs des exercices recommandés (choix déterministe côté backend). */
    #[ORM\Column(type: 'json')]
    private array $recommendedExerciseIds = [];

    /** Feedback IA (Ollama), optionnel. Null si Ollama indisponible. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $aiFeedback = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
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

    /** @return string[] */
    public function getWeakSkillCodes(): array
    {
        return $this->weakSkillCodes;
    }

    /** @param string[] $weakSkillCodes */
    public function setWeakSkillCodes(array $weakSkillCodes): self
    {
        $this->weakSkillCodes = $weakSkillCodes;
        return $this;
    }

    /** @return int[] */
    public function getRecommendedExerciseIds(): array
    {
        return $this->recommendedExerciseIds;
    }

    /** @param int[] $recommendedExerciseIds */
    public function setRecommendedExerciseIds(array $recommendedExerciseIds): self
    {
        $this->recommendedExerciseIds = $recommendedExerciseIds;
        return $this;
    }

    public function getAiFeedback(): ?string
    {
        return $this->aiFeedback;
    }

    public function setAiFeedback(?string $aiFeedback): self
    {
        $this->aiFeedback = $aiFeedback;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
