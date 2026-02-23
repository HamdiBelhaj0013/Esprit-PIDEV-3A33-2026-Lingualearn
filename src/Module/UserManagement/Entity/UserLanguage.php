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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userLanguages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * Now points directly to PlatformLanguage (the admin-managed language with courses).
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

    public function isIsNative(): bool
    {
        return $this->isNative;
    }

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

    public function setAddedAt(\DateTimeInterface $addedAt): self
    {
        $this->addedAt = $addedAt;
        return $this;
    }
}
