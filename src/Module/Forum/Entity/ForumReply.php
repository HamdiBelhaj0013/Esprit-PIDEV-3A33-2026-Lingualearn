<?php

namespace App\Module\Forum\Entity;

use App\Module\Forum\Repository\ForumReplyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumReplyRepository::class)]
#[ORM\Table(name: 'forum_reply')]
#[ORM\HasLifecycleCallbacks]
class ForumReply
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\ManyToOne(targetEntity: ForumPost::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ForumPost $post = null;

    #[ORM\Column]
    private ?int $authorId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $repliedAt = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?bool $isBestAnswer = false;

    public function __construct()
    {
        $this->repliedAt = new \DateTime();
        $this->isActive = true;
        $this->isBestAnswer = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getPost(): ?ForumPost
    {
        return $this->post;
    }

    public function setPost(?ForumPost $post): static
    {
        $this->post = $post;
        return $this;
    }

    public function getAuthorId(): ?int
    {
        return $this->authorId;
    }

    public function setAuthorId(int $authorId): static
    {
        $this->authorId = $authorId;
        return $this;
    }

    public function getRepliedAt(): ?\DateTimeInterface
    {
        return $this->repliedAt;
    }

    public function setRepliedAt(\DateTimeInterface $repliedAt): static
    {
        $this->repliedAt = $repliedAt;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isBestAnswer(): ?bool
    {
        return $this->isBestAnswer;
    }

    public function setIsBestAnswer(bool $isBestAnswer): static
    {
        $this->isBestAnswer = $isBestAnswer;
        return $this;
    }
}