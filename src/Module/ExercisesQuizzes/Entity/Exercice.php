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
#[ORM\Column(type: 'smallint', options: ['default' => 3])]
private int $difficulty = 3;
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

    /**
     * Codes de compétences liées à cet exercice (ex: grammar, vocab, listening...)
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $skillCodes = [];

    #[ORM\Column]
    private bool $aiGenerated = false;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\ManyToOne(targetEntity: Quiz::class, inversedBy: 'exercices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Quiz $quiz = null;

    // --------------------------
    // Validation
    // --------------------------

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, $payload): void
    {
        if ($this->type === 'multiple_choice' && empty($this->options)) {
            $context->buildViolation('Les options sont obligatoires pour un exercice à choix multiples.')
                ->atPath('options')
                ->addViolation();
        }

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
public function getDifficulty(): int
{
    return $this->difficulty;
}

public function setDifficulty(int $difficulty): self
{
    $this->difficulty = max(1, min(5, $difficulty));
    return $this;
}
    public static function normalizeOption(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $value = preg_replace('/[\p{C}\p{Z}]/u', ' ', $value);
        return trim($value);
    }

    // --------------------------
    // Getters / Setters
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
        // nettoyage + suppression des doublons
        $skillCodes = array_map('strval', $skillCodes);
        $skillCodes = array_values(array_unique(array_filter($skillCodes, fn($v) => trim($v) !== '')));

        $this->skillCodes = $skillCodes;
        return $this;
    }

    public function addSkillCode(string $skillCode): self
    {
        $skillCode = trim($skillCode);
        if ($skillCode !== '' && !in_array($skillCode, $this->skillCodes, true)) {
            $this->skillCodes[] = $skillCode;
        }
        return $this;
    }

    public function removeSkillCode(string $skillCode): self
    {
        $this->skillCodes = array_values(array_filter(
            $this->skillCodes,
            fn($s) => $s !== $skillCode
        ));
        return $this;
    }
}