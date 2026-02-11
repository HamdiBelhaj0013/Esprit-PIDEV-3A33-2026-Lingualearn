<?php

namespace App\Module\Forum\Entity;

use App\Module\Forum\Repository\ForumPostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumPostRepository::class)]
#[ORM\Table(name: 'forum_post')]
#[ORM\HasLifecycleCallbacks]
class ForumPost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column]
    private ?int $authorId = null;

    #[ORM\Column]
    private ?int $platformLanguageId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $postedAt = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?int $viewCount = 0;

    #[ORM\Column]
    private ?int $replyCount = 0;

    #[ORM\OneToMany(targetEntity: ForumReply::class, mappedBy: 'post', orphanRemoval: true)]
    private Collection $replies;

    public function __construct()
    {
        $this->replies = new ArrayCollection();
        $this->postedAt = new \DateTime();
        $this->isActive = true;
        $this->viewCount = 0;
        $this->replyCount = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
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

    public function getAuthorId(): ?int
    {
        return $this->authorId;
    }

    public function setAuthorId(int $authorId): static
    {
        $this->authorId = $authorId;
        return $this;
    }

    public function getPlatformLanguageId(): ?int
    {
        return $this->platformLanguageId;
    }

    public function setPlatformLanguageId(int $platformLanguageId): static
    {
        $this->platformLanguageId = $platformLanguageId;
        return $this;
    }

    public function getPostedAt(): ?\DateTimeInterface
    {
        return $this->postedAt;
    }

    public function setPostedAt(\DateTimeInterface $postedAt): static
    {
        $this->postedAt = $postedAt;
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

    public function getViewCount(): ?int
    {
        return $this->viewCount;
    }

    public function setViewCount(int $viewCount): static
    {
        $this->viewCount = $viewCount;
        return $this;
    }

    public function incrementViewCount(): static
    {
        $this->viewCount++;
        return $this;
    }

    public function getReplyCount(): ?int
    {
        return $this->replyCount;
    }

    public function setReplyCount(int $replyCount): static
    {
        $this->replyCount = $replyCount;
        return $this;
    }

    public function incrementReplyCount(): static
    {
        $this->replyCount++;
        return $this;
    }

    public function decrementReplyCount(): static
    {
        if ($this->replyCount > 0) {
            $this->replyCount--;
        }
        return $this;
    }

    /**
     * @return Collection<int, ForumReply>
     */
    public function getReplies(): Collection
    {
        return $this->replies;
    }

    public function addReply(ForumReply $reply): static
    {
        if (!$this->replies->contains($reply)) {
            $this->replies->add($reply);
            $reply->setPost($this);
            $this->incrementReplyCount();
        }
        return $this;
    }

    public function removeReply(ForumReply $reply): static
    {
        if ($this->replies->removeElement($reply)) {
            if ($reply->getPost() === $this) {
                $reply->setPost(null);
            }
            $this->decrementReplyCount();
        }
        return $this;
    }
}