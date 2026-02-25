<?php

namespace App\Module\InternationalTests\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: "App\Module\InternationalTests\Repository\TestQuestionRepository")]
#[ORM\Table(name: 'test_question')]
#[ORM\HasLifecycleCallbacks]
class TestQuestion
{
    // Types de questions (pour compatibilité avec l'ancien système)
    public const TYPE_QCM_SINGLE = 'qcm_single';      // QCM à choix unique
    public const TYPE_QCM_MULTIPLE = 'qcm_multiple';  // QCM à choix multiples
    public const TYPE_READING = 'reading';            // Question de compréhension écrite

    // Nouveaux types de questions
    public const TYPE_QCM = 'qcm';
    public const TYPE_WRITING = 'writing';
    public const TYPE_SPEAKING = 'speaking';
    public const TYPE_LISTENING = 'listening';

    public const QUESTION_TYPES = [
        self::TYPE_QCM,
        self::TYPE_WRITING,
        self::TYPE_SPEAKING,
        self::TYPE_LISTENING,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MockTest::class, inversedBy: 'testQuestions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?MockTest $mockTest = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $sectionCategory;

    // Type de question (qcm, writing, speaking, listening)
    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'qcm'])]
    private string $questionType = self::TYPE_QCM;

    #[ORM\Column(type: 'text')]
    private string $questionText;

    // Pour Reading: peut contenir le texte de lecture
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $readingPassage = null;

    // Pour Listening: texte qui sera lu à voix haute
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $audioText = null;

    // Pour Writing: sujet donné à l'utilisateur
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $writingSubject = null;

    #[ORM\Column(type: 'json')]
    private array $options = [];

    // Pour QCM single: une seule réponse (ex: "Un comparatif de supériorité")
    // Pour QCM multiple: plusieurs réponses séparées par | (ex: "option1|option2")
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $correctAnswer = '';

    #[ORM\Column(type: 'integer')]
    private int $points;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt      = new \DateTime();
        $this->isActive       = true;
        $this->questionType   = self::TYPE_QCM;
        $this->correctAnswer  = '';
        $this->sectionCategory = '';
        $this->points         = 1;
        $this->questionText   = '';
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMockTest(): ?MockTest
    {
        return $this->mockTest;
    }

    public function setMockTest(?MockTest $mockTest): self
    {
        $this->mockTest = $mockTest;
        return $this;
    }

    public function getSectionCategory(): string
    {
        return $this->sectionCategory;
    }

    public function setSectionCategory(string $sectionCategory): self
    {
        $this->sectionCategory = $sectionCategory;
        return $this;
    }

    public function getQuestionText(): string
    {
        return $this->questionText;
    }

    public function setQuestionText(string $questionText): self
    {
        $this->questionText = $questionText;
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

    public function getCorrectAnswer(): string
    {
        return $this->correctAnswer ?? '';
    }

    public function setCorrectAnswer(?string $correctAnswer): self
    {
        $this->correctAnswer = $correctAnswer ?? '';
        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): self
    {
        $this->points = $points;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
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

    public function getQuestionType(): string
    {
        return $this->questionType;
    }

    public function setQuestionType(string $questionType): self
    {
        // Accepter les nouveaux types ET les anciens (qcm_single, qcm_multiple, reading)
        $allValid = array_merge(self::QUESTION_TYPES, [
            self::TYPE_QCM_SINGLE,
            self::TYPE_QCM_MULTIPLE,
            self::TYPE_READING,
        ]);
        if (!in_array($questionType, $allValid)) {
            throw new \InvalidArgumentException("Invalid question type: $questionType");
        }
        $this->questionType = $questionType;
        return $this;
    }

    public function getReadingPassage(): ?string
    {
        return $this->readingPassage;
    }

    public function setReadingPassage(?string $readingPassage): self
    {
        $this->readingPassage = $readingPassage;
        return $this;
    }

    public function getAudioText(): ?string
    {
        return $this->audioText;
    }

    public function setAudioText(?string $audioText): self
    {
        $this->audioText = $audioText;
        return $this;
    }

    public function getWritingSubject(): ?string
    {
        return $this->writingSubject;
    }

    public function setWritingSubject(?string $writingSubject): self
    {
        $this->writingSubject = $writingSubject;
        return $this;
    }

    /**
     * Vérifie si la réponse de l'utilisateur est correcte
     * Gère les QCM simples, multiples et reading
     */
    public function isAnswerCorrect(?string $userAnswer): bool
    {
        if ($userAnswer === null) {
            return false;
        }

        $userAnswer = trim($userAnswer);
        $correctAnswer = trim($this->correctAnswer);

        // Pour QCM multiple, les réponses sont séparées par |
        if ($this->questionType === self::TYPE_QCM_MULTIPLE) {
            $correctAnswers = array_map('trim', explode('|', $correctAnswer));
            $userAnswers = array_map('trim', explode('|', $userAnswer));

            sort($correctAnswers);
            sort($userAnswers);

            return $correctAnswers === $userAnswers;
        }

        // Pour QCM simple et Reading: comparaison exacte
        return $userAnswer === $correctAnswer;
    }
}