<?php

namespace App\Module\Support\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Module\Support\Repository\FAQRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FAQRepository::class)]
#[ORM\Table(name: 'faq')]
class FAQ
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\Type(type: 'string', message: 'La question doit être une chaîne de caractères.')]
    #[Assert\NotBlank(message: 'La question ne peut pas être vide.')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'La question doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'La question ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $question = null;

    #[ORM\Column(type: 'text')]
    #[Assert\Type(type: 'string', message: 'La réponse doit être une chaîne de caractères.')]
    #[Assert\NotBlank(message: 'La réponse ne peut pas être vide.')]
    #[Assert\Length(
        min: 10,
        minMessage: 'La réponse doit contenir au moins {{ limit }} caractères.'
    )]
    private ?string $answer = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['payment','technical','content','premium','account','other'], message: 'Sujet invalide.')]
    private ?string $subject = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['general','technical','pedagogical','billing'], message: 'Catégorie invalide.')]
    private ?string $category = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $submittedAt = null;

    public function __construct()
    {
        $this->submittedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion(): ?string
    {
        return $this->question;
    }

    public function setQuestion(string $question): self
    {
        $this->question = $question;
        return $this;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function setAnswer(string $answer): self
    {
        $this->answer = $answer;
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

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function __toString(): string
    {
        return $this->question ?? 'FAQ #'.$this->id;
    }
}