<?php

namespace App\Module\PedagogicalContent\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: App\Module\PedagogicalContent\Repository\PlatformLanguageRepository::class)]
class PlatformLanguage
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
