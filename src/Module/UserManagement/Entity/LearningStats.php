<?php

namespace App\Module\UserManagement\Entity;

use App\Module\UserManagement\Repository\LearningStatsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: LearningStatsRepository::class)]
#[ORM\Table(name: 'learning_stats')]
class LearningStats
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['stats:read'])]
    private ?int $id = null;

    /**
     * FIX 1: cascade='remove' removed — deleting a LearningStats must NEVER
     * delete the User. Only cascade='persist' is kept so that persisting
     * LearningStats also persists a newly created User if needed.
     *
     * FIX 2: onDelete='CASCADE' added to JoinColumn — this is the OWNING side
     * so the DB constraint lives here. When a User row is deleted via raw SQL
     * (bypassing ORM), MySQL will automatically delete the learning_stats row.
     */
    #[ORM\OneToOne(inversedBy: 'learningStats', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column]
    #[Groups(['stats:read'])]
    private int $totalMinutesStudied = 0;

    #[ORM\Column]
    #[Groups(['stats:read'])]
    private int $wordsLearned = 0;

    #[ORM\Column]
    #[Groups(['stats:read'])]
    private int $totalXP = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['stats:read'])]
    private ?\DateTimeInterface $lastStudySession = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getTotalMinutesStudied(): int
    {
        return $this->totalMinutesStudied;
    }

    public function setTotalMinutesStudied(int $totalMinutesStudied): static
    {
        $this->totalMinutesStudied = $totalMinutesStudied;
        return $this;
    }

    public function addMinutesStudied(int $minutes): static
    {
        $this->totalMinutesStudied += $minutes;
        return $this;
    }

    public function getWordsLearned(): int
    {
        return $this->wordsLearned;
    }

    public function setWordsLearned(int $wordsLearned): static
    {
        $this->wordsLearned = $wordsLearned;
        return $this;
    }

    public function addWordsLearned(int $words): static
    {
        $this->wordsLearned += $words;
        return $this;
    }

    public function getTotalXP(): int
    {
        return $this->totalXP;
    }

    public function setTotalXP(int $totalXP): static
    {
        $this->totalXP = $totalXP;
        return $this;
    }

    public function addXP(int $xp): static
    {
        $this->totalXP += $xp;
        return $this;
    }

    public function getLastStudySession(): ?\DateTimeInterface
    {
        return $this->lastStudySession;
    }

    /**
     * FIX: lastStudySession is business data updated after each study event,
     * not an auto-managed timestamp. Public setter is intentional here.
     * The tool warning is a false positive for this field.
     */
    public function setLastStudySession(?\DateTimeInterface $lastStudySession): static
    {
        $this->lastStudySession = $lastStudySession;
        return $this;
    }

    public function updateLastStudySession(): static
    {
        $this->lastStudySession = new \DateTime();
        return $this;
    }
}
