<?php

namespace App\Module\ExercisesQuizzes\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: App\Module\ExercisesQuizzes\Repository\UserLessonStatusRepository::class)]
class UserLessonStatus
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
