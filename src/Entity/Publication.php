<?php

namespace App\Entity;

use App\Repository\PublicationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Module\UserManagement\Entity\User; // ✅ Import the correct User entity

#[ORM\Entity(repositoryClass: PublicationRepository::class)]
class Publication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titrePub = null;

    #[ORM\Column(length: 255)]
    private ?string $typePub = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lienPub = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $contenuPub = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $datePub = null;

    #[ORM\Column]
    private ?int $likes = 0;

    #[ORM\Column]
    private ?int $dislikes = 0;

    #[ORM\Column(nullable: true)]
    private ?int $reportPub = null;

    #[ORM\Column(nullable: true)]
    private ?int $floue = null;

    // ------------------ Relation avec User ------------------
    #[ORM\ManyToOne(inversedBy: 'publications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null; // ✅ Changed from Utilisateur $utilisateur

    // ------------------ Relation avec Commentaire ------------------
    #[ORM\OneToMany(mappedBy: 'publication', targetEntity: Commentaire::class, cascade: ['persist', 'remove'])]
    private Collection $commentaires;

    public function __construct()
    {
        $this->commentaires = new ArrayCollection();
    }

    public function getCommentaires(): Collection
    {
        return $this->commentaires;
    }

    public function addCommentaire(Commentaire $commentaire): static
    {
        if (!$this->commentaires->contains($commentaire)) {
            $this->commentaires[] = $commentaire;
            $commentaire->setPublication($this);
        }
        return $this;
    }

    public function removeCommentaire(Commentaire $commentaire): static
    {
        if ($this->commentaires->removeElement($commentaire)) {
            if ($commentaire->getPublication() === $this) {
                $commentaire->setPublication(null);
            }
        }
        return $this;
    }

    // ------------------ Getters & Setters ------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitrePub(): ?string
    {
        return $this->titrePub;
    }

    public function setTitrePub(string $titrePub): static
    {
        $this->titrePub = $titrePub;
        return $this;
    }

    public function getTypePub(): ?string
    {
        return $this->typePub;
    }

    public function setTypePub(string $typePub): static
    {
        $this->typePub = $typePub;
        return $this;
    }

    public function getLienPub(): ?string
    {
        return $this->lienPub;
    }

    public function setLienPub(?string $lienPub): static
    {
        $this->lienPub = $lienPub;
        return $this;
    }

    public function getContenuPub(): ?string
    {
        return $this->contenuPub;
    }

    public function setContenuPub(string $contenuPub): static
    {
        $this->contenuPub = $contenuPub;
        return $this;
    }

    public function getDatePub(): ?\DateTimeInterface
    {
        return $this->datePub;
    }

    public function setDatePub(\DateTimeInterface $datePub): static
    {
        $this->datePub = $datePub;
        return $this;
    }

    public function getLikes(): int
    {
        return $this->likes ?? 0;
    }

    public function setLikes(?int $likes): static
    {
        $this->likes = $likes;
        return $this;
    }

    public function getDislikes(): int
    {
        return $this->dislikes ?? 0;
    }

    public function setDislikes(?int $dislikes): static
    {
        $this->dislikes = $dislikes;
        return $this;
    }

    public function getReportPub(): ?int
    {
        return $this->reportPub;
    }

    public function setReportPub(?int $reportPub): static
    {
        $this->reportPub = $reportPub;
        return $this;
    }

    public function getFloue(): ?int
    {
        return $this->floue;
    }

    public function setFloue(?int $floue): static
    {
        $this->floue = $floue;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }
}
