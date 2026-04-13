<?php

namespace App\Module\Support\Entity;

use App\Module\Support\Repository\ReclamationRepository;
use App\Module\UserManagement\Entity\User;
use DH\Auditor\Provider\Doctrine\Auditing\Annotation as Audit;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReclamationRepository::class)]
#[ORM\Table(name: 'reclamation')]
#[ORM\HasLifecycleCallbacks]
#[Audit\Auditable]
class Reclamation
{
    const PRIORITY_LOW    = 'LOW';
    const PRIORITY_MEDIUM = 'MEDIUM';
    const PRIORITY_HIGH   = 'HIGH';
    const PRIORITY_URGENT = 'URGENT';

    const SLA_HOURS = [
        'LOW'    => 72,
        'MEDIUM' => 24,
        'HIGH'   => 8,
        'URGENT' => 2,
    ];

    const URGENT_KEYWORDS = ['urgent', 'bloqué', 'impossible', 'critique', 'erreur grave', 'paiement échoué'];
    const HIGH_KEYWORDS   = ['problème', 'bug', 'ne fonctionne pas', 'accès refusé', 'premium'];
    const LOW_KEYWORDS    = ['suggestion', 'amélioration', 'question', 'information'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 100)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $messageBody = null;

    #[ORM\Column(length: 20)]
    private string $status = 'PENDING';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $submittedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reclamations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    /**
     * FIX: orphanRemoval=true added — SupportResponse is owned by Reclamation.
     * Removing a response from the collection now deletes it from the DB.
     */
    #[ORM\OneToMany(targetEntity: SupportResponse::class, mappedBy: 'reclamation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    /** @var Collection<int, SupportResponse> */
    private Collection $responses;

    #[ORM\Column(length: 10)]
    private string $priority = 'MEDIUM';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $slaDeadline = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isLate = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $resolvedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $satisfactionScore = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $satisfactionComment = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $satisfactionRatedAt = null;

    public function __construct()
    {
        $this->responses   = new ArrayCollection();
        $this->submittedAt = new \DateTime();
        $this->status      = 'PENDING';
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->submittedAt === null) {
            $this->submittedAt = new \DateTime();
        }
        if ($this->priority === 'MEDIUM' && $this->messageBody) {
            $this->priority = $this->detectPriority();
        }
        $this->calculateSlaDeadline();
    }

    public function detectPriority(): string
    {
        $text = strtolower($this->subject . ' ' . $this->messageBody);
        foreach (self::URGENT_KEYWORDS as $kw) { if (str_contains($text, $kw)) return self::PRIORITY_URGENT; }
        foreach (self::HIGH_KEYWORDS as $kw)   { if (str_contains($text, $kw)) return self::PRIORITY_HIGH; }
        foreach (self::LOW_KEYWORDS as $kw)    { if (str_contains($text, $kw)) return self::PRIORITY_LOW; }
        return self::PRIORITY_MEDIUM;
    }

    public function calculateSlaDeadline(): void
    {
        $hours = self::SLA_HOURS[$this->priority] ?? 24;
        $this->slaDeadline = (new \DateTime())->modify("+{$hours} hours");
    }

    public function checkIfLate(): void
    {
        if ($this->slaDeadline && in_array($this->status, ['PENDING', 'IN_PROGRESS'])) {
            $this->isLate = new \DateTime() > $this->slaDeadline;
        }
    }

    public function getRemainingHours(): int
    {
        if (!$this->slaDeadline || $this->isLate) return 0;
        $diff = (new \DateTime())->diff($this->slaDeadline);
        return ($diff->days * 24) + $diff->h;
    }

    public function getSlaLabel(): string
    {
        return match($this->priority) {
            'URGENT' => '2h', 'HIGH' => '8h', 'MEDIUM' => '24h', 'LOW' => '72h', default => '24h',
        };
    }

    public function getPriorityColor(): string
    {
        return match($this->priority) {
            'URGENT' => 'red', 'HIGH' => 'orange', 'MEDIUM' => 'blue', 'LOW' => 'green', default => 'blue',
        };
    }

    public function canBeRated(): bool
    {
        return $this->status === 'RESOLVED' && $this->satisfactionScore === null;
    }

    public function hasBeenRated(): bool
    {
        return $this->satisfactionScore !== null;
    }

    // ── Getters / Setters ──
    public function getId(): ?int { return $this->id; }
    public function getSubject(): ?string { return $this->subject; }
    public function setSubject(string $s): static { $this->subject = $s; return $this; }
    public function getMessageBody(): ?string { return $this->messageBody; }
    public function setMessageBody(string $m): static { $this->messageBody = $m; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static
    {
        if ($status === 'RESOLVED' && $this->status !== 'RESOLVED') {
            $this->resolvedAt = new \DateTime();
        }
        $this->status = $status;
        return $this;
    }
    public function getSubmittedAt(): ?\DateTimeInterface { return $this->submittedAt; }
    public function setSubmittedAt(\DateTimeInterface $d): static { $this->submittedAt = $d; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $u): static { $this->user = $u; return $this; }
    /** @return Collection<int, SupportResponse> */
    public function getResponses(): Collection { return $this->responses; }
    public function addResponse(SupportResponse $r): static
    {
        if (!$this->responses->contains($r)) { $this->responses->add($r); $r->setReclamation($this); }
        return $this;
    }
    public function removeResponse(SupportResponse $r): static
    {
        if ($this->responses->removeElement($r) && $r->getReclamation() === $this) { $r->setReclamation(null); }
        return $this;
    }
    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $p): static { $this->priority = $p; $this->calculateSlaDeadline(); return $this; }
    public function getSlaDeadline(): ?\DateTimeInterface { return $this->slaDeadline; }
    public function setSlaDeadline(?\DateTimeInterface $d): static { $this->slaDeadline = $d; return $this; }
    public function isLate(): bool { return $this->isLate; }
    public function setIsLate(bool $l): static { $this->isLate = $l; return $this; }
    public function getResolvedAt(): ?\DateTimeInterface { return $this->resolvedAt; }
    public function setResolvedAt(?\DateTimeInterface $d): static { $this->resolvedAt = $d; return $this; }
    public function getSatisfactionScore(): ?int { return $this->satisfactionScore; }
    public function setSatisfactionScore(?int $s): static { $this->satisfactionScore = $s; return $this; }
    public function getSatisfactionComment(): ?string { return $this->satisfactionComment; }
    public function setSatisfactionComment(?string $c): static { $this->satisfactionComment = $c; return $this; }
    public function getSatisfactionRatedAt(): ?\DateTimeInterface { return $this->satisfactionRatedAt; }
    public function setSatisfactionRatedAt(?\DateTimeInterface $d): static { $this->satisfactionRatedAt = $d; return $this; }
}
