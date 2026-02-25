<?php

namespace App\Module\InternationalTests\Entity;

use App\Module\PedagogicalContent\Entity\PlatformLanguage;
use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: "App\Module\InternationalTests\Repository\CertificateRepository")]
#[ORM\Table(name: 'certificate')]
#[ORM\HasLifecycleCallbacks]
class Certificate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: PlatformLanguage::class)]
    #[ORM\JoinColumn(name: 'platform_language_id', referencedColumnName: 'id', nullable: false)]
    private ?PlatformLanguage $language = null;

    #[ORM\Column(type: 'float')]
    private float $avgScore;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $uniqueCode;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $issuedAt = null;

    public function __construct()
    {
        $this->uniqueCode = Uuid::v4()->toRfc4122();
        $this->issuedAt = new \DateTime();
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

    public function getLanguage(): ?PlatformLanguage
    {
        return $this->language;
    }

    public function setLanguage(?PlatformLanguage $language): self
    {
        $this->language = $language;
        return $this;
    }

    public function getAvgScore(): float
    {
        return $this->avgScore;
    }

    public function setAvgScore(float $avgScore): self
    {
        $this->avgScore = $avgScore;
        return $this;
    }

    public function getUniqueCode(): string
    {
        return $this->uniqueCode;
    }

    public function setUniqueCode(string $uniqueCode): self
    {
        $this->uniqueCode = $uniqueCode;
        return $this;
    }

    public function getIssuedAt(): ?\DateTimeInterface
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(\DateTimeInterface $issuedAt): self
    {
        $this->issuedAt = $issuedAt;
        return $this;
    }
}

