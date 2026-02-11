<?php

namespace App\Module\UserManagement\Entity;

use App\Module\UserManagement\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(
    fields: ['email'],
    message: 'This email is already registered.'
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['user:read'])]
    #[Assert\NotBlank(message: 'Email is required.')]
    #[Assert\Email(message: 'Please enter a valid email address.')]
    #[Assert\Length(
        max: 180,
        maxMessage: 'Email cannot be longer than {{ limit }} characters.'
    )]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 50)]
    #[Groups(['user:read'])]
    #[Assert\Choice(
        choices: ['FREE', 'MONTHLY', 'YEARLY'],
        message: 'Invalid subscription plan. Must be FREE, MONTHLY, or YEARLY.'
    )]
    private ?string $subscriptionPlan = 'FREE'; // FREE, MONTHLY, YEARLY

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeInterface $subscriptionExpiry = null;

    #[ORM\Column]
    #[Groups(['user:read'])]
    private bool $isPremium = false;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $lastPaymentStatus = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['user:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(length: 50)]
    #[Groups(['user:read'])]
    #[Assert\NotBlank(message: 'Status is required.')]
    #[Assert\Choice(
        choices: ['active', 'suspended', 'deleted'],
        message: 'Status must be one of: active, suspended, or deleted.'
    )]
    private ?string $status = 'active';

    #[ORM\Column(length: 100)]
    #[Groups(['user:read'])]
    #[Assert\NotBlank(message: 'First name is required.')]
    #[Assert\Length(min: 2, max: 100)]
    #[Assert\Regex(pattern: '/^[a-zA-ZÀ-ÿ\s\'-]+$/u')]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Groups(['user:read'])]
    #[Assert\NotBlank(message: 'Last name is required.')]
    #[Assert\Length(min: 2, max: 100)]
    #[Assert\Regex(pattern: '/^[a-zA-ZÀ-ÿ\s\'-]+$/u')]
    private ?string $lastName = null;

    #[ORM\OneToOne(mappedBy: 'user', cascade: ['persist', 'remove'])]
    #[Groups(['stats:read'])]
    private ?LearningStats $learningStats = null;

    #[ORM\OneToMany(targetEntity: UserLanguage::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $userLanguages;

    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $notifications;

    public function __construct()
    {
        $this->userLanguages = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->roles = ['ROLE_USER'];
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }
    public function getUserIdentifier(): string { return (string) $this->email; }
    public function getRoles(): array { $roles = $this->roles; $roles[] = 'ROLE_USER'; return array_unique($roles); }
    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }
    public function eraseCredentials(): void {}
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(string $firstName): static { $this->firstName = $firstName; return $this; }
    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(string $lastName): static { $this->lastName = $lastName; return $this; }
    public function getFullName(): string { return $this->firstName . ' ' . $this->lastName; }
    public function getSubscriptionPlan(): ?string { return $this->subscriptionPlan; }
    public function setSubscriptionPlan(string $subscriptionPlan): static
    {
        $this->subscriptionPlan = $subscriptionPlan;
        $this->updatePremiumStatus();
        return $this;
    }
    public function getSubscriptionExpiry(): ?\DateTimeInterface { return $this->subscriptionExpiry; }
    public function setSubscriptionExpiry(?\DateTimeInterface $subscriptionExpiry): static
    {
        $this->subscriptionExpiry = $subscriptionExpiry;
        $this->updatePremiumStatus();
        return $this;
    }
    public function isPremium(): bool { return $this->isPremium; }
    public function setPremium(bool $isPremium): static { $this->isPremium = $isPremium; return $this; }
    private function updatePremiumStatus(): void
    {
        $this->isPremium = (
            in_array($this->subscriptionPlan, ['MONTHLY', 'YEARLY']) &&
            $this->subscriptionExpiry &&
            $this->subscriptionExpiry > new \DateTime()
        );
    }
    public function getLastPaymentStatus(): ?string { return $this->lastPaymentStatus; }
    public function setLastPaymentStatus(?string $lastPaymentStatus): static
    {
        $this->lastPaymentStatus = $lastPaymentStatus;
        return $this;
    }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getLearningStats(): ?LearningStats { return $this->learningStats; }
    public function setLearningStats(?LearningStats $learningStats): static
    {
        if ($learningStats === null && $this->learningStats !== null) {
            $this->learningStats->setUser(null);
        }
        if ($learningStats !== null && $learningStats->getUser() !== $this) {
            $learningStats->setUser($this);
        }
        $this->learningStats = $learningStats;
        return $this;
    }
    public function getUserLanguages(): Collection { return $this->userLanguages; }
    public function addUserLanguage(UserLanguage $userLanguage): static
    {
        if (!$this->userLanguages->contains($userLanguage)) {
            $this->userLanguages->add($userLanguage);
            $userLanguage->setUser($this);
        }
        return $this;
    }
    public function removeUserLanguage(UserLanguage $userLanguage): static
    {
        if ($this->userLanguages->removeElement($userLanguage)) {
            if ($userLanguage->getUser() === $this) {
                $userLanguage->setUser(null);
            }
        }
        return $this;
    }
    public function getNotifications(): Collection { return $this->notifications; }
    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }
        return $this;
    }
    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }
        return $this;
    }
}
