<?php

namespace App\Module\Support\Entity;

use App\Module\Support\Repository\AuditLogRepository;
use App\Module\UserManagement\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'support_audit_logs')]
class AuditLog
{
    const ACTION_CREATED        = 'CREATED';
    const ACTION_UPDATED        = 'UPDATED';
    const ACTION_STATUS_CHANGED = 'STATUS_CHANGED';
    const ACTION_RESPONSE_ADDED = 'RESPONSE_ADDED';
    const ACTION_DELETED        = 'DELETED';
    const ACTION_BANNED         = 'USER_BANNED';
    const ACTION_SPAM_BLOCKED   = 'SPAM_BLOCKED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $action;

    #[ORM\Column(length: 255)]
    private string $description;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\ManyToOne(targetEntity: Reclamation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Reclamation $reclamation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $performedBy = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $oldValue = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $newValue = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }

    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getMetadata(): ?array { return $this->metadata; }
    public function setMetadata(?array $metadata): static { $this->metadata = $metadata; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function getReclamation(): ?Reclamation { return $this->reclamation; }
    public function setReclamation(?Reclamation $reclamation): static { $this->reclamation = $reclamation; return $this; }

    public function getPerformedBy(): ?User { return $this->performedBy; }
    public function setPerformedBy(?User $performedBy): static { $this->performedBy = $performedBy; return $this; }

    public function getOldValue(): ?string { return $this->oldValue; }
    public function setOldValue(?string $oldValue): static { $this->oldValue = $oldValue; return $this; }

    public function getNewValue(): ?string { return $this->newValue; }
    public function setNewValue(?string $newValue): static { $this->newValue = $newValue; return $this; }
}