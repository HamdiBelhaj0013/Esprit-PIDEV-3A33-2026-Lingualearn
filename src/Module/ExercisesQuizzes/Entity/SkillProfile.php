<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Entity;

use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Profil de maîtrise par compétence pour un utilisateur.
 * mastery 0..100, mis à jour après chaque analyse (quiz ou bouton "Analyser mes compétences").
 */
#[ORM\Entity]
#[ORM\Table(name: 'skill_profile')]
#[ORM\UniqueConstraint(name: 'uq_skill_profile_user_skill', columns: ['user_id', 'skill_code'])]
#[ORM\Index(columns: ['user_id'], name: 'idx_skill_profile_user')]
class SkillProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 100)]
    private ?string $skillCode = null;

    /** Maîtrise 0-100. */
    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $mastery = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $attemptsCount = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function getSkillCode(): ?string
    {
        return $this->skillCode;
    }

    public function setSkillCode(string $skillCode): self
    {
        $this->skillCode = $skillCode;
        return $this;
    }

    public function getMastery(): int
    {
        return $this->mastery;
    }

    public function setMastery(int $mastery): self
    {
        $this->mastery = max(0, min(100, $mastery));
        return $this;
    }

    public function getAttemptsCount(): int
    {
        return $this->attemptsCount;
    }

    public function setAttemptsCount(int $attemptsCount): self
    {
        $this->attemptsCount = $attemptsCount;
        return $this;
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
