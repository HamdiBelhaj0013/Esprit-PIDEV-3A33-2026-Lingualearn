<?php

namespace App\Module\UserManagement\Entity;

use App\Module\PedagogicalContent\Entity\PlatformLanguage;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class UserLanguage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * FIX: onDelete='CASCADE' added — when a User is deleted via raw SQL,
     * the DB removes orphaned UserLanguage rows without ORM involvement.
     * This aligns the DB constraint with the orphanRemoval=true on User side.
     */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userLanguages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * Points directly to PlatformLanguage (the admin-managed language with courses).
     * No more intermediate Language entity needed in the enrollment flow.
     */
    #[ORM\ManyToOne(targetEntity: PlatformLanguage::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlatformLanguage $platformLanguage = null;

    #[ORM\Column(length: 20)]
    private string $proficiencyLevel = 'A1';

    #[ORM\Column]
    private bool $isNative = false;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $addedAt;

    public function __construct()
    {
        $this->addedAt = new \DateTime();
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

    public function getPlatformLanguage(): ?PlatformLanguage
    {
        return $this->platformLanguage;
    }

    public function setPlatformLanguage(?PlatformLanguage $platformLanguage): self
    {
        $this->platformLanguage = $platformLanguage;
        return $this;
    }

    /**
     * Alias so Twig templates using ul.language.name still work during migration.
     * You can remove this once all templates are updated to use ul.platformLanguage.
     */
    public function getLanguage(): ?PlatformLanguage
    {
        return $this->platformLanguage;
    }

    public function getProficiencyLevel(): string
    {
        return $this->proficiencyLevel;
    }

    public function setProficiencyLevel(string $proficiencyLevel): self
    {
        $this->proficiencyLevel = $proficiencyLevel;
        return $this;
    }

    public function isNative(): bool
    {
        return $this->isNative;
    }

    // isIsNative() removed — duplicate of isNative(). Use isNative() directly.
    public function getIsNative(): bool
    {
        return $this->isNative;
    }

    public function setIsNative(bool $isNative): self
    {
        $this->isNative = $isNative;
        return $this;
    }

    public function getAddedAt(): \DateTimeInterface
    {
        return $this->addedAt;
    }
    // FIX: No public setAddedAt — addedAt is set once in the constructor
    // and must never be changed. Removing the public setter prevents
    // accidental manipulation of the enrollment timestamp.
}
