<?php

namespace App\Module\InternationalTests\Entity;

use App\Module\UserManagement\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: "App\Module\InternationalTests\Repository\TestResultRepository")]
#[ORM\Table(name: 'test_result')]
#[ORM\HasLifecycleCallbacks]
class TestResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MockTest::class, inversedBy: 'testResults')]
    #[ORM\JoinColumn(name: 'mock_test_id', referencedColumnName: 'id', nullable: false)]
    private ?MockTest $mockTest = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'float')]
    private float $overallScore;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $aiPredictedScore = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $aiWeaknessReport = null;

    // Correction AI pour Writing/Speaking/Listening
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $aiCorrection = null;

    // Note donnée par l'AI (sur 20) pour les tests corrigés par AI
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $aiNote = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateTaken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->dateTaken = new \DateTime();
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getOverallScore(): float
    {
        return $this->overallScore;
    }

    public function setOverallScore(float $overallScore): self
    {
        $this->overallScore = $overallScore;
        return $this;
    }

    public function getAiPredictedScore(): ?float
    {
        return $this->aiPredictedScore;
    }

    public function setAiPredictedScore(?float $aiPredictedScore): self
    {
        $this->aiPredictedScore = $aiPredictedScore;
        return $this;
    }

    public function getAiWeaknessReport(): ?array
    {
        return $this->aiWeaknessReport;
    }

    public function setAiWeaknessReport(?array $aiWeaknessReport): self
    {
        $this->aiWeaknessReport = $aiWeaknessReport;
        return $this;
    }

    public function getDateTaken(): ?\DateTimeInterface
    {
        return $this->dateTaken;
    }

    public function setDateTaken(\DateTimeInterface $dateTaken): self
    {
        $this->dateTaken = $dateTaken;
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

    public function getAiCorrection(): ?array
    {
        return $this->aiCorrection;
    }

    public function setAiCorrection(?array $aiCorrection): self
    {
        $this->aiCorrection = $aiCorrection;
        return $this;
    }

    public function getAiNote(): ?float
    {
        return $this->aiNote;
    }

    public function setAiNote(?float $aiNote): self
    {
        $this->aiNote = $aiNote;
        return $this;
    }
}
