<?php

namespace App\Module\PedagogicalContent\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity]
#[Vich\Uploadable]
class Lesson
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'lessons')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le cours associé est obligatoire.')]
    private ?Course $course = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Le contenu est obligatoire.')]
    #[Assert\Length(
        min: 10,
        minMessage: 'Le contenu doit contenir au moins {{ limit }} caractères.'
    )]
    private ?string $content = null;

    #[ORM\Column(type: 'json')]
    private array $vocabularyData = [];

    #[ORM\Column(type: 'json')]
    private array $grammarData = [];

    #[ORM\Column]
    #[Assert\NotNull(message: 'La récompense XP est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'La récompense XP doit être positive ou zéro.')]
    private ?int $xpReward = null;

    /**
     * ============================
     * VICH UPLOADER - LESSON MEDIA
     * ============================
     */

    // 🎥 VIDEO
    #[Vich\UploadableField(mapping: 'lesson_video', fileNameProperty: 'videoName')]
    private ?File $videoFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $videoName = null;

    // 🖼️ THUMBNAIL
    #[Vich\UploadableField(mapping: 'lesson_thumb', fileNameProperty: 'thumbName')]
    private ?File $thumbFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbName = null;

    // 📄 RESSOURCE (PDF/PPTX...)
    #[Vich\UploadableField(mapping: 'lesson_resource', fileNameProperty: 'resourceName')]
    private ?File $resourceFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resourceName = null;

    // ✅ important pour déclencher Vich quand on upload
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCourse(): ?Course
    {
        return $this->course;
    }

    public function setCourse(?Course $course): self
    {
        $this->course = $course;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getVocabularyData(): array
    {
        return $this->vocabularyData;
    }

    public function setVocabularyData(array $vocabularyData): self
    {
        $this->vocabularyData = $vocabularyData;
        return $this;
    }

    public function getGrammarData(): array
    {
        return $this->grammarData;
    }

    public function setGrammarData(array $grammarData): self
    {
        $this->grammarData = $grammarData;
        return $this;
    }

    public function getXpReward(): ?int
    {
        return $this->xpReward;
    }

    public function setXpReward(?int $xpReward): self
    {
        $this->xpReward = $xpReward;
        return $this;
    }

    // ============================
    // VICH GETTERS/SETTERS
    // ============================

    public function setVideoFile(?File $file = null): void
    {
        $this->videoFile = $file;

        if ($file !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getVideoFile(): ?File
    {
        return $this->videoFile;
    }

    public function getVideoName(): ?string
    {
        return $this->videoName;
    }

    public function setVideoName(?string $videoName): void
    {
        $this->videoName = $videoName;
    }

    public function setThumbFile(?File $file = null): void
    {
        $this->thumbFile = $file;

        if ($file !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getThumbFile(): ?File
    {
        return $this->thumbFile;
    }

    public function getThumbName(): ?string
    {
        return $this->thumbName;
    }

    public function setThumbName(?string $thumbName): void
    {
        $this->thumbName = $thumbName;
    }

    public function setResourceFile(?File $file = null): void
    {
        $this->resourceFile = $file;

        if ($file !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getResourceFile(): ?File
    {
        return $this->resourceFile;
    }

    public function getResourceName(): ?string
    {
        return $this->resourceName;
    }

    public function setResourceName(?string $resourceName): void
    {
        $this->resourceName = $resourceName;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}