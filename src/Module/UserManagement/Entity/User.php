<?php

namespace App\Module\UserManagement\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: App\Module\UserManagement\Repository\UserRepository::class)]
class User
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
