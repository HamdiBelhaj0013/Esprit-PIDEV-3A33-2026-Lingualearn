<?php

namespace App\Module\PedagogicalContent\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Lesson
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'lessons')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le cours associé est obligatoire.')]
    private ?Course $course = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Le contenu est obligatoire.')]
    #[Assert\Length(
        min: 10,
        minMessage: 'Le contenu doit contenir au moins {{ limit }} caractères.'
    )]
    private ?string $content = null;

    #[ORM\Column(type: 'json')]
    private array $vocabularyData = [];

    #[ORM\Column(type: 'json')]
    private array $grammarData = [];

    #[ORM\Column]
    #[Assert\NotNull(message: 'La récompense XP est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'La récompense XP doit être positive ou zéro.')]
    private ?int $xpReward = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): self
    {
        $this->course = $course;
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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getVocabularyData(): array
    {
        return $this->vocabularyData;
    }

    public function setVocabularyData(array $vocabularyData): self
    {
        $this->vocabularyData = $vocabularyData;
        return $this;
    }

    public function getGrammarData(): array
    {
        return $this->grammarData;
    }

    public function setGrammarData(array $grammarData): self
    {
        $this->grammarData = $grammarData;
        return $this;
    }

    public function getXpReward(): ?int
    {
        return $this->xpReward;
    }

    public function setXpReward(?int $xpReward): self
    {
        $this->xpReward = $xpReward;
        return $this;
    }
}
