<?php

namespace App\Module\PedagogicalContent\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class PlatformLanguage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $name;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank(message: 'Le code est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 10,
        minMessage: 'Le code doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le code ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $code;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "L'URL du drapeau est obligatoire.")]
    #[Assert\Url(message: "L'URL du drapeau n'est pas valide.")]
    private string $flagUrl;

    #[ORM\Column]
    #[Assert\Type(type: 'bool', message: 'Le statut doit être un booléen.')]
    private bool $isEnabled = true;

    #[ORM\OneToMany(mappedBy: 'platformLanguage', targetEntity: Course::class)]
    private Collection $courses;

    public function __construct()
    {
        $this->courses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getFlagUrl(): string
    {
        return $this->flagUrl;
    }

    public function setFlagUrl(string $flagUrl): self
    {
        $this->flagUrl = $flagUrl;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;
        return $this;
    }

    public function getCourses(): Collection
    {
        return $this->courses;
    }
}
