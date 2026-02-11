<?php

namespace App\Module\Support\Entity;

use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Module\Support\Repository\ReclamationRepository;

#[ORM\Entity(repositoryClass: ReclamationRepository::class)]
#[ORM\Table(name: 'reclamation')]
class Reclamation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reclamations')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $subject = null;

    #[ORM\Column(type: 'text')]
    private ?string $messageBody = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $status = 'PENDING';

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $submittedAt = null;

    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: SupportResponse::class, mappedBy: 'reclamation', cascade: ['persist', 'remove'])]
    private Collection $responses;

    public function __construct()
    {
        $this->responses = new ArrayCollection();
        $this->submittedAt = new \DateTime();
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

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getMessageBody(): ?string
    {
        return $this->messageBody;
    }

    public function setMessageBody(string $messageBody): self
    {
        $this->messageBody = $messageBody;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeInterface
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(\DateTimeInterface $submittedAt): self
    {
        $this->submittedAt = $submittedAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, SupportResponse>
     */
    public function getResponses(): Collection
    {
        return $this->responses;
    }

    public function addResponse(SupportResponse $response): self
    {
        if (!$this->responses->contains($response)) {
            $this->responses->add($response);
            $response->setReclamation($this);
        }
        return $this;
    }

    public function removeResponse(SupportResponse $response): self
    {
        if ($this->responses->removeElement($response)) {
            if ($response->getReclamation() === $this) {
                $response->setReclamation(null);
            }
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->subject ?? 'Reclamation #'.$this->id;
    }
}