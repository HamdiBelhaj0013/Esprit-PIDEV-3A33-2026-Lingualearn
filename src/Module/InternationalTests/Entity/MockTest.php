<?php

namespace App\Module\InternationalTests\Entity;

use App\Module\PedagogicalContent\Entity\PlatformLanguage;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: "App\Module\InternationalTests\Repository\MockTestRepository")]
#[ORM\Table(name: 'mock_test')]
#[ORM\HasLifecycleCallbacks]
class MockTest
{
    // Niveaux disponibles
    public const LEVEL_BEGINNER     = 'Beginner';
    public const LEVEL_INTERMEDIATE = 'Intermediate';
    public const LEVEL_ADVANCED     = 'Advanced';

    public const LEVELS = [
        self::LEVEL_BEGINNER,
        self::LEVEL_INTERMEDIATE,
        self::LEVEL_ADVANCED,
    ];

    // Types de tests disponibles
    public const TYPE_QCM       = 'QCM';
    public const TYPE_WRITING   = 'Writing';
    public const TYPE_SPEAKING  = 'Speaking';
    public const TYPE_LISTENING = 'Listening';

    public const TEST_TYPES = [
        self::TYPE_QCM,
        self::TYPE_WRITING,
        self::TYPE_SPEAKING,
        self::TYPE_LISTENING,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PlatformLanguage::class)]
    #[ORM\JoinColumn(name: 'platform_language_id', referencedColumnName: 'id', nullable: false)]
    private ?PlatformLanguage $platformLanguage = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'string', length: 50)]
    private string $testType;

    // Catégorie du test (QCM, Writing, Speaking, Listening)
    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'QCM'])]
    private string $testCategory = self::TYPE_QCM;

    // ─── NOUVEAU CHAMP ───
    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'Beginner'])]
    private string $level = self::LEVEL_BEGINNER;

    #[ORM\Column(type: 'integer')]
    private int $durationMinutes;

    /**
     * FIX: orphanRemoval=true added — TestQuestion is owned by MockTest
     * (composition). Removing from collection now deletes the DB row.
     * onDelete='CASCADE' aligns ORM cascade with the DB constraint so
     * direct SQL DELETEs on mock_test don't cause FK violations.
     */
    #[ORM\OneToMany(mappedBy: 'mockTest', targetEntity: TestQuestion::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $testQuestions;

    /**
     * FIX: same as testQuestions — TestResult is owned by MockTest.
     */
    #[ORM\OneToMany(mappedBy: 'mockTest', targetEntity: TestResult::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $testResults;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->testQuestions = new ArrayCollection();
        $this->testResults   = new ArrayCollection();
        $this->createdAt     = new \DateTime();
        $this->isActive      = true;
        $this->level         = self::LEVEL_BEGINNER;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getPlatformLanguage(): ?PlatformLanguage { return $this->platformLanguage; }
    public function setPlatformLanguage(?PlatformLanguage $platformLanguage): self
    {
        $this->platformLanguage = $platformLanguage;
        return $this;
    }

    public function getPlatformLanguageId(): ?int { return $this->platformLanguage?->getId(); }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getTestType(): string { return $this->testType; }
    public function setTestType(string $testType): self { $this->testType = $testType; return $this; }

    // ─── GETTER / SETTER LEVEL ───
    public function getLevel(): string { return $this->level; }
    public function setLevel(string $level): self
    {
        if (!in_array($level, self::LEVELS)) {
            throw new \InvalidArgumentException("Invalid level: $level");
        }
        $this->level = $level;
        return $this;
    }

    public function getDurationMinutes(): int { return $this->durationMinutes; }
    public function setDurationMinutes(int $durationMinutes): self { $this->durationMinutes = $durationMinutes; return $this; }

    public function getTestQuestions(): Collection { return $this->testQuestions; }

    public function addTestQuestion(TestQuestion $testQuestion): self
    {
        if (!$this->testQuestions->contains($testQuestion)) {
            $this->testQuestions->add($testQuestion);
            $testQuestion->setMockTest($this);
        }
        return $this;
    }

    public function removeTestQuestion(TestQuestion $testQuestion): self
    {
        if ($this->testQuestions->removeElement($testQuestion)) {
            if ($testQuestion->getMockTest() === $this) {
                $testQuestion->setMockTest(null);
            }
        }
        return $this;
    }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    // FIX: No public setCreatedAt — set in constructor, never changed after.

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    // FIX: No public setUpdatedAt — managed exclusively by the PreUpdate lifecycle callback.

    public function getTestResults(): Collection { return $this->testResults; }

    public function addTestResult(TestResult $testResult): self
    {
        if (!$this->testResults->contains($testResult)) {
            $this->testResults->add($testResult);
            $testResult->setMockTest($this);
        }
        return $this;
    }

    public function removeTestResult(TestResult $testResult): self
    {
        if ($this->testResults->removeElement($testResult)) {
            if ($testResult->getMockTest() === $this) {
                $testResult->setMockTest(null);
            }
        }
        return $this;
    }

    public function getTestCategory(): string
    {
        return $this->testCategory;
    }

    public function setTestCategory(string $testCategory): self
    {
        if (!in_array($testCategory, self::TEST_TYPES)) {
            throw new \InvalidArgumentException("Invalid test category: $testCategory");
        }
        $this->testCategory = $testCategory;
        return $this;
    }
}
