<?php

namespace App\Entity;

use App\Repository\CommentaireRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentaireRepository::class)]
class Commentaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: "text")]
    private ?string $contenuC = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $dateCom = null;

    // ------------------ Relations ------------------

    
    #[ORM\ManyToOne(inversedBy: 'commentaires')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Publication $publication = null;

    // ------------------ Getters et Setters ------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContenuC(): ?string
    {
        return $this->contenuC;
    }

    public function setContenuC(string $contenuC): static
    {
        $this->contenuC = $contenuC;
        return $this;
    }

    public function getDateCom(): ?\DateTimeInterface
    {
        return $this->dateCom;
    }

    public function setDateCom(?\DateTimeInterface $dateCom): static
    {
        $this->dateCom = $dateCom;
        return $this;
    }

  
   

    public function getPublication(): ?Publication
    {
        return $this->publication;
    }

    public function setPublication(?Publication $publication): static
    {
        $this->publication = $publication;
        return $this;
    }
}