<?php

namespace App\Module\ExercisesQuizzes\Entity;

use App\Module\ExercisesQuizzes\Repository\ExerciceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ExerciceRepository::class)]
class Exercice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "Le type d'exercice ne peut pas être vide.")]
    private ?string $type = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: "La question ne peut pas être vide.")]
    #[Assert\Length(
        min: 5,
        minMessage: "La question doit faire au moins {{ limit }} caractères."
    )]
    private ?string $question = null;

    #[ORM\Column(type: 'json')]
    private array $options = [];

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "La réponse correcte ne peut pas être vide.")]
    private ?string $correctAnswer = null;

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, $payload): void
    {
        if ($this->type === 'multiple_choice' && empty($this->options)) {
            $context->buildViolation('Les options sont obligatoires pour un exercice à choix multiples.')
                ->atPath('options')
                ->addViolation();
        }
        // Quand des options sont fournies, la réponse correcte doit correspondre à l'une d'elles
        if ($this->correctAnswer !== null && $this->correctAnswer !== '' && !empty($this->options)) {
            $normalizedAnswer = self::normalizeOption($this->correctAnswer);
            $normalizedOptions = array_map([self::class, 'normalizeOption'], $this->options);
            if (!in_array($normalizedAnswer, $normalizedOptions, true)) {
                $context->buildViolation('La réponse correcte doit correspondre à l\'une des options fournies.')
                    ->atPath('correctAnswer')
                    ->addViolation();
            }
        }
    }

    /**
     * Normalise une chaîne pour comparaison (trim, suppression caractères de contrôle/format).
     */
    public static function normalizeOption(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $value = preg_replace('/[\p{C}\p{Z}]/u', ' ', $value);
        return trim($value);
    }

    #[ORM\Column]
    private bool $aiGenerated = false;

    #[ORM\Column]
    private bool $enabled = true; // <-- Nouveau champ pour activer/désactiver l'exercice

    #[ORM\ManyToOne(targetEntity: Quiz::class, inversedBy: 'exercices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Quiz $quiz = null;

    // --------------------------
    // Getters et setters
    // --------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuiz(): ?Quiz
    {
        return $this->quiz;
    }

    public function setQuiz(?Quiz $quiz): self
    {
        $this->quiz = $quiz;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
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

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function getCorrectAnswer(): ?string
    {
        return $this->correctAnswer;
    }

    public function setCorrectAnswer(string $correctAnswer): self
    {
        $this->correctAnswer = $correctAnswer;
        return $this;
    }

    public function isAiGenerated(): bool
    {
        return $this->aiGenerated;
    }

    public function setAiGenerated(bool $aiGenerated): self
    {
        $this->aiGenerated = $aiGenerated;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }
}
