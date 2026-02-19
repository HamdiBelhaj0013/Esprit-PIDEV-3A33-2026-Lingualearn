<?php

namespace App\Module\PedagogicalContent\Entity;

use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Relation avec User
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'courses')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Assert\NotNull(message: 'L\'auteur est obligatoire.')]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: PlatformLanguage::class, inversedBy: 'courses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La langue est obligatoire.')]
    private ?PlatformLanguage $platformLanguage = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $title = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le niveau est obligatoire.')]
    #[Assert\Choice(
        choices: ['beginner', 'intermediate', 'advanced'],
        message: 'Le niveau sélectionné est invalide.'
    )]
    private ?string $level = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['draft', 'published', 'archived'],
        message: 'Le statut sélectionné est invalide.'
    )]
    private ?string $status = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $publishedAt = null;

    #[ORM\OneToMany(mappedBy: 'course', targetEntity: Lesson::class, cascade: ['persist', 'remove'])]
    private Collection $lessons;

    public function __construct()
    {
        $this->lessons = new ArrayCollection();
        $this->publishedAt = new \DateTime();
    }

    // ------------------------
    // Getters & Setters
    // ------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(?string $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getPublishedAt(): ?\DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeInterface $publishedAt): self
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    public function getLessons(): Collection
    {
        return $this->lessons;
    }
}
