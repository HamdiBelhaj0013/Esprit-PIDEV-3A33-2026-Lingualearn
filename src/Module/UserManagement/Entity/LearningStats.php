<?php

namespace App\Module\UserManagement\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: App\Module\UserManagement\Repository\LearningStatsRepository::class)]
class LearningStats
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
