<?php

namespace App\Module\ExercisesQuizzes\Entity;

use App\Module\PedagogicalContent\Entity\Lesson;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Quiz
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Leçon à laquelle ce quiz est rattaché (relation côté module ExercisesQuizzes uniquement). */
    #[ORM\ManyToOne(targetEntity: Lesson::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Lesson $lesson = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre ne peut pas être vide.")]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: "Le titre doit faire au moins {{ limit }} caractères.",
        maxMessage: "Le titre ne peut pas dépasser {{ limit }} caractères."
    )]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Le score de réussite est obligatoire.")]
    #[Assert\PositiveOrZero(message: "Le score doit être positif ou zéro.")]
    private ?int $passingScore = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Le nombre de questions est obligatoire.")]
    #[Assert\Positive(message: "Le nombre de questions doit être supérieur à zéro.")]
    private ?int $questionCount = null;

    /** Niveau de difficulté global du quiz (1-5), pour affichage et pré-remplissage des exercices. */
    #[ORM\Column(type: 'smallint', options: ['default' => 3])]
    private int $difficulty = 3;

    /** Codes de compétences couverts par ce quiz (ex: grammar, vocab), pour affichage. */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $skillCodes = [];

    #[ORM\OneToMany(mappedBy: 'quiz', targetEntity: Exercice::class, cascade: ['persist', 'remove'])]
    private Collection $exercices;

    public function __construct()
    {
        $this->exercices = new ArrayCollection();
    }

    /**
     * @return Collection<int, Exercice>
     */
    public function getExercices(): Collection
    {
        return $this->exercices;
    }

    public function addExercice(Exercice $exercice): self
    {
        if (!$this->exercices->contains($exercice)) {
            $this->exercices->add($exercice);
            $exercice->setQuiz($this);
        }

        return $this;
    }

    public function removeExercice(Exercice $exercice): self
    {
        if ($this->exercices->removeElement($exercice)) {
            // set the owning side to null (unless already changed)
            if ($exercice->getQuiz() === $this) {
                $exercice->setQuiz(null);
            }
        }

        return $this;
    }

    // Ajout des colonnes pour gérer la création et modification
   #[ORM\Column(type: 'datetime_immutable', nullable: true)]
private ?\DateTimeImmutable $createdAt = null;

#[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private bool $enabled = true;

    // -----------------------------
    // Getters et Setters
    // -----------------------------

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPassingScore(): ?int
    {
        return $this->passingScore;
    }

    public function setPassingScore(int $passingScore): self
    {
        $this->passingScore = $passingScore;
        return $this;
    }

    public function getQuestionCount(): ?int
    {
        return $this->questionCount;
    }

    public function setQuestionCount(int $questionCount): self
    {
        $this->questionCount = $questionCount;
        return $this;
    }

    public function getDifficulty(): int
    {
        return $this->difficulty;
    }

    public function setDifficulty(int $difficulty): self
    {
        $this->difficulty = max(1, min(5, $difficulty));
        return $this;
    }

    /**
     * @return string[]
     */
    public function getSkillCodes(): array
    {
        return $this->skillCodes;
    }

    /**
     * @param string[] $skillCodes
     */
    public function setSkillCodes(array $skillCodes): self
    {
        $skillCodes = array_map('strval', $skillCodes);
        $this->skillCodes = array_values(array_unique(array_filter($skillCodes, fn ($v) => trim($v) !== '')));
        return $this;
    }

    public function getLesson(): ?Lesson
    {
        return $this->lesson;
    }

    public function setLesson(?Lesson $lesson): self
    {
        $this->lesson = $lesson;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
{
    return $this->createdAt;
}



    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

   public function getUpdatedAt(): ?\DateTimeImmutable
{
    return $this->updatedAt;
}
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
